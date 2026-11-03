# MikroTik VPN & Dst-NAT Manager (Full PHP + SQLite)

Aplikasi web manajemen VPN (SSTP & L2TP/IPsec) dan Port Forwarding (Dst-NAT) berbasis **MikroTik RouterOS API (Port 8728)** dengan arsitektur **tanpa database jaringan ganda** (MikroTik sebagai *single source of truth*) dan **SQLite** khusus untuk otentikasi admin, multi-tenant data isolation, dan audit log.

---

## 🌟 Fitur Utama

1. **Full PHP Native & Ringan**:
   - Berjalan pada PHP 8.x dengan ekstensi bawaan `pdo_sqlite`, `openssl`, dan `sockets`.
   - Tanpa ketergantungan composer berat.

2. **Dukungan Remote via IP Publik & Domain / DDNS**:
   - Super Admin dapat mengatur **Domain / Hostname DDNS** (contoh: `vpn.domainanda.com` atau `xxxx.sn.mynetname.net`).
   - Klien VPN dapat memilih koneksi menggunakan **Domain** atau **IP Publik MikroTik** secara fleksibel pada generator script.

3. **Web Installer Terpadu (`install.php`)**:
   - Mempermudah setup awal di VPS atau server lokal: pengecekan modul server, inisialisasi basis data SQLite, pembuatan akun Super Admin, dan pengujian koneksi ke MikroTik secara otomatis.

4. **Dashboard Real-Time Monitoring**:
   - Metrik kesehatan router: **Beban CPU**, pemakaian **RAM/Memori**, durasi aktif (**Uptime**), model board, dan versi RouterOS.
   - **Grafik Kecepatan Internet Real-Time (Live WAN Traffic)**: Visualisasi *Download (RX)* dan *Upload (TX)* dalam Kbps/Mbps yang diperbarui secara langsung.

5. **Multi-Admin Data Isolation (Privasi Penuh Antar-Admin)**:
   - Super Admin dapat membuat akun untuk `admin1`, `admin2`, dst.
   - Tiap admin memiliki ruang privasi terpisah: `admin1` tidak dapat melihat atau mengedit VPN maupun rule Dst-NAT milik `admin2`.
   - Menggunakan standardisasi penandaan metadata otomatis pada comment router: `[owner:username]`.

6. **Manajemen IP Pool & PPP Profile Privat per Admin**:
   - Setiap admin (`admin1`, `admin2`) dapat membuat **IP Pool** dan **PPP Profile** sendiri secara terisolasi.
   - Mengatur gateway lokal (*local address*), rentang pool klien (*remote address*), DNS Server, dan batasan bandwidth (*rate limit*) untuk masing-masing user VPN.

7. **Generator Script MikroTik (Tinggal Paste ke Terminal)**:
   - **Script Server Setup**: 1-Klik Salin (*Copy*) script inisialisasi server untuk membuat IP Pool, Profile, mengaktifkan SSTP & L2TP server, firewall filter, serta NAT Masquerade di MikroTik server.
   - **Script Klien MikroTik (Router-to-Router)**: Tombol *Copy Script* pada setiap akun user VPN (pilihan koneksi via Domain atau IP Publik) untuk langsung di-paste ke terminal MikroTik klien.

8. **Cross-Admin Port Conflict Notification**:
   - Jika `admin2` mencoba mendaftarkan port forwarding publik yang sudah dipakai oleh `admin1`, sistem otomatis memblokir dan menampilkan notifikasi transparan:
     > `⚠️ Port publik [port] telah digunakan oleh 'admin1'. Silakan gunakan nomor port lain.`

9. **Tampilan Responsif & Dual Theme (Light & Dark Mode)**:
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
│   ├── Database.php            # Koneksi & Skema SQLite PDO (Auto-migration domain)
│   ├── RouterOS.php            # Driver binary socket MikroTik API (Port 8728)
│   ├── Auth.php                # Manajemen sesi, login, & RBAC
│   ├── Helpers.php             # Tagging kepemilikan [owner:xxx] & enkripsi AES-256
│   ├── header.php              # Header & navigasi responsif (Mobile Drawer & Sidebar)
│   └── footer.php              # Footer, Chart.js, & script tema
├── api/
│   ├── traffic.php             # Endpoint polling real-time CPU, RAM, & WAN Speed
│   ├── vpn_action.php          # CRUD PPP Secret, IP Pool, PPP Profile, & Server setting
│   ├── nat_action.php          # CRUD Dst-NAT & validasi bentrok port antar-admin
│   └── theme_action.php        # Sinkronisasi preferensi tema ke database
├── install.php                 # Web Setup Wizard awal
├── index.php                   # Halaman Login
├── dashboard.php               # Dashboard monitoring real-time & grafik internet
├── vpn.php                     # Kelola VPN SSTP, L2TP, IP Pool, Profile & Script Generator
├── nat.php                     # Kelola Port Forwarding (Dst-NAT)
├── admins.php                  # Kelola Sub-Admin (Khusus Super Admin)
├── settings.php                # Pengaturan profil & IP/Domain MikroTik
├── logout.php                  # Keluar sesi
└── README.md                   # Dokumentasi instalasi dan penggunaan
```
