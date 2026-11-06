<?php
/**
 * CoachFee – Parent: Make Payment (Simulated)
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';

$pageTitle  = 'Make Payment';
$activePage = 'p-pay';
include BASE_PATH . '/templates/parent_header.php';

$pdo          = db();
$preInstId    = sanitize_int($_GET['instalment_id'] ?? 0);
$errors       = [];
$paymentDone  = false;

if (!$currentStudentId) { echo '<div class="page-content"><p class="text-muted">No student linked.</p></div>'; include BASE_PATH . '/templates/footer.php'; exit; }

$fs = $pdo->prepare("SELECT * FROM fee_structures WHERE student_id=? LIMIT 1");
$fs->execute([$currentStudentId]);
$fs = $fs->fetch();

$unpaid = $fs ? $pdo->query("
    SELECT i.* FROM instalments i
    LEFT JOIN payments p ON p.instalment_id=i.id
    WHERE i.fee_structure_id={$fs['id']} AND i.status != 'paid'
    ORDER BY i.due_date ASC")->fetchAll() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $errors[] = 'Invalid request. Please try again.'; }
    else {
        $instId = sanitize_int($_POST['instalment_id'] ?? 0);
        $amount = sanitize_decimal($_POST['amount'] ?? 0);
        $cardHolder = trim($_POST['card_holder'] ?? '');
        $cardNumber = preg_replace('/\D/','',$_POST['card_number'] ?? '');
        $cardExpiry = trim($_POST['card_expiry'] ?? '');
        $cardCvv    = trim($_POST['card_cvv'] ?? '');

        // Validate instalment
        if (!$instId) $errors[] = 'Please select an instalment.';
        if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';

        // Validate card fields (simulated)
        if (!$cardHolder)                 $errors[] = 'Cardholder name is required.';
        if (strlen($cardNumber) < 16)     $errors[] = 'Enter a valid 16-digit card number.';
        if (!preg_match('/^\d{2}\/\d{2}$/',$cardExpiry)) $errors[] = 'Card expiry must be in MM/YY format.';
        if (strlen($cardCvv) < 3)         $errors[] = 'Enter a valid CVV.';

        // Check instalment belongs to this student & unpaid
        if (!$errors) {
            $instCheck = $pdo->prepare("
                SELECT i.* FROM instalments i
                JOIN fee_structures fs ON fs.id=i.fee_structure_id
                WHERE i.id=? AND fs.student_id=?");
            $instCheck->execute([$instId, $currentStudentId]);
            $inst = $instCheck->fetch();

            if (!$inst) {
                $errors[] = 'Invalid instalment.';
            } elseif ($inst['status'] === 'paid') {
                $errors[] = 'This instalment has already been paid. Duplicate payment prevented.';
            } elseif ($amount > $inst['amount']) {
                $errors[] = 'Payment cannot exceed instalment amount of ' . fmt_currency((float)$inst['amount']) . '.';
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $receiptNo = generate_receipt_number();
                $txnRef    = generate_txn_ref();

                $pdo->prepare("INSERT INTO payments (instalment_id,student_id,amount,payment_date,method,txn_reference,receipt_number,notes,is_simulated)
                    VALUES (?,?,?,NOW(),'online_sim',?,?,'Simulated online payment by parent',1)")
                    ->execute([$instId,$currentStudentId,$amount,$txnRef,$receiptNo]);

                $paymentId = $pdo->lastInsertId();

                $newStatus = $amount >= $inst['amount'] ? 'paid' : 'partial';
                $pdo->prepare("UPDATE instalments SET status=? WHERE id=?")->execute([$newStatus, $instId]);

                $pdo->commit();
                $paymentDone = true;

                set_flash('success', 'Payment of ' . fmt_currency($amount) . ' processed successfully! Receipt: ' . $receiptNo);
                header('Location: /CoachFee/src/staff/receipt.php?id=' . $paymentId);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Payment processing failed. Please try again.';
            }
        }
    }
}
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title"><h2>Make Payment</h2><p>Secure simulated online payment</p></div>
  </div>

  <div class="sim-banner">
    <i data-lucide="shield" style="width:14px;height:14px;"></i>
    <strong>Demo Payment Gateway</strong> — This is a simulated payment page for demonstration only.
    No real financial transaction will be processed. Do not enter real card details.
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger"><i data-lucide="alert-circle"></i>
    <ul style="margin:0;padding-left:16px;"><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <?php if (empty($unpaid)): ?>
  <div class="card"><div class="card-body"><div class="empty-state">
    <div class="empty-state-icon"><i data-lucide="check-circle"></i></div>
    <h3>All Paid!</h3>
    <p>No pending instalments found. All fees are paid up.</p>
  </div></div></div>
  <?php else: ?>

  <div style="display:grid;grid-template-columns:3fr 2fr;gap:var(--space-6);">

    <!-- Payment Form -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i data-lucide="credit-card" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Payment Details</span></div>
      <div class="card-body">
        <form method="POST" id="payForm" novalidate>
          <?= csrf_field() ?>

          <div class="form-group">
            <label>Select Instalment</label>
            <select name="instalment_id" id="instalment_id" class="form-control" required onchange="prefillAmt()">
              <option value="">— Choose instalment —</option>
              <?php foreach ($unpaid as $i): ?>
              <option value="<?= $i['id'] ?>" data-amount="<?= $i['amount'] ?>"
                <?= $i['id']==$preInstId?'selected':'' ?>>
                Instalment <?= $i['instalment_no'] ?> — <?= fmt_currency((float)$i['amount']) ?>
                (Due: <?= fmt_date($i['due_date']) ?>)
                <?= $i['status']==='overdue'?' ⚠ OVERDUE':'' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Amount (₹)</label>
            <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="1" required placeholder="0.00">
          </div>

          <hr class="divider">
          <div style="font-size:.85rem;font-weight:600;color:var(--text-muted);margin-bottom:var(--space-4);text-transform:uppercase;letter-spacing:.05em;">Card Information (Demo)</div>

          <div class="form-group">
            <label>Cardholder Name</label>
            <input type="text" name="card_holder" class="form-control" placeholder="Name on card" value="<?= h($_POST['card_holder'] ?? '') ?>">
          </div>

          <!-- Visual Card Preview -->
          <div style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:var(--radius-xl);padding:var(--space-6);margin-bottom:var(--space-5);color:#fff;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-20px;right:-20px;width:120px;height:120px;background:rgba(255,255,255,.08);border-radius:50%;"></div>
            <div style="position:absolute;bottom:-30px;left:-30px;width:140px;height:140px;background:rgba(255,255,255,.05);border-radius:50%;"></div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-5);">
              <div style="display:flex;gap:6px;"><div style="width:30px;height:20px;background:rgba(255,255,255,.3);border-radius:3px;"></div><div style="width:30px;height:20px;background:rgba(255,255,255,.2);border-radius:3px;"></div></div>
              <span style="font-size:.7rem;opacity:.6;">DEMO CARD</span>
            </div>
            <div style="font-size:1.1rem;letter-spacing:.2em;font-family:monospace;margin-bottom:var(--space-4);" id="cardPreview">•••• •••• •••• ••••</div>
            <div style="display:flex;justify-content:space-between;">
              <div><div style="font-size:.6rem;opacity:.6;text-transform:uppercase;">Card Holder</div><div style="font-weight:600;" id="holderPreview">Your Name</div></div>
              <div><div style="font-size:.6rem;opacity:.6;text-transform:uppercase;">Expires</div><div style="font-weight:600;" id="expiryPreview">MM/YY</div></div>
            </div>
          </div>

          <div class="form-group">
            <label>Card Number</label>
            <input type="text" name="card_number" id="cardNumber" class="form-control" placeholder="4111 1111 1111 1111" maxlength="19" oninput="formatCard(this)" value="<?= h($_POST['card_number'] ?? '') ?>">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Expiry (MM/YY)</label>
              <input type="text" name="card_expiry" id="cardExpiry" class="form-control" placeholder="12/27" maxlength="5" value="<?= h($_POST['card_expiry'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>CVV</label>
              <input type="password" name="card_cvv" class="form-control" placeholder="•••" maxlength="4">
            </div>
          </div>

          <button type="submit" class="btn btn-accent" style="width:100%;padding:14px;font-size:1rem;margin-top:8px;" id="payBtn">
            <i data-lucide="lock"></i> Pay Securely (Simulated)
          </button>
        </form>
      </div>
    </div>

    <!-- Order Summary -->
    <div style="display:flex;flex-direction:column;gap:var(--space-5);">
      <div class="card">
        <div class="card-header"><span class="card-title">Order Summary</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light);">
            <span class="text-muted">Instalment</span>
            <span id="summaryInst" style="font-weight:600;">—</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light);">
            <span class="text-muted">Due Date</span>
            <span id="summaryDue" style="font-weight:600;">—</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:12px 0;background:var(--bg);border-radius:var(--radius-md);padding:12px 16px;margin-top:8px;">
            <span style="font-weight:700;">Amount</span>
            <span id="summaryAmt" style="font-weight:800;font-size:1.2rem;color:var(--accent);">₹0.00</span>
          </div>
        </div>
      </div>

      <div class="card" style="background:rgba(39,155,114,.05);border:1px solid rgba(39,155,114,.2);">
        <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
          <?php foreach (['256-bit SSL encryption','No real charges','Instant confirmation','Receipt emailed (demo)'] as $feat): ?>
          <div style="display:flex;align-items:center;gap:8px;font-size:.82rem;">
            <i data-lucide="check" style="width:14px;height:14px;color:var(--success);"></i>
            <?= $feat ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>
  <?php endif; ?>
</div>

<?php
// Build instalment data for JS
$instData = [];
foreach ($unpaid as $i) {
    $instData[$i['id']] = ['no'=>$i['instalment_no'],'amount'=>$i['amount'],'due'=>date('d M Y',strtotime($i['due_date']))];
}
$instJson = json_encode($instData);

$extraJs = <<<JS
const instData = $instJson;

function prefillAmt() {
    const sel = document.getElementById('instalment_id');
    const id  = sel.value;
    const d   = instData[id];
    if (!d) { clearSummary(); return; }
    document.getElementById('amount').value       = parseFloat(d.amount).toFixed(2);
    document.getElementById('summaryInst').textContent = 'Instalment ' + d.no;
    document.getElementById('summaryDue').textContent  = d.due;
    document.getElementById('summaryAmt').textContent  = '₹' + parseFloat(d.amount).toLocaleString('en-IN',{minimumFractionDigits:2});
}

function clearSummary() {
    document.getElementById('summaryInst').textContent = '—';
    document.getElementById('summaryDue').textContent  = '—';
    document.getElementById('summaryAmt').textContent  = '₹0.00';
}

function formatCard(input) {
    let val = input.value.replace(/\D/g,'').substring(0,16);
    input.value = val.match(/.{1,4}/g)?.join(' ') || val;
    document.getElementById('cardPreview').textContent = (val || '').padEnd(16,'•').match(/.{1,4}/g).join(' ');
}

document.getElementById('cardNumber')?.addEventListener('input', function() { formatCard(this); });
document.querySelector('[name="card_holder"]')?.addEventListener('input', function() {
    document.getElementById('holderPreview').textContent = this.value || 'Your Name';
});
document.getElementById('cardExpiry')?.addEventListener('input', function() {
    document.getElementById('expiryPreview').textContent = this.value || 'MM/YY';
});
document.getElementById('amount')?.addEventListener('input', function() {
    const v = parseFloat(this.value) || 0;
    document.getElementById('summaryAmt').textContent = '₹' + v.toLocaleString('en-IN',{minimumFractionDigits:2});
});

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('instalment_id').value) prefillAmt();
    document.getElementById('payForm')?.addEventListener('submit', function() {
        setButtonLoading(document.getElementById('payBtn'), true);
    });
});
JS;
include BASE_PATH . '/templates/footer.php';
?>
