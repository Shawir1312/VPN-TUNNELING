<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Helpers.php';
require_once __DIR__ . '/../includes/MikhmonManager.php';

$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$myUsername = strtolower($user['username']);
$myId = (int)$user['id'];

switch ($action) {
    // ==========================================
    // 1. BUAT APLIKASI MIKHMON BARU
    // ==========================================
    case 'create_instance':
        $appName = trim($_POST['app_name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $routerHost = trim($_POST['router_host'] ?? '');
        $routerPort = (int)($_POST['router_port'] ?? 8728);
        $routerUser = trim($_POST['router_user'] ?? '');
        $routerPass = $_POST['router_pass'] ?? '';
        $mikhmonUser = trim($_POST['mikhmon_user'] ?? 'admin');
        $mikhmonPass = $_POST['mikhmon_pass'] ?? '1234';
        $hotspotName = trim($_POST['hotspot_name'] ?? '');
        $dnsName = trim($_POST['dns_name'] ?? '');
        $currency = trim($_POST['currency'] ?? 'Rp');

        if (empty($appName) || empty($slug) || empty($routerHost) || empty($routerUser)) {
            echo json_encode(['success' => false, 'message' => 'Nama Aplikasi, Slug URL, Host IP, dan User MikroTik wajib diisi.']);
            exit;
        }

        $res = MikhmonManager::createInstance(
            $myId,
            $myUsername,
            $appName,
            $slug,
            $routerHost,
            $routerPort,
            $routerUser,
            $routerPass,
            $mikhmonUser ?: 'admin',
            $mikhmonPass ?: '1234',
            $hotspotName ?: $appName,
            $dnsName,
            $currency ?: 'Rp'
        );

        if ($res['success']) {
            Helpers::logAudit($myId, $myUsername, 'CREATE_MIKHMON', 'mikhmon_instance', [
                'app_name' => $appName,
                'slug' => $slug
            ]);
        }

        echo json_encode($res);
        break;

    // ==========================================
    // 2. EDIT APLIKASI MIKHMON
    // ==========================================
    case 'edit_instance':
        $instanceId = (int)($_POST['id'] ?? 0);
        $appName = trim($_POST['app_name'] ?? '');
        $routerHost = trim($_POST['router_host'] ?? '');
        $routerPort = (int)($_POST['router_port'] ?? 8728);
        $routerUser = trim($_POST['router_user'] ?? '');
        $routerPass = $_POST['router_pass'] ?? ''; // Jika kosong, pakai yang lama
        $mikhmonUser = trim($_POST['mikhmon_user'] ?? 'admin');
        $mikhmonPass = $_POST['mikhmon_pass'] ?? ''; // Jika kosong, pakai yang lama
        $hotspotName = trim($_POST['hotspot_name'] ?? '');
        $dnsName = trim($_POST['dns_name'] ?? '');
        $currency = trim($_POST['currency'] ?? 'Rp');

        if ($instanceId <= 0 || empty($appName) || empty($routerHost) || empty($routerUser)) {
            echo json_encode(['success' => false, 'message' => 'Semua kolom wajib diisi.']);
            exit;
        }

        $res = MikhmonManager::updateInstance(
            $instanceId,
            $myUsername,
            $appName,
            $routerHost,
            $routerPort,
            $routerUser,
            $routerPass,
            $mikhmonUser ?: 'admin',
            $mikhmonPass,
            $hotspotName ?: $appName,
            $dnsName,
            $currency ?: 'Rp'
        );

        if ($res['success']) {
            Helpers::logAudit($myId, $myUsername, 'UPDATE_MIKHMON', 'mikhmon_instance', [
                'id' => $instanceId,
                'app_name' => $appName
            ]);
        }

        echo json_encode($res);
        break;

    // ==========================================
    // 3. HAPUS APLIKASI MIKHMON
    // ==========================================
    case 'delete_instance':
        $instanceId = (int)($_POST['id'] ?? 0);
        if ($instanceId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID instance tidak valid']);
            exit;
        }

        $res = MikhmonManager::deleteInstance($instanceId, $myUsername);
        if ($res['success']) {
            Helpers::logAudit($myId, $myUsername, 'DELETE_MIKHMON', 'mikhmon_instance', ['id' => $instanceId]);
        }
        echo json_encode($res);
        break;

    // ==========================================
    // 4. TOGGLE STATUS AKTIF / NONAKTIF
    // ==========================================
    case 'toggle_instance':
        $instanceId = (int)($_POST['id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 1);
        $res = MikhmonManager::toggleStatus($instanceId, $myUsername, $isActive);
        echo json_encode($res);
        break;

    // ==========================================
    // 5. AMBIL DAFTAR USER VPN UNTUK DROPDOWN MODAL (ASYNC)
    // ==========================================
    case 'get_vpn_users':
        $myVpnUsers = [];
        $ros = Auth::getRouterConnection();
        if ($ros && $ros->connected) {
            $allSecrets = $ros->comm('/ppp/secret/print');
            $filteredSecrets = Helpers::filterByOwner($allSecrets, $myUsername);
            foreach ($filteredSecrets as $sec) {
                if (!empty($sec['remote-address'])) {
                    $myVpnUsers[] = [
                        'name' => $sec['name'] ?? '',
                        'ip' => $sec['remote-address']
                    ];
                }
            }
            $ros->disconnect();
        }
        echo json_encode(['success' => true, 'data' => $myVpnUsers]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
        break;
}

