<?php
$pageTitle = "Port Forwarding (Dst-NAT) - MikroTik Manager";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/Helpers.php';

$ros = Auth::getRouterConnection();
$routerOnline = ($ros && $ros->connected);

$natRules = [];
$myVpnUsers = [];
$vpnLocalIps = [];
$detectedPublicIp = '';

$configuredDomain = trim($routerProfile['domain_name'] ?? '');

// 1. Deteksi IP Publik nyata dari Domain jika diset
if (!empty($configuredDomain)) {
    $resolved = @gethostbyname($configuredDomain);
    if (filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $detectedPublicIp = $resolved;
    }
}

if ($routerOnline) {
    // 2. Cek Cloud Public IP dari MikroTik (/ip/cloud) jika belum terdeteksi dari domain
    if (empty($detectedPublicIp)) {
        $cloudInfo = $ros->comm('/ip/cloud/print');
        if (!empty($cloudInfo[0]['public-address'])) {
            $detectedPublicIp = trim($cloudInfo[0]['public-address']);
        }
    }

    // 3. Cek Host Router jika berupa IP Publik
    if (empty($detectedPublicIp) && !empty($routerProfile['host'])) {
        $hostIp = trim($routerProfile['host']);
        if (filter_var($hostIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $detectedPublicIp = $hostIp;
        }
    }

    // 4. Ambil gateway VPN dari /ppp/profile untuk blacklist IP pool lokal
    $rawProfiles = $ros->comm('/ppp/profile/print');
    foreach ($rawProfiles as $p) {
        if (!empty($p['local-address'])) {
            $vpnLocalIps[] = trim($p['local-address']);
        }
    }

    // 5. Ambil IP Pools untuk mendeteksi subnet pool lokal (misal: 70.70.70.1)
    $rawPools = $ros->comm('/ip/pool/print');
    foreach ($rawPools as $pl) {
        $ranges = $pl['ranges'] ?? '';
        if (preg_match('/^(\d+\.\d+\.\d+)\./', $ranges, $m)) {
            $vpnLocalIps[] = $m[1] . '.1';
        }
    }

    // 6. Ambil user VPN milik admin yang login untuk opsi target internal
    $allSecrets = $ros->comm('/ppp/secret/print');
    $filteredSecrets = Helpers::filterByOwner($allSecrets, $currentUser);
    foreach ($filteredSecrets as $sec) {
        if (!empty($sec['remote-address'])) {
            $myVpnUsers[] = [
                'name' => $sec['name'],
                'ip' => $sec['remote-address'],
                'service' => $sec['service'] ?? 'any'
            ];
        }
    }

    // 7. Ambil seluruh rule Dst-NAT & filter milik admin ini
    $allRules = $ros->comm('/ip/firewall/nat/print', ['?chain=dstnat']);
    $natRules = Helpers::filterByOwner($allRules, $currentUser);

    $ros->disconnect();
}

// Fallback jika belum terdeteksi sama sekali
if (empty($detectedPublicIp)) {
    $detectedPublicIp = $routerProfile['host'] ?? '103.182.98.100';
}

$defaultRemoteEndpoint = !empty($configuredDomain) ? $configuredDomain : $detectedPublicIp;
?>

<div class="space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="network" class="w-6 h-6 text-emerald-500"></i>
                <span>Port Forwarding (Dst-NAT)</span>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-0.5">
                Forward trafik dari IP Publik / Domain ke IP User VPN atau server lokal dengan port acak & template siap pakai.
            </p>
        </div>

        <button onclick="openModalAddNat()" class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-lg shadow-blue-500/20 transition">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>+ Tambah Dst-NAT</span>
        </button>
    </div>

    <!-- Alert Info Box -->
    <div class="bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/80 rounded-2xl p-4 text-xs text-blue-700 dark:text-blue-300 flex items-start gap-3">
        <i data-lucide="info" class="w-5 h-5 flex-shrink-0 text-blue-500"></i>
        <div class="space-y-1">
            <div class="font-semibold flex items-center gap-2">
                <span>Alamat IP Publik Terdeteksi:</span>
                <span class="px-2 py-0.5 rounded bg-blue-200 dark:bg-blue-900 text-blue-800 dark:text-blue-200 font-mono font-bold"><?= htmlspecialchars($detectedPublicIp) ?></span>
                <?php if (!empty($configuredDomain)): ?>
                    <span class="text-slate-500 font-normal">(Domain: <?= htmlspecialchars($configuredDomain) ?>)</span>
                <?php endif; ?>
            </div>
            <p class="text-blue-600 dark:text-blue-300/90 leading-relaxed">
                Untuk akses remote dari internet ke dalam, <strong>Dst. Address diisi dengan IP Publik MikroTik (<?= htmlspecialchars($detectedPublicIp) ?>)</strong> atau dibiarkan Otomatis. <em>Jangan menggunakan IP pool lokal VPN (seperti 70.70.70.1) agar tidak gagal koneksi.</em>
            </p>
        </div>
    </div>

    <!-- Rules Container -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm overflow-hidden">
        
        <div class="p-4 border-b border-slate-200 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs text-slate-500">
                Total <strong class="text-slate-800 dark:text-slate-200"><?= count($natRules) ?></strong> rule Dst-NAT milik akun Anda.
            </div>
            <div class="relative w-full sm:w-64">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input type="text" id="searchNatInput" placeholder="Cari port atau target IP..." class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
        </div>

        <!-- Desktop View Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200 dark:border-slate-700/80">
                    <tr>
                        <th class="py-3 px-4">Alamat Remote Publik (Akses Luar)</th>
                        <th class="py-3 px-4">Protokol</th>
                        <th class="py-3 px-4">Target Internal (IP User VPN:Port)</th>
                        <th class="py-3 px-4">Keterangan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60" id="natTableBody">
                    <?php if (empty($natRules)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                Belum ada rule Dst-NAT. Klik tombol "+ Tambah Dst-NAT" di atas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($natRules as $r): 
                            $isDisabled = ($r['disabled'] ?? 'false') === 'true';
                            $displayNote = Helpers::getDisplayComment($r['comment'] ?? '');
                            $publicPort = $r['dst-port'] ?? '80';
                            $dstAddr = $r['dst-address'] ?? '';
                            $fullRemoteAddress = "{$defaultRemoteEndpoint}:{$publicPort}";
                            
                            // Deteksi jika user salah setting dst-address ke IP Pool lokal
                            $isLocalPool = (!empty($dstAddr) && (in_array($dstAddr, $vpnLocalIps, true) || str_starts_with($dstAddr, '10.') || str_starts_with($dstAddr, '192.168.') || str_starts_with($dstAddr, '70.70.')));
                        ?>
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition">
                                <td class="py-3.5 px-4 font-mono">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($fullRemoteAddress) ?></span>
                                        <button onclick="copyText('<?= htmlspecialchars($fullRemoteAddress) ?>')" class="p-1 rounded text-slate-400 hover:text-blue-500 hover:bg-slate-100 dark:hover:bg-slate-700" title="Salin Alamat Remote">
                                            <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Indikator Dst IP Publik vs IP Pool Lokal -->
                                    <?php if ($isLocalPool): ?>
                                        <div class="text-[11px] text-rose-500 font-bold flex flex-wrap items-center gap-1.5 mt-1">
                                            <span>⚠️ Dst IP: <?= htmlspecialchars($dstAddr) ?> (IP Lokal!)</span>
                                            <button type="button" onclick='autoFixDstAddress(<?= json_encode($r) ?>)' class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-rose-100 hover:bg-rose-200 dark:bg-rose-950/70 dark:hover:bg-rose-900 text-rose-700 dark:text-rose-300 text-[10px] font-semibold border border-rose-300 dark:border-rose-700 cursor-pointer shadow-sm transition">
                                                <i data-lucide="wrench" class="w-3 h-3"></i>
                                                <span>Klik: Perbaiki Jadi IP Publik (<?= htmlspecialchars($detectedPublicIp) ?>)</span>
                                            </button>
                                        </div>
                                    <?php elseif (!empty($dstAddr)): ?>
                                        <div class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold mt-0.5 font-mono">
                                            Dst IP: <?= htmlspecialchars($dstAddr) ?> (IP Publik)
                                        </div>
                                    <?php else: ?>
                                        <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium mt-0.5">
                                            Dst IP: Otomatis (Semua Trafik IP Publik & Domain)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono uppercase text-[10px] font-semibold text-slate-600 dark:text-slate-300">
                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 font-bold"><?= htmlspecialchars($r['protocol'] ?? 'tcp') ?></span>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    <?= htmlspecialchars($r['to-addresses'] ?? '-') ?>:<?= htmlspecialchars($r['to-ports'] ?? '-') ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 max-w-xs truncate" title="<?= htmlspecialchars($displayNote) ?>">
                                    <?= htmlspecialchars($displayNote) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <button onclick="toggleNatStatus('<?= $r['.id'] ?>', '<?= $isDisabled ? 'no' : 'yes' ?>')" class="px-2.5 py-1 rounded-full text-[10px] font-semibold transition <?= $isDisabled ? 'bg-slate-100 dark:bg-slate-700 text-slate-500' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' ?>">
                                        <?= $isDisabled ? 'Nonaktif' : 'Aktif' ?>
                                    </button>
                                </td>
                                <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                    <!-- Tombol Edit Rule -->
                                    <button onclick='openEditNatModal(<?= json_encode($r) ?>)' class="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-slate-700" title="Edit Rule Dst-NAT">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                    <!-- Tombol Hapus -->
                                    <button onclick="deleteNat('<?= $r['.id'] ?>', '<?= htmlspecialchars($r['dst-port'] ?? '') ?>')" class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30" title="Hapus Rule">
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
        <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700/60" id="natCardsList">
            <?php if (empty($natRules)): ?>
                <div class="p-6 text-center text-xs text-slate-400">
                    Belum ada rule Dst-NAT.
                </div>
            <?php else: ?>
                <?php foreach ($natRules as $r): 
                    $isDisabled = ($r['disabled'] ?? 'false') === 'true';
                    $displayNote = Helpers::getDisplayComment($r['comment'] ?? '');
                    $publicPort = $r['dst-port'] ?? '80';
                    $dstAddr = $r['dst-address'] ?? '';
                    $fullRemoteAddress = "{$defaultRemoteEndpoint}:{$publicPort}";
                    $isLocalPool = (!empty($dstAddr) && (in_array($dstAddr, $vpnLocalIps, true) || str_starts_with($dstAddr, '10.') || str_starts_with($dstAddr, '192.168.') || str_starts_with($dstAddr, '70.70.')));
                ?>
                    <div class="p-4 space-y-2.5">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-bold text-sm text-slate-900 dark:text-white font-mono flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($fullRemoteAddress) ?></span>
                                    <button onclick="copyText('<?= htmlspecialchars($fullRemoteAddress) ?>')" class="text-blue-500"><i data-lucide="copy" class="w-3.5 h-3.5"></i></button>
                                </div>
                                <?php if ($isLocalPool): ?>
                                    <div class="text-[10px] text-rose-500 font-bold mt-1 space-y-1">
                                        <div>⚠️ Dst IP: <?= htmlspecialchars($dstAddr) ?> (IP Lokal!)</div>
                                        <button type="button" onclick='autoFixDstAddress(<?= json_encode($r) ?>)' class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-rose-100 hover:bg-rose-200 dark:bg-rose-950/70 text-rose-700 dark:text-rose-300 text-[10px] font-semibold border border-rose-300 dark:border-rose-700">
                                            <i data-lucide="wrench" class="w-3 h-3"></i>
                                            <span>Perbaiki Jadi IP Publik (<?= htmlspecialchars($detectedPublicIp) ?>)</span>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold font-mono mt-0.5">
                                        Dst IP: <?= empty($dstAddr) ? 'Otomatis (IP Publik)' : htmlspecialchars($dstAddr) ?>
                                    </div>
                                <?php endif; ?>
                                <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 mt-1 inline-block">
                                    <?= htmlspecialchars($r['protocol'] ?? 'tcp') ?>
                                </span>
                            </div>
                            <button onclick="toggleNatStatus('<?= $r['.id'] ?>', '<?= $isDisabled ? 'no' : 'yes' ?>')" class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $isDisabled ? 'bg-slate-200 dark:bg-slate-700 text-slate-500' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' ?>">
                                <?= $isDisabled ? 'Off' : 'Active' ?>
                            </button>
                        </div>

                        <div class="text-xs font-mono text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                            <span>&rarr; Target:</span>
                            <strong><?= htmlspecialchars($r['to-addresses'] ?? '-') ?>:<?= htmlspecialchars($r['to-ports'] ?? '-') ?></strong>
                        </div>

                        <div class="text-xs text-slate-500">
                            Note: <?= htmlspecialchars($displayNote) ?>
                        </div>

                        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/40 flex justify-end space-x-2">
                            <button onclick='openEditNatModal(<?= json_encode($r) ?>)' class="px-3 py-1 rounded-lg text-xs bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                Edit Rule
                            </button>
                            <button onclick="deleteNat('<?= $r['.id'] ?>', '<?= htmlspecialchars($r['dst-port'] ?? '') ?>')" class="px-3 py-1 rounded-lg text-xs bg-rose-50 dark:bg-rose-950/30 text-rose-600">
                                Hapus
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- ============================================================== -->
<!-- MODAL: TAMBAH DST-NAT -->
<!-- ============================================================== -->
<div id="modalAddNat" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-5 h-5 text-emerald-500"></i>
                <span>Tambah Port Forwarding (Dst-NAT)</span>
            </h3>
            <button onclick="closeModal('modalAddNat')" class="p-1 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <!-- Conflict Alert Box -->
        <div id="modalConflictAlert" class="hidden p-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 rounded-xl text-xs text-rose-700 dark:text-rose-400 flex items-start gap-2.5">
            <i data-lucide="alert-octagon" class="w-5 h-5 flex-shrink-0 text-rose-500 mt-0.5"></i>
            <div>
                <span class="font-bold">Konflik Port Terdeteksi!</span>
                <p id="modalConflictMessage" class="mt-0.5"></p>
            </div>
        </div>

        <form id="formAddNat" class="space-y-3.5 text-xs">
            
            <!-- IP Publik Target (PRE-FILLED DENGAN IP PUBLIK NYATA) -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-slate-600 dark:text-slate-300 font-semibold">
                        IP Publik MikroTik (Dst. Address)
                    </label>
                    <div class="space-x-1.5">
                        <button type="button" onclick="setDstAddressInput('addNatDstAddress', '<?= htmlspecialchars($detectedPublicIp) ?>')" class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold hover:underline">
                            📍 Set IP: <?= htmlspecialchars($detectedPublicIp) ?>
                        </button>
                        <button type="button" onclick="setDstAddressInput('addNatDstAddress', '')" class="text-[10px] text-slate-500 hover:underline">
                            (Kosongkan)
                        </button>
                    </div>
                </div>
                <input type="text" name="dst_address" id="addNatDstAddress" value="<?= htmlspecialchars($detectedPublicIp) ?>" placeholder="misal: <?= htmlspecialchars($detectedPublicIp) ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono font-semibold focus:outline-none focus:border-blue-500">
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                    ✓ Otomatis terisi dengan IP Publik MikroTik Anda (<strong><?= htmlspecialchars($detectedPublicIp) ?></strong>).
                </p>
            </div>

            <!-- Protocol & Public Port (DENGAN TOMBOL RANDOM PORT) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Protokol *</label>
                    <select name="protocol" id="addNatProtocol" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                    </select>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-slate-600 dark:text-slate-300 font-semibold">Port Publik (Akses Luar) *</label>
                        <button type="button" onclick="generateRandomPublicPort('addNatDstPort')" class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold hover:underline flex items-center gap-0.5">
                            <i data-lucide="dices" class="w-3 h-3"></i>
                            <span>Acak Port (Random)</span>
                        </button>
                    </div>
                    <input type="number" name="dst_port" id="addNatDstPort" required placeholder="Klik 'Acak Port' atau ketik" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
                </div>
            </div>

            <!-- Target Internal IP (DENGAN PILIHAN DARI USER VPN) -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-slate-600 dark:text-slate-300 font-semibold">Target Internal IP * (IP User VPN)</label>
                    <?php if (!empty($myVpnUsers)): ?>
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400">Pilih dari User VPN atau Manual</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($myVpnUsers)): ?>
                    <select onchange="handleSelectVpnUser(this, 'addNatToAddress')" class="w-full mb-2 bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-slate-800 dark:text-slate-200 font-mono text-[11px]">
                        <option value="">-- Pilih IP dari User VPN Anda --</option>
                        <?php foreach ($myVpnUsers as $vu): ?>
                            <option value="<?= htmlspecialchars($vu['ip']) ?>"><?= htmlspecialchars($vu['name']) ?> &rarr; <?= htmlspecialchars($vu['ip']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <input type="text" name="to_addresses" id="addNatToAddress" required placeholder="misal: 70.70.70.5 atau IP server lokal" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <!-- Target Internal Port (DENGAN TEMPLATE WEB, WINBOX, API, DLL) -->
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Port Internal * (Template Cepat)</label>
                
                <div class="flex flex-wrap gap-1.5 mb-2">
                    <button type="button" onclick="applyPortTemplate('8291', 'Winbox', 'addNatToPort', 'addNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                        📦 Winbox (8291)
                    </button>
                    <button type="button" onclick="applyPortTemplate('80', 'Web HTTP', 'addNatToPort', 'addNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                        🌐 Web (80)
                    </button>
                    <button type="button" onclick="applyPortTemplate('443', 'Web HTTPS', 'addNatToPort', 'addNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                        🔒 HTTPS (443)
                    </button>
                    <button type="button" onclick="applyPortTemplate('8728', 'API MikroTik', 'addNatToPort', 'addNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                        ⚙️ API (8728)
                    </button>
                    <button type="button" onclick="applyPortTemplate('22', 'SSH Terminal', 'addNatToPort', 'addNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                        💻 SSH (22)
                    </button>
                    <button type="button" onclick="applyPortTemplate('3389', 'RDP Desktop', 'addNatToPort', 'addNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                        🖥️ RDP (3389)
                    </button>
                </div>

                <input type="number" name="to_ports" id="addNatToPort" required placeholder="Nomor port internal (misal: 8090, 8291)" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <!-- Comment -->
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Keterangan / Catatan</label>
                <input type="text" name="comment" id="addNatComment" placeholder="misal: Akses Web Server Kantor" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddNat')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">Simpan Rule</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: EDIT DST-NAT (BISA MENGUBAH DST-ADDRESS KE IP PUBLIK / KOSONG) -->
<!-- ============================================================== -->
<div id="modalEditNat" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="edit-3" class="w-5 h-5 text-blue-500"></i>
                <span>Edit Rule Port Forwarding (Dst-NAT)</span>
            </h3>
            <button onclick="closeModal('modalEditNat')" class="p-1 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <div id="modalEditConflictAlert" class="hidden p-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 rounded-xl text-xs text-rose-700 dark:text-rose-400 flex items-start gap-2.5">
            <i data-lucide="alert-octagon" class="w-5 h-5 flex-shrink-0 text-rose-500 mt-0.5"></i>
            <div>
                <span class="font-bold">Konflik Port!</span>
                <p id="modalEditConflictMessage" class="mt-0.5"></p>
            </div>
        </div>

        <form id="formEditNat" class="space-y-3.5 text-xs">
            <input type="hidden" name="id" id="editNatId">

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-slate-600 dark:text-slate-300 font-semibold">
                        IP Publik MikroTik (Dst. Address)
                    </label>
                    <div class="space-x-1.5">
                        <button type="button" onclick="setDstAddressInput('editNatDstAddress', '<?= htmlspecialchars($detectedPublicIp) ?>')" class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold hover:underline">
                            📍 Pasang IP Publik: <?= htmlspecialchars($detectedPublicIp) ?>
                        </button>
                        <button type="button" onclick="setDstAddressInput('editNatDstAddress', '')" class="text-[10px] text-slate-500 hover:underline">
                            (Kosongkan)
                        </button>
                    </div>
                </div>
                <input type="text" name="dst_address" id="editNatDstAddress" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono font-semibold focus:outline-none focus:border-blue-500">
                <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1 font-medium">
                    ✓ Pastikan kolom ini berisi IP Publik nyata (<strong><?= htmlspecialchars($detectedPublicIp) ?></strong>) atau kosongkan, bukan IP pool lokal (seperti 70.70.70.1).
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Protokol *</label>
                    <select name="protocol" id="editNatProtocol" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100">
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                    </select>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-slate-600 dark:text-slate-300 font-semibold">Port Publik (Akses Luar) *</label>
                        <button type="button" onclick="generateRandomPublicPort('editNatDstPort')" class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold hover:underline flex items-center gap-0.5">
                            <i data-lucide="dices" class="w-3 h-3"></i>
                            <span>Acak Port</span>
                        </button>
                    </div>
                    <input type="number" name="dst_port" id="editNatDstPort" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-slate-600 dark:text-slate-300 font-semibold">Target Internal IP *</label>
                    <?php if (!empty($myVpnUsers)): ?>
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400">Pilih dari User VPN atau Ketik</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($myVpnUsers)): ?>
                    <select onchange="handleSelectVpnUser(this, 'editNatToAddress')" class="w-full mb-2 bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-slate-800 dark:text-slate-200 font-mono text-[11px]">
                        <option value="">-- Pilih IP dari User VPN Anda --</option>
                        <?php foreach ($myVpnUsers as $vu): ?>
                            <option value="<?= htmlspecialchars($vu['ip']) ?>"><?= htmlspecialchars($vu['name']) ?> &rarr; <?= htmlspecialchars($vu['ip']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <input type="text" name="to_addresses" id="editNatToAddress" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono">
            </div>

            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Port Internal * (Template Cepat)</label>
                <div class="flex flex-wrap gap-1.5 mb-2">
                    <button type="button" onclick="applyPortTemplate('8291', 'Winbox', 'editNatToPort', 'editNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-[10px] font-semibold">Winbox (8291)</button>
                    <button type="button" onclick="applyPortTemplate('80', 'Web HTTP', 'editNatToPort', 'editNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-[10px] font-semibold">Web (80)</button>
                    <button type="button" onclick="applyPortTemplate('443', 'Web HTTPS', 'editNatToPort', 'editNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-[10px] font-semibold">HTTPS (443)</button>
                    <button type="button" onclick="applyPortTemplate('8728', 'API MikroTik', 'editNatToPort', 'editNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-[10px] font-semibold">API (8728)</button>
                    <button type="button" onclick="applyPortTemplate('22', 'SSH', 'editNatToPort', 'editNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-[10px] font-semibold">SSH (22)</button>
                    <button type="button" onclick="applyPortTemplate('3389', 'RDP', 'editNatToPort', 'editNatComment')" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-[10px] font-semibold">RDP (3389)</button>
                </div>
                <input type="number" name="to_ports" id="editNatToPort" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 font-mono">
            </div>

            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Keterangan / Catatan</label>
                <input type="text" name="comment" id="editNatComment" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100">
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalEditNat')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
const DETECTED_PUBLIC_IP = "<?= htmlspecialchars($detectedPublicIp) ?>";

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function openModalAddNat() {
    document.getElementById('modalConflictAlert').classList.add('hidden');
    if (!document.getElementById('addNatDstPort').value) {
        generateRandomPublicPort('addNatDstPort');
    }
    // Pastikan terisi IP Publik
    if (!document.getElementById('addNatDstAddress').value) {
        document.getElementById('addNatDstAddress').value = DETECTED_PUBLIC_IP;
    }
    openModal('modalAddNat');
}

function setDstAddressInput(inputId, value) {
    document.getElementById(inputId).value = value;
}

function copyText(str) {
    navigator.clipboard.writeText(str).then(() => {
        alert(`✅ Alamat remote disalin: ${str}`);
    });
}

function generateRandomPublicPort(targetInputId) {
    const min = 10000;
    const max = 65000;
    const randomPort = Math.floor(Math.random() * (max - min + 1)) + min;
    document.getElementById(targetInputId).value = randomPort;
}

function handleSelectVpnUser(selectEl, targetInputId) {
    if (selectEl.value) {
        document.getElementById(targetInputId).value = selectEl.value;
    }
}

function applyPortTemplate(port, label, portInputId, commentInputId) {
    document.getElementById(portInputId).value = port;
    const commentInput = document.getElementById(commentInputId);
    if (!commentInput.value || commentInput.value.includes('Akses')) {
        commentInput.value = `Akses ${label}`;
    }
}

// Perbaiki Rule Otomatis ke IP Publik
function autoFixDstAddress(rule) {
    const port = rule['dst-port'] || '';
    const oldDst = rule['dst-address'] || '';
    if (!confirm(`Perbaiki rule port ${port}?\nDst Address akan diubah dari "${oldDst}" menjadi IP Publik "${DETECTED_PUBLIC_IP}".`)) {
        return;
    }
    const fd = new FormData();
    fd.append('action', 'edit_nat');
    fd.append('id', rule['.id']);
    fd.append('dst_address', DETECTED_PUBLIC_IP);
    fd.append('protocol', rule['protocol'] || 'tcp');
    fd.append('dst_port', port);
    fd.append('to_addresses', rule['to-addresses'] || '');
    fd.append('to_ports', rule['to-ports'] || '');
    let comment = rule['comment'] || '';
    comment = comment.replace(/\[owner:[a-zA-Z0-9_\-\.]+\]/gi, '').trim();
    fd.append('comment', comment);

    fetch('api/nat_action.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(`✅ Sukses! Rule port ${port} berhasil diubah ke IP Publik (${DETECTED_PUBLIC_IP}).\nSekarang siap diakses dari internet luar.`);
            location.reload();
        } else {
            alert('❌ Gagal memperbarui: ' + res.message);
        }
    })
    .catch(() => alert('Terjadi gangguan jaringan saat menghubungi API'));
}

// Buka Modal Edit Rule
function openEditNatModal(rule) {
    document.getElementById('editNatId').value = rule['.id'];
    
    // Jika rule sebelumnya berisi IP lokal/gateway pool (seperti 70.70.70.1), sarankan langsung ganti ke IP Publik
    let currDst = rule['dst-address'] || '';
    if (currDst.startsWith('70.70.') || currDst.startsWith('10.') || currDst.startsWith('192.168.')) {
        currDst = DETECTED_PUBLIC_IP;
    }
    document.getElementById('editNatDstAddress').value = currDst;

    document.getElementById('editNatProtocol').value = rule['protocol'] || 'tcp';
    document.getElementById('editNatDstPort').value = rule['dst-port'] || '';
    document.getElementById('editNatToAddress').value = rule['to-addresses'] || '';
    document.getElementById('editNatToPort').value = rule['to-ports'] || '';

    let comment = rule['comment'] || '';
    comment = comment.replace(/\[owner:[a-zA-Z0-9_\-\.]+\]/gi, '').trim();
    document.getElementById('editNatComment').value = comment;

    document.getElementById('modalEditConflictAlert').classList.add('hidden');
    openModal('modalEditNat');
    lucide.createIcons();
}

// Form Tambah Dst-NAT
document.getElementById('formAddNat').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'add_nat');

    const alertBox = document.getElementById('modalConflictAlert');
    const alertMsg = document.getElementById('modalConflictMessage');
    alertBox.classList.add('hidden');

    try {
        const res = await fetch('api/nat_action.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (res.status === 409 || json.conflict) {
            alertBox.classList.remove('hidden');
            alertMsg.innerHTML = json.message;
            lucide.createIcons();
            return;
        }

        alert(json.message);
        if (json.success) location.reload();
    } catch (err) {
        alert('Gagal mengirim data rule');
    }
});

