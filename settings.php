<?php
$pageTitle = "Pengaturan - MikroTik Manager";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/Helpers.php';

$db = Database::getConnection();
$message = '';
$error = '';
$routerProfile = Auth::getRouterProfile();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Ganti Password Pribadi
    if ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');

        // Verifikasi password lama
        $stmt = $db->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $curr = $stmt->fetch();

        if (password_verify($oldPass, $curr['password_hash'])) {
            if (!empty($newPass)) {
                if (strlen($newPass) >= 6) {
                    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("UPDATE admin_users SET password_hash = ?, full_name = ? WHERE id = ?");
                    $stmt->execute([$newHash, $fullName ?: $currentUser['username'], $currentUser['id']]);
                    $message = "Profil dan password Anda berhasil diperbarui.";
                } else {
                    $error = "Password baru minimal 6 karakter.";
                }
            } else {
                $stmt = $db->prepare("UPDATE admin_users SET full_name = ? WHERE id = ?");
                $stmt->execute([$fullName ?: $currentUser['username'], $currentUser['id']]);
                $message = "Nama profil berhasil diperbarui.";
            }
        } else {
            $error = "Password lama yang Anda masukkan salah.";
        }
    }

    // 2. Update Router Settings (Khusus Super Admin)
    elseif ($action === 'update_router' && $currentUser['role'] === 'superadmin') {
        $host = trim($_POST['router_host'] ?? '');
        $domainName = trim($_POST['domain_name'] ?? '');
        $port = (int)($_POST['router_port'] ?? 8728);
        $user = trim($_POST['router_user'] ?? '');
        $pass = $_POST['router_pass'] ?? '';
        $wan = trim($_POST['wan_interface'] ?? 'ether1');

        if (empty($host) || empty($user)) {
            $error = "Host IP dan Username MikroTik wajib diisi.";
        } else {
            $updateParams = [
                ':h' => $host,
                ':domain' => $domainName,
                ':port' => $port,
                ':wan' => $wan,
                ':u' => $user
            ];

            $passSql = "";
            if (!empty($pass)) {
                $passSql = ", password_encrypted = :p";
                $updateParams[':p'] = Helpers::encrypt($pass);
            }

            $stmt = $db->prepare("
                UPDATE router_profiles 
                SET host = :h, domain_name = :domain, api_port = :port, wan_interface = :wan, username = :u {$passSql}
                WHERE is_default = 1
            ");
            $stmt->execute($updateParams);
            $message = "Pengaturan koneksi router & domain berhasil disimpan.";
            $routerProfile = Auth::getRouterProfile();
        }
    }
}
?>

<div class="space-y-6 max-w-4xl">

    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="sliders" class="w-6 h-6 text-blue-500"></i>
            <span>Pengaturan Sistem</span>
        </h1>
        <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-0.5">
            Konfigurasi profil admin dan parameter koneksi API & Domain VPN MikroTik.
        </p>
    </div>

    <?php if ($message): ?>
        <div class="p-3.5 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 rounded-xl text-emerald-700 dark:text-emerald-400 text-xs sm:text-sm flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-3.5 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 rounded-xl text-rose-700 dark:text-rose-400 text-xs sm:text-sm flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Card: Profil & Password Saya -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm space-y-4">
            <div class="flex items-center space-x-2.5 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <i data-lucide="user" class="w-4 h-4"></i>
                </div>
                <h3 class="font-bold text-sm text-slate-900 dark:text-white">Profil & Keamanan Akun</h3>
            </div>

            <form method="POST" action="settings.php" class="space-y-3.5 text-xs">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Username</label>
                    <input type="text" disabled value="<?= htmlspecialchars($currentUser['username']) ?>" class="w-full bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-500 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Nama Lengkap</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($currentUser['full_name']) ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Password Lama *</label>
                    <input type="password" name="old_password" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Password Baru (Kosongkan jika tidak ganti)</label>
                    <input type="password" name="new_password" placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">
                        Simpan Profil
                    </button>
                </div>
            </form>
        </div>

        <!-- Card: Koneksi MikroTik & Domain Remote (Super Admin Only) -->
        <?php if ($currentUser['role'] === 'superadmin'): ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <i data-lucide="server" class="w-4 h-4"></i>
                        </div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">Koneksi Router & Domain VPN</h3>
                    </div>
                    <button type="button" onclick="testRouterNow()" id="btnTestRouter" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-semibold">
                        Tes Koneksi
                    </button>
                </div>

                <form method="POST" action="settings.php" class="space-y-3.5 text-xs">
                    <input type="hidden" name="action" value="update_router">
                    
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2">
                            <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Host / IP MikroTik *</label>
                            <input type="text" name="router_host" id="inputRouterHost" value="<?= htmlspecialchars($routerProfile['host'] ?? '') ?>" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Port API</label>
                            <input type="number" name="router_port" id="inputRouterPort" value="<?= htmlspecialchars($routerProfile['api_port'] ?? '8728') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Domain Name / DDNS Setting -->
                    <div class="bg-blue-50/60 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/60 rounded-xl p-3 space-y-1">
                        <label class="block text-blue-700 dark:text-blue-300 font-semibold flex items-center gap-1.5">
                            <i data-lucide="globe" class="w-3.5 h-3.5"></i>
                            <span>Domain / DDNS MikroTik (Untuk Remote Client)</span>
                        </label>
                        <input type="text" name="domain_name" value="<?= htmlspecialchars($routerProfile['domain_name'] ?? '') ?>" placeholder="misal: vpn.domainanda.com atau xxxx.sn.mynetname.net" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">
                            Opsional: Jika diisi, domain ini bisa dipilih pada script koneksi klien VPN selain opsi IP Publik.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">User API MikroTik *</label>
                            <input type="text" name="router_user" id="inputRouterUser" value="<?= htmlspecialchars($routerProfile['username'] ?? '') ?>" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Password API</label>
                            <input type="password" name="router_pass" id="inputRouterPass" placeholder="Kosongkan jika tetap" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Interface Default WAN (Grafik Speed)</label>
                        <input type="text" name="wan_interface" value="<?= htmlspecialchars($routerProfile['wan_interface'] ?? 'ether1') ?>" placeholder="e.g. ether1" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl shadow-md shadow-emerald-500/20">
                            Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
async function testRouterNow() {
    const btn = document.getElementById('btnTestRouter');
    btn.innerText = 'Mengetes...';
    btn.disabled = true;

    try {
        const res = await fetch('api/traffic.php');
        const json = await res.json();
        if (json.online) {
            alert(`✅ Berhasil terhubung ke ${json.board_name} (RouterOS v${json.version})!`);
        } else {
            alert(`❌ Gagal: ${json.error}`);
        }
    } catch (e) {
        alert('Gagal menghubungi backend');
    } finally {
        btn.innerText = 'Tes Koneksi';
        btn.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
