<?php

declare(strict_types=1);

session_start();

// Installing runs migrations and may seed demo data; allow time for slower hosts.
@set_time_limit(300);
@ignore_user_abort(true);

$base = dirname(__DIR__);
$lockPath = $base.'/storage/app/installed.lock';
$envPath = $base.'/.env';
$payloadPath = $base.'/storage/app/install-payload.json';
$alreadyInstalled = file_exists($lockPath);
$csrf = $_SESSION['nexus_install_csrf'] ??= bin2hex(random_bytes(24));
$step = max(1, min(4, (int) ($_GET['step'] ?? 1)));
$errors = [];
$commandOutput = '';
$installComplete = false;
$cliFallback = false;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function field(string $key, string $default = ''): string
{
    return (string) ($_POST[$key] ?? $_SESSION['nexus_install_site'][$key] ?? $default);
}

function can_call(string $function): bool
{
    if (! function_exists($function)) {
        return false;
    }

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    return ! in_array($function, $disabled, true);
}

/**
 * Shared hosts disable proc_open more often than exec. Either one is enough
 * to finish the installation from the browser; the wizard only falls back to
 * manual commands when both are unavailable.
 */
function artisan_available(): bool
{
    return can_call('proc_open') || can_call('exec');
}

function php_cli_binary(): string
{
    $candidates = [PHP_BINARY, PHP_BINDIR.'/php', PHP_BINDIR.'/php.exe'];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    return PHP_BINARY;
}

function check_write(string $path): bool
{
    if (! is_dir($path)) {
        return false;
    }

    $probe = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.nexus-write-test';

    try {
        if (@file_put_contents($probe, 'ok') === false) {
            return false;
        }
        @unlink($probe);

        return true;
    } catch (Throwable) {
        return false;
    }
}

function env_quote(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('/[\s#"\'\\\\$]/', $value)) {
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    return $value;
}

function detect_url(): string
{
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return ($https ? 'https' : 'http').'://'.$host;
}

function requirements(string $base): array
{
    $requiredExtensions = [
        'pdo' => 'Database access',
        'openssl' => 'Encryption and signed cookies',
        'mbstring' => 'Text handling',
        'fileinfo' => 'Upload inspection',
        'zip' => 'Plugin package uploads',
        'tokenizer' => 'Blade template compilation',
        'json' => 'Settings and gateway payloads',
        'dom' => 'XML and feed handling',
        'libxml' => 'XML support',
    ];

    $rows = [
        [
            'label' => 'PHP 8.3 or newer',
            'ok' => PHP_VERSION_ID >= 80300,
            'value' => PHP_VERSION,
        ],
    ];

    foreach ($requiredExtensions as $extension => $label) {
        $rows[] = [
            'label' => $label.' ('.$extension.')',
            'ok' => extension_loaded($extension),
            'value' => extension_loaded($extension) ? 'Loaded' : 'Missing',
        ];
    }

    $sqliteDriver = extension_loaded('pdo_sqlite');
    $mysqlDriver = extension_loaded('pdo_mysql');

    $rows[] = [
        'label' => 'Database driver (SQLite or MySQL)',
        'ok' => $sqliteDriver || $mysqlDriver,
        'value' => $sqliteDriver && $mysqlDriver ? 'SQLite + MySQL' : ($sqliteDriver ? 'SQLite' : ($mysqlDriver ? 'MySQL' : 'None available')),
    ];

    foreach (['pdo_sqlite' => $sqliteDriver, 'pdo_mysql' => $mysqlDriver] as $extension => $loaded) {
        $rows[] = [
            'label' => ($extension === 'pdo_sqlite' ? 'SQLite driver' : 'MySQL driver').' ('.$extension.')',
            'ok' => $loaded,
            'value' => $loaded ? 'Available' : 'Optional',
            'optional' => true,
        ];
    }

    $writable = [
        'storage/app' => $base.'/storage/app',
        'storage/framework' => $base.'/storage/framework',
        'storage/logs' => $base.'/storage/logs',
        'bootstrap/cache' => $base.'/bootstrap/cache',
        'database' => $base.'/database',
        'public/uploads' => $base.'/public/uploads',
    ];

    foreach ($writable as $label => $path) {
        $rows[] = [
            'label' => $label.' is writable',
            'ok' => check_write($path),
            'value' => check_write($path) ? 'Writable' : 'Not writable',
        ];
    }

    $envFile = $base.'/.env';
    $envWritable = is_file($envFile) ? is_writable($envFile) : is_writable($base);
    $rows[] = [
        'label' => '.env can be created or updated',
        'ok' => $envWritable,
        'value' => $envWritable ? 'Writable' : 'Not writable',
    ];

    $rows[] = [
        'label' => 'PHP can run Artisan commands',
        'ok' => artisan_available(),
        'optional' => true,
        'value' => artisan_available()
            ? (can_call('proc_open') ? 'Available (proc_open)' : 'Available (exec)')
            : 'Disabled — the last step gives you commands to run',
    ];

    return $rows;
}

