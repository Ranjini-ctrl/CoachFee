<?php
/**
 * CoachFee – Student Detail & Fee Overview
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pdo = db();
$id  = sanitize_int($_GET['id'] ?? 0);
if (!$id) { header('Location: /CoachFee/src/staff/students.php'); exit; }

$student = $pdo->prepare("SELECT s.*, b.name AS batch_name, b.course_name, b.start_date AS batch_start, b.end_date AS batch_end
    FROM students s LEFT JOIN batches b ON b.id=s.batch_id WHERE s.id=?");
$student->execute([$id]);
$student = $student->fetch();
if (!$student) { set_flash('error','Student not found.'); header('Location: /CoachFee/src/staff/students.php'); exit; }

// Fee structure
$fs = $pdo->prepare("SELECT * FROM fee_structures WHERE student_id=? LIMIT 1");
$fs->execute([$id]);
$fs = $fs->fetch();

// Instalments
$instalments = [];
$totalPaid = 0;
if ($fs) {
    $instStmt = $pdo->prepare("
        SELECT i.*, COALESCE(p.amount,0) AS paid_amount, p.payment_date, p.receipt_number, p.id AS payment_id
        FROM instalments i
        LEFT JOIN payments p ON p.instalment_id = i.id
        WHERE i.fee_structure_id = ?
        ORDER BY i.instalment_no");
    $instStmt->execute([$fs['id']]);
    $instalments = $instStmt->fetchAll();
    $totalPaid = array_sum(array_column($instalments,'paid_amount'));
}

// Payment history
$payments = $pdo->prepare("
    SELECT p.*, i.instalment_no, i.due_date
    FROM payments p
    JOIN instalments i ON i.id = p.instalment_id
    WHERE p.student_id = ?
    ORDER BY p.payment_date DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

// Linked parents
$linkedParents = $pdo->prepare("SELECT u.name, u.email, u.phone, ps.relation FROM users u JOIN parent_students ps ON ps.parent_id=u.id WHERE ps.student_id=?");
$linkedParents->execute([$id]);
$linkedParents = $linkedParents->fetchAll();

// Risk analysis
$risk = $pdo->prepare("SELECT * FROM risk_analysis WHERE student_id=?");
$risk->execute([$id]);
$risk = $risk->fetch();

$outstanding = $fs ? ($fs['net_fee'] - $totalPaid) : 0;
$pct = $fs && $fs['net_fee'] > 0 ? min(100, round($totalPaid/$fs['net_fee']*100)) : 0;

$statusColors = ['active'=>'badge-success','inactive'=>'badge-secondary','dropout'=>'badge-danger','completed'=>'badge-info'];
$initials = implode('',array_map(fn($w)=>strtoupper($w[0]),array_slice(explode(' ',$student['name']),0,2)));

$pageTitle  = h($student['name']);
$activePage = 'students';
include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">

  <!-- Header -->
  <div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
      <a href="/CoachFee/src/staff/students.php" class="btn btn-ghost btn-icon"><i data-lucide="arrow-left"></i></a>
      <div class="avatar avatar-lg"><?= h($initials) ?></div>
      <div>
        <h2 style="margin:0;"><?= h($student['name']) ?></h2>
        <div style="display:flex;align-items:center;gap:10px;margin-top:4px;">
          <code style="font-size:.8rem;background:var(--bg);padding:2px 8px;border-radius:var(--radius-sm);"><?= h($student['student_id']) ?></code>
          <span class="badge <?= $statusColors[$student['status']] ?>"><?= ucfirst($student['status']) ?></span>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="/CoachFee/src/staff/student_form.php?id=<?= $id ?>" class="btn btn-outline"><i data-lucide="edit-2"></i> Edit</a>
      <a href="/CoachFee/src/staff/record_payment.php?student_id=<?= $id ?>" class="btn btn-primary"><i data-lucide="credit-card"></i> Record Payment</a>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 2fr;gap:var(--space-6);">

    <!-- Left: Student Info -->
    <div style="display:flex;flex-direction:column;gap:var(--space-6);">

      <!-- Personal Info Card -->
      <div class="card">
        <div class="card-header"><span class="card-title">Personal Details</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
          <?php $fields = [
            ['Email',       $student['email']     ?? '—', 'mail'],
            ['Phone',       $student['phone']     ?? '—', 'phone'],
            ['Date of Birth', $student['dob'] ? fmt_date($student['dob']) : '—', 'calendar'],
            ['Enrolled',    fmt_date($student['enroll_date']), 'clock'],
            ['Batch',       ($student['batch_name'] ?? '—'), 'book-open'],
            ['Course',      ($student['course_name'] ?? '—'), 'graduation-cap'],
          ]; ?>
          <?php foreach ($fields as [$label,$value,$icon]): ?>
          <div style="display:flex;align-items:center;gap:10px;">
            <i data-lucide="<?= $icon ?>" style="width:16px;height:16px;color:var(--text-muted);flex-shrink:0;"></i>
            <div>
              <div class="text-xs text-muted"><?= $label ?></div>
              <div style="font-weight:500;font-size:.88rem;"><?= h($value) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if ($student['notes']): ?>
          <div style="background:var(--bg);border-radius:var(--radius-sm);padding:var(--space-3);font-size:.82rem;color:var(--text-muted);">
            <i data-lucide="file-text" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i><?= h($student['notes']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Linked Parents -->
      <div class="card">
        <div class="card-header"><span class="card-title">Linked Parents</span></div>
        <div class="card-body" style="padding:var(--space-4);">
          <?php if (empty($linkedParents)): ?>
          <p class="text-muted text-sm">No parents linked. <a href="/CoachFee/src/staff/student_form.php?id=<?= $id ?>">Link a parent →</a></p>
          <?php else: ?>
          <?php foreach ($linkedParents as $lp): ?>
          <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light);">
            <div class="avatar avatar-sm"><?= strtoupper(substr($lp['name'],0,2)) ?></div>
            <div>
              <div style="font-weight:600;font-size:.88rem;"><?= h($lp['name']) ?></div>
              <div class="text-xs text-muted"><?= h($lp['relation']) ?> · <?= h($lp['email']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Risk -->
      <?php if ($risk): ?>
      <?php $riskClass = $risk['risk_level']; $reasons = json_decode($risk['reasons'],true) ?? []; ?>
      <div class="card risk-card <?= $riskClass ?>">
        <div class="card-header" style="border:none;padding-bottom:0;">
          <span class="card-title">Dropout Risk</span>
          <span class="risk-badge <?= $riskClass ?>"><?= strtoupper($riskClass) ?></span>
        </div>
        <div class="card-body" style="padding-top:var(--space-3);">
          <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:var(--space-3);">Score: <?= $risk['risk_score'] ?>/100</div>
          <?php foreach (array_slice($reasons,0,3) as $r): ?>
          <div style="display:flex;gap:6px;align-items:flex-start;margin-bottom:4px;font-size:.82rem;">
            <i data-lucide="alert-circle" style="width:13px;height:13px;flex-shrink:0;margin-top:2px;color:var(--warning);"></i>
            <?= h($r) ?>
          </div>
          <?php endforeach; ?>
          <a href="/CoachFee/src/staff/risk.php" class="btn btn-ghost btn-sm" style="margin-top:8px;padding-left:0;">Full analysis →</a>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /left -->

    <!-- Right: Fee & Instalments -->
    <div style="display:flex;flex-direction:column;gap:var(--space-6);">

      <!-- Fee Summary -->
      <?php if ($fs): ?>
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i data-lucide="indian-rupee" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Fee Summary</span>
          <a href="/CoachFee/src/staff/fee_structure.php?student_id=<?= $id ?>" class="btn btn-ghost btn-sm"><i data-lucide="settings"></i> Manage</a>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-5);margin-bottom:var(--space-5);">
            <div style="text-align:center;padding:var(--space-4);background:var(--bg);border-radius:var(--radius-lg);">
              <div class="text-xs text-muted">Total Fee</div>
              <div style="font-size:1.1rem;font-weight:700;color:var(--primary);margin-top:4px;"><?= fmt_currency((float)$fs['net_fee']) ?></div>
            </div>
            <div style="text-align:center;padding:var(--space-4);background:rgba(39,155,114,.08);border-radius:var(--radius-lg);">
              <div class="text-xs" style="color:var(--success);">Paid</div>
              <div style="font-size:1.1rem;font-weight:700;color:var(--success);margin-top:4px;"><?= fmt_currency((float)$totalPaid) ?></div>
            </div>
            <div style="text-align:center;padding:var(--space-4);background:<?= $outstanding>0?'rgba(217,104,104,.08)':'rgba(39,155,114,.08)' ?>;border-radius:var(--radius-lg);">
              <div class="text-xs" style="color:<?= $outstanding>0?'var(--danger)':'var(--success)' ?>;">Outstanding</div>
              <div style="font-size:1.1rem;font-weight:700;color:<?= $outstanding>0?'var(--danger)':'var(--success)' ?>;margin-top:4px;"><?= fmt_currency((float)$outstanding) ?></div>
            </div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px;">
              <span class="text-muted">Payment progress</span>
              <span style="font-weight:600;"><?= $pct ?>%</span>
            </div>
            <div class="progress" style="height:10px;">
              <div class="progress-bar <?= $pct>=100?'success':($pct>=50?'':'warning') ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <?php if ($fs['discount'] > 0): ?>
          <div class="text-xs text-muted" style="margin-top:8px;">
            Original fee: <?= fmt_currency((float)$fs['total_fee']) ?> · Discount applied: <?= fmt_currency((float)$fs['discount']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Instalment Schedule -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Instalment Schedule</span>
          <a href="/CoachFee/src/staff/record_payment.php?student_id=<?= $id ?>" class="btn btn-primary btn-sm"><i data-lucide="plus"></i> Record Payment</a>
        </div>
        <div class="card-body">
          <div class="instalment-timeline">
            <?php foreach ($instalments as $inst):
              $nodeClass = $inst['status'];
              $icons = ['paid'=>'check','pending'=>'clock','overdue'=>'alert-circle','partial'=>'minus-circle'];
            ?>
            <div class="instalment-item">
              <div class="instalment-node <?= $nodeClass ?>">
                <i data-lucide="<?= $icons[$inst['status']] ?? 'circle' ?>"></i>
              </div>
              <div class="instalment-content">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                  <div>
                    <div class="instalment-label">Instalment <?= $inst['instalment_no'] ?></div>
                    <div class="instalment-meta">Due: <?= fmt_date($inst['due_date']) ?></div>
                    <?php if ($inst['payment_date']): ?>
                    <div class="instalment-meta" style="color:var(--success);">Paid: <?= fmt_date($inst['payment_date'],'d M Y g:i A') ?></div>
                    <?php endif; ?>
                  </div>
                  <div style="text-align:right;">
                    <div style="font-weight:700;"><?= fmt_currency((float)$inst['amount']) ?></div>
                    <?php $sc=['paid'=>'badge-success','pending'=>'badge-warning','overdue'=>'badge-danger','partial'=>'badge-secondary']; ?>
                    <span class="badge <?= $sc[$inst['status']]??'badge-secondary' ?>" style="margin-top:4px;"><?= ucfirst($inst['status']) ?></span>
                  </div>
                </div>
                <?php if ($inst['receipt_number'] && $inst['payment_id']): ?>
                <div style="margin-top:6px;">
                  <a href="/CoachFee/src/staff/receipt.php?id=<?= $inst['payment_id'] ?>" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:.75rem;">
                    <i data-lucide="file-text"></i> Receipt <?= h($inst['receipt_number']) ?>
                  </a>
                </div>
                <?php endif; ?>
                <?php if ($inst['status'] !== 'paid'): ?>
                <div style="margin-top:6px;">
                  <a href="/CoachFee/src/staff/record_payment.php?student_id=<?= $id ?>&instalment_id=<?= $inst['id'] ?>" class="btn btn-accent btn-sm" style="padding:3px 10px;font-size:.75rem;">
                    <i data-lucide="credit-card"></i> Record Payment
                  </a>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="card">
        <div class="card-body">
          <div class="empty-state">
            <div class="empty-state-icon"><i data-lucide="indian-rupee"></i></div>
            <h3>No fee structure</h3>
            <p>Set up a fee plan for this student</p>
            <a href="/CoachFee/src/staff/fee_structure.php?student_id=<?= $id ?>" class="btn btn-primary" style="margin-top:16px;">Set Up Fees</a>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Payment History -->
      <?php if (!empty($payments)): ?>
      <div class="card">
        <div class="card-header"><span class="card-title">Payment History</span></div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Receipt</th><th>Instalment</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($payments as $p):
                $methodMap = ['cash'=>'Cash','online_sim'=>'Simulated Online','bank_transfer'=>'Bank Transfer','cheque'=>'Cheque'];
              ?>
              <tr>
                <td><code style="font-size:.75rem;"><?= h($p['receipt_number']) ?></code></td>
                <td>Instalment <?= $p['instalment_no'] ?></td>
                <td><strong><?= fmt_currency((float)$p['amount']) ?></strong></td>
                <td>
                  <span class="badge <?= $p['is_simulated']?'badge-secondary':'badge-success' ?>">
                    <?= $methodMap[$p['method']] ?? h($p['method']) ?>
                  </span>
                </td>
                <td class="text-sm"><?= fmt_date($p['payment_date'],'d M Y') ?></td>
                <td><a href="/CoachFee/src/staff/receipt.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-icon-sm" title="Receipt"><i data-lucide="file-text"></i></a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /right -->
  </div>
</div>
<?php include BASE_PATH . '/templates/footer.php'; ?>
