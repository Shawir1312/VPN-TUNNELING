<?php
$pageTitle = "Port Forwarding (Dst-NAT) - MikroTik Manager";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/Helpers.php';

$ros = Auth::getRouterConnection();
$routerOnline = ($ros && $ros->connected);

$natRules = [];
$publicIps = [];
$myVpnUsers = [];

if ($routerOnline) {
    // 1. Ambil IP di Router untuk deteksi IP Publik
    $ipAddresses = $ros->comm('/ip/address/print', ['?disabled=no']);
    foreach ($ipAddresses as $addr) {
        $ipNet = explode('/', $addr['address'] ?? '')[0];
        if (!empty($ipNet)) {
            $publicIps[] = [
                'ip' => $ipNet,
                'interface' => $addr['interface'] ?? 'wan'
            ];
        }
    }

    // 2. Ambil user VPN milik admin yang login untuk opsi target internal
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

    // 3. Ambil seluruh rule Dst-NAT & filter milik admin ini
    $allRules = $ros->comm('/ip/firewall/nat/print', ['?chain=dstnat']);
    $natRules = Helpers::filterByOwner($allRules, $currentUser);

    $ros->disconnect();
}

$mainPublicIp = !empty($publicIps) ? $publicIps[0]['ip'] : ($routerProfile['host'] ?? 'IP_PUBLIK');
$configuredDomain = trim($routerProfile['domain_name'] ?? '');
$defaultRemoteEndpoint = !empty($configuredDomain) ? $configuredDomain : $mainPublicIp;
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
            <span class="font-semibold">Privasi Penuh & Alamat Remote Publik:</span>
            <p class="text-blue-600 dark:text-blue-300/90 leading-relaxed">
                Tiap admin hanya melihat rule port forward miliknya sendiri. Anda dapat menyalin alamat remote publik lengkap (<code><?= htmlspecialchars($defaultRemoteEndpoint) ?>:PORT</code>) untuk langsung mengakses perangkat dari internet.
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
                            $fullRemoteAddress = "{$defaultRemoteEndpoint}:{$publicPort}";
                        ?>
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition">
                                <td class="py-3.5 px-4 font-mono">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($fullRemoteAddress) ?></span>
                                        <button onclick="copyText('<?= htmlspecialchars($fullRemoteAddress) ?>')" class="p-1 rounded text-slate-400 hover:text-blue-500 hover:bg-slate-100 dark:hover:bg-slate-700" title="Salin Alamat Remote">
                                            <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        Dst IP: <?= htmlspecialchars($r['dst-address'] ?? 'Any (Semua IP)') ?>
                                    </div>
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
                    $fullRemoteAddress = "{$defaultRemoteEndpoint}:{$publicPort}";
                ?>
                    <div class="p-4 space-y-2.5">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-bold text-sm text-slate-900 dark:text-white font-mono flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($fullRemoteAddress) ?></span>
                                    <button onclick="copyText('<?= htmlspecialchars($fullRemoteAddress) ?>')" class="text-blue-500"><i data-lucide="copy" class="w-3.5 h-3.5"></i></button>
                                </div>
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
<!-- MODAL: TAMBAH DST-NAT (DENGAN RANDOM PORT & TEMPLATES) -->
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
            
            <!-- IP Publik Target -->
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">IP Publik MikroTik (Dst. Address)</label>
                <select name="dst_address" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                    <option value="">Semua IP (Any IP)</option>
                    <?php foreach ($publicIps as $p): ?>
                        <option value="<?= htmlspecialchars($p['ip']) ?>"><?= htmlspecialchars($p['ip']) ?> (<?= htmlspecialchars($p['interface']) ?>)</option>
                    <?php endforeach; ?>
                </select>
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
                        <label class="text-slate-600 dark:text-slate-300 font-semibold">Port Publik (WAN) *</label>
                        <button type="button" onclick="generateRandomPublicPort('addNatDstPort')" class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold hover:underline flex items-center gap-0.5">
                            <i data-lucide="dices" class="w-3 h-3"></i>
                            <span>Acak Port (Random)</span>
                        </button>
                    </div>
                    <div class="relative">
                        <input type="number" name="dst_port" id="addNatDstPort" required placeholder="Klik 'Acak Port' atau ketik" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
                    </div>
                </div>
            </div>

            <!-- Target Internal IP (DENGAN PILIHAN DARI USER VPN) -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-slate-600 dark:text-slate-300 font-semibold">Target Internal IP *</label>
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

                <input type="text" name="to_addresses" id="addNatToAddress" required placeholder="misal: 10.10.10.5 atau IP server lokal" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <!-- Target Internal Port (DENGAN TEMPLATE WEB, WINBOX, API, DLL) -->
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Port Internal * (Template Cepat)</label>
                
                <!-- Quick Preset Buttons -->
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

                <input type="number" name="to_ports" id="addNatToPort" required placeholder="Nomor port internal (misal: 80, 8291)" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <!-- Comment -->
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Keterangan / Catatan</label>
                <input type="text" name="comment" id="addNatComment" placeholder="misal: Akses Winbox Router Cabang" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddNat')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">Simpan Rule</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: EDIT DST-NAT (EDIT PORT PUBLIK, TARGET IP, TARGET PORT, DLL) -->
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
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">IP Publik MikroTik (Dst. Address)</label>
                <select name="dst_address" id="editNatDstAddress" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100">
                    <option value="">Semua IP (Any IP)</option>
                    <?php foreach ($publicIps as $p): ?>
                        <option value="<?= htmlspecialchars($p['ip']) ?>"><?= htmlspecialchars($p['ip']) ?> (<?= htmlspecialchars($p['interface']) ?>)</option>
                    <?php endforeach; ?>
                </select>
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
                        <label class="text-slate-600 dark:text-slate-300 font-semibold">Port Publik (WAN) *</label>
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
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function openModalAddNat() {
    document.getElementById('modalConflictAlert').classList.add('hidden');
    // Jika port belum ada, generate random port otomatis
    if (!document.getElementById('addNatDstPort').value) {
        generateRandomPublicPort('addNatDstPort');
    }
    openModal('modalAddNat');
}

