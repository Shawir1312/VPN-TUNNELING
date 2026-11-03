<?php
/**
 * Helper Functions & Multi-Tenant Utilities
 */

require_once __DIR__ . '/Database.php';

class Helpers {
    private static string $appKey = 'VPN-TUNNELING-MIKROTIK-SECRET-KEY-2026';

    public static function setAppKey(string $key): void {
        self::$appKey = $key;
    }

    /**
     * Enkripsi data sensitif (Password Router)
     */
    public static function encrypt(string $plainText): string {
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($plainText, $cipher, self::$appKey, 0, $iv);
        return base64_encode($iv . $ciphertext);
    }

    /**
     * Dekripsi data sensitif
     */
    public static function decrypt(string $cipherText): string {
        $raw = base64_decode($cipherText);
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = substr($raw, 0, $ivlen);
        $ciphertext = substr($raw, $ivlen);
        return openssl_decrypt($ciphertext, $cipher, self::$appKey, 0, $iv) ?: '';
    }

    /**
     * Ambil nama pemilik dari atribut comment MikroTik
     * Format: [owner:admin1] Catatan pengguna
     */
    public static function getResourceOwner(?string $comment): ?string {
        if (!$comment) {
            return null;
        }
        if (preg_match('/\[owner:([a-zA-Z0-9_\-\.]+)\]/i', $comment, $matches)) {
            return strtolower($matches[1]);
        }
        return null;
    }

    /**
     * Bentuk string comment dengan tag kepemilikan
     */
    public static function buildResourceComment(string $owner, ?string $customComment): string {
        $clean = trim(preg_replace('/\[owner:[a-zA-Z0-9_\-\.]+\]/i', '', $customComment ?? ''));
        return "[owner:{$owner}]" . ($clean !== '' ? " {$clean}" : '');
    }

    /**
     * Ambil catatan murni tanpa tag owner untuk ditampilkan di UI
     */
    public static function getDisplayComment(?string $comment): string {
        if (!$comment) {
            return '-';
        }
        $clean = trim(preg_replace('/\[owner:[a-zA-Z0-9_\-\.]+\]/i', '', $comment));
        return $clean !== '' ? $clean : '-';
    }

    /**
     * Filter data MikroTik berdasarkan wewenang admin yang login.
     * PRIVASI PENUH: Baik Super Admin maupun Sub-Admin HANYA melihat data milik akunnya sendiri.
     * Menerima $currentUser berupa array session admin ATAU string username langsung.
     */
    public static function filterByOwner(array $items, array|string $currentUser): array {
        if (is_array($currentUser)) {
            $myUsername = strtolower(trim($currentUser['username'] ?? ''));
        } else {
            $myUsername = strtolower(trim((string)$currentUser));
        }
        $filtered = [];

        foreach ($items as $item) {
            $comment = $item['comment'] ?? '';
            $owner = self::getResourceOwner($comment);
            if ($owner === $myUsername) {
                $filtered[] = $item;
            }
        }
        return $filtered;
    }

    /**
     * Catat aktivitas ke tabel audit_logs
     */
    public static function logAudit(int $adminId, string $username, string $action, string $resource, array $details = []): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO audit_logs (admin_id, username, action_type, target_resource, details, ip_address)
                VALUES (:admin_id, :username, :action, :resource, :details, :ip)
            ");
            $stmt->execute([
                ':admin_id' => $adminId,
                ':username' => $username,
                ':action' => $action,
                ':resource' => $resource,
                ':details' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Exception $e) {
            // Abaikan kegagalan log agar alur utama tidak terganggu
        }
    }

    /**
     * Format kecepatan dalam bps ke Bps, Kbps, Mbps
     */
    public static function formatBps(float|int $bps): string {
        if ($bps >= 1000000000) {
            return round($bps / 1000000000, 2) . ' Gbps';
        } elseif ($bps >= 1000000) {
            return round($bps / 1000000, 2) . ' Mbps';
        } elseif ($bps >= 1000) {
            return round($bps / 1000, 1) . ' Kbps';
        }
        return $bps . ' bps';
    }

    /**
     * Format ukuran byte ke KB, MB, GB
     */
    public static function formatBytes(float|int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
