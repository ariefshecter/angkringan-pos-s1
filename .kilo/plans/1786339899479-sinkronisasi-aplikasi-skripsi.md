# Rencana Sinkronisasi Aplikasi dengan Skripsi

## Sasaran dan Keputusan

- Refactor aplikasi Laravel agar entitas bisnis memakai tabel fisik singular berbahasa Indonesia: `admin`, `kasir`, `user`, `kategori`, `menu`, `transaksi`, `detail_transaksi`, dan `laporan`.
- Nama kolom tetap berbahasa Inggris agar kode tetap konsisten; kamus data skripsi harus diperbarui setelah implementasi agar memakai nama field aktual.
- Database dibangun ulang kosong (`migrate:fresh --seed`); data lokal lama tidak dimigrasikan.
- Hapus tabel autentikasi staf `users`; buat tabel dan model autentikasi `Admin` serta `Kasir`, dan gunakan tabel `user` hanya untuk pelanggan guest.
- Pertahankan tabel teknis dan fitur yang sudah benar: `devices`, `cart_reservations`, `payment_notifications`, `sessions`, `cache`, `jobs`, aktivasi perangkat, reservasi stok, row locking, webhook Midtrans, QRIS/Virtual Account, dan polling otomatis.
- Admin dan Kasir sama-sama boleh menangani transaksi. Admin juga mengelola kategori, menu/stok, perangkat, akun Kasir, dan laporan.
- Pertahankan `payment_status` dan `order_status`; Pending/Paid/Cancelled pada skripsi merujuk ke status pembayaran, dengan `failed` dan `expired` sebagai status teknis tambahan.
- Struk tersedia sebagai PDF dan halaman thermal print. Laporan harian/bulanan juga dapat diekspor PDF.
- Tabel `laporan` menyimpan snapshot manual; penyimpanan ulang rentang yang sama memperbarui snapshot tersebut.

## Skema Database

1. Susun ulang migration awal agar fresh install langsung menghasilkan skema akhir, bukan rangkaian rename sementara.
2. Buat `admin` dengan `id`, `name`, `username`, `email`, `password`, `is_active`, `remember_token`, dan timestamps. Terapkan unique per tabel pada username/email.
3. Buat `kasir` dengan struktur autentikasi yang sama. Tambahkan validasi aplikasi agar username unik secara global di gabungan `admin` dan `kasir`.
4. Buat `user` pelanggan dengan `session_id` string sebagai primary key, `name`, dan timestamps.
5. Rename/recreate tabel bisnis berikut sambil mempertahankan seluruh kolom teknis yang masih dipakai:
   - `categories` menjadi `kategori`.
   - `menus` menjadi `menu`, dengan FK `category_id` ke `kategori` serta `reserved_stock`, `slug`, `description`, `image_path`, dan `is_available` tetap ada.
   - `transactions` menjadi `transaksi`.
   - `transaction_items` menjadi `detail_transaksi`.
6. Pada `transaksi`, pertahankan nomor pesanan, snapshot nama pelanggan, subtotal/total, metode/channel/status pembayaran, status pesanan, seluruh metadata Midtrans, expiry, timestamps proses, catatan, dan relasi device. Tambahkan:
   - `user_session_id` sebagai FK ke `user.session_id`.
   - `admin_id` dan `kasir_id` nullable sebagai FK aktor penangan transaksi.
   - Check/validasi domain agar paling banyak satu dari `admin_id` atau `kasir_id` terisi.
   - Hilangkan `handled_by` karena FK tersebut tidak lagi valid setelah tabel `users` dihapus.
7. Arahkan `detail_transaksi.transaction_id` ke `transaksi` dan `menu_id` ke `menu`; pertahankan snapshot `menu_name`, `price`, `quantity`, dan `subtotal` agar riwayat tidak berubah saat menu diedit/dihapus.
8. Buat `laporan` dengan `period_start`, `period_end`, `total_revenue`, `transaction_count`, `admin_id`, dan timestamps. Tambahkan unique gabungan periode agar satu rentang hanya memiliki satu snapshot.
9. Sesuaikan FK tabel teknis:
   - `cart_reservations.menu_id` ke `menu`.
   - `payment_notifications.transaction_id` ke `transaksi`.
   - `devices` tetap berelasi dengan `transaksi` dan reservasi.
10. Evaluasi metadata `sessions.user_id` yang menjadi ambigu pada multi-guard; jangan beri FK ke tabel pelanggan. Bila dibutuhkan untuk audit, tambahkan jenis guard/staf tanpa menghubungkannya ke `user` guest.
11. Perbarui seluruh model, `$table`, primary key nonstandar, relasi, factory, dan seeder. Seeder membuat minimal satu Admin, satu Kasir, kategori awal, serta device Admin/Kiosk menggunakan kredensial environment yang tidak sensitif.

