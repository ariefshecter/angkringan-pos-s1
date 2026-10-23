# Angkringan S1 - POS & Self-Service Kiosk System

Sistem Point of Sale (POS) dan Kiosk Pemesanan Mandiri (*Self-Service Kiosk*) untuk UMKM Angkringan S1 berbasis **Laravel 12**, **Tailwind CSS**, **Alpine.js**, dan **Midtrans Payment Gateway**.

Aplikasi ini mendukung alur pemesanan langsung dari kiosk pelanggan, pembayaran tunai di kasir maupun non-tunai (QRIS & Virtual Account multi-bank otomatis via Midtrans), manajemen menu & stok real-time, cetak struk thermal & ekspor laporan penjualan PDF, serta panel administrasi terintegrasi.

---

## Daftar Isi
- [Fitur Utama](#fitur-utama)
- [Hak Akses & Kredensial Default](#hak-akses--kredensial-default)
- [Prasyarat Sistem](#prasyarat-sistem)
- [Panduan Instalasi & Menjalankan di Lokal](#panduan-instalasi--menjalankan-di-lokal)
  - [Metode 1: Menjalankan dengan Docker (Direkomendasikan)](#metode-1-menjalankan-dengan-docker-direkomendasikan)
  - [Metode 2: Menjalankan Secara Native](#metode-2-menjalankan-secara-native)
- [Konfigurasi Midtrans Payment Gateway](#konfigurasi-midtrans-payment-gateway)
  - [1. Mendapatkan Kunci Akses dari Midtrans](#1-mendapatkan-kunci-akses-dari-midtrans)
  - [2. Mengatur Kredensial via Web Admin (Tanpa Edit Kode/.env)](#2-mengatur-kredensial-via-web-admin-tanpa-edit-kodeenv)
  - [3. Perbedaan Mode Sandbox vs Production](#3-perbedaan-mode-sandbox-vs-production)
  - [4. Menguji Koneksi Midtrans Tanpa Transaksi](#4-menguji-koneksi-midtrans-tanpa-transaksi)
- [Konfigurasi Payment Notification URL (Webhook)](#konfigurasi-payment-notification-url-webhook)
  - [Apa itu Notification URL?](#apa-itu-notification-url)
  - [Cara Setting di Dashboard Midtrans](#cara-setting-di-dashboard-midtrans)
  - [Pengujian Webhook di Lingkungan Lokal (Localhost)](#pengujian-webhook-di-lingkungan-lokal-localhost)
- [Lisensi](#lisensi)

---

## Fitur Utama

- **Self-Service Kiosk Pelanggan**:
  - Antarmuka pemesanan mandiri responsif & ramah layar sentuh (*touchscreen*).
  - Pilihan metode pembayaran: Tunai di Kasir, QRIS (GoPay, OVO, Dana, ShopeePay, LinkAja), dan Virtual Account (BCA, BNI, BRI, CIMB Niaga, Permata).
  - Integrasi Midtrans Snap & Core API dengan pembaruan status pembayaran otomatis.
  - Sistem penguncian & reservasi stok keranjang (*cart reservation*) untuk mencegah *overselling*.
- **Manajemen Operasional Kasir**:
  - Konfirmasi pembayaran tunai pesanan kiosk.
  - Pemantauan antrean pesanan real-time dengan notifikasi suara.
  - Cetak struk kasir (*thermal receipt*) dan download struk berformat PDF.
- **Panel Administrator**:
  - Dashboard performa penjualan & ringkasan pendapatan harian.
  - Kelola data menu, varian harga, kategori, dan stok bahan.
  - Kelola akun kasir & perangkat Kiosk (aktivasi perangkat dengan kode unik sekali pakai).
  - Laporan penjualan harian/bulanan dengan ekspor PDF rekapitulasi.
  - **Pengaturan Midtrans Dinamis**: Mengubah Merchant ID, Client Key, Server Key, kanal pembayaran, dan mode Sandbox/Production langsung dari web admin tanpa perlu merestart server atau menyentuh file `.env`.
  - **Fitur Uji Koneksi Midtrans**: Tombol diagnostik untuk memverifikasi validitas *Server Key* ke server Midtrans secara instan.

---

## Hak Akses & Kredensial Default

Setelah database di-seed, akun berikut otomatis tersedia:

| Peran / Fitur | Username / Kode | Password | Akses URL |
|---|---|---|---|
| **Administrator** | `admin` | `password` | `http://localhost:8000/login` |
| **Kasir** | `kasir` | `password` | `http://localhost:8000/login` |
| **Aktivasi Kiosk** | `KIOSK-S1-2026` | *(tanpa password)* | `http://localhost:8000/device/activate` |

---

## Prasyarat Sistem

Pilih salah satu metode eksekusi:

### A. Jika Menggunakan Docker (Paling Praktis)
- Docker Engine >= 20.10 & Docker Compose v2.

### B. Jika Menggunakan Native di Host
- PHP >= 8.2 (dengan ekstensi `pdo_sqlite`, `curl`, `mbstring`, `openssl`, `tokenizer`, `xml`, `fileinfo`)
- Composer >= 2.x
- Node.js >= 18.x & npm
- Git

---

## Panduan Instalasi & Menjalankan di Lokal

### Metode 1: Menjalankan dengan Docker (Direkomendasikan)

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/ariefshecter/angkringan-pos-s1.git
   cd angkringan-pos-s1
   ```

2. **Salin File Environment**:
   ```bash
   cp .env.example .env
   ```

3. **Install Dependensi Composer & Build Aset Frontend**:
   ```bash
   # Install PHP vendor via container Composer
   docker run --rm -u $(id -u):$(id -g) -v $(pwd):/app -w /app composer:2 composer install --ignore-platform-reqs

   # Install Node dependencies & build aset Vite
   npm install
   npm run build
   ```

4. **Inisialisasi Database SQLite & Kunci Aplikasi**:
   ```bash
   # Buat file database SQLite
   touch database/database.sqlite

   # Generate APP_KEY
   docker run --rm -u $(id -u):$(id -g) -v $(pwd):/app -w /app php:8.2-cli php artisan key:generate

   # Jalankan migrasi dan seeder awal
   docker run --rm -u $(id -u):$(id -g) -v $(pwd):/app -w /app php:8.2-cli php artisan migrate --seed --force

   # Hubungkan storage public
   docker run --rm -u $(id -u):$(id -g) -v $(pwd):/app -w /app php:8.2-cli php artisan storage:link
   ```

5. **Jalankan Aplikasi**:
   ```bash
   docker run -d --name angkringan_app \
     -p 8000:8000 \
     --restart=unless-stopped \
     -u $(id -u):$(id -g) \
     -v $(pwd):/app \
     -w /app \
     php:8.2-cli php artisan serve --host=0.0.0.0 --port=8000
   ```
   Aplikasi kini berjalan di **`http://localhost:8000`**.

---

### Metode 2: Menjalankan Secara Native

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/ariefshecter/angkringan-pos-s1.git
   cd angkringan-pos-s1
   ```

2. **Setup File Environment**:
   ```bash
   cp .env.example .env
   ```

3. **Install Dependensi PHP & JavaScript**:
   ```bash
   composer install
   npm install
   npm run build
   ```

4. **Setup Database & Kunci Aplikasi**:
   ```bash
   touch database/database.sqlite
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   ```

5. **Jalankan Server Lokal**:
   ```bash
   php artisan serve --port=8000
   ```
   Buka peramban di **`http://localhost:8000`**.

---

## Konfigurasi Midtrans Payment Gateway

Aplikasi telah dilengkapi fitur **Manajemen Pengaturan Midtrans Terintegrasi** pada Web Admin, sehingga Anda tidak perlu lagi mengedit file kode atau file `.env` setiap kali berganti akun.

### 1. Mendapatkan Kunci Akses dari Midtrans

1. Masuk ke [Merchant Administration Portal (MAP) Midtrans](https://dashboard.midtrans.com/) (atau [Sandbox Midtrans](https://dashboard.sandbox.midtrans.com/)).
2. Pastikan Anda memilih mode yang diinginkan (*Sandbox* di pojok kiri atas untuk uji coba, atau *Production* untuk transaksi riil).
3. Buka menu **Settings** &rarr; **Access Keys**.
4. Catat tiga informasi utama:
   - **Merchant ID** (contoh: `G123456789`)
   - **Client Key** (contoh Sandbox: `SB-Mid-client-xxxx`, contoh Production: `Mid-client-xxxx`)
   - **Server Key** (contoh Sandbox: `SB-Mid-server-xxxx`, contoh Production: `Mid-server-xxxx`)

### 2. Mengatur Kredensial via Web Admin (Tanpa Edit Kode/.env)

1. Login ke website sebagai Administrator:
   - Buka `http://localhost:8000/login`
   - Masukkan Username: `admin` | Password: `password`
2. Di sidebar navigasi sebelah kiri, klik menu **Pengaturan Midtrans** (atau akses `http://localhost:8000/admin/settings/midtrans`).
3. Tentukan opsi:
   - **Mode Lingkungan**: Pilih **Sandbox** (Uji Coba) atau **Production** (Live).
   - **Merchant ID**: Masukkan Merchant ID Anda.
   - **Client Key**: Masukkan Client Key.
   - **Server Key**: Masukkan Server Key (tersedia tombol untuk menampilkan/menyembunyikan teks kunci).
   - **Metode Pembayaran**: Centang kanal yang aktif pada akun Midtrans Anda (QRIS, BCA VA, BNI VA, BRI VA, CIMB Niaga VA, Permata VA).
4. Klik **Simpan Pengaturan Midtrans**. Nilai akan disimpan ke database dan langsung digunakan oleh seluruh proses transaksi saat itu juga.

### 3. Perbedaan Mode Sandbox vs Production

| Aspek | Mode Sandbox (Uji Coba) | Mode Production (Live) |
|---|---|---|
| **Tujuan** | Simulasi pengembangan & skripsi/demo tanpa uang riil | Transaksi bisnis nyata di toko |
| **Prefix Kunci** | Wajib diawali `SB-Mid-server-` dan `SB-Mid-client-` | Diawali `Mid-server-` dan `Mid-client-` (tanpa `SB-`) |
| **Snap JS Script** | `https://app.sandbox.midtrans.com/snap/snap.js` | `https://app.midtrans.com/snap/snap.js` |
| **Simulator Bayar** | [Midtrans Payment Simulator](https://simulator.sandbox.midtrans.com/) | Aplikasi m-Banking / E-Wallet riil pengguna |

### 4. Menguji Koneksi Midtrans Tanpa Transaksi

Pada halaman **Pengaturan Midtrans** di admin panel, terdapat tombol **"Uji Koneksi Midtrans"**:
- Sistem akan mengirimkan probe autentikasi ke server API Midtrans menggunakan *Server Key* yang Anda ketikkan.
- **Hasil Sukses**: Menampilkan notifikasi hijau bahwa Server Key terautentikasi oleh Midtrans.
- **Hasil Gagal**: Menampilkan alasan penolakan (misalnya HTTP 401 Unauthorized bila Server Key keliru, atau peringatan bila format prefix kunci tidak sesuai dengan mode yang dipilih).

---

## Konfigurasi Payment Notification URL (Webhook)

### Apa itu Notification URL?

**Payment Notification URL (Webhook HTTP POST)** adalah pintu komunikasi otomatis antara server Midtrans dan website Angkringan S1.

Ketika pelanggan membayar via QRIS atau transfer Bank VA:
1. Midtrans mendeteksi dana masuk.
2. Midtrans secara instan mengirimkan data transaksi (JSON payload) ke URL notifikasi sistem Anda:
   ```text
   http://<domain-server-anda>/midtrans/notification
   ```
3. Sistem secara otomatis memverifikasi keaslian signature dan mengubah status pesanan dari `pending` menjadi `paid`/`settlement`, memotong kuota reservasi stok menjadi pengurangan stok final, dan mengizinkan kasir mencetak struk pesanan.

### Cara Setting di Dashboard Midtrans

1. Buka [Dashboard Midtrans](https://dashboard.midtrans.com/) (atau Sandbox).
2. Masuk ke menu **Settings** &rarr; **Configuration**.
3. Pada kolom **Payment Notification URL**, masukkan URL notifikasi website Anda:
   ```text
   https://domain-anda.com/midtrans/notification
   ```
4. Pastikan kolom **Append to URL Notification** tidak dicentang jika ingin menggunakan satu URL tunggal yang konsisten.
5. Klik **Save Changes** di bagian bawah halaman.

### Pengujian Webhook di Lingkungan Lokal (Localhost)

Karena server Midtrans berada di cloud internet publik, Midtrans **tidak dapat mengakses alamat `http://localhost:8000` atau `http://127.0.0.1` secara langsung**.

Untuk menguji alur webhook notifikasi otomatis saat menjalankan di laptop:

1. **Gunakan Tunneling (Contoh: Ngrok)**:
   ```bash
   # Jalankan ngrok pada port aplikasi lokal
   ngrok http 8000
   ```
   Ngrok akan memberikan domain HTTPS publik sementara, misalnya:
   `https://a1b2-c3d4.ngrok-free.app`

2. **Salin URL Notifikasi ke Midtrans**:
   Masukkan URL forwarding ngrok tersebut ke pengaturan Midtrans:
   ```text
   https://a1b2-c3d4.ngrok-free.app/midtrans/notification
   ```

3. **Uji Pembayaran**:
   Lakukan checkout QRIS/Virtual Account di Kiosk & bayar menggunakan [Midtrans Sandbox Simulator](https://simulator.sandbox.midtrans.com/). Status pesanan pada layar Kiosk dan Kasir akan otomatis berganti ke "Sudah Dibayar" dalam hitungan detik.

---

## Menjalankan Pengujian Otomatis

Proyek ini dilengkapi rangkaian pengujian unit dan fitur komprehensif (termasuk skenario otentikasi staf, flow kiosk, reservasi stok, dan pengaturan Midtrans):

```bash
docker run --rm -u $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app php:8.2-cli php artisan test
```

---

## Lisensi

Proyek ini dikembangkan di bawah lisensi [MIT](LICENSE).
