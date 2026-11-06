<?php
/**
 * CoachFee – Refund Calculator
 * ASSUMPTION: Sliding-scale refund policy (see config/config.php)
 * This policy is clearly labelled as an assumption and is configurable.
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pdo     = db();
$result  = null;
$errors  = [];
$policy  = unserialize(REFUND_POLICY);
$students= $pdo->query("SELECT s.id,s.name,s.student_id,s.enroll_date,fs.net_fee,
    COALESCE((SELECT SUM(amount) FROM payments WHERE student_id=s.id),0) AS paid
    FROM students s LEFT JOIN fee_structures fs ON fs.student_id=s.id
    WHERE s.status IN ('active','inactive','dropout') ORDER BY s.name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $errors[] = 'Invalid request.'; }
    else {
        $studentId   = sanitize_int($_POST['student_id'] ?? 0);
        $originalFee = sanitize_decimal($_POST['original_fee'] ?? 0);
        $paidAmount  = sanitize_decimal($_POST['paid_amount'] ?? 0);
        $reason      = trim($_POST['reason'] ?? '');
        $enrollDate  = $_POST['enroll_date'] ?? '';

        if (!$studentId)             $errors[] = 'Please select a student.';
        if ($originalFee <= 0)       $errors[] = 'Original fee must be greater than zero.';
        if ($paidAmount < 0)         $errors[] = 'Paid amount cannot be negative.';
        if ($paidAmount > $originalFee) $errors[] = 'Paid amount cannot exceed original fee.';
        if (!$enrollDate)            $errors[] = 'Enrolment date is required to calculate policy.';

        if (empty($errors)) {
            $daysSince = max(0, (int)((time() - strtotime($enrollDate)) / 86400));
            $policyRow = null;
            foreach ($policy as $row) {
                if ($daysSince <= $row['days_max']) { $policyRow = $row; break; }
            }

            $refundPct    = $policyRow['percent'] ?? 0;
            $deduction    = round($paidAmount * (1 - $refundPct / 100), 2);
            $refundAmount = round($paidAmount * $refundPct / 100, 2);

            $result = [
                'student_id'   => $studentId,
                'original_fee' => $originalFee,
                'paid_amount'  => $paidAmount,
                'days_since'   => $daysSince,
                'policy_label' => $policyRow['label'] ?? 'N/A',
                'refund_pct'   => $refundPct,
                'deduction'    => $deduction,
                'refund_amount'=> $refundAmount,
                'reason'       => $reason,
            ];

            // Save if requested
            if (isset($_POST['save_record']) && $_POST['save_record'] === '1') {
                $pdo->prepare("INSERT INTO refund_records (student_id,original_fee,paid_amount,deduction,refund_amount,policy_label,policy_snapshot,reason,status)
                    VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$studentId,$originalFee,$paidAmount,$deduction,$refundAmount,
                        REFUND_POLICY_LABEL,json_encode($policy),$reason,'calculated']);
                set_flash('success','Refund calculation saved to records.');
            }
        }
    }
}

$pageTitle  = 'Refund Calculator';
$activePage = 'refund';
include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title">
      <h2>Refund Calculator</h2>
      <p>Calculate refundable amount based on the institute's refund policy</p>
    </div>
  </div>

  <!-- Policy Notice -->
  <div class="alert alert-warning" style="margin-bottom:var(--space-6);">
    <i data-lucide="alert-triangle"></i>
    <div>
      <strong>Policy Assumption:</strong> <?= REFUND_POLICY_LABEL ?>. This is a sample policy for demonstration purposes.
      Modify <code>REFUND_POLICY</code> in <code>config/config.php</code> to match your institute's actual policy.
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);">

    <!-- Form -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i data-lucide="calculator" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Calculate Refund</span></div>
      <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger"><i data-lucide="alert-circle"></i>
          <ul style="margin:0;padding-left:16px;"><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST" id="refundForm" novalidate>
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="student_id">Select Student <span style="color:var(--danger);">*</span></label>
            <select id="student_id" name="student_id" class="form-control" required onchange="prefillStudent()">
              <option value="">— Select student —</option>
              <?php foreach ($students as $s): ?>
              <option value="<?= $s['id'] ?>"
                data-enroll="<?= $s['enroll_date'] ?>"
                data-fee="<?= $s['net_fee'] ?>"
                data-paid="<?= $s['paid'] ?>"
                <?= ($_POST['student_id']??'')==$s['id']?'selected':'' ?>>
                <?= h($s['name']) ?> (<?= h($s['student_id']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="original_fee">Original / Net Fee (₹) <span style="color:var(--danger);">*</span></label>
              <input type="number" id="original_fee" name="original_fee" class="form-control" step="0.01" min="0" required
                value="<?= h($_POST['original_fee'] ?? '') ?>" placeholder="48000">
            </div>
            <div class="form-group">
              <label for="paid_amount">Amount Paid (₹) <span style="color:var(--danger);">*</span></label>
              <input type="number" id="paid_amount" name="paid_amount" class="form-control" step="0.01" min="0" required
                value="<?= h($_POST['paid_amount'] ?? '') ?>" placeholder="24000">
            </div>
          </div>
          <div class="form-group">
            <label for="enroll_date">Enrolment Date <span style="color:var(--danger);">*</span></label>
            <input type="date" id="enroll_date" name="enroll_date" class="form-control" required value="<?= h($_POST['enroll_date'] ?? '') ?>">
            <div class="form-hint">Used to calculate days since enrolment for policy bracket</div>
          </div>
          <div class="form-group">
            <label for="reason">Reason for Refund</label>
            <textarea id="reason" name="reason" class="form-control" rows="2" placeholder="e.g. Student relocated, medical reasons…"><?= h($_POST['reason'] ?? '') ?></textarea>
          </div>
          <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary" style="flex:1"><i data-lucide="calculator"></i> Calculate</button>
            <button type="submit" name="save_record" value="1" class="btn btn-outline" style="flex:1"><i data-lucide="save"></i> Calculate & Save</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Policy Table + Result -->
    <div style="display:flex;flex-direction:column;gap:var(--space-6);">

      <!-- Policy Table -->
      <div class="card">
        <div class="card-header"><span class="card-title">Refund Policy Brackets</span></div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Days Since Enrolment</th><th>Refund %</th></tr></thead>
            <tbody>
              <?php foreach ($policy as $row): ?>
              <tr <?= (isset($result) && $result['policy_label']===$row['label']) ? 'style="background:rgba(101,184,166,.1);font-weight:700;"' : '' ?>>
                <td><?= h($row['label']) ?></td>
                <td>
                  <span class="badge <?= $row['percent']==100?'badge-success':($row['percent']==0?'badge-danger':'badge-warning') ?>">
                    <?= $row['percent'] ?>%
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer">
          <p class="text-xs text-muted">Policy: <?= h(REFUND_POLICY_LABEL) ?></p>
        </div>
      </div>

      <!-- Result -->
      <?php if ($result): ?>
      <div class="card" style="border:2px solid var(--accent);">
        <div class="card-header" style="background:rgba(101,184,166,.08);">
          <span class="card-title" style="color:var(--accent);"><i data-lucide="check-circle" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Refund Calculation Result</span>
        </div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:10px;">
            <?php $rows=[
              ['Original Fee',      fmt_currency($result['original_fee']),     false],
              ['Amount Paid',       fmt_currency($result['paid_amount']),       false],
              ['Days Since Enrol.', $result['days_since'] . ' days',            false],
              ['Policy Bracket',    $result['policy_label'],                   false],
              ['Refund Rate',       $result['refund_pct'] . '%',               false],
              ['Deduction',         fmt_currency($result['deduction']),         true],
            ]; ?>
            <?php foreach ($rows as [$lbl,$val,$isDed]): ?>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light);">
              <span class="text-muted"><?= $lbl ?></span>
              <span style="font-weight:600;<?= $isDed?'color:var(--danger);':'' ?>"><?= $val ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="background:rgba(101,184,166,.1);border:2px solid var(--accent);border-radius:var(--radius-md);padding:var(--space-5);margin-top:var(--space-4);display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:700;color:var(--primary);font-size:1rem;">Refundable Amount</span>
            <span style="font-size:1.5rem;font-weight:800;color:var(--accent);"><?= fmt_currency($result['refund_amount']) ?></span>
          </div>
          <?php if ($result['refund_pct'] === 0): ?>
          <div class="alert alert-danger" style="margin-top:var(--space-4);">
            <i data-lucide="alert-circle"></i> No refund applicable under the current policy (more than 90 days since enrolment).
          </div>
          <?php endif; ?>
          <?php if ($result['reason']): ?>
          <div style="margin-top:var(--space-4);font-size:.85rem;"><strong>Reason:</strong> <?= h($result['reason']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
function prefillStudent() {
    const sel = document.getElementById('student_id');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('enroll_date').value  = opt.dataset.enroll || '';
    document.getElementById('original_fee').value = parseFloat(opt.dataset.fee || 0).toFixed(2);
    document.getElementById('paid_amount').value  = parseFloat(opt.dataset.paid || 0).toFixed(2);
}
JS;
include BASE_PATH . '/templates/footer.php';
?>
