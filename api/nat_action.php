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
    case 'add_nat':
        $dstAddress = trim($_POST['dst_address'] ?? '');
        $protocol = $_POST['protocol'] ?? 'tcp';
        $dstPort = trim($_POST['dst_port'] ?? '');
        $toAddresses = trim($_POST['to_addresses'] ?? '');
        $toPorts = trim($_POST['to_ports'] ?? '');
        $customComment = trim($_POST['comment'] ?? '');

        if (empty($dstPort) || empty($toAddresses) || empty($toPorts)) {
            echo json_encode(['success' => false, 'message' => 'Port Publik, Target IP, dan Target Port wajib diisi']);
            break;
        }

        // ============================================================
        // FITUR CROSS-ADMIN PORT CONFLICT DETECTION
        // ============================================================
        $existingRules = $ros->comm('/ip/firewall/nat/print', [
            '?chain=dstnat',
            '?protocol=' . $protocol,
            '?dst-port=' . $dstPort
        ]);

        if (!empty($existingRules)) {
            // Cek apakah ada rule yang IP publiknya sama atau wildcard (tanpa dst-address)
            foreach ($existingRules as $rule) {
                $ruleDstAddr = $rule['dst-address'] ?? '';
                if ($ruleDstAddr === '' || $dstAddress === '' || $ruleDstAddr === $dstAddress) {
                    $owner = Helpers::getResourceOwner($rule['comment'] ?? '');
                    $ownerDisplay = $owner ? $owner : 'admin lain / sistem';

                    http_response_code(409); // Conflict
                    echo json_encode([
                        'success' => false,
                        'conflict' => true,
                        'owner' => $ownerDisplay,
                        'message' => "Port publik {$dstPort} ({$protocol}) telah digunakan oleh '{$ownerDisplay}'. Silakan gunakan nomor port publik yang lain."
                    ]);
                    $ros->disconnect();
                    exit;
                }
            }
        }

        // Tagging kepemilikan admin
        $finalComment = Helpers::buildResourceComment($user['username'], $customComment);

        $params = [
            '=chain=dstnat',
            '=action=dst-nat',
            '=protocol=' . $protocol,
            '=dst-port=' . $dstPort,
            '=to-addresses=' . $toAddresses,
            '=to-ports=' . $toPorts,
            '=comment=' . $finalComment
        ];
        if (!empty($dstAddress)) {
            $params[] = '=dst-address=' . $dstAddress;
        }

        $res = $ros->comm('/ip/firewall/nat/add', $params);
        if (isset($res['!trap'])) {
            echo json_encode(['success' => false, 'message' => $res['!trap']['message'] ?? 'Gagal membuat rule Dst-NAT']);
        } else {
            Helpers::logAudit($user['id'], $user['username'], 'ADD_DST_NAT', 'firewall_nat', [
                'dst_port' => $dstPort,
                'to' => "{$toAddresses}:{$toPorts}"
            ]);
            echo json_encode(['success' => true, 'message' => "Rule Dst-NAT port {$dstPort} berhasil ditambahkan"]);
        }
        break;

    case 'delete_nat':
        $id = $_POST['id'] ?? '';
        if (!$isSuperAdmin) {
            $check = $ros->comm('/ip/firewall/nat/print', ['?.id=' . $id]);
            if (empty($check) || strtolower(Helpers::getResourceOwner($check[0]['comment'] ?? '')) !== $myUsername) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak: Rule ini bukan milik Anda']);
                break;
            }
        }

        $res = $ros->comm('/ip/firewall/nat/remove', ['=.id=' . $id]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => isset($res['!trap']) ? $res['!trap']['message'] : 'Rule Dst-NAT berhasil dihapus']);
        break;

    case 'toggle_nat':
        $id = $_POST['id'] ?? '';
        $disabled = $_POST['disabled'] ?? 'no';

        if (!$isSuperAdmin) {
            $check = $ros->comm('/ip/firewall/nat/print', ['?.id=' . $id]);
            if (empty($check) || strtolower(Helpers::getResourceOwner($check[0]['comment'] ?? '')) !== $myUsername) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
                break;
            }
        }

        $res = $ros->comm('/ip/firewall/nat/set', ['=.id=' . $id, '=disabled=' . $disabled]);
        echo json_encode(['success' => !isset($res['!trap']), 'message' => 'Status rule Dst-NAT diperbarui']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
        break;
}

$ros->disconnect();

