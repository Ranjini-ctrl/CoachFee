<?php
/**
 * CoachFee – Parent: Payment History
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';

$pageTitle  = 'Payment History';
$activePage = 'p-history';
include BASE_PATH . '/templates/parent_header.php';

$pdo = db();
if (!$currentStudentId) { echo '<div class="page-content"><p class="text-muted">No student linked.</p></div>'; include BASE_PATH . '/templates/footer.php'; exit; }

$payments = $pdo->query("
    SELECT p.*, i.instalment_no, i.due_date, i.amount AS instalment_amount
    FROM payments p
    JOIN instalments i ON i.id=p.instalment_id
    WHERE p.student_id=$currentStudentId
    ORDER BY p.payment_date DESC")->fetchAll();

$totalPaid = array_sum(array_column($payments,'amount'));
$methodLabels = ['cash'=>'Cash','online_sim'=>'Simulated Online','bank_transfer'=>'Bank Transfer','cheque'=>'Cheque'];
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title">
      <h2>Payment History</h2>
      <p><?= count($payments) ?> payment(s) · Total: <strong><?= fmt_currency($totalPaid) ?></strong></p>
    </div>
  </div>

  <?php if (empty($payments)): ?>
  <div class="card"><div class="card-body"><div class="empty-state">
    <div class="empty-state-icon"><i data-lucide="credit-card"></i></div>
    <h3>No payments yet</h3>
    <p>Your payment history will appear here once payments are recorded.</p>
    <a href="/CoachFee/src/parent/pay.php" class="btn btn-accent" style="margin-top:16px;"><i data-lucide="credit-card"></i> Make First Payment</a>
  </div></div></div>
  <?php else: ?>

  <!-- Payment Cards -->
  <div style="display:flex;flex-direction:column;gap:var(--space-4);">
    <?php foreach ($payments as $p): ?>
    <div class="card" style="border-left:4px solid <?= $p['is_simulated'] ? 'var(--secondary)' : 'var(--success)' ?>;">
      <div style="padding:var(--space-5);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
          <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:44px;height:44px;background:<?= $p['is_simulated']?'rgba(100,127,163,.1)':'rgba(39,155,114,.1)' ?>;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
              <i data-lucide="<?= $p['method']==='cash'?'banknote':($p['method']==='cheque'?'file-text':'credit-card') ?>"
                 style="width:20px;height:20px;color:<?= $p['is_simulated']?'var(--secondary)':'var(--success)' ?>;"></i>
            </div>
            <div>
              <div style="font-weight:700;color:var(--primary);">Instalment <?= $p['instalment_no'] ?></div>
              <div class="text-xs text-muted">Due: <?= fmt_date($p['due_date']) ?> · <?= $methodLabels[$p['method']] ?? h($p['method']) ?></div>
              <div class="text-xs" style="font-family:monospace;color:var(--text-muted);margin-top:2px;"><?= h($p['receipt_number']) ?></div>
            </div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:1.3rem;font-weight:800;color:var(--success);"><?= fmt_currency((float)$p['amount']) ?></div>
            <div class="text-xs text-muted"><?= fmt_date($p['payment_date'],'d M Y, g:i A') ?></div>
            <?php if ($p['is_simulated']): ?>
            <span class="badge badge-secondary" style="margin-top:4px;">Simulated</span>
            <?php else: ?>
            <span class="badge badge-success" style="margin-top:4px;">Confirmed</span>
            <?php endif; ?>
          </div>
        </div>
        <div style="margin-top:12px;display:flex;justify-content:flex-end;">
          <a href="/CoachFee/src/staff/receipt.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">
            <i data-lucide="file-text"></i> View Receipt
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Total Summary -->
  <div class="card" style="margin-top:var(--space-6);">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <div style="font-weight:700;color:var(--primary);">Total Paid to Date</div>
        <div class="text-sm text-muted"><?= count($payments) ?> transaction(s)</div>
      </div>
      <div style="font-size:1.5rem;font-weight:800;color:var(--success);"><?= fmt_currency($totalPaid) ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include BASE_PATH . '/templates/footer.php'; ?>
