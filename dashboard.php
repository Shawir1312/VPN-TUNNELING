<?php
$pageTitle = "Dashboard - MikroTik Manager";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/Helpers.php';

// Cek koneksi awal & data cepat
$ros = Auth::getRouterConnection();
$routerOnline = ($ros && $ros->connected);

$activeVpnCount = 0;
$myVpnCount = 0;
$myNatCount = 0;
$interfaces = [];

if ($routerOnline) {
    // Ambil daftar interface
    $ifaces = $ros->comm('/interface/print', ['.proplist=name,type,running']);
    foreach ($ifaces as $if) {
        if (!empty($if['name'])) {
            $interfaces[] = $if['name'];
        }
    }

    // Ambil PPP Secrets & filter by owner
    $secrets = $ros->comm('/ppp/secret/print', ['.proplist=.id,name,comment']);
    $mySecrets = Helpers::filterByOwner($secrets, $currentUser);
    $myVpnCount = count($mySecrets);

    // Ambil Active Connections (Hanya sesi milik akun admin yang login)
    $actives = $ros->comm('/ppp/active/print', ['.proplist=.id,name']);
    $mySecretNames = array_flip(array_column($mySecrets, 'name'));
    $count = 0;
    foreach ($actives as $act) {
        if (isset($mySecretNames[$act['name'] ?? ''])) {
            $count++;
        }
    }
    $activeVpnCount = $count;

    // Ambil Dst-NAT rules & filter by owner
    $natRules = $ros->comm('/ip/firewall/nat/print', ['?chain=dstnat']);
    $myNat = Helpers::filterByOwner($natRules, $currentUser);
    $myNatCount = count($myNat);

    $ros->disconnect();
}
?>