// Helper Salin Teks
function copyText(str) {
    navigator.clipboard.writeText(str).then(() => {
        alert(`✅ Alamat remote disalin: ${str}`);
    });
}

// Generator Port Publik Acak (10000 - 65000)
function generateRandomPublicPort(targetInputId) {
    const min = 10000;
    const max = 65000;
    const randomPort = Math.floor(Math.random() * (max - min + 1)) + min;
    document.getElementById(targetInputId).value = randomPort;
}

// Handler Pilih User VPN
function handleSelectVpnUser(selectEl, targetInputId) {
    if (selectEl.value) {
        document.getElementById(targetInputId).value = selectEl.value;
    }
}

// Handler Template Port Internal
function applyPortTemplate(port, label, portInputId, commentInputId) {
    document.getElementById(portInputId).value = port;
    const commentInput = document.getElementById(commentInputId);
    if (!commentInput.value || commentInput.value.includes('Akses')) {
        commentInput.value = `Akses ${label}`;
    }
}

// Buka Modal Edit Rule
function openEditNatModal(rule) {
    document.getElementById('editNatId').value = rule['.id'];
    document.getElementById('editNatDstAddress').value = rule['dst-address'] || '';
    document.getElementById('editNatProtocol').value = rule['protocol'] || 'tcp';
    document.getElementById('editNatDstPort').value = rule['dst-port'] || '';
    document.getElementById('editNatToAddress').value = rule['to-addresses'] || '';
    document.getElementById('editNatToPort').value = rule['to-ports'] || '';

    // Bersihkan tag owner dari comment
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
