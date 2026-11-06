<?php
/**
 * CoachFee – Reminders Management
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/database.php';
require_role('staff');

$pdo = db();

// Handle send reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { set_flash('error','Invalid request.'); header('Location: /CoachFee/src/staff/reminders.php'); exit; }

    $action = $_POST['action'] ?? '';

    if ($action === 'send_reminder') {
        $remId = sanitize_int($_POST['reminder_id'] ?? 0);
        $pdo->prepare("UPDATE reminders SET status='sent', sent_at=NOW() WHERE id=?")->execute([$remId]);
        set_flash('success','Demo reminder marked as sent.');
        header('Location: /CoachFee/src/staff/reminders.php'); exit;

    } elseif ($action === 'generate_reminders') {
        // Auto-generate reminders for overdue and upcoming (7 days)
        $overdue = $pdo->query("
            SELECT s.id AS student_id, s.name, i.id AS instalment_id, i.amount, i.due_date
            FROM instalments i
            JOIN fee_structures fs ON fs.id=i.fee_structure_id
            JOIN students s ON s.id=fs.student_id
            WHERE i.status='overdue' AND i.id NOT IN (SELECT instalment_id FROM reminders WHERE type='overdue' AND status='sent' AND instalment_id IS NOT NULL)
            LIMIT 50")->fetchAll();

        $upcoming = $pdo->query("
            SELECT s.id AS student_id, s.name, i.id AS instalment_id, i.amount, i.due_date
            FROM instalments i
            JOIN fee_structures fs ON fs.id=i.fee_structure_id
            JOIN students s ON s.id=fs.student_id
            WHERE i.status='pending' AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND i.id NOT IN (SELECT COALESCE(instalment_id,0) FROM reminders WHERE type='upcoming')
            LIMIT 50")->fetchAll();

        $count = 0;
        foreach (array_merge($overdue, $upcoming) as $r) {
            $type = strtotime($r['due_date']) < time() ? 'overdue' : 'upcoming';
            $msg  = "Dear {$r['name']}, your instalment of " . fmt_currency((float)$r['amount']) .
                    " " . ($type==='overdue' ? "was due on " . fmt_date($r['due_date']) . " and is now overdue. Please pay immediately." :
                    "is due on " . fmt_date($r['due_date']) . ". Please ensure timely payment.");
            $pdo->prepare("INSERT IGNORE INTO reminders (student_id,instalment_id,type,channel,message,status,created_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$r['student_id'],$r['instalment_id'],$type,'demo',$msg,'pending',$_SESSION['user_id']]);
            $count++;
        }
        set_flash('success', "$count reminder(s) generated.");
        header('Location: /CoachFee/src/staff/reminders.php'); exit;

    } elseif ($action === 'send_all') {
        $pdo->prepare("UPDATE reminders SET status='sent', sent_at=NOW() WHERE status='pending'")->execute();
        set_flash('success','All pending reminders marked as sent (demo mode).');
        header('Location: /CoachFee/src/staff/reminders.php'); exit;
    }
}

// Load reminders
$statusF = $_GET['status'] ?? '';
$typeF   = $_GET['type']   ?? '';
$where   = ['1=1'];
$params  = [];
if ($statusF) { $where[] = "r.status=?"; $params[] = $statusF; }
if ($typeF)   { $where[] = "r.type=?";   $params[] = $typeF; }

$reminders = $pdo->prepare("
    SELECT r.*, s.name AS student_name, s.student_id AS sid, i.instalment_no, i.due_date, i.amount AS inst_amount
    FROM reminders r
    JOIN students s ON s.id=r.student_id
    LEFT JOIN instalments i ON i.id=r.instalment_id
    WHERE " . implode(' AND ',$where) . "
    ORDER BY r.created_at DESC");
$reminders->execute($params);
$reminders = $reminders->fetchAll();

$pendingCount = $pdo->query("SELECT COUNT(*) FROM reminders WHERE status='pending'")->fetchColumn();

$pageTitle  = 'Reminders';
$activePage = 'reminders';
include BASE_PATH . '/templates/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div class="page-header-title">
      <h2>Fee Reminders</h2>
      <p><?= $pendingCount ?> pending reminder(s)</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <form method="POST" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate_reminders">
        <button type="submit" class="btn btn-outline"><i data-lucide="refresh-cw"></i> Auto-Generate</button>
      </form>
      <?php if ($pendingCount > 0): ?>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Send all <?= $pendingCount ?> pending reminders in demo mode?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_all">
        <button type="submit" class="btn btn-primary"><i data-lucide="send"></i> Send All Pending</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Demo Notice -->
  <div class="sim-banner"><i data-lucide="info" style="width:14px;height:14px;"></i>
    <strong>Demo mode:</strong> Reminders are stored in the database and marked as "sent" — no actual SMS or email is delivered. Connect an SMS/email provider to enable real delivery.
  </div>

  <!-- Filters -->
  <div class="card" style="margin-bottom:var(--space-6);">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <select name="status" class="form-control" style="width:150px;">
          <option value="">All Status</option>
          <option value="pending" <?= $statusF==='pending'?'selected':'' ?>>Pending</option>
          <option value="sent"    <?= $statusF==='sent'   ?'selected':'' ?>>Sent</option>
          <option value="failed"  <?= $statusF==='failed' ?'selected':'' ?>>Failed</option>
        </select>
        <select name="type" class="form-control" style="width:150px;">
          <option value="">All Types</option>
          <option value="upcoming" <?= $typeF==='upcoming'?'selected':'' ?>>Upcoming</option>
          <option value="overdue"  <?= $typeF==='overdue' ?'selected':'' ?>>Overdue</option>
          <option value="custom"   <?= $typeF==='custom'  ?'selected':'' ?>>Custom</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="filter"></i> Filter</button>
        <a href="/CoachFee/src/staff/reminders.php" class="btn btn-outline btn-sm">Clear</a>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Student</th><th>Type</th><th>Message</th><th>Instalment</th><th>Status</th><th>Sent At</th><th></th></tr>
        </thead>
        <tbody>
          <?php if (empty($reminders)): ?>
          <tr><td colspan="7"><div class="empty-state" style="padding:var(--space-8);">
            <div class="empty-state-icon"><i data-lucide="bell-off"></i></div>
            <h3>No reminders</h3>
            <p>Click "Auto-Generate" to create reminders for overdue and upcoming instalments</p>
          </div></td></tr>
          <?php else: ?>
          <?php foreach ($reminders as $r): ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= h($r['student_name']) ?></div>
              <div class="text-xs text-muted"><?= h($r['sid']) ?></div>
            </td>
            <td>
              <span class="badge <?= $r['type']==='overdue'?'badge-danger':($r['type']==='upcoming'?'badge-warning':'badge-secondary') ?>">
                <?= ucfirst($r['type']) ?>
              </span>
            </td>
            <td style="max-width:260px;font-size:.82rem;color:var(--text-muted);"><?= h(substr($r['message'],0,100)) ?>…</td>
            <td>
              <?php if ($r['instalment_no']): ?>
              <div>Instalment <?= $r['instalment_no'] ?></div>
              <div class="text-xs text-muted"><?= fmt_currency((float)$r['inst_amount']) ?> · <?= fmt_date($r['due_date']) ?></div>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $r['status']==='sent'?'badge-success':($r['status']==='failed'?'badge-danger':'badge-warning') ?>">
                <?= ucfirst($r['status']) ?>
              </span>
            </td>
            <td class="text-sm text-muted"><?= $r['sent_at'] ? fmt_date($r['sent_at'],'d M Y g:i A') : '—' ?></td>
            <td>
              <?php if ($r['status']==='pending'): ?>
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_reminder">
                <input type="hidden" name="reminder_id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-accent btn-sm"><i data-lucide="send"></i> Send</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/templates/footer.php'; ?>
