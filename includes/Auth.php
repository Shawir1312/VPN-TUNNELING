<?php
/**
 * Authentication & Session Management
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/RouterOS.php';

class Auth {
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function checkInstalled(): bool {
        $dbPath = __DIR__ . '/../database/database.sqlite';
        $lockFile = __DIR__ . '/../config/installed.lock';
        return file_exists($dbPath) && file_exists($lockFile);
    }

    public static function user(): ?array {
        self::startSession();
        return $_SESSION['admin_user'] ?? null;
    }

    public static function isLoggedIn(): bool {
        return self::user() !== null;
    }

    public static function requireLogin(): array {
        if (!self::checkInstalled()) {
            header('Location: install.php');
            exit;
        }

        $user = self::user();
        if (!$user) {
            header('Location: index.php');
            exit;
        }

        // Cek apakah akun masih aktif di DB
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id, username, full_name, role, theme_preference, is_active FROM admin_users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $current = $stmt->fetch();
            if (!$current || (int)$current['is_active'] !== 1) {
                self::logout();
                header('Location: index.php?error=inactive');
                exit;
            }
            $_SESSION['admin_user'] = $current;
            return $current;
        } catch (Exception $e) {
            return $user;
        }
    }

    public static function requireSuperAdmin(): array {
        $user = self::requireLogin();
        if ($user['role'] !== 'superadmin') {
            header('Location: dashboard.php?error=forbidden');
            exit;
        }
        return $user;
    }

    public static function attemptLogin(string $username, string $password): bool {
        self::startSession();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ((int)$user['is_active'] !== 1) {
                return false;
            }
            unset($user['password_hash']);
            $_SESSION['admin_user'] = $user;
            Helpers::logAudit($user['id'], $user['username'], 'LOGIN', 'auth', ['message' => 'Login berhasil']);
            return true;
        }
        return false;
    }

    public static function logout(): void {
        self::startSession();
        if (isset($_SESSION['admin_user'])) {
            $u = $_SESSION['admin_user'];
            Helpers::logAudit($u['id'], $u['username'], 'LOGOUT', 'auth', ['message' => 'User logged out']);
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    private static string $lastRouterError = '';

    public static function getLastRouterError(): string {
        return self::$lastRouterError;
    }

    /**
     * Dapatkan koneksi RouterOS aktif
     */
    public static function getRouterConnection(): ?RouterOS {
        self::$lastRouterError = '';
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM router_profiles WHERE is_default = 1 LIMIT 1");
        $router = $stmt->fetch();

        if (!$router) {
            self::$lastRouterError = 'Profil router belum dikonfigurasi di database.';
            return null;
        }

        $password = Helpers::decrypt($router['password_encrypted']);
        $ros = new RouterOS();
        $ok = $ros->connect($router['host'], $router['username'], $password, (int)$router['api_port'], 4);
        if ($ok) {
            return $ros;
        }
        self::$lastRouterError = $ros->error ?: 'Gagal terhubung ke MikroTik API (Port 8728)';
        return null;
    }

    /**
     * Dapatkan data profil router default
     */
    public static function getRouterProfile(): ?array {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM router_profiles WHERE is_default = 1 LIMIT 1");
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
}

