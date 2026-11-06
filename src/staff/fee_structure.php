<?php
/**
 * CoachFee – Fee Structure Management (Staff)
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pdo       = db();
$studentId = sanitize_int($_GET['student_id'] ?? 0);
$errors    = [];

if (!$studentId) { header('Location: /CoachFee/src/staff/students.php'); exit; }

$student = $pdo->prepare("SELECT * FROM students WHERE id=?");
$student->execute([$studentId]);
$student = $student->fetch();
if (!$student) { set_flash('error','Student not found.'); header('Location: /CoachFee/src/staff/students.php'); exit; }

$fs = $pdo->prepare("SELECT * FROM fee_structures WHERE student_id=? LIMIT 1");
$fs->execute([$studentId]);
$fs = $fs->fetch();

$instalments = $fs ? $pdo->query("SELECT * FROM instalments WHERE fee_structure_id={$fs['id']} ORDER BY instalment_no")->fetchAll() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $errors[] = 'Invalid request.'; }
    else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_fee') {
            $totalFee  = sanitize_decimal($_POST['total_fee'] ?? 0);
            $discount  = sanitize_decimal($_POST['discount']  ?? 0);
            $netFee    = $totalFee - $discount;
            $notes     = trim($_POST['notes'] ?? '');

            if ($totalFee <= 0) $errors[] = 'Total fee must be greater than zero.';
            if ($discount > $totalFee) $errors[] = 'Discount cannot exceed total fee.';

            if (empty($errors)) {
                if ($fs) {
                    $pdo->prepare("UPDATE fee_structures SET total_fee=?,discount=?,net_fee=?,notes=? WHERE id=?")
                        ->execute([$totalFee,$discount,$netFee,$notes,$fs['id']]);
                    set_flash('success','Fee structure updated.');
                } else {
                    $pdo->prepare("INSERT INTO fee_structures (student_id,total_fee,discount,net_fee,notes) VALUES (?,?,?,?,?)")
                        ->execute([$studentId,$totalFee,$discount,$netFee,$notes]);
                    set_flash('success','Fee structure created.');
                }
                header('Location: /CoachFee/src/staff/fee_structure.php?student_id='.$studentId); exit;
            }

        } elseif ($action === 'add_instalment') {
            $fsId    = sanitize_int($_POST['fs_id'] ?? 0);
            $amount  = sanitize_decimal($_POST['amount'] ?? 0);
            $dueDate = $_POST['due_date'] ?? '';
            $no      = sanitize_int($_POST['instalment_no'] ?? 1);

            if ($amount <= 0)   $errors[] = 'Instalment amount must be greater than zero.';
            if (!$dueDate)      $errors[] = 'Due date is required.';

            if (empty($errors)) {
                $pdo->prepare("INSERT INTO instalments (fee_structure_id,instalment_no,amount,due_date,status) VALUES (?,?,?,?,'pending')")
                    ->execute([$fsId,$no,$amount,$dueDate]);
                set_flash('success','Instalment added.');
                header('Location: /CoachFee/src/staff/fee_structure.php?student_id='.$studentId); exit;
            }

        } elseif ($action === 'delete_instalment') {
            $instId = sanitize_int($_POST['instalment_id'] ?? 0);
            // Prevent deleting paid instalments
            $check = $pdo->prepare("SELECT status FROM instalments WHERE id=?");
            $check->execute([$instId]);
            $instStatus = $check->fetchColumn();
            if ($instStatus === 'paid') {
                set_flash('error','Cannot delete a paid instalment.');
            } else {
                $pdo->prepare("DELETE FROM instalments WHERE id=?")->execute([$instId]);
                set_flash('success','Instalment removed.');
            }
            header('Location: /CoachFee/src/staff/fee_structure.php?student_id='.$studentId); exit;
        }
    }
}

// Reload after any updates
$fs = $pdo->prepare("SELECT * FROM fee_structures WHERE student_id=? LIMIT 1");
$fs->execute([$studentId]);
$fs = $fs->fetch();
$instalments = $fs ? $pdo->query("SELECT i.*, COALESCE(p.amount,0) AS paid_amount FROM instalments i LEFT JOIN payments p ON p.instalment_id=i.id WHERE i.fee_structure_id={$fs['id']} ORDER BY i.instalment_no")->fetchAll() : [];
$totalInstalments = array_sum(array_column($instalments,'amount'));
$totalPaid = array_sum(array_column($instalments,'paid_amount'));
$nextNo = count($instalments) + 1;

$pageTitle  = 'Fee Structure – ' . h($student['name']);
$activePage = 'students';
include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title">
      <h2>Fee Structure</h2>
      <p>Managing fees for <strong><?= h($student['name']) ?></strong> (<?= h($student['student_id']) ?>)</p>
    </div>
    <a href="/CoachFee/src/staff/student_detail.php?id=<?= $studentId ?>" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back to Student</a>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger"><i data-lucide="alert-circle"></i>
    <ul style="margin:0;padding-left:16px;"><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);">

    <!-- Fee Structure Form -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i data-lucide="indian-rupee" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Fee Configuration</span></div>
      <div class="card-body">
        <form method="POST" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_fee">
          <div class="form-group">
            <label>Total Course Fee (₹)</label>
            <input type="number" name="total_fee" class="form-control" step="0.01" min="0" required
              value="<?= h($fs['total_fee'] ?? '') ?>" placeholder="48000">
          </div>
          <div class="form-group">
            <label>Discount (₹)</label>
            <input type="number" name="discount" class="form-control" step="0.01" min="0"
              value="<?= h($fs['discount'] ?? '0') ?>" placeholder="0">
          </div>
          <?php if ($fs): ?>
          <div style="background:rgba(101,184,166,.08);border-radius:var(--radius-md);padding:var(--space-4);margin-bottom:var(--space-5);">
            <div style="display:flex;justify-content:space-between;"><span class="text-muted">Net Fee</span><strong><?= fmt_currency((float)$fs['net_fee']) ?></strong></div>
            <div style="display:flex;justify-content:space-between;margin-top:6px;"><span class="text-muted">Total Paid</span><strong style="color:var(--success);"><?= fmt_currency($totalPaid) ?></strong></div>
            <div style="display:flex;justify-content:space-between;margin-top:6px;"><span class="text-muted">Outstanding</span><strong style="color:var(--danger);"><?= fmt_currency(max(0,(float)$fs['net_fee']-$totalPaid)) ?></strong></div>
          </div>
          <?php endif; ?>
          <div class="form-group">
            <label>Notes</label>
            <input type="text" name="notes" class="form-control" value="<?= h($fs['notes'] ?? '') ?>" placeholder="Optional notes">
          </div>
          <button type="submit" class="btn btn-primary w-full"><i data-lucide="save"></i> <?= $fs ? 'Update' : 'Create' ?> Fee Structure</button>
        </form>
      </div>
    </div>

    <!-- Add Instalment -->
    <?php if ($fs): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i data-lucide="plus-circle" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Add Instalment</span></div>
      <div class="card-body">
        <form method="POST" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_instalment">
          <input type="hidden" name="fs_id" value="<?= $fs['id'] ?>">
          <div class="form-group">
            <label>Instalment Number</label>
            <input type="number" name="instalment_no" class="form-control" value="<?= $nextNo ?>" min="1">
          </div>
          <div class="form-group">
            <label>Amount (₹)</label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0" required placeholder="e.g. 12000">
          </div>
          <div class="form-group">
            <label>Due Date</label>
            <input type="date" name="due_date" class="form-control" required value="<?= date('Y-m-d') ?>">
          </div>
          <button type="submit" class="btn btn-accent w-full"><i data-lucide="plus"></i> Add Instalment</button>
        </form>

        <?php if (!empty($instalments)): ?>
        <hr class="divider">
        <div style="font-size:.8rem;font-weight:600;color:var(--text-muted);margin-bottom:8px;">
          Total instalments: <?= fmt_currency($totalInstalments) ?>
          <?php if (abs($totalInstalments - (float)$fs['net_fee']) > 0.01): ?>
          <span style="color:var(--warning);">⚠ Doesn't match net fee (<?= fmt_currency((float)$fs['net_fee']) ?>)</span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Instalments List -->
  <?php if (!empty($instalments)): ?>
  <div class="card" style="margin-top:var(--space-6);">
    <div class="card-header"><span class="card-title">Instalment Plan</span></div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>#</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Paid</th><th></th></tr></thead>
        <tbody>
          <?php $sc=['paid'=>'badge-success','pending'=>'badge-warning','overdue'=>'badge-danger','partial'=>'badge-secondary']; ?>
          <?php foreach ($instalments as $i): ?>
          <tr>
            <td style="font-weight:700;"><?= $i['instalment_no'] ?></td>
            <td><?= fmt_currency((float)$i['amount']) ?></td>
            <td><?= fmt_date($i['due_date']) ?></td>
            <td><span class="badge <?= $sc[$i['status']]??'badge-secondary' ?>"><?= ucfirst($i['status']) ?></span></td>
            <td><?= (float)$i['paid_amount'] > 0 ? fmt_currency((float)$i['paid_amount']) : '—' ?></td>
            <td>
              <?php if ($i['status'] !== 'paid'): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this instalment?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_instalment">
                <input type="hidden" name="instalment_id" value="<?= $i['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-icon-sm" title="Delete"><i data-lucide="trash-2" style="color:var(--danger);"></i></button>
              </form>
              <?php else: ?>
              <i data-lucide="lock" style="width:14px;height:14px;color:var(--text-light);" title="Paid – cannot delete"></i>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>
<?php include BASE_PATH . '/templates/footer.php'; ?>
