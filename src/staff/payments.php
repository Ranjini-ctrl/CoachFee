<?php
/**
 * CoachFee – All Payments
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pdo = db();
$search  = trim($_GET['q'] ?? '');
$method  = $_GET['method'] ?? '';
$dateFrom= $_GET['from'] ?? '';
$dateTo  = $_GET['to']   ?? '';

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(s.name LIKE ? OR s.student_id LIKE ? OR p.receipt_number LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like,$like,$like]);
}
if ($method)   { $where[] = "p.method=?";       $params[] = $method; }
if ($dateFrom) { $where[] = "DATE(p.payment_date)>=?"; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "DATE(p.payment_date)<=?"; $params[] = $dateTo; }

$payments = $pdo->prepare("
    SELECT p.*, s.name AS student_name, s.student_id AS sid, i.instalment_no, i.due_date
    FROM payments p
    JOIN students s ON s.id=p.student_id
    JOIN instalments i ON i.id=p.instalment_id
    WHERE " . implode(' AND ',$where) . "
    ORDER BY p.payment_date DESC");
$payments->execute($params);
$payments = $payments->fetchAll();

$totalAmount = array_sum(array_column($payments,'amount'));

$pageTitle  = 'Payment History';
$activePage = 'payments';
include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title">
      <h2>Payment History</h2>
      <p><?= count($payments) ?> record(s) · Total: <strong><?= fmt_currency($totalAmount) ?></strong></p>
    </div>
    <a href="/CoachFee/src/staff/record_payment.php?student_id=" class="btn btn-primary"><i data-lucide="plus"></i> Record Payment</a>
  </div>

  <!-- Filters -->
  <div class="card" style="margin-bottom:var(--space-6);">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;width:100%;align-items:center;">
        <div class="search-bar" style="flex:1;min-width:200px;">
          <i data-lucide="search"></i>
          <input type="text" name="q" class="form-control" placeholder="Search student, receipt…" value="<?= h($search) ?>">
        </div>
        <select name="method" class="form-control" style="width:170px;">
          <option value="">All Methods</option>
          <option value="cash" <?= $method==='cash'?'selected':'' ?>>Cash</option>
          <option value="online_sim" <?= $method==='online_sim'?'selected':'' ?>>Simulated Online</option>
          <option value="bank_transfer" <?= $method==='bank_transfer'?'selected':'' ?>>Bank Transfer</option>
          <option value="cheque" <?= $method==='cheque'?'selected':'' ?>>Cheque</option>
        </select>
        <input type="date" name="from" class="form-control" style="width:150px;" value="<?= h($dateFrom) ?>" title="From date">
        <input type="date" name="to"   class="form-control" style="width:150px;" value="<?= h($dateTo) ?>" title="To date">
        <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="filter"></i> Filter</button>
        <a href="/CoachFee/src/staff/payments.php" class="btn btn-outline btn-sm">Clear</a>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Receipt</th>
            <th>Student</th>
            <th>Instalment</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Date</th>
            <th>Type</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
          <tr><td colspan="8"><div class="empty-state" style="padding:var(--space-8);">
            <div class="empty-state-icon"><i data-lucide="credit-card"></i></div>
            <h3>No payments found</h3><p>Adjust filters to find records</p>
          </div></td></tr>
          <?php else: ?>
          <?php
          $methodLabels=['cash'=>'Cash','online_sim'=>'Sim. Online','bank_transfer'=>'Bank Transfer','cheque'=>'Cheque'];
          foreach ($payments as $p): ?>
          <tr>
            <td><code style="font-size:.75rem;"><?= h($p['receipt_number']) ?></code></td>
            <td>
              <div style="font-weight:600;"><?= h($p['student_name']) ?></div>
              <div class="text-xs text-muted"><?= h($p['sid']) ?></div>
            </td>
            <td>
              <div>#<?= $p['instalment_no'] ?></div>
              <div class="text-xs text-muted">Due: <?= fmt_date($p['due_date']) ?></div>
            </td>
            <td><strong><?= fmt_currency((float)$p['amount']) ?></strong></td>
            <td><?= $methodLabels[$p['method']]??h($p['method']) ?></td>
            <td class="text-sm"><?= fmt_date($p['payment_date'],'d M Y') ?></td>
            <td>
              <?php if ($p['is_simulated']): ?>
              <span class="badge badge-secondary">Simulated</span>
              <?php else: ?>
              <span class="badge badge-success">Real</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="/CoachFee/src/staff/receipt.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-icon-sm" title="View Receipt"><i data-lucide="file-text"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($payments)): ?>
    <div class="card-footer" style="text-align:right;">
      <strong>Total Shown: <?= fmt_currency($totalAmount) ?></strong>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include BASE_PATH . '/templates/footer.php'; ?>
