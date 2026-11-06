<?php
/**
 * CoachFee – Parent: Instalment Schedule
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';

$pageTitle  = 'Instalment Schedule';
$activePage = 'p-schedule';
include BASE_PATH . '/templates/parent_header.php';

$pdo = db();
if (!$currentStudentId) { echo '<div class="page-content"><p class="text-muted">No student linked.</p></div>'; include BASE_PATH . '/templates/footer.php'; exit; }

$fs = $pdo->prepare("SELECT * FROM fee_structures WHERE student_id=? LIMIT 1");
$fs->execute([$currentStudentId]);
$fs = $fs->fetch();

$instalments = $fs ? $pdo->query("
    SELECT i.*, COALESCE(p.amount,0) AS paid_amount, p.payment_date, p.id AS payment_id, p.receipt_number
    FROM instalments i LEFT JOIN payments p ON p.instalment_id=i.id
    WHERE i.fee_structure_id={$fs['id']} ORDER BY i.instalment_no")->fetchAll() : [];

$totalPaid   = $fs ? (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id=$currentStudentId")->fetchColumn() : 0;
$netFee      = $fs ? (float)$fs['net_fee'] : 0;
$outstanding = max(0, $netFee - $totalPaid);
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title"><h2>Instalment Schedule</h2><p>Your complete payment timeline</p></div>
    <?php if ($outstanding > 0): ?>
    <a href="/CoachFee/src/parent/pay.php" class="btn btn-accent"><i data-lucide="credit-card"></i> Make Payment</a>
    <?php endif; ?>
  </div>

  <?php if (empty($instalments)): ?>
  <div class="card"><div class="card-body"><div class="empty-state">
    <div class="empty-state-icon"><i data-lucide="calendar"></i></div>
    <h3>No schedule yet</h3><p>Contact the institute to set up your fee schedule.</p>
  </div></div></div>
  <?php else: ?>

  <!-- Timeline View -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i data-lucide="calendar" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Payment Timeline</span></div>
    <div class="card-body">
      <div class="instalment-timeline">
        <?php foreach ($instalments as $i):
          $icons = ['paid'=>'check','pending'=>'clock','overdue'=>'alert-circle','partial'=>'minus-circle'];
          $today = date('Y-m-d');
          $isPast = $i['due_date'] < $today;
        ?>
        <div class="instalment-item">
          <div class="instalment-node <?= $i['status'] ?>">
            <i data-lucide="<?= $icons[$i['status']] ?? 'circle' ?>"></i>
          </div>
          <div class="instalment-content" style="background:var(--bg);border-radius:var(--radius-lg);padding:var(--space-4) var(--space-5);flex:1;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
              <div>
                <div class="instalment-label" style="font-size:1rem;">Instalment <?= $i['instalment_no'] ?></div>
                <div class="instalment-meta">
                  <i data-lucide="calendar" style="width:12px;height:12px;vertical-align:middle;"></i>
                  Due: <strong><?= fmt_date($i['due_date']) ?></strong>
                  <?php if ($i['status'] === 'overdue'): ?>
                  <span style="color:var(--danger);font-weight:600;margin-left:8px;">
                    <?= abs((int)((strtotime($today)-strtotime($i['due_date']))/86400)) ?> days overdue
                  </span>
                  <?php endif; ?>
                </div>
                <?php if ($i['payment_date']): ?>
                <div class="instalment-meta" style="color:var(--success);">
                  <i data-lucide="check-circle" style="width:12px;height:12px;vertical-align:middle;"></i>
                  Paid: <?= fmt_date($i['payment_date'],'d M Y') ?>
                </div>
                <?php endif; ?>
              </div>
              <div style="text-align:right;">
                <div style="font-size:1.2rem;font-weight:800;color:var(--primary);"><?= fmt_currency((float)$i['amount']) ?></div>
                <?php $sc=['paid'=>'badge-success','pending'=>'badge-warning','overdue'=>'badge-danger','partial'=>'badge-secondary']; ?>
                <span class="badge <?= $sc[$i['status']]??'badge-secondary' ?>" style="margin-top:4px;"><?= ucfirst($i['status']) ?></span>
              </div>
            </div>

            <?php if ($i['status'] === 'paid' && $i['payment_id']): ?>
            <div style="margin-top:10px;display:flex;gap:8px;">
              <a href="/CoachFee/src/staff/receipt.php?id=<?= $i['payment_id'] ?>" class="btn btn-ghost btn-sm">
                <i data-lucide="file-text"></i> View Receipt (<?= h($i['receipt_number']) ?>)
              </a>
            </div>
            <?php elseif ($i['status'] !== 'paid'): ?>
            <div style="margin-top:10px;">
              <a href="/CoachFee/src/parent/pay.php?instalment_id=<?= $i['id'] ?>" class="btn btn-accent btn-sm">
                <i data-lucide="credit-card"></i> Pay This Instalment
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Summary Table -->
  <div class="card" style="margin-top:var(--space-6);">
    <div class="card-header"><span class="card-title">Summary</span></div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>#</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Paid Amount</th></tr></thead>
        <tbody>
          <?php $sc=['paid'=>'badge-success','pending'=>'badge-warning','overdue'=>'badge-danger','partial'=>'badge-secondary']; ?>
          <?php foreach ($instalments as $i): ?>
          <tr>
            <td style="font-weight:700;"><?= $i['instalment_no'] ?></td>
            <td><?= fmt_currency((float)$i['amount']) ?></td>
            <td><?= fmt_date($i['due_date']) ?></td>
            <td><span class="badge <?= $sc[$i['status']]??'badge-secondary' ?>"><?= ucfirst($i['status']) ?></span></td>
            <td><?= (float)$i['paid_amount'] > 0 ? fmt_currency((float)$i['paid_amount']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--bg);font-weight:700;">
            <td colspan="2" style="padding:12px 20px;">Total</td>
            <td colspan="2"><?= fmt_currency($netFee) ?></td>
            <td style="color:var(--success);"><?= fmt_currency($totalPaid) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include BASE_PATH . '/templates/footer.php'; ?>