<div class="space-y-6">
    
    <!-- Page Header & Welcome -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>Dashboard Monitoring</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold <?= $currentUser['role'] === 'superadmin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' ?>">
                    <?= $currentUser['role'] === 'superadmin' ? 'Super Admin' : 'Sub-Admin' ?>
                </span>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-0.5">
                Halo, <span class="font-semibold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($currentUser['full_name']) ?></span>! Status performa MikroTik & ringkasan resource Anda.
            </p>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex items-center gap-2">
            <a href="vpn.php?open=create" class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl shadow-md shadow-blue-500/20 transition">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>User VPN Baru</span>
            </a>
            <a href="nat.php?open=create" class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-slate-800 hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-xl transition">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Dst-NAT Baru</span>
            </a>
        </div>
    </div>

    <!-- Alert if Router is Offline -->
    <div id="routerOfflineAlert" class="<?= $routerOnline ? 'hidden' : '' ?> bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 rounded-2xl p-4 text-rose-700 dark:text-rose-400 text-sm flex items-start gap-3">
        <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
        <div>
            <div class="font-semibold">Router MikroTik Tidak Terhubung!</div>
            <p class="text-xs mt-0.5 text-rose-600 dark:text-rose-300">
                Gagal menghubungi port 8728 pada host <strong><?= htmlspecialchars($routerProfile['host'] ?? '-') ?></strong>. Pastikan service API diaktifkan via Winbox menu <em>IP -> Services</em>.
            </p>
        </div>
    </div>

    <!-- Metric Cards Grid (CPU, RAM, Uptime, Active Sessions) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5">
        
        <!-- CPU Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/80 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Beban CPU</span>
                <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <i data-lucide="cpu" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="flex items-baseline space-x-2">
                <span id="metricCpu" class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">- %</span>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 dark:bg-slate-700 h-1.5 rounded-full mt-3 overflow-hidden">
                <div id="metricCpuBar" class="bg-blue-600 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>

        <!-- RAM Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/80 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Memori (RAM)</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <i data-lucide="hard-drive" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="flex items-baseline space-x-1">
                <span id="metricRam" class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white">- / -</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5" id="metricRamPercent">0% terpakai</div>
            <div class="w-full bg-slate-100 dark:bg-slate-700 h-1.5 rounded-full mt-2 overflow-hidden">
                <div id="metricRamBar" class="bg-emerald-500 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>

        <!-- Uptime Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/80 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Router Uptime</span>
                <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                </div>
            </div>
            <div id="metricUptime" class="text-base sm:text-lg font-bold text-slate-900 dark:text-white truncate">
                -
            </div>
            <div class="text-[11px] text-slate-400 mt-1 truncate" id="metricBoard">
                <?= htmlspecialchars($routerProfile['name'] ?? 'MikroTik') ?>
            </div>
        </div>

        <!-- Active VPN Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700/80 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Sesi VPN Aktif</span>
                <div class="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                    <i data-lucide="activity" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="flex items-baseline space-x-2">
                <span class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white"><?= $activeVpnCount ?></span>
                <span class="text-xs text-slate-400">klien</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-2">
                Total Akun Anda: <strong class="text-slate-700 dark:text-slate-200"><?= $myVpnCount ?></strong> secret
            </div>
        </div>

    </div>

    <!-- Live WAN Bandwidth Chart Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm p-4 sm:p-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="line-chart" class="w-5 h-5 text-blue-500"></i>
                    <span>Grafik Kecepatan Internet Real-Time (WAN)</span>
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Memantau throughput Download (RX) dan Upload (TX) setiap 2 detik.
                </p>
            </div>

            <!-- Interface Selector & Live Values -->
            <div class="flex items-center gap-3">
                <div class="flex items-center space-x-4 text-xs font-semibold">
                    <div class="flex items-center space-x-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>⬇️ RX: <span id="valRx">0 bps</span></span>
                    </div>
                    <div class="flex items-center space-x-1.5 text-blue-600 dark:text-blue-400">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                        <span>⬆️ TX: <span id="valTx">0 bps</span></span>
                    </div>
                </div>

                <!-- Interface Dropdown -->
                <select id="selectWanInterface" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs rounded-xl px-2.5 py-1.5 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500">
                    <?php if (!empty($interfaces)): ?>
                        <?php foreach ($interfaces as $ifName): ?>
                            <option value="<?= htmlspecialchars($ifName) ?>" <?= $ifName === ($routerProfile['wan_interface'] ?? 'ether1') ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ifName) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="<?= htmlspecialchars($routerProfile['wan_interface'] ?? 'ether1') ?>">
                            <?= htmlspecialchars($routerProfile['wan_interface'] ?? 'ether1') ?>
                        </option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <!-- Chart Canvas Container -->
        <div class="relative w-full h-64 sm:h-80">
            <canvas id="wanTrafficChart"></canvas>
        </div>

    </div>

    <!-- Quick Overview Tables (VPN & Dst-NAT Milik Admin) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Summary VPN Secret Milik Admin -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm p-4 sm:p-5">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                    <i data-lucide="shield" class="w-4 h-4 text-blue-500"></i>
                    <span>Akun VPN Saya (<?= $myVpnCount ?>)</span>
                </h4>
                <a href="vpn.php" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Kelola Semua &rarr;</a>
            </div>
            
            <?php if ($myVpnCount > 0): ?>
                <div class="space-y-2">
                    <?php foreach (array_slice($mySecrets, 0, 4) as $sec): ?>
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800 text-xs">
                            <div>
                                <span class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($sec['name'] ?? '-') ?></span>
                                <span class="text-slate-400 ml-1.5 uppercase font-mono text-[10px]">[<?= htmlspecialchars($sec['service'] ?? 'any') ?>]</span>
                            </div>
                            <span class="text-slate-500 dark:text-slate-400 font-mono"><?= htmlspecialchars($sec['remote-address'] ?? 'Pool/Dynamic') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-6 text-center text-xs text-slate-400">
                    Belum ada akun VPN yang Anda buat.
                </div>
            <?php endif; ?>
        </div>

        <!-- Summary Dst-NAT Milik Admin -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/80 shadow-sm p-4 sm:p-5">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                    <i data-lucide="network" class="w-4 h-4 text-emerald-500"></i>
                    <span>Dst-NAT Port Forward Saya (<?= $myNatCount ?>)</span>
                </h4>
                <a href="nat.php" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Kelola Semua &rarr;</a>
            </div>

            <?php if ($myNatCount > 0): ?>
                <div class="space-y-2">
                    <?php foreach (array_slice($myNat, 0, 4) as $nat): ?>
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800 text-xs">
                            <div>
                                <span class="font-mono font-semibold text-slate-800 dark:text-slate-100">Port <?= htmlspecialchars($nat['dst-port'] ?? '-') ?></span>
                                <span class="text-slate-400 ml-1 uppercase text-[10px]">(<?= htmlspecialchars($nat['protocol'] ?? 'tcp') ?>)</span>
                            </div>
                            <span class="text-slate-500 dark:text-slate-400 font-mono">&rarr; <?= htmlspecialchars($nat['to-addresses'] ?? '-') ?>:<?= htmlspecialchars($nat['to-ports'] ?? '-') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-6 text-center text-xs text-slate-400">
                    Belum ada rule Dst-NAT yang Anda pasang.
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Real-Time Polling & Chart.js Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('wanTrafficChart').getContext('2d');
    const selectIface = document.getElementById('selectWanInterface');

    const maxDataPoints = 20;
    const labels = Array(maxDataPoints).fill('');
    const rxData = Array(maxDataPoints).fill(0);
    const txData = Array(maxDataPoints).fill(0);

    const isDarkMode = document.documentElement.classList.contains('dark');
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.05)';
    const textColor = isDarkMode ? '#94a3b8' : '#64748b';

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Download (RX)',
                    data: rxData,
                    borderColor: '#10b981', // Emerald
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0
                },
                {
                    label: 'Upload (TX)',
                    data: txData,
                    borderColor: '#3b82f6', // Blue
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.raw;
                            if (val >= 1000000) return (val / 1000000).toFixed(2) + ' Mbps';
                            if (val >= 1000) return (val / 1000).toFixed(1) + ' Kbps';
                            return val + ' bps';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: gridColor },
                    ticks: { color: textColor, font: { size: 10 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        font: { size: 10 },
                        callback: function(value) {
                            if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                            if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
                            return value;
                        }
                    }
                }
            }
        }
    });

    // Function to poll backend every 2s
    async function updateTraffic() {
        const iface = selectIface ? selectIface.value : 'ether1';
        try {
            const res = await fetch(`api/traffic.php?interface=${encodeURIComponent(iface)}`);
            const data = await res.json();

            if (data.online) {
                document.getElementById('routerOfflineAlert').classList.add('hidden');
                
                // Update CPU
                document.getElementById('metricCpu').innerText = data.cpu + '%';
                const cpuBar = document.getElementById('metricCpuBar');
                cpuBar.style.width = data.cpu + '%';
                if (data.cpu > 80) cpuBar.className = 'bg-rose-500 h-full rounded-full transition-all';
                else if (data.cpu > 50) cpuBar.className = 'bg-amber-500 h-full rounded-full transition-all';
                else cpuBar.className = 'bg-blue-600 h-full rounded-full transition-all';

                // Update RAM
                document.getElementById('metricRam').innerText = `${data.memory_used} / ${data.memory_total}`;
                document.getElementById('metricRamPercent').innerText = `${data.memory_percent}% terpakai`;
                document.getElementById('metricRamBar').style.width = data.memory_percent + '%';

                // Update Uptime
                document.getElementById('metricUptime').innerText = data.uptime;
                document.getElementById('metricBoard').innerText = `${data.board_name} (ROS v${data.version})`;

                // Update Speed Text
                document.getElementById('valRx').innerText = data.rx_formatted;
                document.getElementById('valTx').innerText = data.tx_formatted;

                // Push to Chart
                labels.shift();
                labels.push(data.timestamp);
                rxData.shift();
                rxData.push(data.rx_bps);
                txData.shift();
                txData.push(data.tx_bps);

                chart.update();
            } else {
                document.getElementById('routerOfflineAlert').classList.remove('hidden');
            }
        } catch (e) {
            // Network fail
        }
    }

    // Jalankan segera dan set interval 2 detik
    updateTraffic();
    setInterval(updateTraffic, 2000);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

