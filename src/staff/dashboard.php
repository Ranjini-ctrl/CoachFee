<?php
/**
 * CoachFee – Staff Dashboard
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$pdo = db();

// --- KPI Stats ---
$totalStudents   = $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$totalCollected  = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$pendingFees     = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM instalments WHERE status='pending'")->fetchColumn();
$overdueFees     = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM instalments WHERE status='overdue'")->fetchColumn();

// Recent payments (last 8)
$recentPayments = $pdo->query("
    SELECT p.*, s.name AS student_name, s.student_id AS sid
    FROM payments p
    JOIN students s ON s.id = p.student_id
    ORDER BY p.payment_date DESC LIMIT 8
")->fetchAll();

// Monthly collections (last 6 months)
$monthlyData = $pdo->query("
    SELECT DATE_FORMAT(payment_date,'%b %Y') AS month,
           DATE_FORMAT(payment_date,'%Y-%m') AS month_key,
           SUM(amount) AS total
    FROM payments
    WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month
    ORDER BY month_key ASC
")->fetchAll();

// Status distribution
$statusDist = $pdo->query("
    SELECT status, COUNT(*) AS cnt, SUM(amount) AS total
    FROM instalments
    GROUP BY status
")->fetchAll();

// Risk summary
$riskSummary = $pdo->query("
    SELECT risk_level, COUNT(*) AS cnt FROM risk_analysis GROUP BY risk_level
")->fetchAll();
$riskMap = array_column($riskSummary, 'cnt', 'risk_level');

// Overdue students (top 5)
$overdueStudents = $pdo->query("
    SELECT s.name, s.student_id AS sid, COUNT(i.id) AS overdue_count, SUM(i.amount) AS overdue_amount
    FROM students s
    JOIN fee_structures fs ON fs.student_id = s.id
    JOIN instalments i ON i.fee_structure_id = fs.id AND i.status = 'overdue'
    GROUP BY s.id
    ORDER BY overdue_amount DESC
    LIMIT 5
")->fetchAll();

// Chart data
$chartLabels  = json_encode(array_column($monthlyData, 'month'));
$chartAmounts = json_encode(array_map(fn($r) => (float)$r['total'], $monthlyData));

$statusLabels = json_encode(['Paid', 'Pending', 'Overdue']);
$statusCounts = json_encode([
    array_sum(array_column(array_filter($statusDist, fn($r) => $r['status']==='paid'),   'cnt')),
    array_sum(array_column(array_filter($statusDist, fn($r) => $r['status']==='pending'),'cnt')),
    array_sum(array_column(array_filter($statusDist, fn($r) => $r['status']==='overdue'),'cnt')),
]);

include BASE_PATH . '/templates/header.php';
?>

<div class="page-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-title">
            <h2>Accounts Dashboard</h2>
            <p>Overview of fee collections and student activity</p>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <a href="/CoachFee/src/staff/students.php?action=add" class="btn btn-primary">
                <i data-lucide="user-plus"></i> Add Student
            </a>
            <a href="/CoachFee/src/staff/reminders.php" class="btn btn-outline">
                <i data-lucide="bell"></i> Send Reminders
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid-cols-4" style="margin-bottom:var(--space-8);">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i data-lucide="users"></i></div>
            <div class="stat-label">Active Students</div>
            <div class="stat-value"><?= number_format($totalStudents) ?></div>
            <div class="stat-sub">Currently enrolled</div>
        </div>
        <div class="stat-card accent">
            <div class="stat-icon accent"><i data-lucide="indian-rupee"></i></div>
            <div class="stat-label">Total Collected</div>
            <div class="stat-value"><?= '₹' . number_format($totalCollected/1000, 1) ?>K</div>
            <div class="stat-sub"><?= fmt_currency((float)$totalCollected) ?></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon warning"><i data-lucide="clock"></i></div>
            <div class="stat-label">Pending Fees</div>
            <div class="stat-value"><?= '₹' . number_format($pendingFees/1000, 1) ?>K</div>
            <div class="stat-sub"><?= fmt_currency((float)$pendingFees) ?></div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon danger"><i data-lucide="alert-triangle"></i></div>
            <div class="stat-label">Overdue Fees</div>
            <div class="stat-value"><?= '₹' . number_format($overdueFees/1000, 1) ?>K</div>
            <div class="stat-sub"><?= fmt_currency((float)$overdueFees) ?></div>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-6);margin-bottom:var(--space-6);">

        <!-- Monthly Collections Chart -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i data-lucide="bar-chart-2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Monthly Collections</span>
                <span class="text-muted text-sm">Last 6 months</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:240px;">
                    <canvas id="collectionsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Instalment Status Donut -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Instalment Status</span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;align-items:center;">
                <div class="chart-container" style="height:200px;width:200px;">
                    <canvas id="statusChart"></canvas>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;margin-top:12px;width:100%;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="display:flex;align-items:center;gap:6px;font-size:.82rem;"><span style="width:10px;height:10px;border-radius:50%;background:var(--success);display:inline-block;"></span>Paid</span>
                        <?php $paid = array_sum(array_column(array_filter($statusDist,fn($r)=>$r['status']==='paid'),'cnt')); ?>
                        <span class="badge badge-success"><?= $paid ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="display:flex;align-items:center;gap:6px;font-size:.82rem;"><span style="width:10px;height:10px;border-radius:50%;background:var(--warning);display:inline-block;"></span>Pending</span>
                        <?php $pend = array_sum(array_column(array_filter($statusDist,fn($r)=>$r['status']==='pending'),'cnt')); ?>
                        <span class="badge badge-warning"><?= $pend ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="display:flex;align-items:center;gap:6px;font-size:.82rem;"><span style="width:10px;height:10px;border-radius:50%;background:var(--danger);display:inline-block;"></span>Overdue</span>
                        <?php $ovrd = array_sum(array_column(array_filter($statusDist,fn($r)=>$r['status']==='overdue'),'cnt')); ?>
                        <span class="badge badge-danger"><?= $ovrd ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-6);">

        <!-- Recent Payments -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i data-lucide="receipt" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Recent Payments</span>
                <a href="/CoachFee/src/staff/payments.php" class="btn btn-ghost btn-sm">View all</a>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                        <tr><td colspan="5"><div class="empty-state" style="padding:var(--space-6);"><p>No payments recorded yet.</p></div></td></tr>
                        <?php else: ?>
                        <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:var(--primary);"><?= h($p['student_name']) ?></div>
                                <div class="text-xs text-muted"><?= h($p['sid']) ?></div>
                            </td>
                            <td><strong><?= fmt_currency((float)$p['amount']) ?></strong></td>
                            <td>
                                <?php $methodLabels = ['cash'=>'Cash','online_sim'=>'Online (Sim)','bank_transfer'=>'Bank Transfer','cheque'=>'Cheque']; ?>
                                <span class="badge <?= $p['is_simulated'] ? 'badge-secondary' : 'badge-success' ?>">
                                    <?= $methodLabels[$p['method']] ?? h($p['method']) ?>
                                </span>
                            </td>
                            <td class="text-sm"><?= fmt_date($p['payment_date'], 'd M Y') ?></td>
                            <td>
                                <a href="/CoachFee/src/staff/receipt.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-icon-sm" title="View Receipt">
                                    <i data-lucide="file-text"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Risk Summary + Overdue -->
        <div style="display:flex;flex-direction:column;gap:var(--space-6);">

            <!-- Risk Summary -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i data-lucide="shield-alert" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Dropout Risk</span>
                    <a href="/CoachFee/src/staff/risk.php" class="btn btn-ghost btn-sm">Details</a>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:rgba(217,104,104,.06);border-radius:var(--radius-md);border:1px solid rgba(217,104,104,.15);">
                        <span style="font-size:.88rem;font-weight:600;color:var(--danger);">High Risk</span>
                        <span class="badge badge-danger"><?= $riskMap['high'] ?? 0 ?> students</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:rgba(231,168,75,.06);border-radius:var(--radius-md);border:1px solid rgba(231,168,75,.2);">
                        <span style="font-size:.88rem;font-weight:600;color:#9a6a0d;">Medium Risk</span>
                        <span class="badge badge-warning"><?= $riskMap['medium'] ?? 0 ?> students</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:rgba(39,155,114,.06);border-radius:var(--radius-md);border:1px solid rgba(39,155,114,.15);">
                        <span style="font-size:.88rem;font-weight:600;color:var(--success);">Low Risk</span>
                        <span class="badge badge-success"><?= $riskMap['low'] ?? 0 ?> students</span>
                    </div>
                </div>
            </div>

            <!-- Top Overdue -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Top Overdue</span>
                    <a href="/CoachFee/src/staff/reminders.php" class="btn btn-ghost btn-sm">Remind</a>
                </div>
                <div class="card-body" style="padding:var(--space-4);">
                    <?php if (empty($overdueStudents)): ?>
                    <div class="empty-state" style="padding:var(--space-5);"><i data-lucide="check-circle" style="color:var(--success);"></i><p>No overdue students!</p></div>
                    <?php else: ?>
                    <?php foreach ($overdueStudents as $os): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-light);">
                        <div>
                            <div style="font-weight:600;font-size:.85rem;"><?= h($os['name']) ?></div>
                            <div class="text-xs text-muted"><?= $os['overdue_count'] ?> instalment(s) overdue</div>
                        </div>
                        <span style="font-weight:700;color:var(--danger);font-size:.88rem;"><?= fmt_currency((float)$os['overdue_amount']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</div><!-- /page-content -->

<?php
$extraJs = <<<JS
// Monthly Collections Bar Chart
(function() {
    const labels  = {$chartLabels};
    const amounts = {$chartAmounts};
    if (!labels.length) return;
    const ctx = document.getElementById('collectionsChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Collections (₹)',
                data: amounts,
                backgroundColor: 'rgba(101,184,166,.2)',
                borderColor: 'rgba(101,184,166,1)',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { ticks: { callback: v => '₹' + (v/1000).toFixed(0) + 'K' }, grid: { color: 'rgba(0,0,0,.04)' } },
                x: { grid: { display: false } }
            }
        }
    });
})();

// Status Donut Chart
(function() {
    const labels = {$statusLabels};
    const data   = {$statusCounts};
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: ['#279B72','#E7A84B','#D96868'],
                borderWidth: 0,
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.raw + ' instalments' } }
            },
            cutout: '70%'
        }
    });
})();
JS;

include BASE_PATH . '/templates/footer.php';
?>
