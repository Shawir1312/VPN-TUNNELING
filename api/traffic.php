<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Helpers.php';

$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$profile = Auth::getRouterProfile();
$wanInterface = $_GET['interface'] ?? ($profile['wan_interface'] ?? 'ether1');

$ros = Auth::getRouterConnection();
if (!$ros || !$ros->connected) {
    echo json_encode([
        'online' => false,
        'error' => Auth::getLastRouterError() ?: 'Tidak dapat terhubung ke MikroTik API (Port 8728)'
    ]);
    exit;
}

// 1. Ambil Resource (CPU, RAM, Uptime)
$res = $ros->comm('/system/resource/print');
$resource = $res[0] ?? [];

$cpuLoad = isset($resource['cpu-load']) ? (int)$resource['cpu-load'] : 0;
$totalMem = isset($resource['total-memory']) ? (float)$resource['total-memory'] : 1;
$freeMem = isset($resource['free-memory']) ? (float)$resource['free-memory'] : 0;
$usedMem = max(0, $totalMem - $freeMem);
$memPercent = round(($usedMem / $totalMem) * 100);

// 2. Ambil Traffic WAN
$traffic = $ros->comm('/interface/monitor-traffic', [
    '=interface=' . $wanInterface,
    '=once=true'
]);

$rxBps = 0;
$txBps = 0;
if (!empty($traffic[0])) {
    $rxBps = (float)($traffic[0]['rx-bits-per-second'] ?? 0);
    $txBps = (float)($traffic[0]['tx-bits-per-second'] ?? 0);
}

$ros->disconnect();

echo json_encode([
    'online' => true,
    'cpu' => $cpuLoad,
    'memory_used' => Helpers::formatBytes($usedMem),
    'memory_total' => Helpers::formatBytes($totalMem),
    'memory_percent' => $memPercent,
    'uptime' => $resource['uptime'] ?? '-',
    'board_name' => $resource['board-name'] ?? ($resource['platform'] ?? 'MikroTik'),
    'version' => $resource['version'] ?? '',
    'rx_bps' => $rxBps,
    'tx_bps' => $txBps,
    'rx_formatted' => Helpers::formatBps($rxBps),
    'tx_formatted' => Helpers::formatBps($txBps),
    'wan_interface' => $wanInterface,
    'timestamp' => date('H:i:s')
]);

