<?php
/**
 * Web Installer - MikroTik VPN & Dst-NAT Manager
 * Mempermudah instalasi di VPS / Server lokal dalam hitungan menit
 */

$lockFile = __DIR__ . '/config/installed.lock';
if (file_exists($lockFile)) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

// Cek Persyaratan Server
$checks = [
    'PHP Version (>= 8.0)' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO SQLite Extension' => extension_loaded('pdo_sqlite'),
    'OpenSSL Extension' => extension_loaded('openssl'),
    'Sockets / Network Streams' => function_exists('fsockopen') || extension_loaded('sockets'),
    'Folder config/ writable' => is_writable(__DIR__) || (is_dir(__DIR__ . '/config') && is_writable(__DIR__ . '/config')),
    'Folder database/ writable' => is_writable(__DIR__) || (is_dir(__DIR__ . '/database') && is_writable(__DIR__ . '/database')),
];

$allPass = !in_array(false, $checks, true);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allPass) {
    require_once __DIR__ . '/includes/Database.php';
    require_once __DIR__ . '/includes/Helpers.php';
    require_once __DIR__ . '/includes/RouterOS.php';

    $adminUser = trim($_POST['admin_username'] ?? '');
    $adminName = trim($_POST['admin_fullname'] ?? '');
    $adminPass = $_POST['admin_password'] ?? '';
    
    $routerHost = trim($_POST['router_host'] ?? '');
    $routerPort = (int)($_POST['router_port'] ?? 8728);
    $routerUser = trim($_POST['router_user'] ?? '');
    $routerPass = $_POST['router_pass'] ?? '';
    $wanInterface = trim($_POST['wan_interface'] ?? 'ether1');

    if (empty($adminUser) || strlen($adminPass) < 6) {
        $errors[] = "Username admin wajib diisi dan password minimal 6 karakter.";
    }

    if (empty($routerHost) || empty($routerUser)) {
        $errors[] = "Host IP dan Username MikroTik wajib diisi.";
    }

    // Tes koneksi MikroTik (opsional tapi sangat disarankan)
    $testConn = false;
    if (isset($_POST['test_mikrotik']) && empty($errors)) {
        $ros = new RouterOS();
        if ($ros->connect($routerHost, $routerUser, $routerPass, $routerPort, 3)) {
            $testConn = true;
            $ros->disconnect();
        } else {
            $errors[] = "Peringatan: Gagal terhubung ke MikroTik API ({$ros->error}). Pastikan port 8728 aktif di '/ip service print' pada MikroTik.";
        }
    }

    // Jika tidak ada error atau user memilih tetap simpan meski peringatan
    if (empty($errors) || (isset($_POST['force_install']) && !empty($adminUser))) {
        try {
            // Buat folder jika belum ada
            if (!is_dir(__DIR__ . '/config')) {
                mkdir(__DIR__ . '/config', 0755, true);
            }
            if (!is_dir(__DIR__ . '/database')) {
                mkdir(__DIR__ . '/database', 0755, true);
            }

            // Inisialisasi DB
            $db = Database::getConnection();
            Database::initSchema($db);

            // Buat Akun Super Admin
            $passHash = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                INSERT OR REPLACE INTO admin_users (username, password_hash, full_name, role, is_active)
                VALUES (:u, :p, :name, 'superadmin', 1)
            ");
            $stmt->execute([
                ':u' => $adminUser,
                ':p' => $passHash,
                ':name' => $adminName ?: 'Super Administrator'
            ]);

            // Buat Profil Router
            $encryptedPass = Helpers::encrypt($routerPass);
            $stmt = $db->prepare("
                INSERT OR REPLACE INTO router_profiles (name, host, api_port, wan_interface, username, password_encrypted, is_default)
                VALUES ('MikroTik Utama', :host, :port, :wan, :u, :p, 1)
            ");
            $stmt->execute([
                ':host' => $routerHost,
                ':port' => $routerPort,
                ':wan' => $wanInterface ?: 'ether1',
                ':u' => $routerUser,
                ':p' => $encryptedPass
            ]);

            // Tulis file lock instalasi
            file_put_contents($lockFile, "Installed on " . date('Y-m-d H:i:s'));

            $success = true;
        } catch (Exception $e) {
            $errors[] = "Instalasi gagal: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Setup Wizard - MikroTik VPN & Dst-NAT Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-slate-800 rounded-2xl border border-slate-700 shadow-2xl overflow-hidden p-6 sm:p-8">
        
        <!-- Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600/20 text-blue-400 mb-3 border border-blue-500/30">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white">Setup Wizard MikroTik Manager</h1>
            <p class="text-slate-400 text-sm mt-1">Inisialisasi akun Super Admin & koneksi RouterOS API</p>
        </div>

        <?php if ($success): ?>
            <!-- Success Notification -->
            <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-6 text-center">
                <div class="w-12 h-12 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-1">Instalasi Berhasil!</h3>
                <p class="text-slate-300 text-sm mb-5">Database SQLite telah dibuat dan akun Super Admin siap digunakan.</p>
                <a href="index.php" class="inline-flex items-center justify-center px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-xl transition shadow-lg shadow-blue-600/30">
                    Buka Dashboard Login &rarr;
                </a>
            </div>
        <?php else: ?>

            <!-- System Checks -->
            <div class="mb-6 bg-slate-900/60 rounded-xl p-4 border border-slate-700/60">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2.5">Pemeriksaan Sistem</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                    <?php foreach ($checks as $name => $ok): ?>
                        <div class="flex items-center space-x-2">
                            <?php if ($ok): ?>
                                <span class="text-emerald-400 font-bold">✓</span>
                                <span class="text-slate-300"><?= htmlspecialchars($name) ?></span>
                            <?php else: ?>
                                <span class="text-rose-400 font-bold">✗</span>
                                <span class="text-rose-300"><?= htmlspecialchars($name) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 bg-rose-500/10 border border-rose-500/30 rounded-xl p-4 text-rose-300 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($allPass): ?>
                <form method="POST" action="install.php" class="space-y-5">
                    
                    <!-- Section: Super Admin -->
                    <div>
                        <h3 class="text-sm font-semibold text-blue-400 mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            1. Akun Super Admin
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Username Admin *</label>
                                <input type="text" name="admin_username" value="<?= htmlspecialchars($_POST['admin_username'] ?? 'superadmin') ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Nama Lengkap</label>
                                <input type="text" name="admin_fullname" value="<?= htmlspecialchars($_POST['admin_fullname'] ?? 'Administrator') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-slate-400 mb-1">Password Admin * (min. 6 karakter)</label>
                                <input type="password" name="admin_password" required placeholder="••••••••" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Section: MikroTik Connection -->
                    <div class="pt-2 border-t border-slate-700/60">
                        <h3 class="text-sm font-semibold text-emerald-400 mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                            2. Koneksi RouterOS API (Port 8728)
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-slate-400 mb-1">IP / Host MikroTik *</label>
                                <input type="text" name="router_host" value="<?= htmlspecialchars($_POST['router_host'] ?? '192.168.88.1') ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Port API</label>
                                <input type="number" name="router_port" value="<?= htmlspecialchars($_POST['router_port'] ?? '8728') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">User API MikroTik *</label>
                                <input type="text" name="router_user" value="<?= htmlspecialchars($_POST['router_user'] ?? 'admin') ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Password API</label>
                                <input type="password" name="router_pass" placeholder="Kosongkan jika tidak ada" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Interface WAN</label>
                                <input type="text" name="wan_interface" value="<?= htmlspecialchars($_POST['wan_interface'] ?? 'ether1') ?>" placeholder="e.g. ether1" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 flex flex-col sm:flex-row gap-3">
                        <button type="submit" name="test_mikrotik" value="1" class="flex-1 px-4 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-medium rounded-xl transition">
                            Tes & Simpan Instalasi
                        </button>
                        <button type="submit" name="force_install" value="1" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-xl transition shadow-lg shadow-blue-600/30">
                            Pasang Langsung &rarr;
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-300 text-sm">
                    Mohon perbaiki modul PHP di atas yang bertanda silang (✗) sebelum melanjutkan instalasi.
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <div class="mt-6 text-center text-xs text-slate-500">
            MikroTik SSTP, L2TP & Dst-NAT Manager &bull; v1.3.0
        </div>
    </div>
</body>
</html>

