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
$myPools = [];
$myProfiles = [];
$publicIps = [];

if ($routerOnline) {
    // 1. Ambil Secrets & Filter by Owner
    $rawSecrets = $ros->comm('/ppp/secret/print');
    $secrets = Helpers::filterByOwner($rawSecrets, $currentUser);

    // 2. Ambil IP Pools & Filter by Owner
    $rawPools = $ros->comm('/ip/pool/print');
    $myPools = Helpers::filterByOwner($rawPools, $currentUser);

    // 3. Ambil PPP Profiles & Filter by Owner
    $rawProfiles = $ros->comm('/ppp/profile/print');
    $filteredProfiles = Helpers::filterByOwner($rawProfiles, $currentUser);
    $systemProfiles = array_filter($rawProfiles, fn($p) => in_array($p['name'] ?? '', ['default', 'default-encryption'], true));
    $myProfiles = array_merge($systemProfiles, $filteredProfiles);
    $uniqueProfiles = [];
    foreach ($myProfiles as $pr) {
        $uniqueProfiles[$pr['name']] = $pr;
    }
    $myProfiles = array_values($uniqueProfiles);

    // 4. Ambil Active Sessions (Hanya sesi milik akun admin yang login)
    $rawActives = $ros->comm('/ppp/active/print');
    $mySecretNames = array_flip(array_column($secrets, 'name'));
    foreach ($rawActives as $act) {
        if (isset($mySecretNames[$act['name'] ?? ''])) {
            $activeSessions[] = $act;
        }
    }

    // 5. Ambil Server Settings (SSTP & L2TP)
    $sstpRes = $ros->comm('/interface/sstp-server/server/print');
    $sstpServer = $sstpRes[0] ?? [];

    $l2tpRes = $ros->comm('/interface/l2tp-server/server/print');
    $l2tpServer = $l2tpRes[0] ?? [];

    // 6. Ambil IP Publik untuk generator script klien
    $ipAddresses = $ros->comm('/ip/address/print', ['?disabled=no']);
    foreach ($ipAddresses as $addr) {
        $ipNet = explode('/', $addr['address'] ?? '')[0];
        if (!empty($ipNet)) {
            $publicIps[] = $ipNet;
        }
    }

    $ros->disconnect();
}

$mainPublicIp = !empty($publicIps) ? $publicIps[0] : ($routerProfile['host'] ?? 'IP_PUBLIK_MIKROTIK');
$configuredDomain = trim($routerProfile['domain_name'] ?? '');
// Default host remote prioritas domain jika terisi, fallback ke IP Publik
$defaultRemoteHost = !empty($configuredDomain) ? $configuredDomain : $mainPublicIp;
?>

