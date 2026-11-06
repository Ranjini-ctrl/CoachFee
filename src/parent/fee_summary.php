<?php
/**
 * CoachFee – Parent: Fee Summary
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';

$pageTitle  = 'Fee Summary';
$activePage = 'p-fee';
include BASE_PATH . '/templates/parent_header.php';

$pdo = db();
if (!$currentStudentId) { echo '<div class="page-content"><p class="text-muted">No student linked.</p></div>'; include BASE_PATH . '/templates/footer.php'; exit; }

$student = $pdo->prepare("SELECT s.*, b.name AS batch_name, b.course_name, b.start_date, b.end_date FROM students s LEFT JOIN batches b ON b.id=s.batch_id WHERE s.id=?");
$student->execute([$currentStudentId]);
$student = $student->fetch();

$fs = $pdo->prepare("SELECT * FROM fee_structures WHERE student_id=? LIMIT 1");
$fs->execute([$currentStudentId]);
$fs = $fs->fetch();

$totalPaid   = $fs ? (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id=$currentStudentId")->fetchColumn() : 0;
$netFee      = $fs ? (float)$fs['net_fee'] : 0;
$outstanding = max(0, $netFee - $totalPaid);
$pct         = $netFee > 0 ? min(100, round($totalPaid / $netFee * 100)) : 0;

$instalments = $fs ? $pdo->query("
    SELECT i.*, COALESCE(p.amount,0) AS paid_amount, p.payment_date, p.receipt_number, p.id AS payment_id
    FROM instalments i LEFT JOIN payments p ON p.instalment_id=i.id
    WHERE i.fee_structure_id={$fs['id']} ORDER BY i.instalment_no")->fetchAll() : [];
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title"><h2>Fee Summary</h2><p>Complete fee breakdown for <?= h($student['name'] ?? '') ?></p></div>
  </div>

  <!-- Summary Banner -->
  <div style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:var(--radius-xl);padding:var(--space-8);margin-bottom:var(--space-6);color:#fff;position:relative;overflow:hidden;">
    <div style="position:absolute;top:-30px;right:-30px;width:160px;height:160px;background:rgba(255,255,255,.05);border-radius:50%;"></div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-6);position:relative;z-index:1;">
      <?php foreach ([
        ['Total Course Fee', fmt_currency($netFee),         'rgba(255,255,255,.15)'],
        ['Amount Paid',      fmt_currency($totalPaid),      'rgba(39,155,114,.35)'],
        ['Outstanding',      fmt_currency($outstanding),    $outstanding>0?'rgba(217,104,104,.35)':'rgba(39,155,114,.35)'],
      ] as [$label, $value, $bg]): ?>
      <div style="background:<?= $bg ?>;border-radius:var(--radius-lg);padding:var(--space-5);text-align:center;">
        <div style="font-size:.78rem;color:rgba(255,255,255,.7);margin-bottom:4px;"><?= $label ?></div>
        <div style="font-size:1.3rem;font-weight:800;color:#fff;"><?= $value ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:var(--space-5);position:relative;z-index:1;">
      <div style="display:flex;justify-content:space-between;font-size:.82rem;color:rgba(255,255,255,.75);margin-bottom:6px;">
        <span>Payment Progress</span><span><?= $pct ?>%</span>
      </div>
      <div style="height:10px;background:rgba(255,255,255,.2);border-radius:var(--radius-full);overflow:hidden;">
        <div style="height:100%;width:<?= $pct ?>%;background:var(--accent);border-radius:var(--radius-full);transition:width .6s ease;"></div>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-6);">

    <!-- Instalment Schedule -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Instalment Schedule</span>
        <a href="/CoachFee/src/parent/schedule.php" class="btn btn-ghost btn-sm">Full schedule</a>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>#</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Paid On</th><th>Receipt</th></tr></thead>
          <tbody>
            <?php if (empty($instalments)): ?>
            <tr><td colspan="6"><div class="empty-state" style="padding:var(--space-5);"><p>No instalments configured.</p></div></td></tr>
            <?php else: ?>
            <?php $sc=['paid'=>'badge-success','pending'=>'badge-warning','overdue'=>'badge-danger','partial'=>'badge-secondary']; ?>
            <?php foreach ($instalments as $i): ?>
            <tr>
              <td style="font-weight:700;"><?= $i['instalment_no'] ?></td>
              <td><strong><?= fmt_currency((float)$i['amount']) ?></strong></td>
              <td><?= fmt_date($i['due_date']) ?></td>
              <td><span class="badge <?= $sc[$i['status']]??'badge-secondary' ?>"><?= ucfirst($i['status']) ?></span></td>
              <td class="text-sm text-muted"><?= $i['payment_date'] ? fmt_date($i['payment_date'],'d M Y') : '—' ?></td>
              <td>
                <?php if ($i['payment_id']): ?>
                <a href="/CoachFee/src/staff/receipt.php?id=<?= $i['payment_id'] ?>" class="btn btn-ghost btn-icon-sm" title="View Receipt"><i data-lucide="file-text"></i></a>
                <?php else: ?>
                <?php if ($i['status'] !== 'paid'): ?>
                <a href="/CoachFee/src/parent/pay.php?instalment_id=<?= $i['id'] ?>" class="btn btn-accent btn-sm" style="font-size:.75rem;padding:3px 10px;"><i data-lucide="credit-card"></i> Pay</a>
                <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Course + Discount Info -->
    <div style="display:flex;flex-direction:column;gap:var(--space-6);">
      <div class="card">
        <div class="card-header"><span class="card-title">Course Details</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
          <?php $fields = [
            ['Course',   $student['course_name'] ?? '—'],
            ['Batch',    $student['batch_name']  ?? '—'],
            ['Start',    fmt_date($student['start_date'] ?? '')],
            ['End',      fmt_date($student['end_date']   ?? '')],
          ]; ?>
          <?php foreach ($fields as [$l,$v]): ?>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-light);">
            <span class="text-muted text-sm"><?= $l ?></span>
            <span style="font-weight:600;font-size:.88rem;"><?= h($v) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($fs && $fs['discount'] > 0): ?>
      <div class="card" style="border:2px solid var(--success);">
        <div class="card-body" style="text-align:center;">
          <i data-lucide="tag" style="width:28px;height:28px;color:var(--success);"></i>
          <div style="font-size:1.1rem;font-weight:800;color:var(--success);margin:8px 0;"><?= fmt_currency((float)$fs['discount']) ?> OFF</div>
          <div class="text-muted text-sm">Discount applied to this student</div>
          <div style="margin-top:8px;font-size:.82rem;">Original: <?= fmt_currency((float)$fs['total_fee']) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($outstanding > 0): ?>
      <a href="/CoachFee/src/parent/pay.php" class="btn btn-accent" style="text-align:center;padding:var(--space-4);">
        <i data-lucide="credit-card"></i> Make a Payment
      </a>
      <?php endif; ?>
    </div>

  </div>
</div>
<?php include BASE_PATH . '/templates/footer.php'; ?>