function db_connection(array $db, string $base): PDO
{
    if (($db['driver'] ?? 'sqlite') === 'sqlite') {
        $path = trim((string) ($db['database'] ?? '')) ?: $base.'/database/database.sqlite';
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = $base.'/'.ltrim($path, '/\\');
        }
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            throw new RuntimeException('Could not create the database folder: '.$dir);
        }
        if (! file_exists($path)) {
            if (@touch($path) === false) {
                throw new RuntimeException('Could not create the SQLite database file.');
            }
        }

        return new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    $host = trim((string) ($db['host'] ?? '127.0.0.1'));
    $port = trim((string) ($db['port'] ?? '3306'));
    $name = trim((string) ($db['database'] ?? ''));
    $user = (string) ($db['username'] ?? '');
    $password = (string) ($db['password'] ?? '');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Database name and username are required.');
    }

    $dsn = 'mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4';

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 8,
    ]);
}

function run_artisan(array $arguments, string $base): array
{
    if (! artisan_available()) {
        return [1, 'Both proc_open and exec are disabled on this server. Run the install commands from a terminal instead.'];
    }

    $binary = php_cli_binary();

    if (! can_call('proc_open')) {
        $command = implode(' ', array_map('escapeshellarg', array_merge([$binary, $base.'/artisan'], $arguments))).' 2>&1';
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        return [$exitCode, preg_replace('/\x1b\[[0-9;]*m/', '', implode(PHP_EOL, $output)) ?? implode(PHP_EOL, $output)];
    }

    $command = array_merge([$binary, $base.'/artisan'], $arguments);
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptor, $pipes, $base, null, ['bypass_shell' => true]);

    if (! is_resource($process)) {
        return [1, 'Could not start the PHP command.'];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $output = '';
    $started = time();
    $exitCode = null;

    while (true) {
        $output .= stream_get_contents($pipes[1]);
        $output .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);

        if (! $status['running']) {
            $exitCode = (int) $status['exitcode'];
            break;
        }

        if (time() - $started > 180) {
            proc_terminate($process, 9);
            $output .= "\nThe installer command timed out after 180 seconds.";
            $exitCode = 1;
            break;
        }

        usleep(120000);
    }

    $output .= stream_get_contents($pipes[1]);
    $output .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed = proc_close($process);

    if ($exitCode === null || $exitCode < 0) {
        $exitCode = $closed;
    }

    return [$exitCode, preg_replace('/\x1b\[[0-9;]*m/', '', $output) ?? $output];
}

