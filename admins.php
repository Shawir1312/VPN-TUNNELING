<?php
$pageTitle = "Manajemen Admin - MikroTik Manager";
require_once __DIR__ . '/includes/header.php';
Auth::requireSuperAdmin();

$db = Database::getConnection();
$message = '';
$error = '';

// Handle Create Sub-Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_admin') {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'subadmin';

        if (empty($username) || strlen($password) < 6) {
            $error = 'Username wajib diisi dan password minimal 6 karakter.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("
                    INSERT INTO admin_users (username, password_hash, full_name, role, created_by_admin_id)
                    VALUES (:u, :p, :name, :role, :creator)
                ");
                $stmt->execute([
                    ':u' => strtolower($username),
                    ':p' => $hash,
                    ':name' => $fullname ?: $username,
                    ':role' => $role,
                    ':creator' => $currentUser['id']
                ]);
                $message = "Akun admin '{$username}' berhasil dibuat.";
            } catch (PDOException $e) {
                $error = "Gagal membuat admin: Username '{$username}' sudah terdaftar.";
            }
        }
    } elseif ($_POST['action'] === 'toggle_active') {
        $id = (int)$_POST['id'];
        $status = (int)$_POST['status'];
        if ($id !== (int)$currentUser['id']) {
            $stmt = $db->prepare("UPDATE admin_users SET is_active = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            $message = "Status akun diperbarui.";
        }
    } elseif ($_POST['action'] === 'reset_password') {
        $id = (int)$_POST['id'];
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) >= 6) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $id]);
            $message = "Password admin berhasil direset.";
        } else {
            $error = "Password baru minimal 6 karakter.";
        }
    }
}

// Ambil semua daftar admin
$stmt = $db->query("SELECT * FROM admin_users ORDER BY id ASC");
$allAdmins = $stmt->fetchAll();
?>

<div class="space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="users" class="w-6 h-6 text-purple-500"></i>
                <span>Manajemen Akun Admin (Multi-Tenant)</span>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-0.5">
                Buat dan kelola akses akun sub-admin (misal: admin1, admin2) dengan privasi data terisolasi.
            </p>
        </div>

        <button onclick="openModal('modalCreateAdmin')" class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-lg shadow-blue-500/20 transition">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span>Tambah Sub-Admin Baru</span>
        </button>
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

    <!-- Table Container -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm overflow-hidden">
        
        <div class="p-4 border-b border-slate-200 dark:border-slate-700/80 text-xs text-slate-500">
            Total terdaftar: <strong class="text-slate-800 dark:text-slate-200"><?= count($allAdmins) ?></strong> pengguna.
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200 dark:border-slate-700/80">
                    <tr>
                        <th class="py-3 px-4">Username</th>
                        <th class="py-3 px-4">Nama Lengkap</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4">Tema UI</th>
                        <th class="py-3 px-4">Tanggal Dibuat</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <?php foreach ($allAdmins as $adm): 
                        $isSelf = ((int)$adm['id'] === (int)$currentUser['id']);
                    ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition">
                            <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                <div class="w-6 h-6 rounded-md bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 font-bold flex items-center justify-center text-[10px]">
                                    <?= strtoupper(substr($adm['username'], 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars($adm['username']) ?></span>
                                <?php if ($isSelf): ?>
                                    <span class="text-[10px] px-1.5 py-0.2 bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 rounded font-normal">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-700 dark:text-slate-300">
                                <?= htmlspecialchars($adm['full_name']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase <?= $adm['role'] === 'superadmin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' ?>">
                                    <?= htmlspecialchars($adm['role']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-400 capitalize">
                                <?= htmlspecialchars($adm['theme_preference']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-400 font-mono text-[11px]">
                                <?= htmlspecialchars(substr($adm['created_at'], 0, 10)) ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($isSelf): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">Aktif</span>
                                <?php else: ?>
                                    <form method="POST" action="admins.php" class="inline">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= $adm['id'] ?>">
                                        <input type="hidden" name="status" value="<?= (int)$adm['is_active'] === 1 ? '0' : '1' ?>">
                                        <button type="submit" class="px-2.5 py-1 rounded-full text-[10px] font-semibold transition <?= (int)$adm['is_active'] === 1 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' ?>">
                                            <?= (int)$adm['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <button onclick="openResetModal('<?= $adm['id'] ?>', '<?= htmlspecialchars($adm['username']) ?>')" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 rounded-lg text-xs font-medium">
                                    Reset Pass
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Modal: Tambah Admin -->
<div id="modalCreateAdmin" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-md w-full p-6 space-y-4">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-blue-500"></i>
                <span>Buat Akun Sub-Admin Baru</span>
            </h3>
            <button onclick="closeModal('modalCreateAdmin')" class="p-1 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="admins.php" class="space-y-3.5 text-xs">
            <input type="hidden" name="action" value="create_admin">
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Username Admin * (tanpa spasi)</label>
                <input type="text" name="username" required placeholder="misal: admin1" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Nama Lengkap</label>
                <input type="text" name="fullname" placeholder="misal: Operator Cabang 1" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Password * (min. 6 karakter)</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Peran (Role)</label>
                <select name="role" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                    <option value="subadmin">Sub-Admin (Data VPN & Dst-NAT Terisolasi)</option>
                    <option value="superadmin">Super Admin (Akses Global Semua Data)</option>
                </select>
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalCreateAdmin')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">Buat Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset Password -->
<div id="modalResetPassword" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-sm w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="key" class="w-5 h-5 text-amber-500"></i>
                <span>Reset Password: <span id="resetTargetUsername" class="text-blue-500"></span></span>
            </h3>
            <button onclick="closeModal('modalResetPassword')" class="p-1 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="admins.php" class="space-y-3.5 text-xs">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="resetTargetId">
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Password Baru * (min. 6 karakter)</label>
                <input type="password" name="new_password" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalResetPassword')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">Simpan Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function openResetModal(id, username) {
    document.getElementById('resetTargetId').value = id;
    document.getElementById('resetTargetUsername').innerText = username;
    openModal('modalResetPassword');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