## Autentikasi dan Otorisasi

1. Jadikan `Admin` dan `Kasir` implementasi `Authenticatable`; jadikan model pelanggan guest model biasa dan hentikan penggunaan `App\Models\User` sebagai akun staf.
2. Konfigurasikan provider dan session guard `admin` serta `kasir` pada `config/auth.php`.
3. Pertahankan satu halaman login berbasis `username`. Login mencoba tabel Admin lalu Kasir, hanya menerima akun aktif, menetapkan guard aktif di sesi, meregenerasi session ID, dan memakai rate limit berbasis username + IP.
4. Pastikan username tidak dapat sama lintas dua tabel agar login tidak ambigu.
5. Ubah middleware staf, admin-only, route model access, logout, profile, perubahan password, dan `current_password` agar memakai guard aktif.
6. Hapus registrasi publik dan verifikasi email yang tidak relevan. Pertahankan lupa password publik hanya untuk Admin melalui email; Kasir meminta Admin melakukan reset.
7. Tambahkan modul Admin untuk daftar/tambah/edit/aktif-nonaktif/reset password akun Kasir. Jangan sediakan penghapusan akun sendiri pada halaman profil.
8. Terapkan matriks akses:
   - Admin dan Kasir: dashboard, feed transaksi, riwayat/detail, konfirmasi cash, terima pesanan digital Paid, batalkan Pending, thermal print, unduh struk PDF.
   - Admin saja: kategori, menu/stok, perangkat, akun Kasir, laporan, snapshot, dan ekspor laporan PDF.

## Pelanggan dan Transaksi

1. Saat checkout, gunakan ID sesi Laravel/browser sebagai `user.session_id`; buat/perbarui record guest dengan nama input pelanggan.
2. Simpan `user_session_id` dan `customer_name` snapshot pada setiap transaksi.
3. Pertahankan validasi stok, `cart_reservations`, `reserved_stock`, `lockForUpdate`, dan transaksi database. Jangan menggantinya dengan validasi stok sederhana karena mekanisme ini mencegah overselling antar-kiosk.
4. Pertahankan alur cash: transaksi Pending menahan reservasi, konfirmasi Admin/Kasir secara atomik mengubah pembayaran menjadi Paid, mengurangi `stock` dan `reserved_stock`, mengisi salah satu FK staf, serta memulai status pemrosesan.
5. Pertahankan alur digital: backend membuat Snap QRIS atau Core API Virtual Account; webhook memvalidasi signature dan nominal, mencatat payload audit, idempotently mengubah Paid, dan mengurangi stok tanpa intervensi staf.
6. Tombol “terima pesanan digital” hanya mengisi staf penangan, `confirmed_at`, dan `order_status`; tombol itu tidak boleh menandai pembayaran digital Paid.
7. Pertahankan pembatalan Pending oleh pelanggan maupun staf. Pembatalan digital harus lebih dahulu berhasil dibatalkan di Midtrans sebelum status lokal dan reservasi dilepas.
8. Rotasi sesi pelanggan ketika pesanan benar-benar selesai/diterima atau dibatalkan/gagal/kedaluwarsa, biasanya pada request kiosk berikutnya. Jangan rotasi ketika pelanggan hanya membatalkan pembayaran untuk mengganti metode.
9. Pertahankan pembatasan transaksi kiosk berdasarkan `device_id` dan jangan mengekspos order hanya berdasarkan nomor publik.
10. Pertahankan polling katalog/stok/status/feed sebagai implementasi “real-time tanpa refresh”, dengan interval dan penanganan gagal jaringan yang tidak mengubah data.

## Laporan dan PDF

1. Ubah laporan dari pilihan “7 hari/12 bulan” menjadi mode Harian dan Bulanan:
   - Harian menerima tanggal pilihan.
   - Bulanan menerima bulan dan tahun pilihan.
   - Hanya transaksi dengan `payment_status = paid` dan `paid_at` dalam periode yang dihitung.