// Form Edit Dst-NAT
document.getElementById('formEditNat').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'edit_nat');

    const alertBox = document.getElementById('modalEditConflictAlert');
    const alertMsg = document.getElementById('modalEditConflictMessage');
    alertBox.classList.add('hidden');

    try {
        const res = await fetch('api/nat_action.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (res.status === 409 || json.conflict) {
            alertBox.classList.remove('hidden');
            alertMsg.innerHTML = json.message;
            lucide.createIcons();
            return;
        }

        alert(json.message);
        if (json.success) location.reload();
    } catch (err) {
        alert('Gagal memperbarui rule');
    }
});

// Toggle Status NAT
async function toggleNatStatus(id, disabled) {
    const formData = new FormData();
    formData.append('action', 'toggle_nat');
    formData.append('id', id);
    formData.append('disabled', disabled);

    const res = await fetch('api/nat_action.php', { method: 'POST', body: formData });
    const json = await res.json();
    if (json.success) location.reload();
    else alert(json.message);
}

// Hapus Rule NAT
async function deleteNat(id, port) {
    if (!confirm(`Hapus port forwarding untuk port '${port}'?`)) return;

    const formData = new FormData();
    formData.append('action', 'delete_nat');
    formData.append('id', id);

    const res = await fetch('api/nat_action.php', { method: 'POST', body: formData });
    const json = await res.json();
    alert(json.message);
    if (json.success) location.reload();
}

// Search Filter
const searchInput = document.getElementById('searchNatInput');
if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        const val = e.target.value.toLowerCase();
        document.querySelectorAll('#natTableBody tr').forEach(tr => {
            tr.style.display = tr.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
        document.querySelectorAll('#natCardsList > div').forEach(card => {
            card.style.display = card.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