<div class="space-y-6">

    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="shield-check" class="w-6 h-6 text-blue-500"></i>
                <span>Manajemen VPN (SSTP & L2TP)</span>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-0.5">
                Kelola user VPN, IP Pool, Profil privat, serta generator script via IP Publik & Domain.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="openModal('modalAddPool')" class="inline-flex items-center space-x-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-xl shadow-md shadow-emerald-500/20 transition">
                <i data-lucide="layers" class="w-4 h-4"></i>
                <span>+ IP Pool</span>
            </button>
            <button onclick="openModal('modalAddProfile')" class="inline-flex items-center space-x-1.5 px-3 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold rounded-xl shadow-md shadow-purple-500/20 transition">
                <i data-lucide="sliders" class="w-4 h-4"></i>
                <span>+ PPP Profile</span>
            </button>
            <button onclick="openModal('modalAddSecret')" class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl shadow-md shadow-blue-500/20 transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>+ User VPN</span>
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-800 flex overflow-x-auto space-x-4 sm:space-x-8 text-xs sm:text-sm">
        <a href="?tab=secrets" class="pb-3 border-b-2 font-medium transition whitespace-nowrap flex items-center gap-1.5 <?= $activeTab === 'secrets' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' ?>">
            <i data-lucide="users" class="w-4 h-4"></i>
            <span>Akun VPN (<?= count($secrets) ?>)</span>
        </a>
        <a href="?tab=pools_profiles" class="pb-3 border-b-2 font-medium transition whitespace-nowrap flex items-center gap-1.5 <?= $activeTab === 'pools_profiles' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' ?>">
            <i data-lucide="layers" class="w-4 h-4"></i>
            <span>Profil & IP Pool Saya</span>
        </a>
        <a href="?tab=scripts" class="pb-3 border-b-2 font-medium transition whitespace-nowrap flex items-center gap-1.5 <?= $activeTab === 'scripts' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' ?>">
            <i data-lucide="terminal" class="w-4 h-4 text-emerald-500"></i>
            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">Script MikroTik (Siap Paste)</span>
        </a>
        <a href="?tab=active" class="pb-3 border-b-2 font-medium transition whitespace-nowrap flex items-center gap-1.5 <?= $activeTab === 'active' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' ?>">
            <i data-lucide="activity" class="w-4 h-4"></i>
            <span>Sesi Terhubung (<?= count($activeSessions) ?>)</span>
        </a>
        <?php if ($currentUser['role'] === 'superadmin'): ?>
            <a href="?tab=servers" class="pb-3 border-b-2 font-medium transition whitespace-nowrap flex items-center gap-1.5 <?= $activeTab === 'servers' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' ?>">
                <i data-lucide="settings" class="w-4 h-4"></i>
                <span>Server SSTP & L2TP</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- ============================================================== -->
    <!-- TAB 1: AKUN VPN (SECRETS) -->
    <!-- ============================================================== -->
    <?php if ($activeTab === 'secrets'): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm overflow-hidden">
            
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-xs text-slate-500">
                    Menampilkan <strong class="text-slate-800 dark:text-slate-200"><?= count($secrets) ?></strong> akun VPN milik Anda.
                    <?php if (!empty($configuredDomain)): ?>
                        &bull; Domain Remote: <span class="font-mono text-blue-600 dark:text-blue-400 font-semibold"><?= htmlspecialchars($configuredDomain) ?></span>
                    <?php endif; ?>
                </div>
                <div class="relative w-full sm:w-64">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input type="text" id="searchSecretInput" placeholder="Cari user atau IP..." class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <!-- Desktop View Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200 dark:border-slate-700/80">
                        <tr>
                            <th class="py-3 px-4">Nama User</th>
                            <th class="py-3 px-4">Service</th>
                            <th class="py-3 px-4">Remote IP</th>
                            <th class="py-3 px-4">Profil</th>
                            <th class="py-3 px-4">Keterangan</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Aksi & Script</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60" id="secretTableBody">
                        <?php if (empty($secrets)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    Belum ada user VPN yang dibuat. Klik tombol "+ User VPN" di atas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($secrets as $s): 
                                $isDisabled = ($s['disabled'] ?? 'false') === 'true';
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
                                    <td class="py-3.5 px-4 text-slate-500 font-mono">
                                        <?= htmlspecialchars($s['profile'] ?? 'default') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 max-w-xs truncate" title="<?= htmlspecialchars($displayNote) ?>">
                                        <?= htmlspecialchars($displayNote) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <button onclick="toggleSecretStatus('<?= $s['.id'] ?>', '<?= $isDisabled ? 'no' : 'yes' ?>')" class="px-2.5 py-1 rounded-full text-[10px] font-semibold transition <?= $isDisabled ? 'bg-slate-100 dark:bg-slate-700 text-slate-500' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' ?>">
                                            <?= $isDisabled ? 'Nonaktif' : 'Aktif' ?>
                                        </button>
                                    </td>
                                    <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                        <!-- Tombol Copy Script Klien MikroTik -->
                                        <button onclick='openClientScriptModal("<?= htmlspecialchars($s['name']) ?>", "<?= htmlspecialchars($s['password'] ?? '') ?>", "<?= htmlspecialchars($s['service'] ?? 'any') ?>")' class="px-2 py-1 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 rounded-lg text-[11px] font-semibold inline-flex items-center gap-1" title="Copy Script Klien MikroTik">
                                            <i data-lucide="terminal" class="w-3.5 h-3.5"></i>
                                            <span>Copy Script</span>
                                        </button>
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

            <!-- Mobile View Cards -->
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
                                <span class="font-mono text-[11px] text-slate-400"><?= htmlspecialchars($s['profile'] ?? 'default') ?></span>
                            </div>

                            <div class="pt-2 border-t border-slate-100 dark:border-slate-700/40 flex items-center justify-end space-x-2">
                                <button onclick='openClientScriptModal("<?= htmlspecialchars($s['name']) ?>", "<?= htmlspecialchars($s['password'] ?? '') ?>", "<?= htmlspecialchars($s['service'] ?? 'any') ?>")' class="px-2.5 py-1.5 rounded-lg text-xs bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 font-semibold flex items-center gap-1">
                                    <i data-lucide="terminal" class="w-3.5 h-3.5"></i>
                                    <span>Script</span>
                                </button>
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

    <!-- ============================================================== -->
    <!-- TAB 2: PROFIL & IP POOL SAYA -->
    <!-- ============================================================== -->
    <?php elseif ($activeTab === 'pools_profiles'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- SECTION IP POOLS -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
                            <i data-lucide="layers" class="w-4 h-4 text-emerald-500"></i>
                            <span>IP Pool Saya (<?= count($myPools) ?>)</span>
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Alokasi rentang IP privat khusus untuk klien VPN Anda</p>
                    </div>
                    <button onclick="openModal('modalAddPool')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold">
                        + Tambah Pool
                    </button>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
                    <?php if (empty($myPools)): ?>
                        <div class="py-8 text-center text-slate-400">
                            Belum ada IP Pool yang Anda buat.
                        </div>
                    <?php else: ?>
                        <?php foreach ($myPools as $pool): 
                            $owner = Helpers::getResourceOwner($pool['comment'] ?? '') ?: 'System';
                        ?>
                            <div class="py-3 flex items-center justify-between">
                                <div>
                                    <div class="font-bold font-mono text-slate-800 dark:text-slate-100"><?= htmlspecialchars($pool['name']) ?></div>
                                    <div class="font-mono text-emerald-600 dark:text-emerald-400 text-[11px] mt-0.5">
                                        <?= htmlspecialchars($pool['ranges'] ?? '-') ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="deletePool('<?= $pool['.id'] ?>', '<?= htmlspecialchars($pool['name']) ?>')" class="p-1 text-slate-400 hover:text-rose-600" title="Hapus Pool">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SECTION PPP PROFILES -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
                            <i data-lucide="sliders" class="w-4 h-4 text-purple-500"></i>
                            <span>PPP Profile Saya (<?= count($myProfiles) ?>)</span>
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Konfigurasi Gateway (Local), Pool (Remote), DNS & Limit</p>
                    </div>
                    <button onclick="openModal('modalAddProfile')" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-xs font-semibold">
                        + Tambah Profile
                    </button>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs">
                    <?php foreach ($myProfiles as $prof): 
                        $isDefault = in_array($prof['name'], ['default', 'default-encryption'], true);
                        $owner = Helpers::getResourceOwner($prof['comment'] ?? '') ?: ($isDefault ? 'Default' : 'System');
                    ?>
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <div class="font-bold font-mono text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($prof['name']) ?></span>
                                    <?php if ($isDefault): ?>
                                        <span class="text-[9px] px-1 py-0.2 bg-slate-100 dark:bg-slate-700 text-slate-500 rounded font-normal">System</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                    Local: <span class="text-slate-600 dark:text-slate-300"><?= htmlspecialchars($prof['local-address'] ?? 'Auto') ?></span> &bull; 
                                    Remote: <span class="text-slate-600 dark:text-slate-300"><?= htmlspecialchars($prof['remote-address'] ?? 'Auto') ?></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if (!$isDefault): ?>
                                    <button onclick="deleteProfile('<?= $prof['.id'] ?>', '<?= htmlspecialchars($prof['name']) ?>')" class="p-1 text-slate-400 hover:text-rose-600" title="Hapus Profile">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    <!-- ============================================================== -->
    <!-- TAB 3: SCRIPT MIKROTIK (SIAP PASTE) -->
    <!-- ============================================================== -->
    <?php elseif ($activeTab === 'scripts'): ?>
        <div class="space-y-6">

            <!-- Banner info -->
            <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 rounded-2xl p-4 text-xs text-emerald-800 dark:text-emerald-300 flex items-start gap-3">
                <i data-lucide="terminal" class="w-5 h-5 flex-shrink-0 text-emerald-500"></i>
                <div>
                    <span class="font-bold">Generator Script RouterOS:</span>
                    <p class="mt-0.5 leading-relaxed">
                        Salin (*Copy*) script di bawah ini langsung ke <strong>Terminal Winbox / SSH MikroTik</strong>. Anda dapat memilih koneksi menggunakan <strong>Domain/DDNS</strong> atau <strong>IP Publik MikroTik</strong>.
                    </p>
                </div>
            </div>

            <!-- Generator 1: Script Server Setup (One-Click Copy) -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>1. Script Setup VPN Server (SSTP & L2TP) di MikroTik Utama</span>
                        </h3>
                        <p class="text-[11px] text-slate-400">Jalankan di MikroTik Server untuk membuka port dan membuat masquerade NAT</p>
                    </div>
                    <button onclick="copyToClipboard('serverScriptCode')" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 self-start sm:self-auto">
                        <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                        <span>Copy Script Server</span>
                    </button>
                </div>

                <div class="relative">
                    <pre id="serverScriptCode" class="p-4 bg-slate-900 text-emerald-400 font-mono text-xs rounded-xl overflow-x-auto leading-relaxed border border-slate-800">
# ========================================================
# 1. BUAT IP POOL & PROFILE VPN
# ========================================================
/ip pool add name="pool-vpn-main" ranges=10.10.10.2-10.10.10.254 comment="[VPN-TUNNELING] Pool Klien"
/ppp profile add name="profile-vpn-main" local-address=10.10.10.1 remote-address="pool-vpn-main" dns-server=8.8.8.8,1.1.1.1 comment="[VPN-TUNNELING] Profile Utama"

# ========================================================
# 2. AKTIFKAN SERVER SSTP & L2TP
# ========================================================
/interface sstp-server server set enabled=yes port=443 default-profile="profile-vpn-main"
/interface l2tp-server server set enabled=yes use-ipsec=yes ipsec-secret="rahasiavpn123" default-profile="profile-vpn-main"

# ========================================================
# 3. FIREWALL FILTER (IZINKAN PORT VPN)
# ========================================================
/ip firewall filter add chain=input protocol=tcp dst-port=443 action=accept comment="[VPN] Allow SSTP Server"
/ip firewall filter add chain=input protocol=udp dst-port=500,4500,1701 action=accept comment="[VPN] Allow L2TP/IPSec"
/ip firewall filter add chain=input protocol=ipsec-esp action=accept comment="[VPN] Allow IPsec ESP"

# ========================================================
# 4. NAT MASQUERADE AGAR KLIEN VPN DAPAT INTERNET
# ========================================================
/ip firewall nat add chain=srcnat src-address=10.10.10.0/24 action=masquerade comment="[VPN] Masquerade Tunnel Subnet"</pre>
                </div>
            </div>

            <!-- Generator 2: Script Klien MikroTik Router-to-Router -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span>2. Generator Script Klien Router MikroTik (Client Connection)</span>
                        </h3>
                        <p class="text-[11px] text-slate-400">Pilih user VPN Anda dan tentukan alamat remote (Domain atau IP Publik)</p>
                    </div>
                    <button onclick="copyToClipboard('clientScriptCode')" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 self-start sm:self-auto">
                        <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                        <span>Copy Script Klien</span>
                    </button>
                </div>

                <!-- Selector User, Remote Host Type & Service -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block text-slate-400 mb-1">Pilih User VPN:</label>
                        <select id="genUserSelect" onchange="generateClientScript()" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100">
                            <?php foreach ($secrets as $sec): ?>
                                <option value="<?= htmlspecialchars($sec['name']) ?>" data-service="<?= htmlspecialchars($sec['service']) ?>">
                                    <?= htmlspecialchars($sec['name']) ?> (<?= strtoupper($sec['service']) ?>)
                                </option>
                            <?php endforeach; ?>
                            <?php if (empty($secrets)): ?>
                                <option value="">Belum ada user VPN</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Remote Address Type (Domain vs IP Publik) -->
                    <div>
                        <label class="block text-slate-400 mb-1">Target Remote (Connect-To):</label>
                        <select id="genHostType" onchange="handleHostTypeChange()" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100 font-semibold">
                            <?php if (!empty($configuredDomain)): ?>
                                <option value="domain" selected>🌐 Domain (<?= htmlspecialchars($configuredDomain) ?>)</option>
                                <option value="ip">📍 IP Publik (<?= htmlspecialchars($mainPublicIp) ?>)</option>
                            <?php else: ?>
                                <option value="ip" selected>📍 IP Publik (<?= htmlspecialchars($mainPublicIp) ?>)</option>
                                <option value="custom">✏️ Input Domain Manual...</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-1">Host / Domain yang Digunakan:</label>
                        <input type="text" id="genRemoteHost" value="<?= htmlspecialchars($defaultRemoteHost) ?>" oninput="generateClientScript()" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100 font-mono">
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-1">Tipe Protokol:</label>
                        <select id="genServiceSelect" onchange="generateClientScript()" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100">
                            <option value="sstp">SSTP Client (Port 443)</option>
                            <option value="l2tp">L2TP/IPsec Client</option>
                        </select>
                    </div>
                </div>

                <div class="relative">
                    <pre id="clientScriptCode" class="p-4 bg-slate-900 text-blue-400 font-mono text-xs rounded-xl overflow-x-auto leading-relaxed border border-slate-800">
# Script koneksi otomatis akan muncul di sini</pre>
                </div>
            </div>

        </div>

    <!-- ============================================================== -->
    <!-- TAB 4: SESI AKTIF -->
    <!-- ============================================================== -->
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
                                            Kick
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ============================================================== -->
    <!-- TAB 5: SERVER SETTINGS (SUPER ADMIN) -->
    <!-- ============================================================== -->
    <?php elseif ($activeTab === 'servers' && $currentUser['role'] === 'superadmin'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-950/60 text-cyan-600 flex items-center justify-center">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">SSTP Server (SSL VPN)</h3>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= ($sstpServer['enabled'] ?? 'false') === 'true' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700' ?>">
                        <?= ($sstpServer['enabled'] ?? 'false') === 'true' ? 'Aktif' : 'Nonaktif' ?>
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

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 flex items-center justify-center">
                            <i data-lucide="key" class="w-4 h-4"></i>
                        </div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">L2TP / IPsec Server</h3>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= ($l2tpServer['enabled'] ?? 'false') === 'true' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700' ?>">
                        <?= ($l2tpServer['enabled'] ?? 'false') === 'true' ? 'Aktif' : 'Nonaktif' ?>
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
                        <input type="text" id="inputL2tpSecret" placeholder="Masukkan PSK" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        <span class="text-slate-700 dark:text-slate-300">Aktifkan L2TP Server:</span>
                        <input type="checkbox" id="checkL2tpEnabled" <?= ($l2tpServer['enabled'] ?? 'false') === 'true' ? 'checked' : '' ?> class="w-4 h-4 rounded text-blue-600">
                    </div>
                </div>
            </div>

            <div class="md:col-span-2 flex justify-end">
                <button onclick="saveServerSettings()" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-lg shadow-blue-500/20 transition">
                    Simpan Perubahan Server
                </button>
            </div>

        </div>
    <?php endif; ?>

</div>

<!-- ============================================================== -->
<!-- MODAL: COPY SCRIPT KLIEN MIKROTIK POPUP (DENGAN PILIHAN DOMAIN / IP) -->
<!-- ============================================================== -->
<div id="modalClientScript" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-lg w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="terminal" class="w-5 h-5 text-emerald-500"></i>
                <span>Script Klien: <span id="popupClientUser" class="text-blue-500 font-mono"></span></span>
            </h3>
            <button onclick="closeModal('modalClientScript')" class="p-1 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Pilihan Remote: Domain atau IP Publik -->
        <div class="bg-slate-50 dark:bg-slate-900/70 p-3 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
            <span class="text-slate-600 dark:text-slate-300 font-semibold">Alamat Remote Target:</span>
            <div class="flex items-center gap-2">
                <?php if (!empty($configuredDomain)): ?>
                    <button type="button" id="btnUseDomain" onclick="switchPopupHost('domain')" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-600 text-white transition">
                        🌐 Domain
                    </button>
                    <button type="button" id="btnUseIp" onclick="switchPopupHost('ip')" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                        📍 IP Publik
                    </button>
                <?php else: ?>
                    <span class="font-mono text-emerald-600 dark:text-emerald-400 font-bold"><?= htmlspecialchars($mainPublicIp) ?></span>
                    <?php if ($currentUser['role'] === 'superadmin'): ?>
                        <a href="settings.php" class="text-[10px] text-blue-500 hover:underline">(+ Set Domain)</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- SSTP Option -->
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-cyan-600 dark:text-cyan-400 uppercase font-mono">Pilihan 1: SSTP Client (Port 443)</span>
                <button onclick="copyToClipboard('popupSstpCode')" class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                    <span>Copy SSTP</span>
                </button>
            </div>
            <pre id="popupSstpCode" class="p-3 bg-slate-900 text-cyan-300 font-mono text-[11px] rounded-xl overflow-x-auto border border-slate-800 leading-relaxed"></pre>
        </div>

        <!-- L2TP Option -->
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase font-mono">Pilihan 2: L2TP/IPsec Client</span>
                <button onclick="copyToClipboard('popupL2tpCode')" class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                    <span>Copy L2TP</span>
                </button>
            </div>
            <pre id="popupL2tpCode" class="p-3 bg-slate-900 text-indigo-300 font-mono text-[11px] rounded-xl overflow-x-auto border border-slate-800 leading-relaxed"></pre>
        </div>

        <div class="pt-2 flex justify-end">
            <button onclick="closeModal('modalClientScript')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH USER VPN -->
<div id="modalAddSecret" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-blue-500"></i>
                <span>Tambah Akun User VPN</span>
            </h3>
            <button onclick="closeModal('modalAddSecret')" class="p-1 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
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
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Pilih Profil VPN</label>
                    <select name="profile" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        <?php foreach ($myProfiles as $prof): ?>
                            <option value="<?= htmlspecialchars($prof['name']) ?>"><?= htmlspecialchars($prof['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Static Remote IP (Opsional)</label>
                <input type="text" name="remote_address" placeholder="misal: 10.10.10.50 (kosongkan jika ikut pool profil)" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
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

<!-- MODAL TAMBAH IP POOL -->
<div id="modalAddPool" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="layers" class="w-5 h-5 text-emerald-500"></i>
                <span>Tambah IP Pool Baru</span>
            </h3>
            <button onclick="closeModal('modalAddPool')" class="p-1 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form id="formAddPool" class="space-y-3.5 text-xs">
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Nama Pool *</label>
                <input type="text" name="name" required placeholder="misal: pool-<?= htmlspecialchars($currentUser['username']) ?>-vpn" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Rentang IP (Ranges) *</label>
                <input type="text" name="ranges" required placeholder="misal: 10.10.20.2-10.10.20.254" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                <p class="text-[10px] text-slate-400 mt-1">Format: <code>10.x.x.start-10.x.x.end</code></p>
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Keterangan (Opsional)</label>
                <input type="text" name="comment" placeholder="misal: Pool Khusus Kantor Cabang" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddPool')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl shadow-md shadow-emerald-500/20">Simpan Pool</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH PPP PROFILE -->
<div id="modalAddProfile" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="sliders" class="w-5 h-5 text-purple-500"></i>
                <span>Tambah PPP Profile Baru</span>
            </h3>
            <button onclick="closeModal('modalAddProfile')" class="p-1 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form id="formAddProfile" class="space-y-3.5 text-xs">
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Nama Profile *</label>
                <input type="text" name="name" required placeholder="misal: profile-<?= htmlspecialchars($currentUser['username']) ?>-10m" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Gateway (Local Address) *</label>
                    <input type="text" name="local_address" required placeholder="misal: 10.10.20.1" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Klien Pool (Remote) *</label>
                    <select name="remote_address" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        <?php foreach ($myPools as $pl): ?>
                            <option value="<?= htmlspecialchars($pl['name']) ?>"><?= htmlspecialchars($pl['name']) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($myPools)): ?>
                            <option value="pool-vpn-main">pool-vpn-main</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">DNS Server</label>
                    <input type="text" name="dns_server" value="8.8.8.8,1.1.1.1" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Limit Kecepatan</label>
                    <input type="text" name="rate_limit" placeholder="misal: 10M/10M" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Keterangan (Opsional)</label>
                <input type="text" name="comment" placeholder="misal: Profile Kecepatan 10 Mbps" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>
            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddProfile')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-semibold rounded-xl shadow-md shadow-purple-500/20">Simpan Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT USER VPN -->
<div id="modalEditSecret" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="edit-3" class="w-5 h-5 text-blue-500"></i>
                <span>Edit Akun VPN: <span id="editSecretName" class="text-blue-500"></span></span>
            </h3>
            <button onclick="closeModal('modalEditSecret')" class="p-1 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
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
const PUBLIC_SERVER_IP = "<?= htmlspecialchars($mainPublicIp) ?>";
const CONFIGURED_DOMAIN = "<?= htmlspecialchars($configuredDomain) ?>";
let activePopupHost = CONFIGURED_DOMAIN ? CONFIGURED_DOMAIN : PUBLIC_SERVER_IP;
let currentPopupUser = { name: '', pass: '', service: 'any' };

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// Clipboard Helper
function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('✅ Script berhasil disalin ke clipboard! Silakan paste di terminal MikroTik.');
    }).catch(() => {
        alert('Gagal menyalin');
    });
}

// Buka Modal Popup Script Klien untuk User Terpilih
function openClientScriptModal(name, pass, service) {
    currentPopupUser = { name, pass, service };
    document.getElementById('popupClientUser').innerText = name;
    renderPopupScripts();
    openModal('modalClientScript');
    lucide.createIcons();
}

function switchPopupHost(type) {
    const btnDomain = document.getElementById('btnUseDomain');
    const btnIp = document.getElementById('btnUseIp');

    if (type === 'domain') {
        activePopupHost = CONFIGURED_DOMAIN;
        if (btnDomain) btnDomain.className = "px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-600 text-white transition";
        if (btnIp) btnIp.className = "px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 transition";
    } else {
        activePopupHost = PUBLIC_SERVER_IP;
        if (btnDomain) btnDomain.className = "px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 transition";
        if (btnIp) btnIp.className = "px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-600 text-white transition";
    }
    renderPopupScripts();
}

function renderPopupScripts() {
    const name = currentPopupUser.name;
    const pass = currentPopupUser.pass;
    const host = activePopupHost;

    const sstpCmd = `/interface sstp-client add name="sstp-${name}" connect-to="${host}" user="${name}" password="${pass}" profile=default-encryption disabled=no comment="[VPN] Tunnel via ${host}"`;
    document.getElementById('popupSstpCode').innerText = sstpCmd;

    const l2tpCmd = `/interface l2tp-client add name="l2tp-${name}" connect-to="${host}" user="${name}" password="${pass}" ipsec-secret="rahasiavpn123" use-ipsec=yes profile=default-encryption disabled=no comment="[VPN] Tunnel via ${host}"`;
    document.getElementById('popupL2tpCode').innerText = l2tpCmd;
}

// Handler Ganti Tipe Host di Tab 3
function handleHostTypeChange() {
    const type = document.getElementById('genHostType').value;
    const hostInput = document.getElementById('genRemoteHost');
    if (type === 'domain') {
        hostInput.value = CONFIGURED_DOMAIN;
    } else if (type === 'ip') {
        hostInput.value = PUBLIC_SERVER_IP;
    }
    generateClientScript();
}

// Generator Script Klien Interaktif di Tab 3
function generateClientScript() {
    const userSelect = document.getElementById('genUserSelect');
    if (!userSelect || !userSelect.value) return;

    const username = userSelect.value;
    const host = document.getElementById('genRemoteHost').value || activePopupHost;
    const service = document.getElementById('genServiceSelect').value;

    let script = "";
    if (service === 'sstp') {
        script = `# ========================================================
# PASTE KE TERMINAL MIKROTIK KLIEN UNTUK KONEK SSTP
# Alamat Remote: ${host}
# ========================================================
/interface sstp-client add name="sstp-${username}" connect-to="${host}" user="${username}" password="PASSWORD_ANDA" profile=default-encryption disabled=no comment="[VPN] SSTP Tunnel via ${host}"
/ip route add dst-address=10.10.0.0/16 gateway="sstp-${username}" comment="Routing ke Subnet Server"`;
    } else {
        script = `# ========================================================
# PASTE KE TERMINAL MIKROTIK KLIEN UNTUK KONEK L2TP/IPSEC
# Alamat Remote: ${host}
# ========================================================
/interface l2tp-client add name="l2tp-${username}" connect-to="${host}" user="${username}" password="PASSWORD_ANDA" ipsec-secret="rahasiavpn123" use-ipsec=yes profile=default-encryption disabled=no comment="[VPN] L2TP Tunnel via ${host}"
/ip route add dst-address=10.10.0.0/16 gateway="l2tp-${username}" comment="Routing ke Subnet Server"`;
    }

    const pre = document.getElementById('clientScriptCode');
    if (pre) pre.innerText = script;
}

document.addEventListener('DOMContentLoaded', () => {
    generateClientScript();
});

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
    showLoading('Membuat Akun VPN...', 'Mendaftarkan secret ke MikroTik PPP...');
    const formData = new FormData(e.target);
    formData.append('action', 'add_secret');
    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal mengirim data');
    }
});

