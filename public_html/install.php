<?php
/**
 * Hesabix Web Installer
 * Run this file once via browser to set up the database and run migrations.
 * IMPORTANT: Delete or block this file after installation is complete.
 *
 * Usage: https://yourdomain.com/install.php?token=YOUR_INSTALL_TOKEN
 * Set INSTALL_TOKEN below to a secret string before uploading.
 */

const INSTALL_TOKEN = 'CHANGE_THIS_SECRET_TOKEN';

// ── Security check ────────────────────────────────────────────────────────────
if (!isset($_GET['token']) || $_GET['token'] !== INSTALL_TOKEN) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Missing or invalid install token. Add ?token=YOUR_TOKEN to the URL.</p>');
}

define('BASE_DIR', dirname(__DIR__));
define('CORE_DIR', BASE_DIR . '/hesabixCore');

// ── Helpers ───────────────────────────────────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function runConsole(string $command): array {
    $php = PHP_BINARY ?: 'php';
    $console = CORE_DIR . '/bin/console';
    $full = escapeshellarg($php) . ' ' . escapeshellarg($console) . ' ' . $command . ' 2>&1';
    $output = [];
    $code = 0;
    exec($full, $output, $code);
    return ['output' => implode("\n", $output), 'code' => $code];
}

function check(string $label, bool $ok, string $detail = ''): void {
    $icon = $ok ? '✅' : '❌';
    $color = $ok ? '#2e7d32' : '#c62828';
    echo "<tr><td>{$label}</td><td style='color:{$color}'>{$icon}</td><td>" . h($detail) . "</td></tr>\n";
}

// ── Action ────────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? 'check';

