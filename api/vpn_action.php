<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Helpers.php';

$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// ==============================================================
// AKSI KHUSUS: TES KONEKSI & DIAGNOSTIK KE ROUTEROS
// ==============================================================
if ($action === 'test_connection') {
    $host = trim($_POST['host'] ?? '');
    $port = (int)($_POST['port'] ?? 8728);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Jika password kosong, coba gunakan password tersimpan di database
    if ($password === '') {
        $profile = Auth::getRouterProfile();
        if ($profile && !empty($profile['password_encrypted'])) {
            $password = Helpers::decrypt($profile['password_encrypted']);
        }
    }

    if (empty($host) || empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Host/IP MikroTik dan Username wajib diisi']);
        exit;
    }

    $testRos = new RouterOS();
    $ok = $testRos->connect($host, $username, $password, $port, 5);
    if ($ok) {
        $res = $testRos->comm('/system/resource/print');
        $board = $res[0]['board-name'] ?? ($res[0]['platform'] ?? 'MikroTik');
        $ver = $res[0]['version'] ?? '';
        $testRos->disconnect();
        echo json_encode([
            'success' => true,
            'message' => "Berhasil terhubung ke {$board} (RouterOS v{$ver})!"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $testRos->error ?: 'Gagal terhubung ke MikroTik API'
        ]);
    }
    exit;
}

$ros = Auth::getRouterConnection();
if (!$ros || !$ros->connected) {
    echo json_encode(['success' => false, 'message' => Auth::getLastRouterError() ?: 'Tidak dapat terhubung ke MikroTik API']);
    exit;
}

$myUsername = strtolower($user['username']);
$isSuperAdmin = ($user['role'] === 'superadmin');

