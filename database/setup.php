<?php
/**
 * CoachFee – Auto-Setup via Apache/PHP
 * This runs through the web server which shares MySQL with XAMPP
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$secret = $_GET['secret'] ?? '';
if ($secret !== 'coachfee_setup_2025') {
    die('<h3 style="font-family:sans-serif;color:#D96868;">Access denied. Add ?secret=coachfee_setup_2025</h3>');
}

$host    = '127.0.0.1';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

echo '<html><head><title>CoachFee Setup</title>';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">';
echo '<style>body{font-family:Inter,sans-serif;background:#F2F5FA;padding:40px;}.box{background:#fff;border-radius:16px;padding:32px;max-width:620px;margin:0 auto;box-shadow:0 4px 20px rgba(0,0,0,.1);}h2{color:#24324A;}.ok{background:#f0faf5;border:1px solid #27a070;border-radius:8px;padding:12px 16px;margin:6px 0;color:#1e7a55;font-size:.88rem;}.err{background:#fdf0f0;border:1px solid #d96868;border-radius:8px;padding:12px 16px;margin:6px 0;color:#c04040;font-size:.88rem;}.btn{display:inline-block;background:#65B8A6;color:#fff;padding:14px 28px;border-radius:10px;text-decoration:none;font-weight:600;margin-top:20px;font-size:1rem;}.creds{background:#24324A;color:#fff;border-radius:10px;padding:20px;margin-top:20px;font-size:.88rem;line-height:1.8;}code{color:#65B8A6;}</style></head><body><div class="box">';
echo '<h2>🎓 CoachFee – Database Setup</h2>';

ob_implicit_flush(true);

try {
    // Connect without DB
    $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);

    echo '<div class="ok">✅ Connected to MySQL successfully</div>';

    // Drop and create
    $pdo->exec("DROP DATABASE IF EXISTS coachfee");
    $pdo->exec("CREATE DATABASE coachfee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE coachfee");
    echo '<div class="ok">✅ Database <strong>coachfee</strong> created</div>';

    // Helper: split SQL file into individual statements safely
    function parseSqlFile(string $sql): array {
        // Remove single-line comments (-- style) but keep lines
        $lines = explode("\n", $sql);
        $cleaned = [];
        foreach ($lines as $line) {
            $stripped = trim($line);
            if (strpos($stripped, '--') === 0) continue; // skip full comment lines
            // Remove inline -- comments
            $pos = strpos($stripped, ' --');
            if ($pos !== false) $stripped = substr($stripped, 0, $pos);
            $cleaned[] = $stripped;
        }
        $sql = implode("\n", $cleaned);
        // Remove CREATE DATABASE and USE statements
        $sql = preg_replace('/CREATE\s+DATABASE[^;]+;/i', '', $sql);
        $sql = preg_replace('/USE\s+[^;]+;/i', '', $sql);
        // Split on semicolons
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        return array_values($stmts);
    }

    // Run schema
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) throw new Exception("schema.sql not found at $schemaFile");
    $stmts = parseSqlFile(file_get_contents($schemaFile));
    $cnt = 0;
    foreach ($stmts as $stmt) {
        if ($stmt && strlen($stmt) > 5) { $pdo->exec($stmt); $cnt++; }
    }
    echo "<div class='ok'>✅ Schema created ($cnt tables/indexes)</div>";

    // Run seed
    $seedFile = __DIR__ . '/seed.sql';
    if (!file_exists($seedFile)) throw new Exception("seed.sql not found at $seedFile");
    $seedStmts = parseSqlFile(file_get_contents($seedFile));
    $cnt2 = 0;
    foreach ($seedStmts as $stmt) {
        if ($stmt && strlen($stmt) > 5) { $pdo->exec($stmt); $cnt2++; }
    }
    echo "<div class='ok'>✅ Sample data inserted ($cnt2 statements)</div>";

    // Verification
    $pdo->exec("USE coachfee");
    $checks = [];
    foreach (['users','students','batches','fee_structures','instalments','payments','reminders','risk_analysis'] as $t) {
        $n = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        $checks[] = "$t: <strong>$n</strong>";
    }
    echo "<div class='ok'>✅ Verification — " . implode(' · ', $checks) . "</div>";

    echo '<div class="creds">
        <strong>🔑 Demo Credentials</strong><br><br>
        <strong>Staff:</strong> <code>admin@coachfee.dev</code> / <code>Demo@1234</code><br>
        <strong>Parent:</strong> <code>parent1@coachfee.dev</code> / <code>Demo@1234</code> (OTP: <code>123456</code>)
    </div>';
    echo '<a href="/CoachFee/" class="btn">🚀 Launch CoachFee</a>';
    echo '<p style="margin-top:16px;color:#D96868;font-size:.85rem;">⚠ Delete <code>database/setup.php</code> after setup!</p>';

} catch (Exception $e) {
    echo '<div class="err">❌ <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<div class="err">Make sure MySQL is running in XAMPP Control Panel, then refresh this page.</div>';
}

echo '</div></body></html>';
