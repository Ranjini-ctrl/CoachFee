<?php
/**
 * CoachFee – Record Payment
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pdo        = db();
$studentId  = sanitize_int($_GET['student_id'] ?? 0);
$instalmentId = sanitize_int($_GET['instalment_id'] ?? 0);
$errors     = [];

if (!$studentId) { header('Location: /CoachFee/src/staff/students.php'); exit; }

$student = $pdo->prepare("SELECT * FROM students WHERE id=?");
$student->execute([$studentId]);
$student = $student->fetch();
if (!$student) { set_flash('error','Student not found.'); header('Location: /CoachFee/src/staff/students.php'); exit; }

// Get fee structure & unpaid instalments
$fs = $pdo->prepare("SELECT * FROM fee_structures WHERE student_id=? LIMIT 1");
$fs->execute([$studentId]);
$fs = $fs->fetch();

$unpaidInstalments = [];
if ($fs) {
    $stmt = $pdo->prepare("
        SELECT i.* FROM instalments i
        LEFT JOIN payments p ON p.instalment_id = i.id
        WHERE i.fee_structure_id = ? AND i.status != 'paid'
        ORDER BY i.due_date ASC");
    $stmt->execute([$fs['id']]);
    $unpaidInstalments = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $errors[] = 'Invalid request.'; }
    else {
        $instId  = sanitize_int($_POST['instalment_id'] ?? 0);
        $amount  = sanitize_decimal($_POST['amount'] ?? 0);
        $method  = $_POST['method'] ?? 'cash';
        $payDate = $_POST['payment_date'] ?? date('Y-m-d');
        $notes   = trim($_POST['notes'] ?? '');
        $validMethods = ['cash','online_sim','bank_transfer','cheque'];

        // Validation
        if (!$instId)                           $errors[] = 'Please select an instalment.';
        if ($amount <= 0)                       $errors[] = 'Payment amount must be greater than zero.';
        if (!in_array($method,$validMethods))   $errors[] = 'Invalid payment method.';
        if (!$payDate)                          $errors[] = 'Payment date is required.';

        // Check instalment belongs to this student & is unpaid
        if (!$errors) {
            $instCheck = $pdo->prepare("
                SELECT i.* FROM instalments i
                JOIN fee_structures fs ON fs.id = i.fee_structure_id
                WHERE i.id=? AND fs.student_id=?");
            $instCheck->execute([$instId, $studentId]);
            $instData = $instCheck->fetch();

            if (!$instData) {
                $errors[] = 'Invalid instalment selected.';
            } elseif ($instData['status'] === 'paid') {
                $errors[] = 'This instalment has already been paid. Duplicate payments are not allowed.';
            } elseif ($amount > $instData['amount']) {
                $errors[] = 'Payment amount (' . fmt_currency($amount) . ') cannot exceed instalment amount (' . fmt_currency((float)$instData['amount']) . ').';
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $receiptNo = generate_receipt_number();
                $txnRef    = generate_txn_ref();
                $isSimulated = $method === 'online_sim' ? 1 : 0;

                // Insert payment
                $pdo->prepare("
                    INSERT INTO payments (instalment_id, student_id, amount, payment_date, method, txn_reference, receipt_number, notes, recorded_by, is_simulated)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$instId, $studentId, $amount, $payDate . ' ' . date('H:i:s'), $method, $txnRef, $receiptNo, $notes, $_SESSION['user_id'], $isSimulated]);

                $paymentId = $pdo->lastInsertId();

                // Update instalment status
                $newStatus = $amount >= $instData['amount'] ? 'paid' : 'partial';
                $pdo->prepare("UPDATE instalments SET status=? WHERE id=?")->execute([$newStatus, $instId]);

                // Log
                $pdo->prepare("INSERT INTO audit_log (user_id,action,entity,entity_id,details) VALUES (?,?,?,?,?)")
                    ->execute([$_SESSION['user_id'],'record_payment','payment',$paymentId,
                        json_encode(['student_id'=>$studentId,'amount'=>$amount,'method'=>$method,'receipt'=>$receiptNo])]);

                $pdo->commit();
                set_flash('success', "Payment of " . fmt_currency($amount) . " recorded. Receipt: $receiptNo");
                header('Location: /CoachFee/src/staff/receipt.php?id=' . $paymentId);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Payment recording failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle  = 'Record Payment – ' . h($student['name']);
$activePage = 'payments';
include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title">
      <h2>Record Payment</h2>
      <p>Student: <strong><?= h($student['name']) ?></strong> (<?= h($student['student_id']) ?>)</p>
    </div>
    <a href="/CoachFee/src/staff/student_detail.php?id=<?= $studentId ?>" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger"><i data-lucide="alert-circle"></i>
    <ul style="margin:0;padding-left:16px;"><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <?php if (empty($unpaidInstalments)): ?>
  <div class="card"><div class="card-body"><div class="empty-state">
    <div class="empty-state-icon"><i data-lucide="check-circle"></i></div>
    <h3>All paid!</h3>
    <p>All instalments for this student have been paid.</p>
  </div></div></div>
  <?php else: ?>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-6);">

    <div class="card">
      <div class="card-header"><span class="card-title"><i data-lucide="credit-card" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Payment Details</span></div>
      <div class="card-body">
        <div class="sim-banner"><i data-lucide="info" style="width:14px;height:14px;"></i>
          Payments marked as <strong>Simulated Online</strong> are demonstration transactions only. No real money is transferred.
        </div>

        <form method="POST" id="paymentForm" novalidate>
          <?= csrf_field() ?>

          <div class="form-group">
            <label for="instalment_id">Select Instalment <span style="color:var(--danger);">*</span></label>
            <select id="instalment_id" name="instalment_id" class="form-control" required onchange="prefillAmount()">
              <option value="">— Choose instalment —</option>
              <?php foreach ($unpaidInstalments as $inst):
                $isSelected = $inst['id'] == $instalmentId;
                $overdueLabel = $inst['status'] === 'overdue' ? ' ⚠ OVERDUE' : '';
              ?>
              <option value="<?= $inst['id'] ?>" data-amount="<?= $inst['amount'] ?>"
                <?= $isSelected ? 'selected' : '' ?>>
                Instalment <?= $inst['instalment_no'] ?> — <?= fmt_currency((float)$inst['amount']) ?> (Due: <?= fmt_date($inst['due_date']) ?>)<?= $overdueLabel ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="amount">Amount (₹) <span style="color:var(--danger);">*</span></label>
              <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="1" required placeholder="0.00">
              <div class="form-hint">Enter actual payment amount (partial allowed)</div>
            </div>
            <div class="form-group">
              <label for="payment_date">Payment Date <span style="color:var(--danger);">*</span></label>
              <input type="date" id="payment_date" name="payment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="method">Payment Method <span style="color:var(--danger);">*</span></label>
            <select id="method" name="method" class="form-control" required>
              <option value="cash">Cash</option>
              <option value="online_sim">Simulated Online Payment</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cheque">Cheque</option>
            </select>
          </div>

          <div class="form-group">
            <label for="notes">Notes (optional)</label>
            <input type="text" id="notes" name="notes" class="form-control" placeholder="e.g. Paid by father, cheque no. 12345">
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;" id="submitBtn">
            <i data-lucide="check-circle"></i> Confirm & Record Payment
          </button>
        </form>
      </div>
    </div>

    <!-- Summary Panel -->
    <div style="display:flex;flex-direction:column;gap:var(--space-6);">
      <?php if ($fs): ?>
      <?php $totalPaid = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id=$studentId")->fetchColumn(); ?>
      <div class="card">
        <div class="card-header"><span class="card-title">Fee Summary</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
          <?php $rows=[['Net Fee',$fs['net_fee']],['Total Paid',$totalPaid],['Outstanding',$fs['net_fee']-$totalPaid]]; ?>
          <?php foreach ($rows as [$label,$val]): $isOut = $label==='Outstanding'; ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light);">
            <span class="text-muted"><?= $label ?></span>
            <span style="font-weight:700;color:<?= $isOut&&$val>0?'var(--danger)':($isOut?'var(--success)':'var(--primary)') ?>;"><?= fmt_currency((float)$val) ?></span>
          </div>
          <?php endforeach; ?>
          <?php $pct=min(100,round($totalPaid/$fs['net_fee']*100)); ?>
          <div class="progress" style="margin-top:4px;height:8px;">
            <div class="progress-bar <?= $pct>=100?'success':'accent' ?>" style="width:<?= $pct ?>%;background:var(--accent);"></div>
          </div>
          <div class="text-xs text-muted" style="text-align:right;"><?= $pct ?>% paid</div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header"><span class="card-title">Pending Instalments</span></div>
        <div class="card-body" style="padding:var(--space-4);">
          <?php foreach ($unpaidInstalments as $inst): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-light);">
            <div>
              <div style="font-size:.85rem;font-weight:600;">Instalment <?= $inst['instalment_no'] ?></div>
              <div class="text-xs text-muted">Due: <?= fmt_date($inst['due_date']) ?></div>
            </div>
            <div style="text-align:right;">
              <div style="font-weight:700;"><?= fmt_currency((float)$inst['amount']) ?></div>
              <?php $sc=['pending'=>'badge-warning','overdue'=>'badge-danger','partial'=>'badge-secondary']; ?>
              <span class="badge <?= $sc[$inst['status']]??'badge-secondary' ?>"><?= ucfirst($inst['status']) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>
  <?php endif; ?>
</div>

<?php
$extraJs = <<<JS
function prefillAmount() {
    const sel = document.getElementById('instalment_id');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.amount) {
        document.getElementById('amount').value = parseFloat(opt.dataset.amount).toFixed(2);
    }
}
// Prefill on load if instalment_id is pre-selected
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('instalment_id').value) {
        prefillAmount();
    }
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('submitBtn');
        setButtonLoading(btn, true);
    });
});
JS;
include BASE_PATH . '/templates/footer.php';
?>
