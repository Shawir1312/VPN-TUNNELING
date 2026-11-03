<?php
$pageTitle = "Port Forwarding (Dst-NAT) - MikroTik Manager";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/Helpers.php';

$ros = Auth::getRouterConnection();
$routerOnline = ($ros && $ros->connected);

$natRules = [];
$publicIps = [];

if ($routerOnline) {
    // 1. Ambil daftar IP di Router untuk deteksi IP Publik
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

    // 2. Ambil seluruh rule Dst-NAT & filter by owner
    $allRules = $ros->comm('/ip/firewall/nat/print', ['?chain=dstnat']);
    $natRules = Helpers::filterByOwner($allRules, $currentUser);

    $ros->disconnect();
}
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
                Mengarahkan trafik dari IP Publik MikroTik ke server/klien VPN internal Anda.
            </p>
        </div>

        <button onclick="openModal('modalAddNat')" class="inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-lg shadow-blue-500/20 transition">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>Tambah Port Forward (Dst-NAT)</span>
        </button>
    </div>

    <!-- Alert Cross-Admin Conflict Info Box -->
    <div class="bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/80 rounded-2xl p-4 text-xs text-blue-700 dark:text-blue-300 flex items-start gap-3">
        <i data-lucide="info" class="w-5 h-5 flex-shrink-0 text-blue-500"></i>
        <div>
            <span class="font-semibold">Privasi Data & Pencegahan Tabrakan Port:</span>
            <p class="mt-0.5 text-blue-600 dark:text-blue-300/90 leading-relaxed">
                Anda hanya dapat melihat daftar port forward milik akun Anda. Jika port publik yang Anda daftarkan sudah terpakai oleh admin lain, sistem akan otomatis memberi tahu Anda nama admin pemilik port tersebut untuk menghindari bentrok lalu lintas jaringan.
            </p>
        </div>
    </div>

    <!-- Rules Container -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm overflow-hidden">
        
        <div class="p-4 border-b border-slate-200 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs text-slate-500">
                Total <strong class="text-slate-800 dark:text-slate-200"><?= count($natRules) ?></strong> rule Dst-NAT aktif milik Anda.
            </div>
            <div class="relative w-full sm:w-64">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input type="text" id="searchNatInput" placeholder="Cari port atau IP tujuan..." class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
        </div>

        <!-- Desktop View Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200 dark:border-slate-700/80">
                    <tr>
                        <th class="py-3 px-4">Port Publik (WAN)</th>
                        <th class="py-3 px-4">Protokol</th>
                        <th class="py-3 px-4">IP Publik Tujuan</th>
                        <th class="py-3 px-4">Target Internal (To Address:Port)</th>
                        <?php if ($currentUser['role'] === 'superadmin'): ?>
                            <th class="py-3 px-4">Owner</th>
                        <?php endif; ?>
                        <th class="py-3 px-4">Keterangan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60" id="natTableBody">
                    <?php if (empty($natRules)): ?>
                        <tr>
                            <td colspan="<?= $currentUser['role'] === 'superadmin' ? '8' : '7' ?>" class="py-12 text-center text-slate-400">
                                Belum ada rule Dst-NAT yang dibuat. Klik tombol "+ Tambah Port Forward" di atas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($natRules as $r): 
                            $isDisabled = ($r['disabled'] ?? 'false') === 'true';
                            $owner = Helpers::getResourceOwner($r['comment'] ?? '') ?: 'System';
                            $displayNote = Helpers::getDisplayComment($r['comment'] ?? '');
                        ?>
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition">
                                <td class="py-3.5 px-4 font-bold font-mono text-slate-900 dark:text-white">
                                    Port <?= htmlspecialchars($r['dst-port'] ?? '-') ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono uppercase text-[10px] font-semibold text-slate-600 dark:text-slate-300">
                                    <?= htmlspecialchars($r['protocol'] ?? 'tcp') ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-300">
                                    <?= htmlspecialchars($r['dst-address'] ?? 'Semua IP (Any)') ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    <?= htmlspecialchars($r['to-addresses'] ?? '-') ?>:<?= htmlspecialchars($r['to-ports'] ?? '-') ?>
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
                                    <button onclick="toggleNatStatus('<?= $r['.id'] ?>', '<?= $isDisabled ? 'no' : 'yes' ?>')" class="px-2.5 py-1 rounded-full text-[10px] font-semibold transition <?= $isDisabled ? 'bg-slate-100 dark:bg-slate-700 text-slate-500' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' ?>">
                                        <?= $isDisabled ? 'Nonaktif' : 'Aktif' ?>
                                    </button>
                                </td>
                                <td class="py-3.5 px-4 text-right">
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
                    $owner = Helpers::getResourceOwner($r['comment'] ?? '') ?: 'System';
                    $displayNote = Helpers::getDisplayComment($r['comment'] ?? '');
                ?>
                    <div class="p-4 space-y-2.5">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="font-bold text-sm text-slate-900 dark:text-white font-mono">
                                    Public Port: <?= htmlspecialchars($r['dst-port'] ?? '-') ?>
                                </span>
                                <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 ml-1">
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

                        <div class="text-xs text-slate-500 flex items-center justify-between">
                            <span>Note: <?= htmlspecialchars($displayNote) ?></span>
                            <?php if ($currentUser['role'] === 'superadmin'): ?>
                                <span class="text-[10px] px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 rounded-full"><?= htmlspecialchars($owner) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/40 flex justify-end">
                            <button onclick="deleteNat('<?= $r['.id'] ?>', '<?= htmlspecialchars($r['dst-port'] ?? '') ?>')" class="px-3 py-1 rounded-lg text-xs bg-rose-50 dark:bg-rose-950/30 text-rose-600">
                                Hapus Rule
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Modal: Tambah Dst-NAT -->
<div id="modalAddNat" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl max-w-lg w-full p-6 space-y-4">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-5 h-5 text-emerald-500"></i>
                <span>Tambah Port Forwarding (Dst-NAT)</span>
            </h3>
            <button onclick="closeModal('modalAddNat')" class="p-1 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Interactive Conflict Alert Placeholder inside Modal -->
        <div id="modalConflictAlert" class="hidden p-3.5 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 rounded-xl text-xs text-rose-700 dark:text-rose-400 flex items-start gap-2.5">
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
                <div class="flex gap-2">
                    <select name="dst_address_select" id="dstAddressSelect" onchange="handleSelectIp(this)" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        <option value="">Semua IP (Any IP)</option>
                        <?php foreach ($publicIps as $p): ?>
                            <option value="<?= htmlspecialchars($p['ip']) ?>"><?= htmlspecialchars($p['ip']) ?> (<?= htmlspecialchars($p['interface']) ?>)</option>
                        <?php endforeach; ?>
                        <option value="custom">Input IP Manual...</option>
                    </select>
                </div>
                <input type="text" name="dst_address" id="dstAddressCustom" placeholder="Masukkan IP Publik manual..." class="hidden mt-2 w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>

            <!-- Protocol & Public Port -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Protokol *</label>
                    <select name="protocol" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Port Publik (Dst. Port) *</label>
                    <input type="number" name="dst_port" required placeholder="misal: 8080" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <!-- Target Internal IP & Port -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">IP Target (To Address) *</label>
                    <input type="text" name="to_addresses" required placeholder="misal: 10.10.0.5" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Port Target (To Port) *</label>
                    <input type="number" name="to_ports" required placeholder="misal: 80" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <!-- Comment -->
            <div>
                <label class="block text-slate-600 dark:text-slate-300 font-semibold mb-1">Keterangan / Catatan</label>
                <input type="text" name="comment" placeholder="misal: Web Server Sales Kantor" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500">
            </div>

            <div class="pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddNat')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl">Batal</button>
                <button type="submit" id="btnSubmitNat" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl shadow-md shadow-blue-500/20">Simpan Rule</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.getElementById('modalConflictAlert').classList.add('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// Buka otomatis jika ada query parameter ?open=create
if (new URLSearchParams(window.location.search).get('open') === 'create') {
    openModal('modalAddNat');
}

function handleSelectIp(sel) {
    const custom = document.getElementById('dstAddressCustom');
    if (sel.value === 'custom') {
        custom.classList.remove('hidden');
        custom.focus();
    } else {
        custom.classList.add('hidden');
        custom.value = sel.value;
    }
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

// Form Add NAT Submission (With Cross-Admin Conflict Detection)
document.getElementById('formAddNat').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'add_nat');

    // Jika custom IP terisi
    const customIp = document.getElementById('dstAddressCustom').value;
    const selectIp = document.getElementById('dstAddressSelect').value;
    formData.set('dst_address', selectIp === 'custom' ? customIp : selectIp);

    const alertBox = document.getElementById('modalConflictAlert');
    const alertMsg = document.getElementById('modalConflictMessage');
    alertBox.classList.add('hidden');

    try {
        const res = await fetch('api/nat_action.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (res.status === 409 || json.conflict) {
            // Tampilkan alert konflik port antar-admin
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

