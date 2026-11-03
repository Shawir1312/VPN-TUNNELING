<?php
$pageTitle = "Manajemen VPN (SSTP & L2TP) - MikroTik Manager";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/Helpers.php';

$activeTab = $_GET['tab'] ?? 'secrets';
$ros = Auth::getRouterConnection();
$routerOnline = ($ros && $ros->connected);

$secrets = [];
$activeSessions = [];
$sstpServer = [];
$l2tpServer = [];
$profiles = [];

if ($routerOnline) {
    // 1. Ambil Secrets
    $rawSecrets = $ros->comm('/ppp/secret/print');
    $secrets = Helpers::filterByOwner($rawSecrets, $currentUser);

    // 2. Ambil Profiles
    $rawProfiles = $ros->comm('/ppp/profile/print', ['.proplist=name']);
    foreach ($rawProfiles as $p) {
        if (!empty($p['name'])) $profiles[] = $p['name'];
    }

    // 3. Ambil Active Sessions
    $rawActives = $ros->comm('/ppp/active/print');
    if ($currentUser['role'] === 'superadmin') {
        $activeSessions = $rawActives;
    } else {
        $mySecretNames = array_flip(array_column($secrets, 'name'));
        foreach ($rawActives as $act) {
            if (isset($mySecretNames[$act['name'] ?? ''])) {
                $activeSessions[] = $act;
            }
        }
    }

    // 4. Ambil Server Settings (jika superadmin)
    if ($currentUser['role'] === 'superadmin') {
        $sstpRes = $ros->comm('/interface/sstp-server/server/print');
        $sstpServer = $sstpRes[0] ?? [];

        $l2tpRes = $ros->comm('/interface/l2tp-server/server/print');
        $l2tpServer = $l2tpRes[0] ?? [];
    }

    $ros->disconnect();
}
?>

