<?php
/**
 * CoachFee – Batch Management
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pdo    = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $errors[] = 'Invalid request.'; }
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $bid         = sanitize_int($_POST['batch_id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $course      = trim($_POST['course_name'] ?? '');
            $desc        = trim($_POST['description'] ?? '');
            $totalFee    = sanitize_decimal($_POST['total_fee'] ?? 0);
            $startDate   = $_POST['start_date'] ?? '';
            $endDate     = $_POST['end_date'] ?? '';
            $maxStudents = sanitize_int($_POST['max_students'] ?? 50);

            if (!$name)   $errors[] = 'Batch name is required.';
            if (!$course) $errors[] = 'Course name is required.';

            if (empty($errors)) {
                if ($bid > 0) {
                    $pdo->prepare("UPDATE batches SET name=?,course_name=?,description=?,total_fee=?,start_date=?,end_date=?,max_students=? WHERE id=?")
                        ->execute([$name,$course,$desc,$totalFee,$startDate?:null,$endDate?:null,$maxStudents,$bid]);
                    set_flash('success','Batch updated.');
                } else {
                    $pdo->prepare("INSERT INTO batches (name,course_name,description,total_fee,start_date,end_date,max_students) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$name,$course,$desc,$totalFee,$startDate?:null,$endDate?:null,$maxStudents]);
                    set_flash('success','Batch created.');
                }
                header('Location: /CoachFee/src/staff/batches.php'); exit;
            }
        } elseif ($action === 'toggle') {
            $bid = sanitize_int($_POST['batch_id'] ?? 0);
            $pdo->prepare("UPDATE batches SET is_active = 1 - is_active WHERE id=?")->execute([$bid]);
            set_flash('success','Batch status updated.');
            header('Location: /CoachFee/src/staff/batches.php'); exit;
        }
    }
}

$batches = $pdo->query("
    SELECT b.*, COUNT(s.id) AS student_count
    FROM batches b
    LEFT JOIN students s ON s.batch_id=b.id AND s.status='active'
    GROUP BY b.id ORDER BY b.created_at DESC")->fetchAll();

// Edit mode
$editBatch = null;
if (isset($_GET['edit'])) {
    $editId = sanitize_int($_GET['edit']);
    foreach ($batches as $b) { if ($b['id'] == $editId) { $editBatch = $b; break; } }
}

$pageTitle  = 'Batches';
$activePage = 'batches';
include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title"><h2>Batch / Course Management</h2><p>Manage courses and batches</p></div>
    <button class="btn btn-primary" onclick="openModal('batchModal')"><i data-lucide="plus"></i> New Batch</button>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger"><i data-lucide="alert-circle"></i>
    <ul style="margin:0;padding-left:16px;"><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <div class="grid-cols-3">
    <?php foreach ($batches as $b):
      $pct = $b['max_students'] > 0 ? min(100, round($b['student_count']/$b['max_students']*100)) : 0;
    ?>
    <div class="card" style="<?= !$b['is_active']?'opacity:.7;':'' ?>">
      <div class="card-header">
        <div>
          <div class="card-title"><?= h($b['name']) ?></div>
          <div class="text-xs text-muted"><?= h($b['course_name']) ?></div>
        </div>
        <span class="badge <?= $b['is_active']?'badge-success':'badge-secondary' ?>"><?= $b['is_active']?'Active':'Inactive' ?></span>
      </div>
      <div class="card-body">
        <?php if ($b['description']): ?><p class="text-sm text-muted" style="margin-bottom:12px;"><?= h($b['description']) ?></p><?php endif; ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <span class="text-muted text-sm">Students</span>
          <span style="font-weight:600;"><?= $b['student_count'] ?> / <?= $b['max_students'] ?></span>
        </div>
        <div class="progress" style="margin-bottom:12px;">
          <div class="progress-bar <?= $pct>=90?'danger':($pct>=70?'warning':'success') ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.82rem;">
          <div><span class="text-muted">Fee</span><div style="font-weight:700;"><?= fmt_currency((float)$b['total_fee']) ?></div></div>
          <div><span class="text-muted">Start</span><div style="font-weight:600;"><?= fmt_date($b['start_date']) ?></div></div>
        </div>
      </div>
      <div class="card-footer" style="display:flex;gap:8px;">
        <a href="?edit=<?= $b['id'] ?>" class="btn btn-outline btn-sm" onclick="openModal('batchModal');return false;" data-edit="<?= $b['id'] ?>"><i data-lucide="edit-2"></i> Edit</a>
        <form method="POST" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm"><?= $b['is_active']?'Deactivate':'Activate' ?></button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Batch Modal -->
<div class="modal-overlay" id="batchModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">New Batch</span>
      <button onclick="closeModal('batchModal')" class="btn btn-ghost btn-icon-sm"><i data-lucide="x"></i></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="batch_id" id="modalBatchId" value="0">
        <div class="form-group"><label>Batch Name *</label><input type="text" name="name" id="mName" class="form-control" required placeholder="e.g. JEE-2026-A"></div>
        <div class="form-group"><label>Course Name *</label><input type="text" name="course_name" id="mCourse" class="form-control" required placeholder="e.g. JEE Main & Advanced"></div>
        <div class="form-group"><label>Description</label><textarea name="description" id="mDesc" class="form-control" rows="2"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Total Fee (₹)</label><input type="number" name="total_fee" id="mFee" class="form-control" step="0.01" min="0" placeholder="48000"></div>
          <div class="form-group"><label>Max Students</label><input type="number" name="max_students" id="mMax" class="form-control" min="1" value="50"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Start Date</label><input type="date" name="start_date" id="mStart" class="form-control"></div>
          <div class="form-group"><label>End Date</label><input type="date" name="end_date" id="mEnd" class="form-control"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('batchModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Batch</button>
      </div>
    </form>
  </div>
</div>

<?php
$batches_json = json_encode(array_column($batches, null, 'id'));
$extraJs = <<<JS
// Pre-fill edit modal
document.querySelectorAll('[data-edit]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.dataset.edit;
        const batches = $batches_json;
        const b = batches[id];
        if (!b) return;
        document.getElementById('modalBatchId').value = b.id;
        document.getElementById('mName').value   = b.name;
        document.getElementById('mCourse').value = b.course_name;
        document.getElementById('mDesc').value   = b.description || '';
        document.getElementById('mFee').value    = b.total_fee;
        document.getElementById('mMax').value    = b.max_students;
        document.getElementById('mStart').value  = b.start_date || '';
        document.getElementById('mEnd').value    = b.end_date   || '';
        document.querySelector('#batchModal .modal-title').textContent = 'Edit Batch';
        openModal('batchModal');
    });
});
JS;
include BASE_PATH . '/templates/footer.php';
?>
