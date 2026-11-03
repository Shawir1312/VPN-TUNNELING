# MikroTik VPN & Dst-NAT Manager (Full PHP + SQLite)

Aplikasi web manajemen VPN (SSTP & L2TP/IPsec) dan Port Forwarding (Dst-NAT) berbasis **MikroTik RouterOS API (Port 8728)** dengan arsitektur **tanpa database jaringan ganda** (MikroTik sebagai *single source of truth*) dan **SQLite** khusus untuk otentikasi admin, multi-tenant data isolation, dan audit log.

---

## 🌟 Fitur Utama & Pembaruan

1. **Privasi Penuh Lintas-Admin (Termasuk Super Admin)**:
   - Super Admin bertugas mengelola akun admin (`admins.php`), reset password, serta konfigurasi router & domain (`settings.php`).
   - **Super Admin TIDAK DAPAT melihat atau mengedit VPN & Dst-NAT milik admin lain**. Setiap admin (termasuk Super Admin) hanya melihat dan mengelola resource miliknya sendiri.

2. **Port Forwarding (Dst-NAT) Cerdas & Fleksibel**:
   - **Acak Port Publik (Random)**: Tombol generator untuk menghasilkan nomor port publik secara acak (10000 - 65000) atau input manual custom.
   - **Pilih dari User VPN**: Target IP internal dapat dipilih langsung dari daftar IP akun User VPN milik admin yang sedang login tanpa perlu mengetik manual.
   - **Template Port Internal Cepat**:
     - 📦 **Winbox (8291)**
     - 🌐 **Web HTTP (80)** & 🔒 **Web HTTPS (443)**
     - ⚙️ **MikroTik API (8728)**
     - 💻 **SSH Terminal (22)**
     - 🖥️ **RDP Remote Desktop (3389)**
   - **Alamat Remote Publik Siap Pakai**: Menampilkan endpoint lengkap (`<domain/ip_publik>:<port>`) dengan tombol 1-klik salin (*Copy*).
   - **Edit Penuh**: Seluruh parameter rule Dst-NAT (Port publik, IP target, Port internal, Protokol, Keterangan) dapat diedit kapan saja dengan proteksi deteksi bentrok port.

3. **Generator Script MikroTik (Tinggal Paste)**:
   - **Script Server**: 1-Klik Salin script inisialisasi server (Pool, Profile, Server SSTP/L2TP, Firewall Allow, NAT Masquerade).
   - **Script Klien**: Tombol *Copy Script* per akun VPN dengan pilihan koneksi via **Domain/DDNS** atau **IP Publik MikroTik**.

4. **Manajemen IP Pool & PPP Profile Privat per Admin**:
   - Masing-masing admin dapat membuat IP Pool dan PPP Profile sendiri yang terisolasi.

5. **Dashboard Real-Time Monitoring**:
   - Beban CPU, RAM/Memori, Uptime router, dan Grafik Kecepatan Internet WAN (RX/TX) setiap 2 detik.

6. **Web Installer Terpadu (`install.php`)**:
   - Setup awal di VPS atau server lokal dengan pemeriksaan otomatis dan panduan langkah demi langkah.

---

## 🚀 Panduan Menjalankan Sistem

### Opsi 1: Menjalankan dengan PHP Built-in Server

```bash
php -S 0.0.0.0:8000
```

Buka di browser:
👉 **`http://localhost:8000/install.php`** (atau `http://IP_SERVER_ANDA:8000/install.php`)

---

### Opsi 2: Di VPS / Server Production (aaPanel, Nginx, Apache)

1. Tarik pembaruan terbaru via Git:
   ```bash
   git pull
   ```
   *(Jika ada konflik lokal di server, jalankan `git reset --hard HEAD && git pull`)*
2. Pastikan permission folder `database` dan `config` dapat ditulis oleh web server:
   ```bash
   chown -R www:www database config
   chmod -R 775 database config
   ```

---

## 🛠️ Persyaratan di Sisi MikroTik RouterOS

Pastikan service API bawaan MikroTik aktif pada port 8728:

```routeros
/ip service print
# Jika status api (port 8728) bertanda 'X' (disabled), aktifkan dengan:
/ip service enable api
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
│   ├── nat_action.php          # CRUD & Edit Dst-NAT + validasi bentrok port antar-admin
│   └── theme_action.php        # Sinkronisasi preferensi tema ke database
├── install.php                 # Web Setup Wizard awal
├── index.php                   # Halaman Login
├── dashboard.php               # Dashboard monitoring real-time & grafik internet
├── vpn.php                     # Kelola VPN SSTP, L2TP, IP Pool, Profile & Script Generator
├── nat.php                     # Kelola Dst-NAT (Random port, template port, edit rule)
├── admins.php                  # Kelola Sub-Admin & Reset Password (Super Admin)
├── settings.php                # Pengaturan profil & IP/Domain MikroTik
├── logout.php                  # Keluar sesi
└── README.md                   # Dokumentasi instalasi dan penggunaan
```
