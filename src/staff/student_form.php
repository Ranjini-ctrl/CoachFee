<?php
/**
 * CoachFee – Add / Edit Student Form
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pdo    = db();
$id     = sanitize_int($_GET['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];
$student = [];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if (!$student) { set_flash('error','Student not found.'); header('Location: /CoachFee/src/staff/students.php'); exit; }
}

$batches  = $pdo->query("SELECT * FROM batches WHERE is_active=1 ORDER BY name")->fetchAll();
$parents  = $pdo->query("SELECT id,name,email FROM users WHERE role='parent' AND is_active=1 ORDER BY name")->fetchAll();

// Get currently linked parents
$linkedParents = [];
if ($isEdit) {
    $lp = $pdo->prepare("SELECT parent_id FROM parent_students WHERE student_id=?");
    $lp->execute([$id]);
    $linkedParents = array_column($lp->fetchAll(), 'parent_id');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $errors[] = 'Invalid request.'; }
    else {
        $name        = trim($_POST['name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $dob         = $_POST['dob'] ?? '';
        $address     = trim($_POST['address'] ?? '');
        $batch_id    = sanitize_int($_POST['batch_id'] ?? 0);
        $enroll_date = $_POST['enroll_date'] ?? date('Y-m-d');
        $status      = $_POST['status'] ?? 'active';
        $notes       = trim($_POST['notes'] ?? '');
        $selectedParents = $_POST['parent_ids'] ?? [];

        // Validation
        if (!$name)         $errors[] = 'Student name is required.';
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (!$enroll_date)  $errors[] = 'Enrolment date is required.';

        // Fee structure
        $totalFee  = sanitize_decimal($_POST['total_fee'] ?? 0);
        $discount  = sanitize_decimal($_POST['discount'] ?? 0);
        $netFee    = $totalFee - $discount;
        $instCount = sanitize_int($_POST['instalment_count'] ?? 4);
        $instStart = $_POST['instalment_start'] ?? date('Y-m-d');

        if ($totalFee <= 0 && !$isEdit) $errors[] = 'Total fee must be greater than zero.';
        if ($discount > $totalFee)      $errors[] = 'Discount cannot exceed total fee.';

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                if ($isEdit) {
                    $pdo->prepare("UPDATE students SET name=?,email=?,phone=?,dob=?,address=?,batch_id=?,enroll_date=?,status=?,notes=? WHERE id=?")
                        ->execute([$name,$email,$phone,$dob,$address,$batch_id?:null,$enroll_date,$status,$notes,$id]);
                } else {
                    // Auto-generate student ID
                    $year    = date('Y');
                    $count   = $pdo->query("SELECT COUNT(*) FROM students WHERE YEAR(created_at)=$year")->fetchColumn() + 1;
                    $sid     = 'CF-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
                    $pdo->prepare("INSERT INTO students (student_id,name,email,phone,dob,address,batch_id,enroll_date,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$sid,$name,$email,$phone,$dob,$address,$batch_id?:null,$enroll_date,$status,$notes]);
                    $id = $pdo->lastInsertId();
                }

                // Fee structure (only create if not editing or no existing)
                if (!$isEdit && $totalFee > 0) {
                    $pdo->prepare("INSERT INTO fee_structures (student_id,batch_id,total_fee,discount,net_fee) VALUES (?,?,?,?,?)")
                        ->execute([$id,$batch_id?:null,$totalFee,$discount,$netFee]);
                    $fsId = $pdo->lastInsertId();

                    // Create instalments
                    if ($instCount > 0 && $instStart) {
                        $instAmount = round($netFee / $instCount, 2);
                        $remainder  = $netFee - ($instAmount * ($instCount - 1));
                        $dueDate    = new DateTime($instStart);
                        for ($i = 1; $i <= $instCount; $i++) {
                            $amt = ($i === $instCount) ? $remainder : $instAmount;
                            $pdo->prepare("INSERT INTO instalments (fee_structure_id,instalment_no,amount,due_date,status) VALUES (?,?,?,?,?)")
                                ->execute([$fsId,$i,$amt,$dueDate->format('Y-m-d'),'pending']);
                            $dueDate->modify('+3 months');
                        }
                    }
                }

                // Parent links
                $pdo->prepare("DELETE FROM parent_students WHERE student_id=?")->execute([$id]);
                foreach ($selectedParents as $pid) {
                    $pid = sanitize_int($pid);
                    if ($pid > 0) {
                        $pdo->prepare("INSERT IGNORE INTO parent_students (parent_id,student_id) VALUES (?,?)")->execute([$pid,$id]);
                    }
                }

                $pdo->commit();
                set_flash('success', $isEdit ? 'Student updated successfully.' : 'Student added successfully.');
                header('Location: /CoachFee/src/staff/student_detail.php?id=' . $id);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle  = $isEdit ? 'Edit Student' : 'Add Student';
$activePage = 'students';
include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title">
      <h2><?= $isEdit ? 'Edit Student' : 'Add New Student' ?></h2>
      <p><?= $isEdit ? 'Update student information' : 'Register a new student and set up fee structure' ?></p>
    </div>
    <a href="/CoachFee/src/staff/students.php" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger"><i data-lucide="alert-circle"></i>
    <ul style="margin:0;padding-left:16px;"><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <?= csrf_field() ?>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-6);">

      <!-- Left Column -->
      <div style="display:flex;flex-direction:column;gap:var(--space-6);">

        <!-- Personal Info -->
        <div class="card">
          <div class="card-header"><span class="card-title"><i data-lucide="user" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Personal Information</span></div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group">
                <label for="name">Full Name <span style="color:var(--danger);">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= h($student['name'] ?? $_POST['name'] ?? '') ?>" placeholder="e.g. Aarav Verma">
              </div>
              <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" id="dob" name="dob" class="form-control" value="<?= h($student['dob'] ?? $_POST['dob'] ?? '') ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= h($student['email'] ?? $_POST['email'] ?? '') ?>" placeholder="student@example.com">
              </div>
              <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?= h($student['phone'] ?? $_POST['phone'] ?? '') ?>" placeholder="9000000001">
              </div>
            </div>
            <div class="form-group">
              <label for="address">Address</label>
              <textarea id="address" name="address" class="form-control" rows="2" placeholder="Student's home address"><?= h($student['address'] ?? $_POST['address'] ?? '') ?></textarea>
            </div>
          </div>
        </div>

        <!-- Enrolment -->
        <div class="card">
          <div class="card-header"><span class="card-title"><i data-lucide="book-open" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Enrolment Details</span></div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group">
                <label for="batch_id">Batch / Course</label>
                <select id="batch_id" name="batch_id" class="form-control">
                  <option value="">— Select Batch —</option>
                  <?php foreach ($batches as $b): ?>
                  <option value="<?= $b['id'] ?>" <?= ($student['batch_id']??$_POST['batch_id']??'')==$b['id']?'selected':'' ?>>
                    <?= h($b['name']) ?> – <?= h($b['course_name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="enroll_date">Enrolment Date <span style="color:var(--danger);">*</span></label>
                <input type="date" id="enroll_date" name="enroll_date" class="form-control" required value="<?= h($student['enroll_date'] ?? $_POST['enroll_date'] ?? date('Y-m-d')) ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                  <?php foreach (['active','inactive','dropout','completed'] as $st): ?>
                  <option value="<?= $st ?>" <?= ($student['status']??'active')===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="notes">Notes</label>
                <input type="text" id="notes" name="notes" class="form-control" value="<?= h($student['notes'] ?? '') ?>" placeholder="Optional notes">
              </div>
            </div>
          </div>
        </div>

        <?php if (!$isEdit): ?>
        <!-- Fee Structure (only on add) -->
        <div class="card">
          <div class="card-header"><span class="card-title"><i data-lucide="indian-rupee" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Fee Structure</span></div>
          <div class="card-body">
            <div class="alert alert-info" style="margin-bottom:var(--space-5);"><i data-lucide="info"></i> Fee structure can be updated later from Student Detail page.</div>
            <div class="form-row">
              <div class="form-group">
                <label for="total_fee">Total Course Fee (₹) <span style="color:var(--danger);">*</span></label>
                <input type="number" id="total_fee" name="total_fee" class="form-control" step="0.01" min="0" value="<?= h($_POST['total_fee'] ?? '') ?>" placeholder="48000" oninput="calcNet()">
              </div>
              <div class="form-group">
                <label for="discount">Discount (₹)</label>
                <input type="number" id="discount" name="discount" class="form-control" step="0.01" min="0" value="<?= h($_POST['discount'] ?? '0') ?>" placeholder="0" oninput="calcNet()">
              </div>
            </div>
            <div style="background:var(--bg);border-radius:var(--radius-md);padding:var(--space-4);margin-bottom:var(--space-5);display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">Net Payable Fee</span>
              <span id="netFeeDisplay" style="font-size:1.2rem;font-weight:700;color:var(--accent);">₹0.00</span>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="instalment_count">Number of Instalments</label>
                <select id="instalment_count" name="instalment_count" class="form-control">
                  <option value="1">1 (Full payment)</option>
                  <option value="2">2 (Half-yearly)</option>
                  <option value="3">3 (Term-wise)</option>
                  <option value="4" selected>4 (Quarterly)</option>
                  <option value="6">6 (Bi-monthly)</option>
                  <option value="12">12 (Monthly)</option>
                </select>
              </div>
              <div class="form-group">
                <label for="instalment_start">First Instalment Due Date</label>
                <input type="date" id="instalment_start" name="instalment_start" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /left -->

      <!-- Right Column -->
      <div style="display:flex;flex-direction:column;gap:var(--space-6);">

        <!-- Parent Links -->
        <div class="card">
          <div class="card-header"><span class="card-title"><i data-lucide="users" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Link Parent Accounts</span></div>
          <div class="card-body" style="max-height:300px;overflow-y:auto;">
            <?php foreach ($parents as $p): ?>
            <label style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light);cursor:pointer;font-weight:400;">
              <input type="checkbox" name="parent_ids[]" value="<?= $p['id'] ?>"
                <?= in_array($p['id'], $linkedParents) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent);">
              <div>
                <div style="font-weight:600;font-size:.88rem;"><?= h($p['name']) ?></div>
                <div class="text-xs text-muted"><?= h($p['email']) ?></div>
              </div>
            </label>
            <?php endforeach; ?>
            <?php if (empty($parents)): ?>
            <p class="text-muted text-sm">No parent accounts exist yet.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Submit -->
        <div class="card">
          <div class="card-body">
            <button type="submit" class="btn btn-primary w-full" style="margin-bottom:10px;">
              <i data-lucide="save"></i> <?= $isEdit ? 'Update Student' : 'Add Student' ?>
            </button>
            <a href="/CoachFee/src/staff/students.php" class="btn btn-outline w-full">Cancel</a>
          </div>
        </div>

      </div><!-- /right -->
    </div>
  </form>
</div>

<?php
$extraJs = <<<JS
function calcNet() {
    const total    = parseFloat(document.getElementById('total_fee').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value)  || 0;
    const net = Math.max(0, total - discount);
    document.getElementById('netFeeDisplay').textContent = '₹' + net.toLocaleString('en-IN', {minimumFractionDigits:2});
}
calcNet();
JS;
include BASE_PATH . '/templates/footer.php';
?>
