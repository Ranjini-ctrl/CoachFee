<?php
/**
 * CoachFee – Parent Dashboard
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';

$pageTitle  = 'Parent Dashboard';
$activePage = 'p-dashboard';
include BASE_PATH . '/templates/parent_header.php';

$pdo = db();

if (!$currentStudentId) {
    echo '<div class="page-content"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="users"></i></div><h3>No student linked</h3><p>Contact the institute to link your child\'s account.</p></div></div>';
    include BASE_PATH . '/templates/footer.php';
    exit;
}

$s = $pdo->prepare("SELECT s.*, b.name AS batch_name, b.course_name FROM students s LEFT JOIN batches b ON b.id=s.batch_id WHERE s.id=?");
$s->execute([$currentStudentId]);
$student = $s->fetch();

$fs = $pdo->prepare("SELECT * FROM fee_structures WHERE student_id=? LIMIT 1");
$fs->execute([$currentStudentId]);
$fs = $fs->fetch();

$totalPaid   = $fs ? (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id=$currentStudentId")->fetchColumn() : 0;
$netFee      = $fs ? (float)$fs['net_fee'] : 0;
$outstanding = max(0, $netFee - $totalPaid);
$pct         = $netFee > 0 ? min(100, round($totalPaid / $netFee * 100)) : 0;

// Next due instalment
$nextDue = $fs ? $pdo->query("SELECT * FROM instalments WHERE fee_structure_id={$fs['id']} AND status IN ('pending','overdue') ORDER BY due_date ASC LIMIT 1")->fetch() : null;

// Recent payments
$recentPayments = $pdo->query("SELECT p.*, i.instalment_no FROM payments p JOIN instalments i ON i.id=p.instalment_id WHERE p.student_id=$currentStudentId ORDER BY p.payment_date DESC LIMIT 5")->fetchAll();

$overdueCnt = $fs ? $pdo->query("SELECT COUNT(*) FROM instalments WHERE fee_structure_id={$fs['id']} AND status='overdue'")->fetchColumn() : 0;
?>
<div class="page-content">

  <!-- Welcome Banner -->
  <div class="parent-header">
    <div style="position:relative;z-index:1;">
      <p style="color:rgba(255,255,255,.75);font-size:.88rem;margin-bottom:4px;">Welcome back,</p>
      <h2><?= h($user['name']) ?></h2>
      <p style="margin-top:4px;">Viewing fee details for <strong><?= h($student['name'] ?? 'your child') ?></strong></p>
    </div>
  </div>

  <?php if ($overdueCnt > 0): ?>
  <div class="alert alert-danger" style="margin-bottom:var(--space-5);">
    <i data-lucide="alert-triangle"></i>
    <div><strong><?= $overdueCnt ?> overdue instalment(s)!</strong> Please make payment at the earliest to avoid issues.</div>
  </div>
  <?php endif; ?>

  <!-- KPI Cards -->
  <div class="grid-cols-3" style="margin-bottom:var(--space-6);">
    <div class="stat-card primary">
      <div class="stat-icon primary"><i data-lucide="indian-rupee"></i></div>
      <div class="stat-label">Total Course Fee</div>
      <div class="stat-value" style="font-size:1.5rem;"><?= fmt_currency($netFee) ?></div>
      <?php if ($fs && $fs['discount'] > 0): ?>
      <div class="stat-sub">Discount: <?= fmt_currency((float)$fs['discount']) ?></div>
      <?php endif; ?>
    </div>
    <div class="stat-card accent">
      <div class="stat-icon accent"><i data-lucide="check-circle"></i></div>
      <div class="stat-label">Amount Paid</div>
      <div class="stat-value" style="font-size:1.5rem;"><?= fmt_currency($totalPaid) ?></div>
      <div class="stat-sub"><?= $pct ?>% of total</div>
    </div>
    <div class="stat-card <?= $outstanding > 0 ? 'danger' : 'success' ?>">
      <div class="stat-icon <?= $outstanding > 0 ? 'danger' : 'success' ?>"><i data-lucide="<?= $outstanding > 0 ? 'clock' : 'check' ?>"></i></div>
      <div class="stat-label">Outstanding</div>
      <div class="stat-value" style="font-size:1.5rem;"><?= fmt_currency($outstanding) ?></div>
      <div class="stat-sub"><?= $outstanding > 0 ? 'Balance due' : 'All paid!' ?></div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-6);">

    <!-- Progress + Next Due -->
    <div style="display:flex;flex-direction:column;gap:var(--space-6);">

      <div class="card">
        <div class="card-header"><span class="card-title">Payment Progress</span></div>
        <div class="card-body">
          <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <span class="text-muted text-sm">Overall payment completion</span>
            <span style="font-weight:700;color:var(--accent);"><?= $pct ?>%</span>
          </div>
          <div class="progress" style="height:14px;border-radius:var(--radius-full);margin-bottom:16px;">
            <div class="progress-bar <?= $pct>=100?'success':'accent' ?>" style="width:<?= $pct ?>%;background:var(--accent);border-radius:var(--radius-full);"></div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center;">
            <?php
            $instAll = $fs ? $pdo->query("SELECT status,COUNT(*) as c FROM instalments WHERE fee_structure_id={$fs['id']} GROUP BY status")->fetchAll() : [];
            $instMap = array_column($instAll,'c','status');
            foreach ([['Paid',$instMap['paid']??0,'success'],['Pending',$instMap['pending']??0,'warning'],['Overdue',$instMap['overdue']??0,'danger']] as [$label,$cnt,$color]):
            ?>
            <div style="padding:12px;background:var(--bg);border-radius:var(--radius-md);">
              <div style="font-size:1.5rem;font-weight:800;color:var(--<?= $color ?>);"><?= $cnt ?></div>
              <div class="text-xs text-muted"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Recent Payments -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Payments</span>
          <a href="/CoachFee/src/parent/history.php" class="btn btn-ghost btn-sm">View all</a>
        </div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Receipt</th><th>Instalment</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
              <?php if (empty($recentPayments)): ?>
              <tr><td colspan="4"><div class="empty-state" style="padding:var(--space-5);"><p>No payments recorded yet.</p></div></td></tr>
              <?php else: ?>
              <?php foreach ($recentPayments as $p): ?>
              <tr>
                <td><code style="font-size:.75rem;"><?= h($p['receipt_number']) ?></code></td>
                <td>Instalment <?= $p['instalment_no'] ?></td>
                <td><strong><?= fmt_currency((float)$p['amount']) ?></strong></td>
                <td class="text-sm"><?= fmt_date($p['payment_date'],'d M Y') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Right: Next Due + Student Info -->
    <div style="display:flex;flex-direction:column;gap:var(--space-6);">

      <!-- Next Due -->
      <?php if ($nextDue): ?>
      <div class="card" style="border:2px solid <?= $nextDue['status']==='overdue'?'var(--danger)':'var(--warning)' ?>;">
        <div class="card-header" style="background:<?= $nextDue['status']==='overdue'?'rgba(217,104,104,.06)':'rgba(231,168,75,.06)' ?>;">
          <span class="card-title" style="color:<?= $nextDue['status']==='overdue'?'var(--danger)':'#9a6a0d' ?>;">
            <i data-lucide="<?= $nextDue['status']==='overdue'?'alert-circle':'clock' ?>" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>
            <?= $nextDue['status']==='overdue' ? 'Overdue Payment' : 'Next Payment Due' ?>
          </span>
        </div>
        <div class="card-body" style="text-align:center;padding:var(--space-6);">
          <div style="font-size:2rem;font-weight:800;color:var(--primary);margin-bottom:8px;"><?= fmt_currency((float)$nextDue['amount']) ?></div>
          <div class="text-muted text-sm">Instalment <?= $nextDue['instalment_no'] ?></div>
          <div style="margin:12px 0;font-size:.9rem;font-weight:600;color:<?= $nextDue['status']==='overdue'?'var(--danger)':'#9a6a0d' ?>;">
            Due: <?= fmt_date($nextDue['due_date']) ?>
          </div>
          <a href="/CoachFee/src/parent/pay.php?instalment_id=<?= $nextDue['id'] ?>" class="btn btn-accent w-full">
            <i data-lucide="credit-card"></i> Pay Now
          </a>
        </div>
      </div>
      <?php else: ?>
      <div class="card">
        <div class="card-body" style="text-align:center;padding:var(--space-8);">
          <i data-lucide="check-circle" style="width:40px;height:40px;color:var(--success);"></i>
          <h3 style="margin-top:12px;">All Up to Date!</h3>
          <p class="text-muted text-sm">No pending payments</p>
        </div>
      </div>
      <?php endif; ?>

      <!-- Student Info -->
      <div class="card">
        <div class="card-header"><span class="card-title">Student Details</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
          <?php $fields=[['Name',$student['name']??'—'],['ID',$student['student_id']??'—'],['Batch',$student['batch_name']??'—'],['Course',$student['course_name']??'—'],['Enrolled',fmt_date($student['enroll_date']??'')]]; ?>
          <?php foreach ($fields as [$l,$v]): ?>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);">
            <span class="text-muted text-sm"><?= $l ?></span>
            <span style="font-weight:600;font-size:.88rem;"><?= h($v) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

  </div>
</div>
<?php include BASE_PATH . '/templates/footer.php'; ?>