?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>Hesabix Installer</title>
<style>
  body { font-family: Tahoma, Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
  .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); max-width: 900px; margin: 0 auto; padding: 24px; }
  h1 { color: #1565c0; margin-top: 0; }
  h2 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 8px; }
  table { width: 100%; border-collapse: collapse; margin: 12px 0; }
  th, td { padding: 8px 12px; text-align: right; border: 1px solid #e0e0e0; }
  th { background: #f0f4ff; }
  .btn { background: #1565c0; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; cursor: pointer; font-size: 14px; margin: 4px; }
  .btn:hover { background: #0d47a1; }
  .btn-danger { background: #c62828; }
  .btn-danger:hover { background: #b71c1c; }
  pre { background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 4px; overflow-x: auto; font-size: 12px; white-space: pre-wrap; }
  .warn { background: #fff3e0; border-left: 4px solid #f57c00; padding: 12px; margin: 12px 0; border-radius: 0 4px 4px 0; }
  .success { background: #e8f5e9; border-left: 4px solid #2e7d32; padding: 12px; margin: 12px 0; border-radius: 0 4px 4px 0; }
</style>
</head>
<body>
<div class="card">
<h1>⚙️ Hesabix Web Installer</h1>

<?php
// ── Requirement checks ────────────────────────────────────────────────────────
$phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
$envExists = file_exists(CORE_DIR . '/.env');
$vendorExists = is_dir(CORE_DIR . '/vendor');
$varWritable = is_writable(CORE_DIR . '/var') || mkdir(CORE_DIR . '/var', 0755, true);
$cacheWritable = is_writable(CORE_DIR . '/var/cache') || mkdir(CORE_DIR . '/var/cache', 0755, true);
$logWritable = is_writable(CORE_DIR . '/var/log') || mkdir(CORE_DIR . '/var/log', 0755, true);
$sessionWritable = is_writable(CORE_DIR . '/var/sessions') || mkdir(CORE_DIR . '/var/sessions', 0755, true);

$envContent = $envExists ? file_get_contents(CORE_DIR . '/.env') : '';
$dbConfigured = $envExists && strpos($envContent, 'DB_PASSWORD') === false && strpos($envContent, '!ChangeMe!') === false && strpos($envContent, 'DB_USER') === false;
$secretConfigured = $envExists && strpos($envContent, 'CHANGE_ME_TO_RANDOM_32_CHAR_STRING') === false;

$allChecksOk = $phpOk && $envExists && $vendorExists && $varWritable && $dbConfigured && $secretConfigured;
?>

<h2>بررسی پیش‌نیازها</h2>
<table>
<thead><tr><th>بررسی</th><th>وضعیت</th><th>جزئیات</th></tr></thead>
<tbody>
<?php
check('PHP نسخه ۸.۲+', $phpOk, 'نسخه فعلی: ' . PHP_VERSION);
check('فایل .env وجود دارد', $envExists, CORE_DIR . '/.env');
check('پوشه vendor وجود دارد', $vendorExists, 'composer install اجرا شده است');
check('پوشه var قابل نوشتن است', $varWritable, CORE_DIR . '/var');
check('پوشه var/cache قابل نوشتن است', $cacheWritable, CORE_DIR . '/var/cache');
check('پوشه var/log قابل نوشتن است', $logWritable, CORE_DIR . '/var/log');
check('تنظیمات DATABASE_URL انجام شده', $dbConfigured, $dbConfigured ? 'DATABASE_URL تنظیم شده است' : 'DATABASE_URL هنوز پیش‌فرض است — فایل .env را ویرایش کنید');
check('APP_SECRET تغییر داده شده', $secretConfigured, $secretConfigured ? 'APP_SECRET سفارشی است' : 'APP_SECRET هنوز پیش‌فرض است — فایل .env را ویرایش کنید');
?>
</tbody>
</table>

<?php if (!$allChecksOk): ?>
<div class="warn">
<strong>⚠️ لطفاً ابتدا موارد بالا را برطرف کنید سپس دوباره صفحه را بارگذاری نمایید.</strong>
</div>
<?php endif; ?>

<?php if ($action === 'run_migrations' && $allChecksOk): ?>
<h2>اجرای Migration ها</h2>
<?php
$result = runConsole('doctrine:migrations:migrate --no-interaction --allow-no-migration');
$ok = $result['code'] === 0;
?>
<pre><?= h($result['output']) ?></pre>
<?php if ($ok): ?>
<div class="success"><strong>✅ Migration ها با موفقیت اجرا شدند.</strong></div>
<?php else: ?>
<div class="warn"><strong>❌ خطا در اجرای migration ها. خروجی بالا را بررسی کنید.</strong></div>
<?php endif; ?>

<?php elseif ($action === 'clear_cache' && $allChecksOk): ?>
<h2>پاکسازی Cache</h2>
<?php
$result = runConsole('cache:clear --no-warmup');
$result2 = runConsole('cache:warmup');
?>
<pre><?= h($result['output'] . "\n" . $result2['output']) ?></pre>
<div class="success"><strong>✅ Cache پاک شد.</strong></div>

<?php elseif ($action === 'check_db' && $allChecksOk): ?>
<h2>بررسی اتصال به پایگاه داده</h2>
<?php
$result = runConsole('doctrine:query:sql "SELECT 1"');
$ok = $result['code'] === 0;
?>
<pre><?= h($result['output']) ?></pre>
<?php if ($ok): ?>
<div class="success"><strong>✅ اتصال به پایگاه داده موفق بود.</strong></div>
<?php else: ?>
<div class="warn"><strong>❌ اتصال به پایگاه داده ناموفق بود. DATABASE_URL را در فایل .env بررسی کنید.</strong></div>
<?php endif; ?>

<?php endif; ?>

<?php if ($allChecksOk): ?>
<h2>عملیات نصب</h2>
<form method="post" action="?token=<?= h(INSTALL_TOKEN) ?>">
    <button class="btn" name="action" value="check_db" type="submit">🔌 بررسی اتصال DB</button>
    <button class="btn" name="action" value="run_migrations" type="submit"
        onclick="return confirm('آیا مطمئن هستید؟ این عملیات پایگاه داده را تغییر می‌دهد.')">
        🗄️ اجرای Migration ها
    </button>
    <button class="btn" name="action" value="clear_cache" type="submit">🔄 پاکسازی Cache</button>
</form>
<?php endif; ?>

<div class="warn" style="margin-top:24px">
<strong>⚠️ هشدار امنیتی:</strong> پس از اتمام نصب، فایل <code>install.php</code> را از سرور حذف کنید یا
خط مربوط به آن را در <code>.htaccess</code> از حالت کامنت خارج کنید.
</div>

<hr style="margin:24px 0; border-color:#eee">
<small style="color:#999">Hesabix Installer — فقط یک بار اجرا کنید</small>
</div>
</body>
</html>