// Form Tambah Pool
document.getElementById('formAddPool').addEventListener('submit', async (e) => {
    e.preventDefault();
    showLoading('Membuat IP Pool...', 'Mendaftarkan range IP ke MikroTik...');
    const formData = new FormData(e.target);
    formData.append('action', 'add_pool');
    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal membuat pool');
    }
});

// Form Tambah Profile
document.getElementById('formAddProfile').addEventListener('submit', async (e) => {
    e.preventDefault();
    showLoading('Membuat PPP Profile...', 'Menyimpan profile ke MikroTik...');
    const formData = new FormData(e.target);
    formData.append('action', 'add_profile');
    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal membuat profile');
    }
});

// Buka Edit Modal
function openEditSecretModal(item) {
    document.getElementById('editSecretId').value = item['.id'];
    document.getElementById('editSecretName').innerText = item['name'];
    document.getElementById('editSecretRemote').value = item['remote-address'] || '';
    
    let comment = item['comment'] || '';
    comment = comment.replace(/\[owner:[a-zA-Z0-9_\-\.]+\]/gi, '').trim();
    document.getElementById('editSecretComment').value = comment;

    openModal('modalEditSecret');
}

// Form Edit User
document.getElementById('formEditSecret').addEventListener('submit', async (e) => {
    e.preventDefault();
    showLoading('Memperbarui User VPN...', 'Sinkronisasi perubahan ke MikroTik...');
    const formData = new FormData(e.target);
    formData.append('action', 'edit_secret');
    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal memperbarui user');
    }
});

