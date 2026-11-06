<?php
/**
 * CoachFee – Payment Receipt
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_login();

$pdo = db();
$id  = sanitize_int($_GET['id'] ?? 0);
if (!$id) { header('Location: /CoachFee/src/staff/payments.php'); exit; }

$payment = $pdo->prepare("
    SELECT p.*, s.name AS student_name, s.student_id AS sid, s.email AS student_email,
           s.phone AS student_phone, b.name AS batch_name, b.course_name,
           i.instalment_no, i.due_date, i.amount AS instalment_amount,
           u.name AS recorded_by_name
    FROM payments p
    JOIN students s ON s.id = p.student_id
    JOIN instalments i ON i.id = p.instalment_id
    LEFT JOIN batches b ON b.id = (SELECT batch_id FROM fee_structures WHERE student_id=s.id LIMIT 1)
    LEFT JOIN users u ON u.id = p.recorded_by
    WHERE p.id = ?");
$payment->execute([$id]);
$payment = $payment->fetch();

if (!$payment) { set_flash('error','Receipt not found.'); header('Location: /CoachFee/src/staff/payments.php'); exit; }

// Parent access check
if ($_SESSION['role'] === 'parent') {
    $check = $pdo->prepare("SELECT 1 FROM parent_students WHERE parent_id=? AND student_id=?");
    $check->execute([$_SESSION['user_id'], $payment['student_id']]);
    if (!$check->fetch()) { set_flash('error','Access denied.'); header('Location: /CoachFee/src/parent/dashboard.php'); exit; }
}

$methodLabels = ['cash'=>'Cash','online_sim'=>'Simulated Online','bank_transfer'=>'Bank Transfer','cheque'=>'Cheque'];

if ($_SESSION['role'] === 'parent') {
    $pageTitle  = 'Payment Receipt';
    $activePage = 'p-history';
    include BASE_PATH . '/templates/parent_header.php';
} else {
    $pageTitle  = 'Payment Receipt';
    $activePage = 'payments';
    include BASE_PATH . '/templates/header.php';
}
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title">
      <h2>Payment Receipt</h2>
      <p>Official payment confirmation</p>
    </div>
    <div style="display:flex;gap:10px;">
      <button onclick="printReceipt()" class="btn btn-outline"><i data-lucide="printer"></i> Print</button>
      <?php if ($_SESSION['role']==='staff'): ?>
      <a href="/CoachFee/src/staff/student_detail.php?id=<?= $payment['student_id'] ?>" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Back</a>
      <?php else: ?>
      <a href="/CoachFee/src/parent/history.php" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Back</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($payment['is_simulated']): ?>
  <div class="sim-banner" style="max-width:520px;margin:0 auto var(--space-5);">
    <i data-lucide="alert-triangle" style="width:14px;height:14px;"></i>
    This is a <strong>simulated payment</strong>. No real financial transaction was processed.
  </div>
  <?php endif; ?>

  <div class="receipt-box">
    <!-- Receipt Header -->
    <div class="receipt-header">
      <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:8px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg,var(--accent),var(--secondary));border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
          <i data-lucide="graduation-cap" style="width:20px;height:20px;color:#fff;"></i>
        </div>
        <div>
          <div style="font-family:var(--font-heading);font-size:1.3rem;font-weight:800;color:var(--primary);">Coach<span style="color:var(--accent);">Fee</span></div>
          <div style="font-size:.7rem;color:var(--text-muted);">Smart Fee Management</div>
        </div>
      </div>
      <div style="font-size:1rem;font-weight:700;color:var(--primary);margin-top:8px;">PAYMENT RECEIPT</div>
      <div style="font-size:.8rem;color:var(--text-muted);">Receipt No: <strong><?= h($payment['receipt_number']) ?></strong></div>
      <?php if ($payment['is_simulated']): ?>
      <div style="display:inline-block;background:rgba(231,168,75,.15);border:1px dashed var(--warning);border-radius:var(--radius-sm);padding:2px 10px;font-size:.72rem;font-weight:700;color:#9a6a0d;margin-top:6px;">SIMULATED PAYMENT</div>
      <?php endif; ?>
    </div>

    <!-- Student Info -->
    <div class="receipt-row"><span class="label">Student Name</span><span class="value"><?= h($payment['student_name']) ?></span></div>
    <div class="receipt-row"><span class="label">Student ID</span><span class="value"><?= h($payment['sid']) ?></span></div>
    <div class="receipt-row"><span class="label">Course / Batch</span><span class="value"><?= h(($payment['course_name'] ?? '') . ' – ' . ($payment['batch_name'] ?? 'N/A')) ?></span></div>

    <hr class="divider" style="margin:12px 0;">

    <!-- Payment Info -->
    <div class="receipt-row"><span class="label">Instalment No.</span><span class="value">#<?= $payment['instalment_no'] ?></span></div>
    <div class="receipt-row"><span class="label">Due Date</span><span class="value"><?= fmt_date($payment['due_date']) ?></span></div>
    <div class="receipt-row"><span class="label">Payment Date</span><span class="value"><?= fmt_date($payment['payment_date'],'d M Y, g:i A') ?></span></div>
    <div class="receipt-row"><span class="label">Payment Method</span><span class="value"><?= $methodLabels[$payment['method']] ?? h($payment['method']) ?></span></div>
    <div class="receipt-row"><span class="label">Transaction Ref</span><span class="value" style="font-family:monospace;font-size:.82rem;"><?= h($payment['txn_reference']) ?></span></div>
    <?php if ($payment['notes']): ?>
    <div class="receipt-row"><span class="label">Notes</span><span class="value"><?= h($payment['notes']) ?></span></div>
    <?php endif; ?>

    <!-- Total -->
    <div class="receipt-total">
      <span class="label">Amount Paid</span>
      <span class="value"><?= fmt_currency((float)$payment['amount']) ?></span>
    </div>

    <!-- Footer -->
    <div style="text-align:center;margin-top:var(--space-6);padding-top:var(--space-4);border-top:1px dashed var(--border);">
      <div style="color:var(--success);font-size:.82rem;font-weight:600;display:flex;align-items:center;justify-content:center;gap:6px;">
        <i data-lucide="check-circle"></i> Payment Successfully Recorded
      </div>
      <?php if ($payment['recorded_by_name']): ?>
      <div style="font-size:.72rem;color:var(--text-muted);margin-top:4px;">Recorded by: <?= h($payment['recorded_by_name']) ?></div>
      <?php endif; ?>
      <div style="font-size:.7rem;color:var(--text-light);margin-top:8px;">Generated on <?= date('d M Y, g:i A') ?> · CoachFee v1.0</div>
    </div>
  </div>

  <div style="text-align:center;margin-top:var(--space-6);">
    <?php if ($_SESSION['role']==='staff'): ?>
    <a href="/CoachFee/src/staff/record_payment.php?student_id=<?= $payment['student_id'] ?>" class="btn btn-primary"><i data-lucide="credit-card"></i> Record Another Payment</a>
    <?php else: ?>
    <a href="/CoachFee/src/parent/pay.php" class="btn btn-accent"><i data-lucide="credit-card"></i> Make Another Payment</a>
    <?php endif; ?>
  </div>
</div>

<style>
@media print {
  .sidebar, .top-header, .page-header .flex, .sim-banner, nav, .btn { display: none !important; }
  .main-content { margin-left: 0 !important; }
  .page-content { padding: 0 !important; }
  .receipt-box { border: 1px solid #ccc !important; box-shadow: none !important; }
}
</style>
<?php include $_SESSION['role']==='parent' ? BASE_PATH . '/templates/footer.php' : BASE_PATH . '/templates/footer.php'; ?>
