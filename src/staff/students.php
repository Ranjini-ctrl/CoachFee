<?php
/**
 * CoachFee – Student Management
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pageTitle  = 'Students';
$activePage = 'students';
$pdo = db();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf()) { set_flash('error','Invalid request.'); header('Location: /CoachFee/src/staff/students.php'); exit; }
    $sid = sanitize_int($_POST['student_id'] ?? 0);
    try {
        $pdo->prepare("UPDATE students SET status='inactive' WHERE id=?")->execute([$sid]);
        set_flash('success','Student deactivated successfully.');
    } catch (Exception $e) {
        set_flash('error','Could not deactivate student.');
    }
    header('Location: /CoachFee/src/staff/students.php'); exit;
}

// Filters
$search    = trim($_GET['q'] ?? '');
$statusF   = $_GET['status'] ?? '';
$batchF    = sanitize_int($_GET['batch_id'] ?? 0);

$where = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(s.name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like,$like,$like,$like]);
}
if ($statusF) { $where[] = "s.status = ?"; $params[] = $statusF; }
if ($batchF)  { $where[] = "s.batch_id = ?"; $params[] = $batchF; }

$sql = "SELECT s.*, b.name AS batch_name, b.course_name,
               COALESCE(fs.net_fee,0) AS net_fee,
               COALESCE(paid_sum.paid,0) AS total_paid
        FROM students s
        LEFT JOIN batches b ON b.id = s.batch_id
        LEFT JOIN fee_structures fs ON fs.student_id = s.id
        LEFT JOIN (SELECT student_id, SUM(amount) AS paid FROM payments GROUP BY student_id) paid_sum ON paid_sum.student_id = s.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$batches = $pdo->query("SELECT id, name, course_name FROM batches WHERE is_active=1 ORDER BY name")->fetchAll();

include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title">
      <h2>Student Management</h2>
      <p><?= count($students) ?> student(s) found</p>
    </div>
    <a href="/CoachFee/src/staff/student_form.php" class="btn btn-primary">
      <i data-lucide="user-plus"></i> Add Student
    </a>
  </div>

  <!-- Filters -->
  <div class="card" style="margin-bottom:var(--space-6);">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;width:100%;align-items:center;">
        <div class="search-bar" style="flex:1;min-width:200px;">
          <i data-lucide="search"></i>
          <input type="text" name="q" class="form-control" placeholder="Search name, ID, email…" value="<?= h($search) ?>">
        </div>
        <select name="status" class="form-control" style="width:150px;">
          <option value="">All Status</option>
          <?php foreach (['active','inactive','dropout','completed'] as $st): ?>
          <option value="<?= $st ?>" <?= $statusF===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="batch_id" class="form-control" style="width:180px;">
          <option value="">All Batches</option>
          <?php foreach ($batches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $batchF==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="filter"></i> Filter</button>
        <a href="/CoachFee/src/staff/students.php" class="btn btn-outline btn-sm">Clear</a>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="card">
    <div class="table-wrapper">
      <table id="studentsTable">
        <thead>
          <tr>
            <th>Student</th>
            <th>ID</th>
            <th>Batch</th>
            <th>Net Fee</th>
            <th>Paid</th>
            <th>Outstanding</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($students)): ?>
          <tr><td colspan="8">
            <div class="empty-state">
              <div class="empty-state-icon"><i data-lucide="users"></i></div>
              <h3>No students found</h3>
              <p>Try adjusting your search filters</p>
            </div>
          </td></tr>
          <?php else: ?>
          <?php foreach ($students as $s):
            $outstanding = $s['net_fee'] - $s['total_paid'];
            $pct = $s['net_fee'] > 0 ? min(100, round($s['total_paid']/$s['net_fee']*100)) : 0;
            $initials = implode('',array_map(fn($w)=>strtoupper($w[0]),array_slice(explode(' ',$s['name']),0,2)));
            $statusColors = ['active'=>'badge-success','inactive'=>'badge-secondary','dropout'=>'badge-danger','completed'=>'badge-info'];
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="avatar avatar-sm"><?= h($initials) ?></div>
                <div>
                  <div style="font-weight:600;color:var(--primary);"><?= h($s['name']) ?></div>
                  <div class="text-xs text-muted"><?= h($s['email'] ?? '—') ?></div>
                </div>
              </div>
            </td>
            <td><code style="font-size:.78rem;"><?= h($s['student_id']) ?></code></td>
            <td>
              <div style="font-size:.85rem;"><?= h($s['batch_name'] ?? '—') ?></div>
              <div class="text-xs text-muted"><?= h($s['course_name'] ?? '') ?></div>
            </td>
            <td><strong><?= fmt_currency((float)$s['net_fee']) ?></strong></td>
            <td>
              <div><?= fmt_currency((float)$s['total_paid']) ?></div>
              <div class="progress" style="margin-top:4px;width:80px;">
                <div class="progress-bar <?= $pct>=100?'success':($pct>=50?'':'warning') ?>" style="width:<?= $pct ?>%"></div>
              </div>
            </td>
            <td style="color:<?= $outstanding>0?'var(--danger)':'var(--success)' ?>;font-weight:600;">
              <?= fmt_currency((float)$outstanding) ?>
            </td>
            <td><span class="badge <?= $statusColors[$s['status']] ?? 'badge-secondary' ?>"><?= ucfirst($s['status']) ?></span></td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="/CoachFee/src/staff/student_detail.php?id=<?= $s['id'] ?>" class="btn btn-ghost btn-icon-sm" title="View Details"><i data-lucide="eye"></i></a>
                <a href="/CoachFee/src/staff/student_form.php?id=<?= $s['id'] ?>" class="btn btn-ghost btn-icon-sm" title="Edit"><i data-lucide="edit-2"></i></a>
                <button class="btn btn-ghost btn-icon-sm" title="Deactivate"
                  onclick="confirmAction('Deactivate <?= h(addslashes($s['name'])) ?>?', () => submitDelete(<?= $s['id'] ?>))">
                  <i data-lucide="user-x" style="color:var(--danger);"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Delete form -->
<form id="deleteForm" method="POST" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="student_id" id="deleteStudentId">
</form>

<?php
$extraJs = <<<JS
function submitDelete(id) {
    document.getElementById('deleteStudentId').value = id;
    document.getElementById('deleteForm').submit();
}
JS;
include BASE_PATH . '/templates/footer.php';
?>
