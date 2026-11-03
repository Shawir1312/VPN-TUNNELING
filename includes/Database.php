<?php
/**
 * Database Connection & Initialization Helper
 * Menggunakan SQLite dengan PDO
 */

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dbPath = __DIR__ . '/../database/database.sqlite';
            
            // Buat folder database jika belum ada
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            try {
                self::$instance = new PDO("sqlite:" . $dbPath);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                // Aktifkan WAL mode untuk performa baca/tulis cepat
                self::$instance->exec("PRAGMA journal_mode = WAL;");
                self::$instance->exec("PRAGMA foreign_keys = ON;");
            } catch (PDOException $e) {
                die("Koneksi ke SQLite gagal: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    public static function initSchema(PDO $db): void {
        $sql = "
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            full_name TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('superadmin', 'subadmin')),
            created_by_admin_id INTEGER,
            theme_preference TEXT NOT NULL DEFAULT 'system' CHECK(theme_preference IN ('system', 'light', 'dark')),
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS router_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            host TEXT NOT NULL,
            api_port INTEGER NOT NULL DEFAULT 8728,
            wan_interface TEXT NOT NULL DEFAULT 'ether1',
            username TEXT NOT NULL,
            password_encrypted TEXT NOT NULL,
            is_default INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER,
            username TEXT NOT NULL,
            action_type TEXT NOT NULL,
            target_resource TEXT NOT NULL,
            details TEXT,
            ip_address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
        );
        ";
        $db->exec($sql);
    }
}