switch ($action) {
    // ==========================================
    // 1. MANAJEMEN AKUN PPP SECRET
    // ==========================================
    case 'add_secret':
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $service = $_POST['service'] ?? 'any';
        $remoteAddress = trim($_POST['remote_address'] ?? '');
        $localAddress = trim($_POST['local_address'] ?? '');
        $profile = trim($_POST['profile'] ?? 'default');
        $customComment = trim($_POST['comment'] ?? '');

        if (empty($name) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username dan Password VPN wajib diisi']);
            break;
        }

        // Tagging kepemilikan admin
        $finalComment = Helpers::buildResourceComment($user['username'], $customComment);

        $params = [
            '=name=' . $name,
            '=password=' . $password,
            '=service=' . $service,
            '=profile=' . ($profile ?: 'default'),
            '=comment=' . $finalComment
        ];
        if (!empty($remoteAddress)) $params[] = '=remote-address=' . $remoteAddress;
        if (!empty($localAddress)) $params[] = '=local-address=' . $localAddress;

        $res = $ros->comm('/ppp/secret/add', $params);
        if (isset($res['!trap'])) {
            echo json_encode(['success' => false, 'message' => $res['!trap']['message'] ?? 'Gagal membuat user VPN']);
        } else {
            Helpers::logAudit($user['id'], $user['username'], 'CREATE_VPN_SECRET', 'ppp_secret', ['name' => $name, 'service' => $service]);
            echo json_encode(['success' => true, 'message' => "User VPN '{$name}' berhasil dibuat"]);
        }
        break;

    case 'edit_secret':
        $id = $_POST['id'] ?? '';
        $password = $_POST['password'] ?? '';
        $remoteAddress = trim($_POST['remote_address'] ?? '');
        $profile = trim($_POST['profile'] ?? '');
        $customComment = trim($_POST['comment'] ?? '');

        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID user tidak valid']);
            break;
        }

        // Cek kepemilikan (Semua role termasuk Super Admin hanya bisa edit miliknya sendiri)
        $check = $ros->comm('/ppp/secret/print', ['?.id=' . $id]);
        if (empty($check) || Helpers::getResourceOwner($check[0]['comment'] ?? '') !== $myUsername) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: User ini bukan milik Anda']);
            break;
        }

        $params = ['=.id=' . $id];
        if (!empty($password)) $params[] = '=password=' . $password;
        if (!empty($remoteAddress)) $params[] = '=remote-address=' . $remoteAddress;
        if (!empty($profile)) $params[] = '=profile=' . $profile;
        if ($customComment !== '') {
            $params[] = '=comment=' . Helpers::buildResourceComment($user['username'], $customComment);
        }

        $res = $ros->comm('/ppp/secret/set', $params);
        if (isset($res['!trap'])) {
            echo json_encode(['success' => false, 'message' => $res['!trap']['message'] ?? 'Gagal memperbarui user']);
        } else {
            echo json_encode(['success' => true, 'message' => 'User VPN berhasil diperbarui']);
        }
        break;

    case 'toggle_secret':
        $id = $_POST['id'] ?? '';
        $disabled = $_POST['disabled'] ?? 'no';

        $check = $ros->comm('/ppp/secret/print', ['?.id=' . $id]);
        if (empty($check) || Helpers::getResourceOwner($check[0]['comment'] ?? '') !== $myUsername) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: User ini bukan milik Anda']);
            break;
        }

        $res = $ros->comm('/ppp/secret/set', ['=.id=' . $id, '=disabled=' . $disabled]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => isset($res['!trap']) ? $res['!trap']['message'] : 'Status user diubah']);
        break;

    case 'delete_secret':
        $id = $_POST['id'] ?? '';
        $check = $ros->comm('/ppp/secret/print', ['?.id=' . $id]);
        if (empty($check) || Helpers::getResourceOwner($check[0]['comment'] ?? '') !== $myUsername) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: User ini bukan milik Anda']);
            break;
        }

        $res = $ros->comm('/ppp/secret/remove', ['=.id=' . $id]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => isset($res['!trap']) ? $res['!trap']['message'] : 'User VPN berhasil dihapus']);
        break;

    case 'kick_active':
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';

        // Pastikan user aktif ini memang milik admin yang login
        $secrets = $ros->comm('/ppp/secret/print', ['?name=' . $name]);
        if (empty($secrets) || Helpers::getResourceOwner($secrets[0]['comment'] ?? '') !== $myUsername) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: Klien ini bukan milik akun Anda']);
            break;
        }

        $res = $ros->comm('/ppp/active/remove', ['=.id=' . $id]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => 'Sesi user berhasil diputus']);
        break;

    // ==========================================
    // 2. MANAJEMEN IP POOL PER ADMIN
    // ==========================================
    case 'add_pool':
        $poolName = trim($_POST['name'] ?? '');
        $ranges = trim($_POST['ranges'] ?? '');
        $customComment = trim($_POST['comment'] ?? '');

        if (empty($poolName) || empty($ranges)) {
            echo json_encode(['success' => false, 'message' => 'Nama Pool dan Rentang IP (Ranges) wajib diisi']);
            break;
        }

        $finalComment = Helpers::buildResourceComment($user['username'], $customComment);

        $res = $ros->comm('/ip/pool/add', [
            '=name=' . $poolName,
            '=ranges=' . $ranges,
            '=comment=' . $finalComment
        ]);

        if (isset($res['!trap'])) {
            echo json_encode(['success' => false, 'message' => $res['!trap']['message'] ?? 'Gagal membuat IP Pool']);
        } else {
            Helpers::logAudit($user['id'], $user['username'], 'CREATE_IP_POOL', 'ip_pool', ['name' => $poolName, 'ranges' => $ranges]);
            echo json_encode(['success' => true, 'message' => "IP Pool '{$poolName}' berhasil dibuat"]);
        }
        break;

    case 'delete_pool':
        $id = $_POST['id'] ?? '';
        $check = $ros->comm('/ip/pool/print', ['?.id=' . $id]);
        if (empty($check) || Helpers::getResourceOwner($check[0]['comment'] ?? '') !== $myUsername) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: IP Pool ini bukan milik Anda']);
            break;
        }

        $res = $ros->comm('/ip/pool/remove', ['=.id=' . $id]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => isset($res['!trap']) ? $res['!trap']['message'] : 'IP Pool berhasil dihapus']);
        break;

    // ==========================================
    // 3. MANAJEMEN PPP PROFILE PER ADMIN
    // ==========================================
    case 'add_profile':
        $profileName = trim($_POST['name'] ?? '');
        $localAddress = trim($_POST['local_address'] ?? '');
        $remoteAddress = trim($_POST['remote_address'] ?? '');
        $dnsServer = trim($_POST['dns_server'] ?? '');
        $rateLimit = trim($_POST['rate_limit'] ?? '');
        $customComment = trim($_POST['comment'] ?? '');

        if (empty($profileName) || empty($localAddress) || empty($remoteAddress)) {
            echo json_encode(['success' => false, 'message' => 'Nama Profil, Local Address (Gateway), dan Remote Address (Pool) wajib diisi']);
            break;
        }

        $finalComment = Helpers::buildResourceComment($user['username'], $customComment);

        $params = [
            '=name=' . $profileName,
            '=local-address=' . $localAddress,
            '=remote-address=' . $remoteAddress,
            '=comment=' . $finalComment
        ];
        if (!empty($dnsServer)) $params[] = '=dns-server=' . $dnsServer;
        if (!empty($rateLimit)) $params[] = '=rate-limit=' . $rateLimit;

        $res = $ros->comm('/ppp/profile/add', $params);
        if (isset($res['!trap'])) {
            echo json_encode(['success' => false, 'message' => $res['!trap']['message'] ?? 'Gagal membuat PPP Profile']);
        } else {
            Helpers::logAudit($user['id'], $user['username'], 'CREATE_PPP_PROFILE', 'ppp_profile', ['name' => $profileName]);
            echo json_encode(['success' => true, 'message' => "PPP Profile '{$profileName}' berhasil dibuat"]);
        }
        break;

    case 'delete_profile':
        $id = $_POST['id'] ?? '';
        $check = $ros->comm('/ppp/profile/print', ['?.id=' . $id]);
        if (empty($check) || Helpers::getResourceOwner($check[0]['comment'] ?? '') !== $myUsername) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: Profil ini bukan milik Anda']);
            break;
        }

        $res = $ros->comm('/ppp/profile/remove', ['=.id=' . $id]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => isset($res['!trap']) ? $res['!trap']['message'] : 'PPP Profile berhasil dihapus']);
        break;

    // ==========================================
    // 4. SERVER CONFIG (SUPER ADMIN ONLY)
    // ==========================================
    case 'save_server_settings':
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang dapat mengubah konfigurasi server']);
            break;
        }

        $sstpEnabled = ($_POST['sstp_enabled'] ?? '') === '1' ? 'yes' : 'no';
        $sstpPort = $_POST['sstp_port'] ?? '443';
        
        $l2tpEnabled = ($_POST['l2tp_enabled'] ?? '') === '1' ? 'yes' : 'no';
        $l2tpUseIpsec = $_POST['l2tp_use_ipsec'] ?? 'yes';
        $l2tpSecret = $_POST['l2tp_ipsec_secret'] ?? '';

        $ros->comm('/interface/sstp-server/server/set', [
            '=enabled=' . $sstpEnabled,
            '=port=' . $sstpPort
        ]);

        $l2tpParams = [
            '=enabled=' . $l2tpEnabled,
            '=use-ipsec=' . $l2tpUseIpsec
        ];
        if (!empty($l2tpSecret)) {
            $l2tpParams[] = '=ipsec-secret=' . $l2tpSecret;
        }
        $ros->comm('/interface/l2tp-server/server/set', $l2tpParams);

        echo json_encode(['success' => true, 'message' => 'Konfigurasi Server VPN berhasil disimpan']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
        break;
}

$ros->disconnect();
