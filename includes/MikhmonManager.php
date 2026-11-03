<?php
/**
 * Mikhmon Multi-Tenant Instance Manager
 * Mengelola pembuatan, konfigurasi, isolasi, dan penghapusan aplikasi Mikhmon per-admin
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';

class MikhmonManager {
    
    // Daftar slug yang tidak boleh digunakan karena merupakan path sistem
    private static array $reservedSlugs = [
        'api', 'config', 'database', 'includes', 'css', 'js', 
        'mikhmon', 'mikhmon-template', 'mikhmonv3-master', 'voucher',
        'hotspot', 'dhcp', 'dashboard', 'status', 'system', 'traffic', 
        'report', 'settings', 'lang', 'lib', 'img', 'vendor', 'assets',
        'admin', 'admins', 'install', 'index', 'login', 'logout', 'vpn', 'nat'
    ];

    /**
     * Dapatkan path master template Mikhmon
     */
    public static function getTemplateDir(): string {
        $base = dirname(__DIR__);
        if (is_dir($base . '/mikhmonv3-master')) {
            return $base . '/mikhmonv3-master';
        }
        if (is_dir($base . '/mikhmon-template')) {
            return $base . '/mikhmon-template';
        }
        return $base . '/mikhmonv3-master';
    }

    /**
     * Enkripsi string sesuai algoritma bawaan Mikhmon V3
     */
    public static function encryptMikhmon(string $string, int $key = 128): string {
        $result = '';
        for ($i = 0, $k = strlen($string); $i < $k; $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr((string)$key, ($i % strlen((string)$key)) - 1, 1);
            $char = chr(ord($char) + ord($keychar));
            $result .= $char;
        }
        return base64_encode($result);
    }

    /**
     * Dekripsi string sesuai algoritma bawaan Mikhmon V3
     */
    public static function decryptMikhmon(string $string, int $key = 128): string {
        $result = '';
        $string = base64_decode($string);
        for ($i = 0, $k = strlen($string); $i < $k; $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr((string)$key, ($i % strlen((string)$key)) - 1, 1);
            $char = chr(ord($char) - ord($keychar));
            $result .= $char;
        }
        return $result;
    }

    /**
     * Buat isi file include/config.php untuk instance Mikhmon
     */
    public static function buildConfigFile(
        string $mikhmonUser,
        string $mikhmonPass,
        string $sessionName,
        string $routerHost,
        int $routerPort,
        string $routerUser,
        string $routerPass,
        string $hotspotName,
        string $dnsName,
        string $currency = 'Rp'
    ): string {
        $mikhmonPassEnc = self::encryptMikhmon($mikhmonPass);
        $routerPassEnc = self::encryptMikhmon($routerPass);
        $ipHostWithPort = "{$routerHost}:{$routerPort}";
        $cleanSession = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionName) ?: 'hotspot';

        $out = "<?php \n";
        $out .= "if(substr(\$_SERVER[\"REQUEST_URI\"], -10) == \"config.php\"){header(\"Location:./\");}; \n";
        $out .= "\$data['mikhmon'] = array ('1'=>'mikhmon<|<{$mikhmonUser}','{$mikhmonUser}>|>{$mikhmonPassEnc}');\n";
        $out .= "\$data['{$cleanSession}'] = array (\n";
        $out .= "  '1'=>'{$cleanSession}!{$ipHostWithPort}',\n";
        $out .= "  '{$cleanSession}@|@{$routerUser}',\n";
        $out .= "  '{$cleanSession}#|#{$routerPassEnc}',\n";
        $out .= "  '{$cleanSession}%{$hotspotName}',\n";
        $out .= "  '{$cleanSession}^{$dnsName}',\n";
        $out .= "  '{$cleanSession}&{$currency}',\n";
        $out .= "  '{$cleanSession}*10',\n";
        $out .= "  '{$cleanSession}(1',\n";
        $out .= "  '{$cleanSession})',\n";
        $out .= "  '{$cleanSession}=10',\n";
        $out .= "  '{$cleanSession}@!@disable'\n";
        $out .= ");\n";

        return $out;
    }

    /**
     * Copy direktori secara rekursif (Dengan akselerasi cp sistem jika tersedia)
     */
    public static function copyRecursive(string $src, string $dst): bool {
        if (!is_dir($src)) return false;

        // Akselerasi super cepat via command exec OS (biasanya < 15ms)
        if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))))) {
            @mkdir($dst, 0755, true);
            $escapedSrc = escapeshellarg(rtrim($src, '/') . '/.');
            $escapedDst = escapeshellarg($dst);
            @exec("cp -a {$escapedSrc} {$escapedDst} 2>&1", $output, $returnCode);
            if ($returnCode === 0 && file_exists($dst . '/index.php')) {
                return true;
            }
        }

        // Fallback rekursif PHP murni
        @mkdir($dst, 0755, true);
        $dir = @opendir($src);
        if (!$dir) return false;
        
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                $srcPath = $src . '/' . $file;
                $dstPath = $dst . '/' . $file;
                if (is_dir($srcPath)) {
                    self::copyRecursive($srcPath, $dstPath);
                } else {
                    @copy($srcPath, $dstPath);
                }
            }
        }
        closedir($dir);
        return true;
    }

    /**
     * Hapus direktori secara rekursif (Dengan akselerasi rm -rf jika tersedia)
     */
    public static function deleteRecursive(string $dirPath): bool {
        if (!is_dir($dirPath)) return false;

        // Akselerasi via rm -rf jika exec tersedia
        if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))))) {
            $escaped = escapeshellarg($dirPath);
            @exec("rm -rf {$escaped} 2>&1", $output, $returnCode);
            if ($returnCode === 0 && !file_exists($dirPath)) {
                return true;
            }
        }

        $files = @scandir($dirPath);
        if (!$files) return false;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = "$dirPath/$file";
            if (is_dir($path)) {
                self::deleteRecursive($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dirPath);
    }

    /**
     * Otomatis sinkronkan file patch kompatibilitas PHP 8 ke instance yang sudah ada di disk
     */
    public static function autoRepairInstances(): void {
        try {
            $templateDir = self::getTemplateDir();
            if (!is_dir($templateDir)) return;

            $db = Database::getConnection();
            $stmt = $db->query("SELECT slug FROM mikhmon_instances");
            $instances = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $coreFiles = [
                'dashboard/home.php',
                'lib/routeros_api.class.php',
                'traffic/trafficmonitor.php',
            ];

            foreach ($instances as $inst) {
                $slug = trim($inst['slug'] ?? '');
                if (empty($slug)) continue;
                $instDir = dirname(__DIR__) . '/' . $slug;
                if (!is_dir($instDir)) continue;

                // Salin file core engine terbaru
                foreach ($coreFiles as $relPath) {
                    $src = $templateDir . '/' . $relPath;
                    $dst = $instDir . '/' . $relPath;
                    if (file_exists($src)) {
                        @mkdir(dirname($dst), 0755, true);
                        @copy($src, $dst);
                    }
                }

                // Perbaiki (ether1 -> (1 pada include/config.php untuk kompatibilitas PHP 8
                $cfgPath = $instDir . '/include/config.php';
                if (file_exists($cfgPath)) {
                    $content = file_get_contents($cfgPath);
                    if (strpos($content, '(ether1') !== false) {
                        $content = str_replace('(ether1', '(1', $content);
                        @file_put_contents($cfgPath, $content);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore if table not created yet
        }
    }

    /**
     * Ambil daftar instance milik admin yang login
     */
    public static function getInstances(int $adminId, string $username, bool $isSuperAdmin = false): array {
        self::autoRepairInstances();
        $db = Database::getConnection();
        // Super Admin & Sub-Admin memiliki data privacy penuh (hanya melihat miliknya sendiri)
        $stmt = $db->prepare("SELECT * FROM mikhmon_instances WHERE owner_username = :u ORDER BY id DESC");
        $stmt->execute([':u' => strtolower($username)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ambil 1 instance berdasarkan ID
     */
    public static function getInstanceById(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM mikhmon_instances WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buat Instance Mikhmon Baru
     */
    public static function createInstance(
        int $adminId,
        string $ownerUsername,
        string $appName,
        string $slug,
        string $routerHost,
        int $routerPort,
        string $routerUser,
        string $routerPass,
        string $mikhmonUser = 'admin',
        string $mikhmonPass = '1234',
        string $hotspotName = '',
        string $dnsName = '',
        string $currency = 'Rp'
    ): array {
        $slug = strtolower(trim($slug));
        $ownerUsername = strtolower(trim($ownerUsername));

        // 1. Validasi format slug
        if (!preg_match('/^[a-z0-9_-]{3,32}$/', $slug)) {
            return ['success' => false, 'message' => 'Slug URL hanya boleh huruf kecil, angka, tanda strip (-) min. 3 karakter.'];
        }

        // 2. Cek slug terlarang
        if (in_array($slug, self::$reservedSlugs, true)) {
            return ['success' => false, 'message' => "Slug URL '{$slug}' merupakan nama sistem dan tidak boleh digunakan."];
        }

        $db = Database::getConnection();

        // 3. Cek keunikan slug di DB
        $stmt = $db->prepare("SELECT id FROM mikhmon_instances WHERE slug = :s LIMIT 1");
        $stmt->execute([':s' => $slug]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => "Slug URL '{$slug}' sudah digunakan oleh pengguna lain. Silakan pilih slug yang berbeda."];
        }

        // 4. Cek apakah folder sudah ada di disk
        $targetDir = dirname(__DIR__) . '/' . $slug;
        if (file_exists($targetDir)) {
            return ['success' => false, 'message' => "Folder '{$slug}' sudah ada di server. Silakan gunakan nama slug lain."];
        }

        // 5. Cek master template
        $templateDir = self::getTemplateDir();
        if (!is_dir($templateDir)) {
            return ['success' => false, 'message' => 'Master template Mikhmon tidak ditemukan di server.'];
        }

        // 6. Copy template ke folder slug
        $copied = self::copyRecursive($templateDir, $targetDir);
        if (!$copied) {
            return ['success' => false, 'message' => "Gagal menduplikasi folder template ke '{$slug}'. Periksa permission folder server."];
        }

        // 7. Generate include/config.php di dalam folder baru
        $configFileContent = self::buildConfigFile(
            $mikhmonUser,
            $mikhmonPass,
            $slug,
            $routerHost,
            $routerPort,
            $routerUser,
            $routerPass,
            $hotspotName ?: $appName,
            $dnsName,
            $currency
        );

        @file_put_contents($targetDir . '/include/config.php', $configFileContent);

        // 8. Simpan ke Database
        $encRouterPass = Helpers::encrypt($routerPass);
        $encMikhmonPass = Helpers::encrypt($mikhmonPass);

        $insertStmt = $db->prepare("
            INSERT INTO mikhmon_instances (
                admin_id, owner_username, app_name, slug, 
                router_host, router_port, router_user, router_pass_encrypted,
                mikhmon_user, mikhmon_pass_encrypted, hotspot_name, dns_name, currency, is_active
            ) VALUES (
                :aid, :owner, :name, :slug,
                :rhost, :rport, :ruser, :rpass,
                :muser, :mpass, :hname, :dname, :curr, 1
            )
        ");

        $insertStmt->execute([
            ':aid' => $adminId,
            ':owner' => $ownerUsername,
            ':name' => $appName,
            ':slug' => $slug,
            ':rhost' => $routerHost,
            ':rport' => $routerPort,
            ':ruser' => $routerUser,
            ':rpass' => $encRouterPass,
            ':muser' => $mikhmonUser,
            ':mpass' => $encMikhmonPass,
            ':hname' => $hotspotName ?: $appName,
            ':dname' => $dnsName,
            ':curr' => $currency
        ]);

        // Tambahkan slug instance ke .gitignore agar tidak mengotori repositori git
        $gitignoreFile = dirname(__DIR__) . '/.gitignore';
        if (file_exists($gitignoreFile)) {
            $giContent = file_get_contents($gitignoreFile);
            if (!str_contains($giContent, "/{$slug}/")) {
                @file_put_contents($gitignoreFile, "\n/{$slug}/", FILE_APPEND);
            }
        }

        return [
            'success' => true,
            'message' => "Aplikasi Mikhmon '{$appName}' berhasil dibuat pada URL /{$slug}",
            'slug' => $slug
        ];
    }

    /**
     * Update Instance Mikhmon
     */
    public static function updateInstance(
        int $instanceId,
        string $currentUsername,
        string $appName,
        string $routerHost,
        int $routerPort,
        string $routerUser,
        ?string $newRouterPass,
        string $mikhmonUser,
        ?string $newMikhmonPass,
        string $hotspotName = '',
        string $dnsName = '',
        string $currency = 'Rp'
    ): array {
        $instance = self::getInstanceById($instanceId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance Mikhmon tidak ditemukan'];
        }

        // Cek kepemilikan
        if (strtolower($instance['owner_username']) !== strtolower($currentUsername)) {
            return ['success' => false, 'message' => 'Akses ditolak: Anda bukan pemilik aplikasi ini'];
        }

        $slug = $instance['slug'];
        $targetDir = dirname(__DIR__) . '/' . $slug;

        // Ambil password router lama jika tidak diisi baru
        $routerPass = !empty($newRouterPass) ? $newRouterPass : Helpers::decrypt($instance['router_pass_encrypted']);
        $mikhmonPass = !empty($newMikhmonPass) ? $newMikhmonPass : Helpers::decrypt($instance['mikhmon_pass_encrypted']);

        // Update config.php pada direktori instance
        if (is_dir($targetDir)) {
            $configFileContent = self::buildConfigFile(
                $mikhmonUser,
                $mikhmonPass,
                $slug,
                $routerHost,
                $routerPort,
                $routerUser,
                $routerPass,
                $hotspotName ?: $appName,
                $dnsName,
                $currency
            );
            @file_put_contents($targetDir . '/include/config.php', $configFileContent);
        }

        // Update ke database
        $db = Database::getConnection();
        $encRouterPass = Helpers::encrypt($routerPass);
        $encMikhmonPass = Helpers::encrypt($mikhmonPass);

        $stmt = $db->prepare("
            UPDATE mikhmon_instances SET
                app_name = :name,
                router_host = :rhost,
                router_port = :rport,
                router_user = :ruser,
                router_pass_encrypted = :rpass,
                mikhmon_user = :muser,
                mikhmon_pass_encrypted = :mpass,
                hotspot_name = :hname,
                dns_name = :dname,
                currency = :curr,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            ':name' => $appName,
            ':rhost' => $routerHost,
            ':rport' => $routerPort,
            ':ruser' => $routerUser,
            ':rpass' => $encRouterPass,
            ':muser' => $mikhmonUser,
            ':mpass' => $encMikhmonPass,
            ':hname' => $hotspotName ?: $appName,
            ':dname' => $dnsName,
            ':curr' => $currency,
            ':id' => $instanceId
        ]);

        return ['success' => true, 'message' => 'Pengaturan Mikhmon berhasil diperbarui'];
    }

    /**
     * Hapus Instance Mikhmon
     */
    public static function deleteInstance(int $instanceId, string $currentUsername): array {
        $instance = self::getInstanceById($instanceId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance Mikhmon tidak ditemukan'];
        }

        if (strtolower($instance['owner_username']) !== strtolower($currentUsername)) {
            return ['success' => false, 'message' => 'Akses ditolak: Anda bukan pemilik aplikasi ini'];
        }

        $slug = $instance['slug'];
        $targetDir = dirname(__DIR__) . '/' . $slug;

        // Hapus folder fisik dari server
        if (is_dir($targetDir)) {
            self::deleteRecursive($targetDir);
        }

        // Hapus record dari database
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM mikhmon_instances WHERE id = :id");
        $stmt->execute([':id' => $instanceId]);

        // Hapus slug dari .gitignore jika ada
        $gitignoreFile = dirname(__DIR__) . '/.gitignore';
        if (file_exists($gitignoreFile)) {
            $giContent = file_get_contents($gitignoreFile);
            $newGi = str_replace("\n/{$slug}/", "", $giContent);
            @file_put_contents($gitignoreFile, $newGi);
        }

        return ['success' => true, 'message' => "Aplikasi Mikhmon '/{$slug}' berhasil dihapus"];
    }

    /**
     * Toggle status aktif / nonaktif
     */
    public static function toggleStatus(int $instanceId, string $currentUsername, int $isActive): array {
        $instance = self::getInstanceById($instanceId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance tidak ditemukan'];
        }

        if (strtolower($instance['owner_username']) !== strtolower($currentUsername)) {
            return ['success' => false, 'message' => 'Akses ditolak'];
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE mikhmon_instances SET is_active = :act, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':act' => $isActive, ':id' => $instanceId]);

        return ['success' => true, 'message' => 'Status aplikasi Mikhmon diperbarui'];
    }
}