<div class="space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="shield-check" class="w-6 h-6 text-blue-500"></i>
                <span>Manajemen VPN (SSTP & L2TP)</span>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-0.5">
                Kelola akun PPP Secret, pantau sesi klien aktif, dan konfigurasi server VPN.
            </p>
        </div>

        <button onclick="openModal('modalAddSecret')" class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-lg shadow-blue-500/20 transition">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span>Tambah User VPN</span>
        </button>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-800 flex space-x-4 sm:space-x-8 text-xs sm:text-sm">
        <a href="?tab=secrets" class="pb-3 border-b-2 font-medium transition flex items-center gap-1.5 <?= $activeTab === 'secrets' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' ?>">
            <i data-lucide="users" class="w-4 h-4"></i>
            <span>Akun VPN (<?= count($secrets) ?>)</span>
        </a>
        <a href="?tab=active" class="pb-3 border-b-2 font-medium transition flex items-center gap-1.5 <?= $activeTab === 'active' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' ?>">
            <i data-lucide="activity" class="w-4 h-4"></i>
            <span>Sesi Terhubung (<?= count($activeSessions) ?>)</span>
        </a>
        <?php if ($currentUser['role'] === 'superadmin'): ?>
            <a href="?tab=servers" class="pb-3 border-b-2 font-medium transition flex items-center gap-1.5 <?= $activeTab === 'servers' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' ?>">
                <i data-lucide="settings" class="w-4 h-4"></i>
                <span>Server SSTP & L2TP</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- TAB 1: PPP SECRETS -->
    <?php if ($activeTab === 'secrets'): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm overflow-hidden">
            
            <!-- Table Header Bar -->
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-xs text-slate-500">
                    Menampilkan <strong class="text-slate-800 dark:text-slate-200"><?= count($secrets) ?></strong> akun VPN milik Anda.
                </div>
                <div class="relative w-full sm:w-64">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input type="text" id="searchSecretInput" placeholder="Cari user atau IP..." class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <!-- Desktop View: Data Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200 dark:border-slate-700/80">
                        <tr>
                            <th class="py-3 px-4">Nama User</th>
                            <th class="py-3 px-4">Service</th>
                            <th class="py-3 px-4">Remote IP</th>
                            <th class="py-3 px-4">Profil</th>
                            <?php if ($currentUser['role'] === 'superadmin'): ?>
                                <th class="py-3 px-4">Owner (Admin)</th>
                            <?php endif; ?>
                            <th class="py-3 px-4">Keterangan</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60" id="secretTableBody">
                        <?php if (empty($secrets)): ?>
                            <tr>
                                <td colspan="<?= $currentUser['role'] === 'superadmin' ? '8' : '7' ?>" class="py-12 text-center text-slate-400">
                                    Belum ada akun VPN yang dibuat. Klik tombol "+ Tambah User VPN" untuk memulai.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($secrets as $s): 
                                $isDisabled = ($s['disabled'] ?? 'false') === 'true';
                                $owner = Helpers::getResourceOwner($s['comment'] ?? '') ?: 'System';
                                $displayNote = Helpers::getDisplayComment($s['comment'] ?? '');
                            ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition">
                                    <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($s['name']) ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold uppercase <?= $s['service'] === 'sstp' ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-400' : ($s['service'] === 'l2tp' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300') ?>">
                                            <?= htmlspecialchars($s['service'] ?? 'any') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-300">
                                        <?= htmlspecialchars($s['remote-address'] ?? 'Dynamic') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500">
                                        <?= htmlspecialchars($s['profile'] ?? 'default') ?>
                                    </td>
                                    <?php if ($currentUser['role'] === 'superadmin'): ?>
                                        <td class="py-3.5 px-4">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300">
                                                <?= htmlspecialchars($owner) ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="py-3.5 px-4 text-slate-500 max-w-xs truncate" title="<?= htmlspecialchars($displayNote) ?>">
                                        <?= htmlspecialchars($displayNote) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <button onclick="toggleSecretStatus('<?= $s['.id'] ?>', '<?= $isDisabled ? 'no' : 'yes' ?>')" class="px-2.5 py-1 rounded-full text-[10px] font-semibold transition <?= $isDisabled ? 'bg-slate-100 dark:bg-slate-700 text-slate-500' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' ?>">
                                            <?= $isDisabled ? 'Nonaktif' : 'Aktif' ?>
                                        </button>
                                    </td>
                                    <td class="py-3.5 px-4 text-right space-x-1">
                                        <button onclick='openEditSecretModal(<?= json_encode($s) ?>)' class="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-slate-700" title="Edit User">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteSecret('<?= $s['.id'] ?>', '<?= htmlspecialchars($s['name']) ?>')" class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30" title="Hapus User">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View: Card List -->
            <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700/60" id="secretCardsList">
                <?php if (empty($secrets)): ?>
                    <div class="p-6 text-center text-xs text-slate-400">
                        Belum ada akun VPN.
                    </div>
                <?php else: ?>
                    <?php foreach ($secrets as $s): 
                        $isDisabled = ($s['disabled'] ?? 'false') === 'true';
                        $owner = Helpers::getResourceOwner($s['comment'] ?? '') ?: 'System';
                        $displayNote = Helpers::getDisplayComment($s['comment'] ?? '');
                    ?>
                        <div class="p-4 space-y-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-bold text-sm text-slate-900 dark:text-white"><?= htmlspecialchars($s['name']) ?></div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold uppercase <?= $s['service'] === 'sstp' ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-400' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400' ?>">
                                            <?= htmlspecialchars($s['service'] ?? 'any') ?>
                                        </span>
                                        <span class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($s['remote-address'] ?? 'Dynamic') ?></span>
                                    </div>
                                </div>
                                <button onclick="toggleSecretStatus('<?= $s['.id'] ?>', '<?= $isDisabled ? 'no' : 'yes' ?>')" class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $isDisabled ? 'bg-slate-200 dark:bg-slate-700 text-slate-500' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' ?>">
                                    <?= $isDisabled ? 'Off' : 'Active' ?>
                                </button>
                            </div>

                            <div class="text-xs text-slate-500 flex items-center justify-between">
                                <span>Note: <?= htmlspecialchars($displayNote) ?></span>
                                <?php if ($currentUser['role'] === 'superadmin'): ?>
                                    <span class="text-[10px] px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 rounded-full"><?= htmlspecialchars($owner) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="pt-2 border-t border-slate-100 dark:border-slate-700/40 flex items-center justify-end space-x-2">
                                <button onclick='openEditSecretModal(<?= json_encode($s) ?>)' class="px-3 py-1.5 rounded-lg text-xs bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                    Edit
                                </button>
                                <button onclick="deleteSecret('<?= $s['.id'] ?>', '<?= htmlspecialchars($s['name']) ?>')" class="px-3 py-1.5 rounded-lg text-xs bg-rose-50 dark:bg-rose-950/30 text-rose-600">
                                    Hapus
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

    <!-- TAB 2: ACTIVE CONNECTIONS -->
    <?php elseif ($activeTab === 'active'): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/80 flex items-center justify-between">
                <div class="text-xs text-slate-500">
                    Sesi VPN aktif yang sedang tersambung ke router MikroTik.
                </div>
                <button onclick="location.reload()" class="inline-flex items-center space-x-1 px-3 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs rounded-lg hover:bg-slate-200">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    <span>Refresh</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200 dark:border-slate-700/80">
                        <tr>
                            <th class="py-3 px-4">User</th>
                            <th class="py-3 px-4">Service</th>
                            <th class="py-3 px-4">IP VPN (Client)</th>
                            <th class="py-3 px-4">IP Asal (Caller ID)</th>
                            <th class="py-3 px-4">Uptime</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php if (empty($activeSessions)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    Tidak ada klien VPN yang sedang aktif.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activeSessions as $act): ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition">
                                    <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <?= htmlspecialchars($act['name']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 uppercase font-mono font-semibold text-[10px]">
                                        <?= htmlspecialchars($act['service'] ?? '-') ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-slate-700 dark:text-slate-300">
                                        <?= htmlspecialchars($act['address'] ?? '-') ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-slate-500">
                                        <?= htmlspecialchars($act['caller-id'] ?? '-') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500">
                                        <?= htmlspecialchars($act['uptime'] ?? '-') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <button onclick="kickSession('<?= $act['.id'] ?>', '<?= htmlspecialchars($act['name']) ?>')" class="px-2.5 py-1 bg-rose-50 dark:bg-rose-950/30 hover:bg-rose-100 text-rose-600 dark:text-rose-400 rounded-lg text-xs font-semibold transition">
                                            Kick (Putus)
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- TAB 3: SERVER SETTINGS (SUPER ADMIN ONLY) -->
    <?php elseif ($activeTab === 'servers' && $currentUser['role'] === 'superadmin'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- SSTP Server Card -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-950/60 text-cyan-600 flex items-center justify-center">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">SSTP Server (SSL VPN)</h3>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= ($sstpServer['enabled'] ?? 'false') === 'true' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700' ?>">
                        <?= ($sstpServer['enabled'] ?? 'false') === 'true' ? 'Server Aktif' : 'Nonaktif' ?>
                    </span>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-400 mb-1">Port SSTP Server</label>
                        <input type="number" id="inputSstpPort" value="<?= htmlspecialchars($sstpServer['port'] ?? '443') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        <span class="text-slate-700 dark:text-slate-300">Aktifkan SSTP Server:</span>
                        <input type="checkbox" id="checkSstpEnabled" <?= ($sstpServer['enabled'] ?? 'false') === 'true' ? 'checked' : '' ?> class="w-4 h-4 rounded text-blue-600">
                    </div>
                </div>
            </div>

            <!-- L2TP/IPsec Server Card -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 flex items-center justify-center">
                            <i data-lucide="key" class="w-4 h-4"></i>
                        </div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">L2TP / IPsec Server</h3>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= ($l2tpServer['enabled'] ?? 'false') === 'true' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700' ?>">
                        <?= ($l2tpServer['enabled'] ?? 'false') === 'true' ? 'Server Aktif' : 'Nonaktif' ?>
                    </span>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-400 mb-1">Use IPsec</label>
                        <select id="selectL2tpUseIpsec" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                            <option value="yes" <?= ($l2tpServer['use-ipsec'] ?? '') === 'yes' ? 'selected' : '' ?>>Yes (Opsional)</option>
                            <option value="required" <?= ($l2tpServer['use-ipsec'] ?? '') === 'required' ? 'selected' : '' ?>>Required (Wajib IPsec)</option>
                            <option value="no" <?= ($l2tpServer['use-ipsec'] ?? '') === 'no' ? 'selected' : '' ?>>No (Plain L2TP)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">IPsec Secret / Pre-Shared Key (PSK)</label>
                        <input type="text" id="inputL2tpSecret" placeholder="Masukkan PSK (e.g. rahasia123)" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        <span class="text-slate-700 dark:text-slate-300">Aktifkan L2TP Server:</span>
                        <input type="checkbox" id="checkL2tpEnabled" <?= ($l2tpServer['enabled'] ?? 'false') === 'true' ? 'checked' : '' ?> class="w-4 h-4 rounded text-blue-600">
                    </div>
                </div>
            </div>

            <!-- Save Server Button -->
            <div class="md:col-span-2 flex justify-end">
                <button onclick="saveServerSettings()" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-lg shadow-blue-500/20 transition">
                    Simpan Perubahan Server
                </button>
            </div>

        </div>
    <?php endif; ?>

</div>

<!-- Modal: Tambah User VPN -->
<div id="modalAddSecret" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-blue-500"></i>
                <span>Tambah Akun User VPN</span>
            </h3>
            <button onclick="closeModal('modalAddSecret')" class="p-1 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="formAddSecret" class="space-y-3 text-xs">
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Username VPN *</label>
                <input type="text" name="name" required placeholder="misal: user_kantor" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Password VPN *</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Service</label>
                    <select name="service" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        <option value="any">Any (Semua)</option>
                        <option value="sstp">SSTP</option>
                        <option value="l2tp">L2TP</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Profil PPP</label>
                    <select name="profile" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        <?php foreach ($profiles as $prof): ?>
                            <option value="<?= htmlspecialchars($prof) ?>"><?= htmlspecialchars($prof) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($profiles)): ?>
                            <option value="default">default</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Static Remote IP (Opsional)</label>
                <input type="text" name="remote_address" placeholder="misal: 10.10.0.50 (kosongkan jika pakai pool)" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Catatan / Keterangan</label>
                <input type="text" name="comment" placeholder="misal: Laptop Sales Jakarta" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddSecret')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">Simpan User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit User VPN -->
<div id="modalEditSecret" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="edit-3" class="w-5 h-5 text-blue-500"></i>
                <span>Edit Akun VPN: <span id="editSecretName" class="text-blue-500"></span></span>
            </h3>
            <button onclick="closeModal('modalEditSecret')" class="p-1 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="formEditSecret" class="space-y-3 text-xs">
            <input type="hidden" name="id" id="editSecretId">
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Ganti Password (Kosongkan jika tidak diubah)</label>
                <input type="password" name="password" placeholder="••••••••" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Remote IP (Static)</label>
                <input type="text" name="remote_address" id="editSecretRemote" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Catatan / Keterangan</label>
                <input type="text" name="comment" id="editSecretComment" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalEditSecret')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">Perbarui User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// Buka otomatis jika ada parameter query ?open=create
if (new URLSearchParams(window.location.search).get('open') === 'create') {
    openModal('modalAddSecret');
}

// Search filter
const searchInput = document.getElementById('searchSecretInput');
if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        const val = e.target.value.toLowerCase();
        document.querySelectorAll('#secretTableBody tr').forEach(tr => {
            tr.style.display = tr.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
        document.querySelectorAll('#secretCardsList > div').forEach(card => {
            card.style.display = card.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
    });
}

// Form Tambah User
document.getElementById('formAddSecret').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'add_secret');

    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        alert(json.message);
        if (json.success) location.reload();
    } catch (err) {
        alert('Gagal mengirim data');
    }
});