2. Tampilkan total pendapatan, jumlah transaksi, rata-rata, perbandingan cash/digital, dan grafik/rincian periode terpilih.
3. Tambahkan aksi `Simpan Rekap` Admin yang melakukan `updateOrCreate` berdasarkan `period_start + period_end`, menghitung ulang total, jumlah transaksi, dan pencatat Admin.
4. Tambahkan daftar/detail snapshot agar data tabel `laporan` benar-benar digunakan, bukan hanya dipenuhi secara nominal.
5. Tambahkan library PDF Laravel yang kompatibel dengan Laravel 12. Gunakan template server-side terpisah yang sederhana dan deterministik.
6. Sediakan endpoint PDF struk hanya untuk transaksi Paid, tanpa menghapus halaman thermal print dan pencatatan `printed_at` yang sudah ada.
7. Sediakan endpoint PDF laporan untuk hasil periode aktif dan/atau snapshot tersimpan; akses Admin saja dan semua angka harus berasal dari query/snapshot yang sama dengan tampilan.

## Penyesuaian Kode dan UI

1. Perbarui seluruh controller/service/model/view agar memakai model dan relasi baru, khususnya `OrderService`, `CartService`, webhook, dashboard, laporan, receipt, middleware, profile, dan autentikasi.
2. Pertahankan layout responsive Tailwind yang sudah ada. Ubah label “Dashboard Admin” menjadi label sesuai guard aktif, dan tampilkan menu navigasi berdasarkan matriks akses.
3. Tambahkan UI pengelolaan Kasir, filter laporan harian/bulanan, simpan/riwayat snapshot, unduh PDF struk, dan unduh PDF laporan.
4. Pastikan seluruh pesan memakai istilah konsisten: Admin, Kasir, Pelanggan, Pending, Paid/Lunas, Cancelled/Dibatalkan, Failed/Gagal, dan Expired/Kedaluwarsa.

## Pengujian dan Verifikasi

1. Tulis ulang factory/test yang masih memakai `User`/role dan nama tabel Inggris.
2. Tambahkan feature test autentikasi: login Admin/Kasir satu form, username global, akun nonaktif, rate limit, guard aktif, logout, profile/password, reset Admin, dan reset Kasir oleh Admin.
3. Tambahkan authorization test untuk matriks akses, terutama Kasir tidak dapat mengelola master data/perangkat/akun/laporan dan guest tidak dapat mengakses backend.
4. Pertahankan dan adaptasi test alur kiosk, device activation, reservasi lintas kiosk, reservation expiry, checkout cash/QRIS/VA, kegagalan Midtrans, cancel, webhook signature/nominal/idempotency, dan pemotongan stok tepat satu kali.
5. Tambahkan test relasi guest session + snapshot nama dan rotasi sesi pada terminal state, termasuk pengecualian saat mengganti metode pembayaran.
6. Tambahkan test audit staf agar tepat satu FK Admin/Kasir terisi setelah transaksi ditangani.
7. Tambahkan test laporan harian/bulanan, isolasi hanya transaksi Paid, snapshot create/update untuk periode sama, akses Admin, dan angka PDF identik dengan halaman.
8. Tambahkan test struk thermal dan PDF: hanya Paid, respons MIME/filename benar, serta transaksi Pending ditolak.
9. Jalankan `php artisan migrate:fresh --seed`, `php artisan test`, `vendor/bin/pint --test`, dan `npm run build`.
10. Lakukan smoke test manual sandbox Midtrans untuk QRIS/VA dan webhook HTTPS karena SDK eksternal tidak dapat divalidasi penuh hanya dengan mock test.

## Risiko dan Catatan Skripsi

- Kamus data skripsi wajib diperbarui setelah implementasi karena nama kolom diputuskan tetap Inggris dan jumlah tabel aktual melebihi delapan akibat tabel teknis.
- Koreksi kalimat “enam tabel utama” menjadi delapan tabel bisnis, lalu jelaskan tabel teknis pendukung secara terpisah.
- Tambahkan `user_session_id`, `admin_id`, dan `kasir_id` pada Class Diagram/ERD agar relasi yang diuraikan benar-benar terlihat.
- Use Case harus menunjukkan Admin dan Kasir sama-sama dapat menangani transaksi sesuai keputusan, dengan hak tambahan Admin untuk master data, perangkat, akun Kasir, dan laporan.
- Activity/Sequence Diagram harus membedakan reservasi stok saat keranjang/Pending dari pengurangan stok final saat Paid.
- Jelaskan bahwa “real-time” diimplementasikan sebagai polling berkala, bukan WebSocket.
- Jelaskan bahwa QRIS memakai Midtrans Snap, Virtual Account memakai Core API, dan callback/webhook divalidasi serta idempotent.
- Rollback dilakukan melalui rebuild database karena strategi yang dipilih adalah fresh install; backup data wajib jika perintah pernah dijalankan pada database berisi data yang perlu disimpan.
