<?php
/**
 * CoachFee – AI Dropout Risk Analysis
 *
 * METHODOLOGY DISCLOSURE:
 * This feature uses a RULE-BASED scoring algorithm — NOT a trained machine learning model.
 * Risk score is computed from payment behaviour indicators with weighted rules.
 * Results are explanatory and require human review before any action is taken.
 *
 * Rules:
 *  - Overdue instalment ratio     (weight: 35)
 *  - Outstanding balance ratio    (weight: 25)
 *  - Average payment delay (days) (weight: 25)
 *  - Student status               (weight: 15)
 *
 * Thresholds: High ≥70, Medium ≥40, Low <40
 * Minimum instalments required: 2 (else "insufficient_data")
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pdo = db();

/**
 * Compute risk score for a student
 */
function computeRisk(array $s, array $instalments, array $payments): array {
    $total = count($instalments);
    if ($total < RISK_MIN_INSTALMENTS) {
        return ['level'=>'insufficient_data','score'=>null,'reasons'=>['Fewer than '.RISK_MIN_INSTALMENTS.' instalments exist'],'evidence'=>[],'recommendation'=>'Insufficient data. Re-analyse after more payment history is available.'];
    }

    $overdue  = count(array_filter($instalments, fn($i)=>$i['status']==='overdue'));
    $paid     = count(array_filter($instalments, fn($i)=>$i['status']==='paid'));
    $netFee   = (float)($s['net_fee'] ?? 0);
    $totalPaid= array_sum(array_column($payments,'amount'));
    $outstanding = max(0, $netFee - $totalPaid);

    // Average delay: days between due_date and payment_date for paid instalments
    $delays = [];
    foreach ($payments as $p) {
        if (!empty($p['due_date']) && !empty($p['payment_date'])) {
            $delay = (strtotime($p['payment_date']) - strtotime($p['due_date'])) / 86400;
            if ($delay > 0) $delays[] = $delay;
        }
    }
    $avgDelay = $delays ? round(array_sum($delays) / count($delays)) : 0;

    // Scoring
    $score = 0;

    // Overdue ratio (0–35)
    $overdueRatio = $total > 0 ? $overdue / $total : 0;
    $score += $overdueRatio * 35;

    // Outstanding ratio (0–25)
    $outstandingRatio = $netFee > 0 ? min(1, $outstanding / $netFee) : 0;
    $score += $outstandingRatio * 25;

    // Delay score (0–25)
    $delayScore = min(25, ($avgDelay / 90) * 25);
    $score += $delayScore;

    // Student status (0–15)
    if ($s['status'] === 'inactive') $score += 10;
    if ($s['status'] === 'dropout')  $score += 15;

    $score = round(min(100, $score), 2);

    // Build reasons
    $reasons = [];
    if ($overdue > 0)       $reasons[] = "$overdue of $total instalment(s) are overdue";
    if ($paid === 0 && $total > 0) $reasons[] = "No instalments paid yet";
    if ($outstanding > 0)   $reasons[] = "Outstanding balance of " . fmt_currency($outstanding);
    if ($avgDelay > 0)      $reasons[] = "Average payment delay of $avgDelay day(s)";
    if ($s['status']==='inactive') $reasons[] = "Student status is Inactive";
    if ($s['status']==='dropout')  $reasons[] = "Student status is Dropout";
    if (empty($reasons))    $reasons[] = "No significant risk indicators detected";

    // Level
    $level = $score >= RISK_THRESHOLD_HIGH ? 'high' : ($score >= RISK_THRESHOLD_MEDIUM ? 'medium' : 'low');

    // Recommendation
    $rec = match($level) {
        'high'   => 'URGENT: Contact parent/student immediately. Discuss payment plan or escalate to management.',
        'medium' => 'Send payment reminder and follow up with a phone call within 3 business days.',
        default  => 'No action required. Continue to monitor.',
    };

    return [
        'level'         => $level,
        'score'         => $score,
        'reasons'       => $reasons,
        'evidence'      => ['total_instalments'=>$total,'paid'=>$paid,'overdue'=>$overdue,'avg_delay_days'=>$avgDelay,'outstanding'=>$outstanding],
        'recommendation'=> $rec,
    ];
}

