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
            
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            try {
                self::$instance = new PDO("sqlite:" . $dbPath);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance->exec("PRAGMA journal_mode = WAL;");
                self::$instance->exec("PRAGMA foreign_keys = ON;");
                
                // Cek migrasi kolom domain_name pada router_profiles
                self::runMigrations(self::$instance);
            } catch (PDOException $e) {
                die("Koneksi ke SQLite gagal: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    private static function runMigrations(PDO $db): void {
        try {
            // Cek apakah tabel router_profiles sudah ada
            $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='router_profiles'")->fetch();
            if ($check) {
                $cols = $db->query("PRAGMA table_info(router_profiles)")->fetchAll();
                $colNames = array_column($cols, 'name');
                if (!in_array('domain_name', $colNames, true)) {
                    $db->exec("ALTER TABLE router_profiles ADD COLUMN domain_name TEXT DEFAULT '';");
                }
            }

            // Migrasi tabel mikhmon_instances
            $db->exec("
                CREATE TABLE IF NOT EXISTS mikhmon_instances (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    admin_id INTEGER NOT NULL,
                    owner_username TEXT NOT NULL,
                    app_name TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    router_host TEXT NOT NULL,
                    router_port INTEGER NOT NULL DEFAULT 8728,
                    router_user TEXT NOT NULL,
                    router_pass_encrypted TEXT NOT NULL,
                    mikhmon_user TEXT NOT NULL DEFAULT 'admin',
                    mikhmon_pass_encrypted TEXT NOT NULL DEFAULT '1234',
                    hotspot_name TEXT DEFAULT '',
                    dns_name TEXT DEFAULT '',
                    currency TEXT DEFAULT 'Rp',
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
                );
            ");
        } catch (Exception $e) {
            // Abaikan jika migrasi sudah berjalan
        }
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
            domain_name TEXT DEFAULT '',
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

        CREATE TABLE IF NOT EXISTS mikhmon_instances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            owner_username TEXT NOT NULL,
            app_name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            router_host TEXT NOT NULL,
            router_port INTEGER NOT NULL DEFAULT 8728,
            router_user TEXT NOT NULL,
            router_pass_encrypted TEXT NOT NULL,
            mikhmon_user TEXT NOT NULL DEFAULT 'admin',
            mikhmon_pass_encrypted TEXT NOT NULL DEFAULT '1234',
            hotspot_name TEXT DEFAULT '',
            dns_name TEXT DEFAULT '',
            currency TEXT DEFAULT 'Rp',
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
        );
        ";
        $db->exec($sql);
    }
}
