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
$ros = Auth::getRouterConnection();
if (!$ros || !$ros->connected) {
    echo json_encode(['success' => false, 'message' => 'Tidak dapat terhubung ke MikroTik API']);
    exit;
}

$myUsername = strtolower($user['username']);
$isSuperAdmin = ($user['role'] === 'superadmin');

switch ($action) {
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

        // Cek kepemilikan jika bukan superadmin
        if (!$isSuperAdmin) {
            $check = $ros->comm('/ppp/secret/print', ['?.id=' . $id]);
            if (empty($check) || strtolower(Helpers::getResourceOwner($check[0]['comment'] ?? '')) !== $myUsername) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: User ini bukan milik Anda']);
                break;
            }
        }

        $params = ['=.id=' . $id];
        if (!empty($password)) $params[] = '=password=' . $password;
        if (!empty($remoteAddress)) $params[] = '=remote-address=' . $remoteAddress;
        if (!empty($profile)) $params[] = '=profile=' . $profile;
        if ($customComment !== '') {
            $owner = $isSuperAdmin ? (Helpers::getResourceOwner($check[0]['comment'] ?? '') ?: $user['username']) : $user['username'];
            $params[] = '=comment=' . Helpers::buildResourceComment($owner, $customComment);
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

        if (!$isSuperAdmin) {
            $check = $ros->comm('/ppp/secret/print', ['?.id=' . $id]);
            if (empty($check) || strtolower(Helpers::getResourceOwner($check[0]['comment'] ?? '')) !== $myUsername) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
                break;
            }
        }

        $res = $ros->comm('/ppp/secret/set', ['=.id=' . $id, '=disabled=' . $disabled]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => isset($res['!trap']) ? $res['!trap']['message'] : 'Status user diubah']);
        break;

    case 'delete_secret':
        $id = $_POST['id'] ?? '';
        if (!$isSuperAdmin) {
            $check = $ros->comm('/ppp/secret/print', ['?.id=' . $id]);
            if (empty($check) || strtolower(Helpers::getResourceOwner($check[0]['comment'] ?? '')) !== $myUsername) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
                break;
            }
        }

        $res = $ros->comm('/ppp/secret/remove', ['=.id=' . $id]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => isset($res['!trap']) ? $res['!trap']['message'] : 'User VPN berhasil dihapus']);
        break;

    case 'kick_active':
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';

        if (!$isSuperAdmin) {
            // Pastikan user aktif ini memang terdaftar sebagai milik sub-admin
            $secrets = $ros->comm('/ppp/secret/print', ['?name=' . $name]);
            if (empty($secrets) || strtolower(Helpers::getResourceOwner($secrets[0]['comment'] ?? '')) !== $myUsername) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
                break;
            }
        }

        $res = $ros->comm('/ppp/active/remove', ['=.id=' . $id]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => 'Sesi user berhasil diputus']);
        break;

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

        // Update SSTP Server
        $ros->comm('/interface/sstp-server/server/set', [
            '=enabled=' . $sstpEnabled,
            '=port=' . $sstpPort
        ]);

        // Update L2TP Server
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

