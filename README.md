# MikroTik VPN & Dst-NAT Manager (Full PHP + SQLite)

Aplikasi web manajemen VPN (SSTP & L2TP/IPsec) dan Port Forwarding (Dst-NAT) berbasis **MikroTik RouterOS API (Port 8728)** dengan arsitektur **tanpa database jaringan ganda** (MikroTik sebagai *single source of truth*) dan **SQLite** khusus untuk otentikasi admin, multi-tenant data isolation, dan audit log.

---

## 🌟 Fitur Utama

1. **Full PHP Native & Ringan**:
   - Berjalan pada PHP 8.x dengan ekstensi bawaan `pdo_sqlite`, `openssl`, dan `sockets`.
   - Tanpa ketergantungan composer berat.

2. **Web Installer Terpadu (`install.php`)**:
   - Mempermudah setup awal di VPS atau server lokal: pengecekan modul server, inisialisasi basis data SQLite, pembuatan akun Super Admin, dan pengujian koneksi ke MikroTik secara otomatis.

3. **Dashboard Real-Time Monitoring**:
   - Metrik kesehatan router: **Beban CPU**, pemakaian **RAM/Memori**, durasi aktif (**Uptime**), model board, dan versi RouterOS.
   - **Grafik Kecepatan Internet Real-Time (Live WAN Traffic)**: Visualisasi *Download (RX)* dan *Upload (TX)* dalam Kbps/Mbps yang diperbarui secara langsung.

4. **Multi-Admin Data Isolation (Privasi Penuh Antar-Admin)**:
   - Super Admin dapat membuat akun untuk `admin1`, `admin2`, dst.
   - Tiap admin memiliki ruang privasi terpisah: `admin1` tidak dapat melihat atau mengedit VPN maupun rule Dst-NAT milik `admin2`.
   - Menggunakan standardisasi penandaan metadata otomatis pada comment router: `[owner:username]`.

5. **Cross-Admin Port Conflict Notification**:
   - Jika `admin2` mencoba mendaftarkan port forwarding publik yang sudah dipakai oleh `admin1`, sistem otomatis memblokir dan menampilkan notifikasi transparan:
     > `⚠️ Port publik [port] telah digunakan oleh 'admin1'. Silakan gunakan nomor port lain.`

6. **Tampilan Responsif & Dual Theme (Light & Dark Mode)**:
   - Optimal diakses melalui smartphone, tablet, maupun layar desktop.
   - Sakelar toggle tema (☀️ Light / 🌙 Dark) yang adaptif dan tersimpan di preferensi pengguna.

---

## 🚀 Panduan Menjalankan Sistem

### Opsi 1: Menjalankan dengan PHP Built-in Server (Cepat & Praktis)

Buka terminal di folder proyek ini dan jalankan perintah:

```bash
php -S 0.0.0.0:8000
```

Buka peramban (browser) Anda di:
👉 **`http://localhost:8000/install.php`** (atau `http://IP_SERVER_ANDA:8000/install.php`)

---

### Opsi 2: Menggunakan Web Server Apache / Nginx di VPS Linux

1. Salin seluruh isi folder proyek ke web root (contoh: `/var/www/html/mikrotik-manager`).
2. Pastikan permission folder `database/` dan `config/` dapat ditulis oleh web server:
   ```bash
   chown -R www-data:www-data /var/www/html/mikrotik-manager
   chmod -R 775 /var/www/html/mikrotik-manager/database
   chmod -R 775 /var/www/html/mikrotik-manager/config
   ```
3. Akses `http://ip-server-anda/mikrotik-manager/install.php` untuk menjalankan setup wizard.

---

## 🛠️ Persyaratan di Sisi MikroTik RouterOS

Pastikan service API bawaan MikroTik aktif pada port 8728. Anda dapat memeriksanya di Terminal MikroTik / Winbox:

```routeros
/ip service print
# Jika status api (port 8728) bertanda 'X' (disabled), aktifkan dengan:
/ip service enable api
```

Jika Anda ingin membatasi akses API hanya dari IP server aplikasi Anda:
```routeros
/ip service set api address=192.168.88.0/24
```

---

## 📁 Struktur Direktori

```
VPN-TUNNELING/
├── config/
│   └── installed.lock          # Dibuat otomatis saat setup selesai
├── database/
│   └── database.sqlite         # Database lokal SQLite (User, Profil, Audit Log)
├── includes/
│   ├── Database.php            # Koneksi & Skema SQLite PDO
│   ├── RouterOS.php            # Driver binary socket MikroTik API (Port 8728)
│   ├── Auth.php                # Manajemen sesi, login, & RBAC
│   ├── Helpers.php             # Tagging kepemilikan [owner:xxx] & enkripsi AES-256
│   ├── header.php              # Header & navigasi responsif (Mobile Drawer & Sidebar)
│   └── footer.php              # Footer, Chart.js, & script tema
├── api/
│   ├── traffic.php             # Endpoint polling real-time CPU, RAM, & WAN Speed
│   ├── vpn_action.php          # CRUD PPP Secret, Active session kick, & Server setting
│   ├── nat_action.php          # CRUD Dst-NAT & validasi bentrok port antar-admin
│   └── theme_action.php        # Sinkronisasi preferensi tema ke database
├── install.php                 # Web Setup Wizard awal
├── index.php                   # Halaman Login
├── dashboard.php               # Dashboard monitoring real-time & grafik internet
├── vpn.php                     # Kelola VPN SSTP, L2TP, Secret & Sesi Aktif
├── nat.php                     # Kelola Port Forwarding (Dst-NAT)
├── admins.php                  # Kelola Sub-Admin (Khusus Super Admin)
├── settings.php                # Pengaturan profil & IP MikroTik
├── logout.php                  # Keluar sesi
└── README.md                   # Dokumentasi instalasi dan penggunaan
```

---

## 🛡️ Hak Akses & Keamanan

- **Password Admin**: Dihash menggunakan algoritma `bcrypt` dengan salt aman.
- **Password MikroTik**: Disimpan terenkripsi secara simetris di basis data SQLite menggunakan algoritma **AES-256-CBC**.
- **Audit Trail**: Seluruh tindakan penambahan, pengubahan status, atau penghapusan resource dicatat ke tabel `audit_logs` di SQLite lengkap dengan alamat IP pembuat.