// Toggle Status Aktif / Mati
async function toggleSecretStatus(id, disabled) {
    showLoading('Mengubah Status User...', 'Sinkronisasi dengan MikroTik...');
    const formData = new FormData();
    formData.append('action', 'toggle_secret');
    formData.append('id', id);
    formData.append('disabled', disabled);

    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal mengubah status user');
    }
}

// Hapus User
async function deleteSecret(id, name) {
    if (!confirm(`Hapus user '${name}'?`)) return;
    showLoading('Menghapus User VPN...', 'Menghapus secret dari MikroTik...');
    const formData = new FormData();
    formData.append('action', 'delete_secret');
    formData.append('id', id);

    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal menghapus user');
    }
}

// Hapus Pool
async function deletePool(id, name) {
    if (!confirm(`Hapus IP Pool '${name}'?`)) return;
    showLoading('Menghapus IP Pool...', 'Menghapus pool dari MikroTik...');
    const formData = new FormData();
    formData.append('action', 'delete_pool');
    formData.append('id', id);

    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal menghapus pool');
    }
}

// Hapus Profile
async function deleteProfile(id, name) {
    if (!confirm(`Hapus PPP Profile '${name}'?`)) return;
    showLoading('Menghapus PPP Profile...', 'Menghapus profile dari MikroTik...');
    const formData = new FormData();
    formData.append('action', 'delete_profile');
    formData.append('id', id);

    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal menghapus profile');
    }
}

// Kick Active Session
async function kickSession(id, name) {
    if (!confirm(`Putus paksa koneksi user '${name}' sekarang?`)) return;
    showLoading('Memutus Sesi VPN...', 'Memutuskan koneksi aktif di MikroTik...');
    const formData = new FormData();
    formData.append('action', 'kick_active');
    formData.append('id', id);
    formData.append('name', name);

    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal memutus sesi');
    }
}

// Save Server Settings (Super Admin)
async function saveServerSettings() {
    showLoading('Menyimpan Pengaturan Server...', 'Menerapkan server SSTP & L2TP di MikroTik...');
    const formData = new FormData();
    formData.append('action', 'save_server_settings');
    formData.append('sstp_enabled', document.getElementById('checkSstpEnabled').checked ? '1' : '0');
    formData.append('sstp_port', document.getElementById('inputSstpPort').value);
    formData.append('l2tp_enabled', document.getElementById('checkL2tpEnabled').checked ? '1' : '0');
    formData.append('l2tp_use_ipsec', document.getElementById('selectL2tpUseIpsec').value);
    formData.append('l2tp_ipsec_secret', document.getElementById('inputL2tpSecret').value);

    try {
        const res = await fetch('api/vpn_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal menyimpan pengaturan server');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