// Handle re-compute
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recompute') {
    if (!verify_csrf()) { set_flash('error','Invalid request.'); header('Location: /CoachFee/src/staff/risk.php'); exit; }

    $allStudents = $pdo->query("SELECT s.*, fs.net_fee FROM students s LEFT JOIN fee_structures fs ON fs.student_id=s.id WHERE s.status IN ('active','inactive','dropout')")->fetchAll();

    foreach ($allStudents as $st) {
        $insts = $pdo->prepare("SELECT i.*, p.payment_date FROM instalments i LEFT JOIN payments p ON p.instalment_id=i.id JOIN fee_structures fs ON fs.id=i.fee_structure_id WHERE fs.student_id=?");
        $insts->execute([$st['id']]);
        $insts = $insts->fetchAll();

        $pmts = $pdo->prepare("SELECT p.*, i.due_date FROM payments p JOIN instalments i ON i.id=p.instalment_id WHERE p.student_id=?");
        $pmts->execute([$st['id']]);
        $pmts = $pmts->fetchAll();

        $r = computeRisk($st, $insts, $pmts);

        $pdo->prepare("INSERT INTO risk_analysis (student_id,risk_level,risk_score,reasons,evidence,recommendation,computed_at)
            VALUES (?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE risk_level=VALUES(risk_level),risk_score=VALUES(risk_score),reasons=VALUES(reasons),
            evidence=VALUES(evidence),recommendation=VALUES(recommendation),computed_at=NOW()")
            ->execute([$st['id'],$r['level'],$r['score'],json_encode($r['reasons']),json_encode($r['evidence']),$r['recommendation']]);
    }
    set_flash('success','Risk analysis recomputed for all students.');
    header('Location: /CoachFee/src/staff/risk.php'); exit;
}

// Load risk data
$filterLevel = $_GET['level'] ?? '';
$where = ['1=1'];
$params = [];
if ($filterLevel) { $where[] = "ra.risk_level=?"; $params[] = $filterLevel; }

$risks = $pdo->prepare("
    SELECT ra.*, s.name AS student_name, s.student_id AS sid, s.status AS student_status,
           b.name AS batch_name, b.course_name
    FROM risk_analysis ra
    JOIN students s ON s.id=ra.student_id
    LEFT JOIN batches b ON b.id=(SELECT batch_id FROM fee_structures WHERE student_id=s.id LIMIT 1)
    WHERE " . implode(' AND ',$where) . "
    ORDER BY ra.risk_score DESC, ra.risk_level ASC");
$risks->execute($params);
$risks = $risks->fetchAll();

// Summary counts
$summary = $pdo->query("SELECT risk_level, COUNT(*) AS cnt FROM risk_analysis GROUP BY risk_level")->fetchAll();
$summaryMap = array_column($summary,'cnt','risk_level');

$pageTitle  = 'AI Risk Analysis';
$activePage = 'risk';
include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">

  <div class="page-header">
    <div class="page-header-title">
      <h2>Dropout Risk Analysis</h2>
      <p>Rule-based payment behaviour analysis · Human review required</p>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="recompute">
      <button type="submit" class="btn btn-primary"><i data-lucide="refresh-cw"></i> Re-Compute All</button>
    </form>
  </div>

  <!-- Methodology Notice -->
  <div class="alert alert-info" style="margin-bottom:var(--space-6);">
    <i data-lucide="info"></i>
    <div>
      <strong>Methodology:</strong> This is a <strong>rule-based scoring system</strong>, not a machine-learning model.
      Scores are computed from: overdue instalment ratio (35%), outstanding balance ratio (25%),
      average payment delay (25%), and student status (15%).
      Thresholds: <strong>High ≥<?= RISK_THRESHOLD_HIGH ?></strong>, <strong>Medium ≥<?= RISK_THRESHOLD_MEDIUM ?></strong>.
      Always use human judgment before taking action.
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="grid-cols-3" style="margin-bottom:var(--space-8);">
    <div class="stat-card danger">
      <div class="stat-icon danger"><i data-lucide="shield-x"></i></div>
      <div class="stat-label">High Risk</div>
      <div class="stat-value"><?= $summaryMap['high'] ?? 0 ?></div>
      <div class="stat-sub">Immediate action needed</div>
    </div>
    <div class="stat-card warning">
      <div class="stat-icon warning"><i data-lucide="shield-alert"></i></div>
      <div class="stat-label">Medium Risk</div>
      <div class="stat-value"><?= $summaryMap['medium'] ?? 0 ?></div>
      <div class="stat-sub">Monitor and follow up</div>
    </div>
    <div class="stat-card success">
      <div class="stat-icon success"><i data-lucide="shield-check"></i></div>
      <div class="stat-label">Low Risk</div>
      <div class="stat-value"><?= $summaryMap['low'] ?? 0 ?></div>
      <div class="stat-sub">No action required</div>
    </div>
  </div>

  <!-- Filter -->
  <div style="display:flex;gap:10px;margin-bottom:var(--space-5);">
    <?php foreach ([''=>'All Students','high'=>'High Risk','medium'=>'Medium Risk','low'=>'Low Risk','insufficient_data'=>'Insufficient Data'] as $val=>$label): ?>
    <a href="?level=<?= $val ?>" class="btn <?= $filterLevel===$val?'btn-primary':'btn-outline' ?> btn-sm"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <!-- Risk Cards -->
  <div style="display:flex;flex-direction:column;gap:var(--space-5);">
    <?php if (empty($risks)): ?>
    <div class="card"><div class="card-body"><div class="empty-state">
      <div class="empty-state-icon"><i data-lucide="shield"></i></div>
      <h3>No risk data</h3>
      <p>Click "Re-Compute All" to generate risk analysis</p>
    </div></div></div>
    <?php else: ?>
    <?php foreach ($risks as $r):
      $level   = $r['risk_level'];
      $reasons = json_decode($r['reasons'] ?? '[]', true) ?? [];
      $evidence= json_decode($r['evidence'] ?? '{}', true) ?? [];
      $initials= implode('',array_map(fn($w)=>strtoupper($w[0]),array_slice(explode(' ',$r['student_name']),0,2)));
      if ($level === 'insufficient_data') $level = 'medium'; // display class fallback
    ?>
    <div class="card risk-card <?= $level ?>" style="border-radius:var(--radius-xl);">
      <div style="padding:var(--space-5);">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">

          <!-- Left: Student info -->
          <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:200px;">
            <div class="avatar avatar-lg" style="background:linear-gradient(135deg,<?= $r['risk_level']==='high'?'var(--danger),#e08080':($r['risk_level']==='medium'?'var(--warning),#e8b55e':'var(--success),#52c698') ?>);">
              <?= h($initials) ?>
            </div>
            <div>
              <div style="font-weight:700;font-size:1rem;color:var(--primary);"><?= h($r['student_name']) ?></div>
              <div class="text-xs text-muted"><?= h($r['sid']) ?> · <?= h($r['batch_name'] ?? 'No batch') ?></div>
              <div style="margin-top:4px;">
                <span class="risk-badge <?= $r['risk_level'] === 'insufficient_data' ? 'medium' : $r['risk_level'] ?>">
                  <?= strtoupper(str_replace('_',' ',$r['risk_level'])) ?>
                </span>
              </div>
            </div>
          </div>

          <!-- Middle: Score & Evidence -->
          <div style="flex:1;min-width:180px;">
            <?php if ($r['risk_score'] !== null): ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
              <div style="font-size:2rem;font-weight:800;color:var(--primary);"><?= $r['risk_score'] ?></div>
              <div class="text-muted text-sm">/ 100<br>risk score</div>
            </div>
            <div class="progress" style="height:8px;width:140px;">
              <div class="progress-bar <?= $r['risk_level']==='high'?'danger':($r['risk_level']==='medium'?'warning':'success') ?>"
                   style="width:<?= $r['risk_score'] ?>%;background:<?= $r['risk_level']==='high'?'var(--danger)':($r['risk_level']==='medium'?'var(--warning)':'var(--success)') ?>"></div>
            </div>
            <?php endif; ?>
            <?php if ($evidence): ?>
            <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px;">
              <?php foreach ([
                ['Paid',$evidence['paid']??'-'],
                ['Overdue',$evidence['overdue']??'-'],
                ['Delay',(($evidence['avg_delay_days']??0)).'d'],
              ] as [$el,$ev]): ?>
              <div style="background:var(--bg);border-radius:var(--radius-sm);padding:4px 10px;font-size:.75rem;text-align:center;">
                <div style="color:var(--text-muted);"><?= $el ?></div>
                <div style="font-weight:700;"><?= $ev ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Right: Reasons & Recommendation -->
          <div style="flex:2;min-width:220px;">
            <div style="font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">Risk Indicators</div>
            <?php foreach ($reasons as $reason): ?>
            <div style="display:flex;gap:6px;align-items:flex-start;margin-bottom:4px;font-size:.85rem;">
              <i data-lucide="<?= strpos(strtolower($reason),'no ')===0?'check-circle':'alert-circle' ?>"
                 style="width:14px;height:14px;flex-shrink:0;margin-top:2px;color:<?= strpos(strtolower($reason),'no ')===0?'var(--success)':'var(--warning)' ?>;"></i>
              <?= h($reason) ?>
            </div>
            <?php endforeach; ?>

            <?php if ($r['recommendation']): ?>
            <div style="margin-top:12px;background:rgba(255,255,255,.7);border-radius:var(--radius-md);padding:var(--space-3);border-left:3px solid <?= $r['risk_level']==='high'?'var(--danger)':($r['risk_level']==='medium'?'var(--warning)':'var(--success)') ?>;">
              <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:3px;">Recommended Action</div>
              <div style="font-size:.85rem;font-weight:500;"><?= h($r['recommendation']) ?></div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Actions -->
          <div style="display:flex;flex-direction:column;gap:6px;min-width:120px;">
            <a href="/CoachFee/src/staff/student_detail.php?id=<?= $r['student_id'] ?>" class="btn btn-outline btn-sm"><i data-lucide="eye"></i> View</a>
            <a href="/CoachFee/src/staff/reminders.php" class="btn btn-sm <?= $r['risk_level']==='high'?'btn-danger':($r['risk_level']==='medium'?'btn-warning':'btn-ghost') ?>">
              <i data-lucide="bell"></i> Remind
            </a>
          </div>

        </div>

        <div style="margin-top:12px;font-size:.72rem;color:var(--text-muted);text-align:right;">
          Last computed: <?= fmt_date($r['computed_at'],'d M Y g:i A') ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
<?php include BASE_PATH . '/templates/footer.php'; ?>