if ($alreadyInstalled) {
    $step = 5;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $alreadyInstalled) {
    if (! hash_equals($_SESSION['nexus_install_csrf'] ?? '', (string) ($_POST['_token'] ?? ''))) {
        $errors[] = 'The security token expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'database') {
            $db = [
                'driver' => ($_POST['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite',
                'host' => trim((string) ($_POST['host'] ?? '127.0.0.1')),
                'port' => trim((string) ($_POST['port'] ?? '3306')),
                'database' => trim((string) ($_POST['database'] ?? '')),
                'username' => trim((string) ($_POST['username'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
            ];

            if ($db['driver'] === 'sqlite') {
                $db['database'] = $db['database'] ?: $base.'/database/database.sqlite';
            }

            try {
                $pdo = db_connection($db, $base);
                $pdo->query('SELECT 1');
                $tables = $db['driver'] === 'sqlite'
                    ? (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchColumn()
                    : (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();

                if ($tables > 0) {
                    throw new RuntimeException(
                        'That database already contains '.$tables.' table(s). The installer only runs on an empty database. '
                        .'Create a new database (or empty the selected SQLite file), then try again. '
                        .'If this is an existing ShopKeeprr store, do not reinstall it: restore the matching .env file and use the updater instead.'
                    );
                }

                $_SESSION['nexus_install_db'] = $db;
                header('Location: install.php?step=3');
                exit;
            } catch (Throwable $exception) {
                $errors[] = 'Database connection failed: '.$exception->getMessage();
                $step = 2;
                $_SESSION['nexus_install_db'] = $db;
            }
        }

        if ($action === 'install') {
            $site = [
                'name' => trim((string) ($_POST['store_name'] ?? '')),
                'email' => trim((string) ($_POST['store_email'] ?? '')),
                'url' => rtrim(trim((string) ($_POST['store_url'] ?? detect_url())), '/'),
                'currency' => (string) ($_POST['currency'] ?? 'USD'),
                'country' => strtoupper(trim((string) ($_POST['country'] ?? 'US'))),
                'mode' => in_array($_POST['mode'] ?? '', ['retail', 'b2b', 'subscription', 'multivendor'], true) ? (string) $_POST['mode'] : null,
                'live' => ! empty($_POST['live']),
                'admin_name' => trim((string) ($_POST['admin_name'] ?? '')),
                'admin_email' => trim((string) ($_POST['admin_email'] ?? '')),
                'admin_password' => (string) ($_POST['admin_password'] ?? ''),
                'demo' => ! empty($_POST['demo']),
            ];

            $_SESSION['nexus_install_site'] = $site;
            $db = $_SESSION['nexus_install_db'] ?? null;

            if (! $db) {
                $errors[] = 'The database step was not completed.';
                $step = 2;
            }
            if ($site['name'] === '') {
                $errors[] = 'Give your store a name.';
            }
            if (! filter_var($site['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid store email address.';
            }
            if (! filter_var($site['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid administrator email address.';
            }
            if (strlen($site['admin_password']) < 10) {
                $errors[] = 'The administrator password must be at least 10 characters.';
            }
            if ($site['admin_password'] !== (string) ($_POST['admin_password_confirmation'] ?? '')) {
                $errors[] = 'The administrator passwords do not match.';
            }
            if ($site['mode'] === null) {
                $site['live'] = false;
            }
            if (! preg_match('/^https?:\/\//', $site['url'])) {
                $errors[] = 'Enter the full store URL, including http:// or https://.';
            }

            if (! $errors) {
                $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$', 'AUD' => 'A$'];
                $symbol = $symbols[$site['currency']] ?? '$';
                $appKey = 'base64:'.base64_encode(random_bytes(32));
                $sqlitePath = (string) $db['database'];
                if ($db['driver'] === 'sqlite' && ! str_starts_with($sqlitePath, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $sqlitePath)) {
                    $sqlitePath = $base.'/'.$sqlitePath;
                }
                $sqlitePath = str_replace('\\', '/', $sqlitePath);

                $envLines = [
                    'APP_NAME='.env_quote($site['name']),
                    'APP_ENV=production',
                    'APP_KEY='.$appKey,
                    'APP_DEBUG=false',
                    'APP_URL='.env_quote($site['url']),
                    '',
                    'APP_LOCALE=en',
                    'APP_FALLBACK_LOCALE=en',
                    'APP_FAKER_LOCALE=en_US',
                    '',
                    'APP_MAINTENANCE_DRIVER=file',
                    'BCRYPT_ROUNDS=12',
                    '',
                    'LOG_CHANNEL=stack',
                    'LOG_STACK=single',
                    'LOG_DEPRECATIONS_CHANNEL=null',
                    'LOG_LEVEL=warning',
                    '',
                    'DB_CONNECTION='.$db['driver'],
                ];

                if ($db['driver'] === 'sqlite') {
                    $envLines[] = 'DB_DATABASE='.env_quote($sqlitePath);
                } else {
                    $envLines[] = 'DB_HOST='.env_quote((string) $db['host']);
                    $envLines[] = 'DB_PORT='.env_quote((string) $db['port']);
                    $envLines[] = 'DB_DATABASE='.env_quote((string) $db['database']);
                    $envLines[] = 'DB_USERNAME='.env_quote((string) $db['username']);
                    $envLines[] = 'DB_PASSWORD='.env_quote((string) $db['password']);
                }

                $envLines = array_merge($envLines, [
                    '',
                    'SESSION_DRIVER=database',
                    'SESSION_LIFETIME=120',
                    'SESSION_ENCRYPT=false',
                    'SESSION_PATH=/',
                    'SESSION_DOMAIN=null',
                    '',
                    'BROADCAST_CONNECTION=log',
                    'FILESYSTEM_DISK=local',
                    'QUEUE_CONNECTION=database',
                    'CACHE_STORE=database',
                    '',
                    'MAIL_MAILER=log',
                    'MAIL_SCHEME=null',
                    'MAIL_HOST=127.0.0.1',
                    'MAIL_PORT=2525',
                    'MAIL_USERNAME=null',
                    'MAIL_PASSWORD=null',
                    'MAIL_FROM_ADDRESS='.env_quote($site['email']),
                    'MAIL_FROM_NAME='.env_quote($site['name']),
                    '',
                    'VITE_APP_NAME='.env_quote($site['name']),
                    '',
                    '# Signed updates stay off until a manifest host is published.',
                    'SHOPKEEPRR_UPDATES_ENABLED=true',
                    '',
                    '# Installation tracking is opt-in. Enable only with disclosure.',
                    'SHOPKEEPRR_TRACKING_ENABLED=false',
                    'SHOPKEEPRR_TRACKING_URL=https://store.shopkeeprr.com/api/v1/installs',
                ]);

                if (file_exists($envPath)) {
                    @copy($envPath, $envPath.'.backup-'.date('YmdHis'));
                }

                if (@file_put_contents($envPath, implode(PHP_EOL, $envLines).PHP_EOL) === false) {
                    $errors[] = 'Could not write the .env file. Check permissions on the application folder.';
                }

                if (! $errors) {
                    @chmod($envPath, 0600);
                    $payload = [
                        'store' => [
                            'name' => $site['name'],
                            'email' => $site['email'],
                            'domain' => parse_url($site['url'], PHP_URL_HOST) ?: null,
                            'currency' => $site['currency'],
                            'currency_symbol' => $symbol,
                            'country' => $site['country'] ?: 'US',
                            'mode' => $site['mode'],
                            'live' => $site['live'],
                        ],
                        'admin' => [
                            'name' => $site['admin_name'] ?: 'Store Administrator',
                            'email' => $site['admin_email'],
                            'password' => $site['admin_password'],
                        ],
                        'demo' => $site['demo'],
                    ];

                    if (@file_put_contents($payloadPath, json_encode($payload, JSON_PRETTY_PRINT)) === false) {
                        $errors[] = 'Could not write the installer payload.';
                    } else {
                        @chmod($payloadPath, 0600);

                        if (! artisan_available()) {
                            // The browser cannot run Artisan here. The .env file and
                            // payload are already written, so the buyer only needs
                            // to run the two commands shown on this screen.
                            $cliFallback = true;
                        } else {
                            $steps = [
                                ['config:clear'],
                                ['migrate', '--force'],
                                ['shopkeeprr:install', '--data='.$payloadPath],
                            ];

                            foreach ($steps as $arguments) {
                                [$exitCode, $output] = run_artisan($arguments, $base);
                                $commandOutput .= '$ php artisan '.implode(' ', array_map('escapeshellarg', $arguments)).PHP_EOL.$output.PHP_EOL;
                                if ($exitCode !== 0) {
                                    $errors[] = 'The installer stopped while running: php artisan '.implode(' ', $arguments);
                                    break;
                                }
                            }

                            if (! $errors && file_exists($lockPath)) {
                                $installComplete = true;
                                $step = 4;
                                $_SESSION['nexus_install_summary'] = [
                                    'url' => $site['url'],
                                    'admin_email' => $site['admin_email'],
                                    'mode' => $site['mode'],
                                ];
                                unset($_SESSION['nexus_install_db'], $_SESSION['nexus_install_site']);
                            } elseif (! $errors) {
                                $errors[] = 'The installation command finished but the lock file was not created.';
                            }
                        }
                    }
                }
            }

            if ($errors) {
                $step = 3;
            }
        }
    }
}

if ($step === 2 && empty($_SESSION['nexus_install_db'])) {
    $_SESSION['nexus_install_db'] = ['driver' => 'sqlite', 'database' => 'database/database.sqlite'];
}
if ($step === 3 && empty($_SESSION['nexus_install_db'])) {
    header('Location: install.php?step=2');
    exit;
}

$requirements = requirements($base);
$requirementsOk = collect_like_every($requirements);

function collect_like_every(array $rows): bool
{
    foreach ($rows as $row) {
        if (! $row['ok'] && empty($row['optional'])) {
            return false;
        }
    }

    return true;
}

$db = $_SESSION['nexus_install_db'] ?? ['driver' => 'sqlite', 'database' => 'database/database.sqlite'];
$site = $_SESSION['nexus_install_site'] ?? $_SESSION['nexus_install_summary'] ?? [];
$detectedUrl = detect_url();
$storeName = $site['name'] ?? '';
$storeEmail = $site['email'] ?? '';
$adminName = $site['admin_name'] ?? '';
$adminEmail = $site['admin_email'] ?? '';
$mode = $site['mode'] ?? 'retail';
$currency = $site['currency'] ?? 'USD';
$country = $site['country'] ?? 'US';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Install ShopKeeprr</title>
    <link rel="stylesheet" href="install.css">
</head>
<body>
<div class="shell">
    <div class="brand">
        <div class="brand-mark">N</div>
        <div>
            <div class="brand-name">ShopKeeprr</div>
            <div class="brand-sub">Commerce, without the plugin sprawl.</div>
        </div>
    </div>

    <div class="layout">
        <aside class="aside">
            <div class="eyebrow">Setup wizard</div>
            <h1>Your store,<br>ready in minutes.</h1>
            <p>Connect a database, name your store, and choose the business model you want to run. You can change everything later.</p>

            <div class="steps">
                <?php foreach ([
                    1 => ['Server check', 'PHP and folder permissions'],
                    2 => ['Database', 'SQLite or MySQL'],
                    3 => ['Store & owner', 'Brand, login and mode'],
                    4 => ['Finish', 'Install and launch'],
                ] as $number => [$title, $subtitle]): ?>
                    <div class="step <?= $step === $number ? 'active' : ($step > $number ? 'done' : '') ?>">
                        <div class="step-dot"><?= $step > $number ? '✓' : $number ?></div>
                        <div><strong><?= e($title) ?></strong><span><?= e($subtitle) ?></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <main>
            <div class="mobile-progress">
                <span class="pill">Step <?= min(4, $step) ?> of 4</span>
            </div>

            <?php if ($alreadyInstalled): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="success">
                            <div class="success-mark">✓</div>
                            <h2>ShopKeeprr is already installed</h2>
                            <p>The installer is locked to protect your store. Open the admin panel to manage your catalog, orders and settings.</p>
                            <div style="margin-top:22px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
                                <a class="btn btn-primary" href="admin/login">Open admin panel</a>
                                <a class="btn btn-ghost" href="./">View storefront</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($step === 1): ?>
                <div class="card">
                    <div class="card-head">
                        <h2>Check your server</h2>
                        <p>ShopKeeprr needs a modern PHP runtime and a few writable folders.</p>
                    </div>
                    <div class="card-body">
                        <?php if (! $requirementsOk): ?>
                            <div class="notice notice-error">Fix the items marked in red, then reload this page. You can continue once every required check passes.</div>
                        <?php else: ?>
                            <div class="notice notice-info">Everything required is available. Your server is ready for ShopKeeprr.</div>
                        <?php endif; ?>

                        <div class="checks">
                            <?php foreach ($requirements as $requirement): ?>
                                <div class="check <?= $requirement['ok'] ? 'ok' : 'bad' ?>">
                                    <div class="check-icon"><?= $requirement['ok'] ? '✓' : '!' ?></div>
                                    <div class="check-title"><?= e($requirement['label']) ?></div>
                                    <div class="check-value"><?= e($requirement['value']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-foot">
                        <span class="help">PHP <?= e(PHP_VERSION) ?> · <?= e(PHP_OS_FAMILY) ?></span>
                        <?php if ($requirementsOk): ?>
                            <a class="btn btn-primary" href="install.php?step=2">Continue to database →</a>
                        <?php else: ?>
                            <a class="btn btn-ghost" href="install.php?step=1">Re-check server</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (! artisan_available()): ?>
                    <div class="card" style="margin-top:18px">
                        <div class="card-head">
                            <h2>proc_open is disabled on this host</h2>
                            <p>This is common on shared hosting. You can still install ShopKeeprr: finish the next two screens and the installer will give you a few short commands to run once.</p>
                        </div>
                        <div class="card-body">
                            <div class="notice notice-warn" style="margin-top:0">
                                You will need terminal or SSH access once. If you do not have it, ask your host to enable <code>proc_open</code>, or install from the command line using the flags in the Installation &amp; Setup Guide.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($step === 2): ?>
                <form class="card" method="post" action="install.php?step=2">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="database">
                    <div class="card-head">
                        <h2>Connect a database</h2>
                        <p>SQLite is perfect for a quick start. MySQL is recommended for a busy production store.</p>
                    </div>
                    <div class="card-body">
                        <?php foreach ($errors as $error): ?>
                            <div class="notice notice-error"><?= e($error) ?></div>
                        <?php endforeach; ?>

                        <div class="grid two">
                            <div class="field">
                                <label for="driver">Database engine</label>
                                <select id="driver" name="driver" onchange="toggleDriver()">
                                    <option value="sqlite" <?= ($db['driver'] ?? 'sqlite') === 'sqlite' ? 'selected' : '' ?>>SQLite — no server required</option>
                                    <option value="mysql" <?= ($db['driver'] ?? '') === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="database">Database name or file</label>
                                <input id="database" name="database" value="<?= e($db['database'] ?? 'database/database.sqlite') ?>" autocomplete="off">
                                <small>SQLite creates the file automatically. For MySQL use an existing, empty database.</small>
                            </div>
                        </div>

                        <div id="mysql-fields" class="grid three" style="margin-top:18px;<?= ($db['driver'] ?? 'sqlite') === 'mysql' ? '' : 'display:none' ?>">
                            <div class="field">
                                <label for="host">Host</label>
                                <input id="host" name="host" value="<?= e($db['host'] ?? '127.0.0.1') ?>" autocomplete="off">
                            </div>
                            <div class="field">
                                <label for="port">Port</label>
                                <input id="port" name="port" value="<?= e($db['port'] ?? '3306') ?>" inputmode="numeric">
                            </div>
                            <div class="field">
                                <label for="username">Username</label>
                                <input id="username" name="username" value="<?= e($db['username'] ?? '') ?>" autocomplete="off">
                            </div>
                            <div class="field">
                                <label for="password">Password</label>
                                <input id="password" type="password" name="password" value="" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="notice notice-warn" style="margin-top:22px;margin-bottom:0">
                            The installer will create ShopKeeprr's tables in this database. Use a dedicated database for this store.
                        </div>
                    </div>
                    <div class="card-foot">
                        <a class="btn btn-ghost" href="install.php?step=1">← Back</a>
                        <button class="btn btn-primary" type="submit">Test connection & continue →</button>
                    </div>
                </form>

            <?php elseif ($step === 3): ?>
                <form class="card" method="post" action="install.php?step=3">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="install">
                    <div class="card-head">
                        <h2>Name your store</h2>
                        <p>This creates the owner account and the first storefront settings.</p>
                    </div>
                    <div class="card-body">
                        <?php if ($cliFallback): ?>
                            <div class="notice notice-warn">
                                <strong>One last step: run these commands.</strong>
                                Your database details and store settings are saved. Because this host disables both <code>proc_open</code> and <code>exec</code>, open a terminal in the application folder and run:
                            </div>
                            <pre class="code">php artisan config:clear
php artisan migrate --force
php artisan shopkeeprr:install --data="<?= e($payloadPath) ?>"</pre>
                            <p class="help">Then reload this page: the installer will detect <code>storage/app/installed.lock</code> and show the completion screen. On cPanel use “Terminal” or SSH; on Plesk use “SSH Terminal”. The saved payload is deleted automatically after a successful install.</p>
                        <?php endif; ?>
                        <?php foreach ($errors as $error): ?>
                            <div class="notice notice-error"><?= e($error) ?></div>
                        <?php endforeach; ?>
                        <?php if ($commandOutput): ?>
                            <div class="notice notice-error">Installer output</div>
                            <pre class="code"><?= e($commandOutput) ?></pre>
                        <?php endif; ?>

                        <div class="grid two">
                            <div class="field">
                                <label for="store_name">Store name</label>
                                <input id="store_name" name="store_name" value="<?= e($storeName) ?>" placeholder="Northstar Goods" required>
                            </div>
                            <div class="field">
                                <label for="store_email">Store email</label>
                                <input id="store_email" type="email" name="store_email" value="<?= e($storeEmail) ?>" placeholder="hello@yourstore.com" required>
                            </div>
                            <div class="field">
                                <label for="store_url">Store URL</label>
                                <input id="store_url" type="url" name="store_url" value="<?= e($site['url'] ?? $detectedUrl) ?>" required>
                            </div>
                            <div class="field">
                                <label for="country">Country</label>
                                <input id="country" name="country" maxlength="2" value="<?= e($country) ?>" placeholder="US">
                                <small>Two-letter ISO country code.</small>
                            </div>
                            <div class="field">
                                <label for="currency">Currency</label>
                                <select id="currency" name="currency">
                                    <?php foreach (['USD' => 'USD — US Dollar', 'EUR' => 'EUR — Euro', 'GBP' => 'GBP — Pound Sterling', 'CAD' => 'CAD — Canadian Dollar', 'AUD' => 'AUD — Australian Dollar'] as $code => $label): ?>
                                        <option value="<?= e($code) ?>" <?= $currency === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Demo content</label>
                                <label class="check" style="margin-top:1px">
                                    <input type="checkbox" name="demo" value="1" <?= isset($site['demo']) && ! $site['demo'] ? '' : 'checked' ?> style="width:auto">
                                    <span class="check-title">Seed a demo catalog and orders</span>
                                </label>
                            </div>
                        </div>

                        <div style="margin-top:26px">
                            <div class="field" style="margin-bottom:10px">
                                <label>Store model</label>
                                <small>Pick the model you want to run. You can switch later; your data stays intact.</small>
                            </div>
                            <div class="mode-grid">
                                <label class="mode">
                                    <input type="radio" name="mode" value="retail" <?= $mode === 'retail' ? 'checked' : '' ?>>
                                    <strong>Retail</strong>
                                    <span>Everyday ecommerce with variants, inventory and checkout.</span>
                                </label>
                                <label class="mode">
                                    <input type="radio" name="mode" value="b2b" <?= $mode === 'b2b' ? 'checked' : '' ?>>
                                    <strong>B2B Wholesale</strong>
                                    <span>Tier pricing, MOQ rules and approved trade accounts.</span>
                                </label>
                                <label class="mode">
                                    <input type="radio" name="mode" value="subscription" <?= $mode === 'subscription' ? 'checked' : '' ?>>
                                    <strong>Subscription Box</strong>
                                    <span>Recurring billing, trials and customer self-service.</span>
                                </label>
                                <label class="mode">
                                    <input type="radio" name="mode" value="multivendor" <?= $mode === 'multivendor' ? 'checked' : '' ?>>
                                    <strong>Multi-Vendor Marketplace</strong>
                                    <span>Independent seller catalogs, a seller portal and automatic commission splits.</span>
                                </label>
                                <label class="mode">
                                    <input type="radio" name="mode" value="" <?= $mode === null ? 'checked' : '' ?>>
                                    <strong>Decide later</strong>
                                    <span>Finish setup now. The storefront stays offline until you choose a model.</span>
                                </label>
                            </div>
                        </div>

                        <label class="check" style="margin-top:12px">
                            <input type="checkbox" name="live" value="1" <?= $mode === null ? '' : 'checked' ?> style="width:auto">
                            <span class="check-title">Make the storefront live immediately</span>
                        </label>

                        <div style="border-top:1px solid var(--line);margin:26px 0 22px"></div>

                        <div class="grid two">
                            <div class="field">
                                <label for="admin_name">Your name</label>
                                <input id="admin_name" name="admin_name" value="<?= e($adminName) ?>" placeholder="Store owner" required>
                            </div>
                            <div class="field">
                                <label for="admin_email">Administrator email</label>
                                <input id="admin_email" type="email" name="admin_email" value="<?= e($adminEmail) ?>" placeholder="you@yourstore.com" required>
                            </div>
                            <div class="field">
                                <label for="admin_password">Password</label>
                                <input id="admin_password" type="password" name="admin_password" minlength="10" required autocomplete="new-password">
                                <small>At least 10 characters. Use a password manager.</small>
                            </div>
                            <div class="field">
                                <label for="admin_password_confirmation">Confirm password</label>
                                <input id="admin_password_confirmation" type="password" name="admin_password_confirmation" minlength="10" required autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                    <div class="card-foot">
                        <a class="btn btn-ghost" href="install.php?step=2">← Back</a>
                        <button class="btn btn-primary" type="submit">Install ShopKeeprr</button>
                    </div>
                </form>

            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <?php if ($installComplete): ?>
                            <div class="success">
                                <div class="success-mark">✓</div>
                                <h2>ShopKeeprr is installed</h2>
                                <p>Your store is ready. Open the admin panel to review settings, products and your first orders.</p>
                            </div>
                            <div class="summary" style="margin-top:26px">
                                <div class="summary-row"><span>Storefront</span><span><?= e($site['url'] ?? $detectedUrl) ?></span></div>
                                <div class="summary-row"><span>Admin panel</span><span><?= e(($site['url'] ?? $detectedUrl).'/admin/login') ?></span></div>
                                <div class="summary-row"><span>Administrator</span><span><?= e($site['admin_email'] ?? '') ?></span></div>
                                <div class="summary-row"><span>Store model</span><span><?= e($site['mode'] ? ucfirst($site['mode']) : 'Decide later') ?></span></div>
                                <div class="summary-row"><span>Install tracking</span><span><?= is_file($base.'/storage/app/install-tracking.json') ? 'Registered — storage/app/install-tracking.json' : 'Not registered' ?></span></div>
                            </div>
                            <?php if (file_exists($base.'/storage/app/demo-credentials.txt')): ?>
                                <div class="notice notice-warn" style="margin-top:20px">
                                    <strong>Demo customers use a random password.</strong>
                                    The credentials are saved in <code>storage/app/demo-credentials.txt</code>, outside the web root. Read it if you want to sign in as a demo customer, then delete the demo accounts and that file before going live.
                                </div>
                            <?php endif; ?>
                            <div class="notice notice-warn" style="margin-top:20px">
                                <strong>Important:</strong> delete or rename <code>public/install.php</code> after installation. The lock file prevents re-runs, but removing the installer entirely is safer.
                            </div>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px">
                                <a class="btn btn-primary" href="admin/login">Open admin panel</a>
                                <a class="btn btn-ghost" href="./">Open storefront</a>
                            </div>
                        <?php else: ?>
                            <div class="notice notice-error">The installation did not finish. Check the output above, then try again.</div>
                            <pre class="code"><?= e($commandOutput) ?></pre>
                            <a class="btn btn-primary" href="install.php?step=3" style="margin-top:16px">Back to store setup</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>
function toggleDriver() {
    var driver = document.getElementById('driver').value;
    document.getElementById('mysql-fields').style.display = driver === 'mysql' ? '' : 'none';
}
</script>
</body>
</html>