// Buka Edit Modal
function openEditSecretModal(item) {
    document.getElementById('editSecretId').value = item['.id'];
    document.getElementById('editSecretName').innerText = item['name'];
    document.getElementById('editSecretRemote').value = item['remote-address'] || '';
    
    // Bersihkan tag owner dari comment
    let comment = item['comment'] || '';
    comment = comment.replace(/\[owner:[a-zA-Z0-9_\-\.]+\]/gi, '').trim();
    document.getElementById('editSecretComment').value = comment;

    openModal('modalEditSecret');
}

// Form Edit User
document.getElementById('formEditSecret').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'edit_secret');

    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        alert(json.message);
        if (json.success) location.reload();
    } catch (err) {
        alert('Gagal memperbarui user');
    }
});

// Toggle Status Aktif / Mati
async function toggleSecretStatus(id, disabled) {
    const formData = new FormData();
    formData.append('action', 'toggle_secret');
    formData.append('id', id);
    formData.append('disabled', disabled);

    const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
    const json = await res.json();
    if (json.success) location.reload();
    else alert(json.message);
}

// Hapus User
async function deleteSecret(id, name) {
    if (!confirm(`Apakah Anda yakin ingin menghapus user '${name}'?`)) return;

    const formData = new FormData();
    formData.append('action', 'delete_secret');
    formData.append('id', id);

    const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
    const json = await res.json();
    alert(json.message);
    if (json.success) location.reload();
}

// Kick Active Session
async function kickSession(id, name) {
    if (!confirm(`Putus paksa koneksi user '${name}' sekarang?`)) return;

    const formData = new FormData();
    formData.append('action', 'kick_active');
    formData.append('id', id);
    formData.append('name', name);

    const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
    const json = await res.json();
    alert(json.message);
    if (json.success) location.reload();
}

// Save Server Settings (Super Admin)
async function saveServerSettings() {
    const formData = new FormData();
    formData.append('action', 'save_server_settings');
    formData.append('sstp_enabled', document.getElementById('checkSstpEnabled').checked ? '1' : '0');
    formData.append('sstp_port', document.getElementById('inputSstpPort').value);
    formData.append('l2tp_enabled', document.getElementById('checkL2tpEnabled').checked ? '1' : '0');
    formData.append('l2tp_use_ipsec', document.getElementById('selectL2tpUseIpsec').value);
    formData.append('l2tp_ipsec_secret', document.getElementById('inputL2tpSecret').value);

    const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
    const json = await res.json();
    alert(json.message);
    if (json.success) location.reload();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

