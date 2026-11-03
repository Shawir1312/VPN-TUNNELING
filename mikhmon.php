<?php
$pageTitle = 'Mikhmon Online - MikroTik Manager';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/MikhmonManager.php';

$instances = MikhmonManager::getInstances((int)$currentUser['id'], $currentUser['username']);

// Ambil domain atau host untuk generate full URL
$configuredDomain = trim($routerProfile['domain_name'] ?? '');
$serverHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseHost = !empty($configuredDomain) ? $configuredDomain : $serverHost;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

// Cek apakah master template tersedia
$templateReady = is_dir(MikhmonManager::getTemplateDir());
?>

<div class="space-y-6">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-amber-500 to-rose-500 text-white flex items-center justify-center shadow-md shadow-rose-500/20">
                    <i data-lucide="flame" class="w-5 h-5"></i>
                </div>
                <span>Mikhmon Online Generator</span>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-0.5">
                Buat dan kelola aplikasi Mikhmon V3 mandiri dengan link khusus per-admin untuk cetak voucher hotspot.
            </p>
        </div>
        <button onclick="openModalAddMikhmon()" class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-lg shadow-blue-500/20 transition">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>+ Buat Mikhmon Baru</span>
        </button>
    </div>

    <!-- Alert Template Status -->
    <?php if (!$templateReady): ?>
        <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-2xl text-xs text-rose-700 dark:text-rose-300 flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 text-rose-500"></i>
            <div>
                <span class="font-bold">Template Master Mikhmon Belum Tersedia!</span>
                <p class="mt-0.5 text-[11px]">Pastikan folder <code>mikhmonv3-master/</code> berada di direktori aplikasi Anda.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-4 shadow-sm flex items-center space-x-3.5">
            <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                <i data-lucide="layers" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Aplikasi Anda</div>
                <div class="text-xl font-bold text-slate-900 dark:text-white"><?= count($instances) ?></div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-4 shadow-sm flex items-center space-x-3.5">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                <i data-lucide="check-circle-2" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status Aktif</div>
                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                    <?= count(array_filter($instances, fn($i) => (int)$i['is_active'] === 1)) ?>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 p-4 shadow-sm flex items-center space-x-3.5">
            <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                <i data-lucide="sparkles" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Engine Mikhmon</div>
                <div class="text-sm font-bold text-purple-600 dark:text-purple-400">Mikhmon V3 (Isolated)</div>
            </div>
        </div>
    </div>

    <!-- Instances Container -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm overflow-hidden">
        
        <div class="p-4 border-b border-slate-200 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs text-slate-500">
                Menampilkan <strong class="text-slate-800 dark:text-slate-200"><?= count($instances) ?></strong> aplikasi Mikhmon milik akun Anda.
            </div>
            <div class="relative w-full sm:w-64">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input type="text" id="searchMikhmonInput" placeholder="Cari nama atau URL..." class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
        </div>

        <!-- Desktop View Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200 dark:border-slate-700/80">
                    <tr>
                        <th class="py-3 px-4">Nama Aplikasi &amp; Link URL</th>
                        <th class="py-3 px-4">Login Mikhmon</th>
                        <th class="py-3 px-4">Target Router MikroTik</th>
                        <th class="py-3 px-4">Nama Hotspot</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60" id="mikhmonTableBody">
                    <?php if (empty($instances)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                <span>Belum ada aplikasi Mikhmon. Klik <strong>"+ Buat Mikhmon Baru"</strong> untuk membuat instance pertama Anda.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($instances as $inst): 
                            $slug = $inst['slug'];
                            $fullUrl = "{$protocol}://{$baseHost}/{$slug}";
                            $isActive = (int)$inst['is_active'] === 1;
                            $mPass = Helpers::decrypt($inst['mikhmon_pass_encrypted']);
                        ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition">
                                <td class="py-3.5 px-4 font-mono">
                                    <div class="font-bold text-slate-900 dark:text-white font-sans text-sm mb-0.5">
                                        <?= htmlspecialchars($inst['app_name']) ?>
                                    </div>
                                    <div class="flex items-center space-x-1.5 text-blue-600 dark:text-blue-400 text-xs">
                                        <a href="<?= htmlspecialchars($fullUrl) ?>" target="_blank" class="hover:underline flex items-center gap-1">
                                            <span>/<?= htmlspecialchars($slug) ?></span>
                                            <i data-lucide="external-link" class="w-3 h-3"></i>
                                        </a>
                                        <button onclick="copyText('<?= htmlspecialchars($fullUrl) ?>')" class="p-1 rounded text-slate-400 hover:text-blue-500 hover:bg-slate-100 dark:hover:bg-slate-700" title="Salin URL Lengkap">
                                            <i data-lucide="copy" class="w-3 h-3"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono">
                                    <div class="text-slate-800 dark:text-slate-200">
                                        User: <strong class="text-blue-600 dark:text-blue-400"><?= htmlspecialchars($inst['mikhmon_user']) ?></strong>
                                    </div>
                                    <div class="text-slate-500 text-[11px] flex items-center gap-1 mt-0.5">
                                        <span>Pass:</span>
                                        <span class="font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($mPass) ?></span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">
                                        <?= htmlspecialchars($inst['router_host']) ?>:<?= htmlspecialchars($inst['router_port']) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        User API: <?= htmlspecialchars($inst['router_user']) ?>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-medium text-slate-800 dark:text-slate-200">
                                        <?= htmlspecialchars($inst['hotspot_name'] ?: '-') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        <?= htmlspecialchars($inst['dns_name'] ?: '') ?>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <button onclick="toggleMikhmonStatus(<?= $inst['id'] ?>, <?= $isActive ? 0 : 1 ?>)" class="px-2.5 py-1 rounded-full text-[10px] font-semibold transition <?= $isActive ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' ?>">
                                        <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                                    </button>
                                </td>
                                <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                    <a href="<?= htmlspecialchars($fullUrl) ?>" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 hover:bg-blue-100 font-medium">
                                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                        <span>Buka</span>
                                    </a>
                                    <button onclick='openEditMikhmonModal(<?= json_encode($inst) ?>)' class="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-slate-700" title="Edit Pengaturan">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="deleteMikhmon(<?= $inst['id'] ?>, '<?= htmlspecialchars($slug) ?>')" class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30" title="Hapus Aplikasi">
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
        <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700/60" id="mikhmonCardsList">
            <?php if (empty($instances)): ?>
                <div class="p-6 text-center text-xs text-slate-400">
                    Belum ada aplikasi Mikhmon. Klik "+ Buat Mikhmon Baru".
                </div>
            <?php else: ?>
                <?php foreach ($instances as $inst): 
                    $slug = $inst['slug'];
                    $fullUrl = "{$protocol}://{$baseHost}/{$slug}";
                    $isActive = (int)$inst['is_active'] === 1;
                    $mPass = Helpers::decrypt($inst['mikhmon_pass_encrypted']);
                ?>
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-bold text-slate-900 dark:text-white text-sm">
                                    <?= htmlspecialchars($inst['app_name']) ?>
                                </h4>
                                <div class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400 text-xs font-mono mt-0.5">
                                    <a href="<?= htmlspecialchars($fullUrl) ?>" target="_blank" class="hover:underline flex items-center gap-1">
                                        <span>/<?= htmlspecialchars($slug) ?></span>
                                        <i data-lucide="external-link" class="w-3 h-3"></i>
                                    </a>
                                    <button onclick="copyText('<?= htmlspecialchars($fullUrl) ?>')"><i data-lucide="copy" class="w-3 h-3 text-slate-400"></i></button>
                                </div>
                            </div>
                            <button onclick="toggleMikhmonStatus(<?= $inst['id'] ?>, <?= $isActive ? 0 : 1 ?>)" class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $isActive ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-200 dark:bg-slate-700 text-slate-500' ?>">
                                <?= $isActive ? 'Aktif' : 'Off' ?>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 dark:bg-slate-900/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700/60 font-mono">
                            <div>
                                <span class="text-[10px] text-slate-400 block font-sans">Login Mikhmon:</span>
                                <strong><?= htmlspecialchars($inst['mikhmon_user']) ?></strong> / <?= htmlspecialchars($mPass) ?>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 block font-sans">Target Router:</span>
                                <strong><?= htmlspecialchars($inst['router_host']) ?>:<?= htmlspecialchars($inst['router_port']) ?></strong>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <span class="text-xs text-slate-500"><?= htmlspecialchars($inst['hotspot_name'] ?: 'Hotspot') ?></span>
                            <div class="space-x-1">
                                <a href="<?= htmlspecialchars($fullUrl) ?>" target="_blank" class="px-3 py-1 rounded-lg text-xs bg-blue-600 text-white font-medium inline-flex items-center gap-1">
                                    <i data-lucide="external-link" class="w-3 h-3"></i>
                                    <span>Buka</span>
                                </a>
                                <button onclick='openEditMikhmonModal(<?= json_encode($inst) ?>)' class="px-2.5 py-1 rounded-lg text-xs bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                    Edit
                                </button>
                                <button onclick="deleteMikhmon(<?= $inst['id'] ?>, '<?= htmlspecialchars($slug) ?>')" class="px-2 py-1 rounded-lg text-xs bg-rose-50 dark:bg-rose-950/30 text-rose-600">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- ============================================================== -->
<!-- MODAL: BUAT APLIKASI MIKHMON BARU -->
<!-- ============================================================== -->
<div id="modalAddMikhmon" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-5 h-5 text-blue-500"></i>
                <span>Buat Aplikasi Mikhmon Baru</span>
            </h3>
            <button onclick="closeModal('modalAddMikhmon')" class="p-1 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form id="formAddMikhmon" class="space-y-3.5 text-xs">
            
            <!-- Nama Aplikasi & Slug URL -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Nama Aplikasi / Hotspot *</label>
                    <input type="text" name="app_name" id="addAppName" required placeholder="misal: Hotspot Alan Net" oninput="autoGenerateSlug(this.value, 'addSlug')" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">URL Path (Slug) *</label>
                    <input type="text" name="slug" id="addSlug" required placeholder="misal: alanmikhon" pattern="[a-z0-9_-]{3,32}" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <!-- Preview Link URL -->
            <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-blue-700 dark:text-blue-300 text-[11px] font-mono flex items-center gap-1.5">
                <i data-lucide="link" class="w-4 h-4 flex-shrink-0"></i>
                <span>Link Akses: <strong><?= $protocol ?>://<?= htmlspecialchars($baseHost) ?>/<span id="previewSlug">alanmikhon</span></strong></span>
            </div>

            <!-- Opsi Target Router (Bisa Pilih dari User VPN) -->
            <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <i data-lucide="router" class="w-4 h-4 text-emerald-500"></i>
                        <span>Koneksi MikroTik RouterOS</span>
                    </span>
                    <span id="vpnUsersStatusIndicator" class="text-[10px] text-slate-400">Manual / Pilih VPN</span>
                </div>

                <div id="vpnRouterContainer" class="hidden mb-2">
                    <select id="selectVpnRouter" onchange="handleSelectVpnRouter(this, 'addRouterHost')" class="w-full bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-slate-800 dark:text-slate-200 font-mono text-[11px]">
                        <option value="">-- Pilih dari User VPN Router Remote Anda --</option>
                    </select>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-2">
                        <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Host / IP Router *</label>
                        <input type="text" name="router_host" id="addRouterHost" value="<?= htmlspecialchars($routerProfile['host'] ?? '103.182.98.100') ?>" required placeholder="e.g. 103.182.98.100 atau IP VPN" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Port API *</label>
                        <input type="number" name="router_port" id="addRouterPort" value="<?= htmlspecialchars($routerProfile['api_port'] ?? '8728') ?>" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 mt-2">
                    <div>
                        <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">User API MikroTik *</label>
                        <input type="text" name="router_user" value="admin" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Password API MikroTik</label>
                        <input type="password" name="router_pass" placeholder="Password Winbox/Router" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 mt-2">
                    <div>
                        <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Nama Hotspot</label>
                        <input type="text" name="hotspot_name" placeholder="misal: Hotspot Kantor" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">DNS Name Hotspot</label>
                        <input type="text" name="dns_name" placeholder="misal: hotspot.net" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-blue-500">
                    </div>
                </div>
            </div>

            <!-- Kredensial Login Mikhmon -->
            <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                <span class="font-bold text-slate-700 dark:text-slate-300 block mb-1.5 flex items-center gap-1.5">
                    <i data-lucide="key" class="w-4 h-4 text-amber-500"></i>
                    <span>Kredensial Login Mikhmon (Untuk Admin / Klien Anda)</span>
                </span>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Username Mikhmon</label>
                        <input type="text" name="mikhmon_user" value="admin" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Password Mikhmon</label>
                        <input type="text" name="mikhmon_pass" value="1234" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
                    </div>
                </div>
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddMikhmon')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">Buat Aplikasi Mikhmon</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: EDIT MIKHMON -->
<!-- ============================================================== -->
<div id="modalEditMikhmon" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="edit-3" class="w-5 h-5 text-blue-500"></i>
                <span>Edit Pengaturan Mikhmon</span>
            </h3>
            <button onclick="closeModal('modalEditMikhmon')" class="p-1 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form id="formEditMikhmon" class="space-y-3.5 text-xs">
            <input type="hidden" name="id" id="editMikhmonId">

            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Nama Aplikasi *</label>
                <input type="text" name="app_name" id="editMikhmonAppName" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div class="col-span-2">
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Host / IP MikroTik *</label>
                    <input type="text" name="router_host" id="editMikhmonHost" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Port API *</label>
                    <input type="number" name="router_port" id="editMikhmonPort" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">User API MikroTik *</label>
                    <input type="text" name="router_user" id="editMikhmonUser" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Ganti Password Router</label>
                    <input type="password" name="router_pass" id="editMikhmonPass" placeholder="Kosongkan jika tetap" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Nama Hotspot</label>
                    <input type="text" name="hotspot_name" id="editMikhmonHotspot" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">DNS Name</label>
                    <input type="text" name="dns_name" id="editMikhmonDns" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">User Login Mikhmon</label>
                    <input type="text" name="mikhmon_user" id="editMikhmonAdmUser" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Ganti Pass Mikhmon</label>
                    <input type="text" name="mikhmon_pass" id="editMikhmonAdmPass" placeholder="Kosongkan jika tetap" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
                </div>
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalEditMikhmon')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl shadow-md shadow-emerald-500/20">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

let vpnUsersLoaded = false;
async function loadVpnUsers() {
    if (vpnUsersLoaded) return;
    const select = document.getElementById('selectVpnRouter');
    const container = document.getElementById('vpnRouterContainer');
    const indicator = document.getElementById('vpnUsersStatusIndicator');
    if (!select || !container) return;

    if (indicator) indicator.innerText = 'Mengecek VPN...';
    try {
        const res = await fetch('api/mikhmon_action.php?action=get_vpn_users');
        const json = await res.json();
        if (json.success && Array.isArray(json.data) && json.data.length > 0) {
            select.innerHTML = '<option value="">-- Pilih dari User VPN Router Remote Anda --</option>';
            json.data.forEach(vu => {
                const opt = document.createElement('option');
                opt.value = vu.ip;
                opt.textContent = `${vu.name} → ${vu.ip}`;
                select.appendChild(opt);
            });
            container.classList.remove('hidden');
            if (indicator) {
                indicator.innerText = `${json.data.length} User VPN Terdeteksi`;
                indicator.className = 'text-[10px] text-emerald-600 dark:text-emerald-400 font-medium';
            }
            vpnUsersLoaded = true;
        } else {
            if (indicator) indicator.innerText = 'Input Manual';
        }
    } catch (e) {
        if (indicator) indicator.innerText = 'Input Manual';
    }
}

function openModalAddMikhmon() {
    openModal('modalAddMikhmon');
    loadVpnUsers();
}

function autoGenerateSlug(name, targetId) {
    const slugInput = document.getElementById(targetId);
    let s = name.toLowerCase().replace(/[^a-z0-9]/g, '');
    if (s.length > 20) s = s.substring(0, 20);
    slugInput.value = s;
    updateSlugPreview(s);
}

document.getElementById('addSlug').addEventListener('input', (e) => {
    updateSlugPreview(e.target.value);
});

function updateSlugPreview(val) {
    const p = document.getElementById('previewSlug');
    if (p) p.innerText = val || 'nama-link';
}

function handleSelectVpnRouter(selectEl, targetInputId) {
    if (selectEl.value) {
        document.getElementById(targetInputId).value = selectEl.value;
    }
}

function copyText(str) {
    navigator.clipboard.writeText(str).then(() => {
        alert(`✅ Link Mikhmon disalin: ${str}`);
    });
}

// Buka Modal Edit Mikhmon
function openEditMikhmonModal(inst) {
    document.getElementById('editMikhmonId').value = inst.id;
    document.getElementById('editMikhmonAppName').value = inst.app_name;
    document.getElementById('editMikhmonHost').value = inst.router_host;
    document.getElementById('editMikhmonPort').value = inst.router_port;
    document.getElementById('editMikhmonUser').value = inst.router_user;
    document.getElementById('editMikhmonPass').value = '';
    document.getElementById('editMikhmonHotspot').value = inst.hotspot_name || '';
    document.getElementById('editMikhmonDns').value = inst.dns_name || '';
    document.getElementById('editMikhmonAdmUser').value = inst.mikhmon_user || 'admin';
    document.getElementById('editMikhmonAdmPass').value = '';

    openModal('modalEditMikhmon');
}

// Submit Tambah Mikhmon
document.getElementById('formAddMikhmon').addEventListener('submit', async (e) => {
    e.preventDefault();
    showLoading('Membuat Instance Mikhmon...', 'Menduplikasi engine Mikhmon & mengonfigurasi router...');

    const formData = new FormData(e.target);
    formData.append('action', 'create_instance');

    try {
        const res = await fetch('api/mikhmon_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert('❌ ' + json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Terjadi kesalahan saat membuat aplikasi Mikhmon');
    }
});

// Submit Edit Mikhmon
document.getElementById('formEditMikhmon').addEventListener('submit', async (e) => {
    e.preventDefault();
    showLoading('Menyimpan Perubahan...', 'Memperbarui konfigurasi Mikhmon...');

    const formData = new FormData(e.target);
    formData.append('action', 'edit_instance');

    try {
        const res = await fetch('api/mikhmon_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            hideLoading();
            alert('❌ ' + json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Terjadi kesalahan saat memperbarui Mikhmon');
    }
});

// Toggle Status Mikhmon
async function toggleMikhmonStatus(id, isActive) {
    showLoading('Mengubah Status...', 'Memperbarui status aplikasi Mikhmon...');
    const formData = new FormData();
    formData.append('action', 'toggle_instance');
    formData.append('id', id);
    formData.append('is_active', isActive);

    try {
        const res = await fetch('api/mikhmon_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) location.reload();
        else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal mengubah status');
    }
}

// Hapus Mikhmon
async function deleteMikhmon(id, slug) {
    if (!confirm(`Hapus aplikasi Mikhmon '/${slug}' secara permanen?\nFolder aplikasi dan semua voucher di dalamnya akan dihapus.`)) {
        return;
    }
    showLoading('Menghapus Aplikasi...', `Menghapus folder /${slug} dan database...`);

    const formData = new FormData();
    formData.append('action', 'delete_instance');
    formData.append('id', id);

    try {
        const res = await fetch('api/mikhmon_action.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) location.reload();
        else {
            hideLoading();
            alert(json.message);
        }
    } catch (err) {
        hideLoading();
        alert('Gagal menghapus aplikasi Mikhmon');
    }
}

// Filter Pencarian
const searchInput = document.getElementById('searchMikhmonInput');
if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        const val = e.target.value.toLowerCase();
        document.querySelectorAll('#mikhmonTableBody tr').forEach(tr => {
            tr.style.display = tr.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
        document.querySelectorAll('#mikhmonCardsList > div').forEach(card => {
            card.style.display = card.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

