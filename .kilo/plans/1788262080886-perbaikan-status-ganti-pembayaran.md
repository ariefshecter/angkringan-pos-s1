# Rencana Perbaikan Status Ganti Metode Pembayaran

## Temuan dan Keputusan

- Akar bug ada pada pemakaian alur pembatalan yang sama untuk dua intent berbeda. Form **Pilih metode pembayaran lain** di `resources/views/kiosk/status.blade.php:75` mengirim ke route `kiosk.orders.cancel`; `CheckoutController::cancel()` lalu memanggil `OrderService::cancelPending()`, yang membatalkan transaksi Midtrans dan menulis `payment_status = cancelled`, `order_status = cancelled`, serta `cancelled_at`. Halaman admin hanya menampilkan nilai tersebut, sehingga bukan sumber bug.
- Test saat ini mengabadikan perilaku yang salah: `tests/Feature/DeviceAndOrderFlowTest.php:382-423` dan `:584-633` mengharapkan ganti metode menghasilkan `PAYMENT_CANCELLED`.
- Transaksi pembayaran lama tidak boleh tetap `pending`: tagihan lama akan tetap aktif, stok dapat tertahan/ganda, dan pembayaran ganda dapat terjadi setelah checkout baru dibuat.
- Keputusan domain: ganti metode menutup transaksi lama dengan status terminal baru `replaced` berlabel **Metode pembayaran diganti**. Instruksi Midtrans lama tetap harus dibatalkan sebelum checkout dipulihkan, tetapi record lokal tidak boleh diberi status **Dibatalkan**.
- Status `cancelled` tetap sah untuk tombol pelanggan **Batalkan pesanan**, pembatalan Admin/Kasir, dan notifikasi resmi `cancel` dari Midtrans. Status `failed` dan `expired` tetap dipertahankan untuk kegagalan/masa berlaku masing-masing.
- Tidak ada perubahan skema yang diperlukan karena kolom status berupa string. Gunakan status transisi singkat `replacing` untuk menutup race dengan webhook Midtrans, lalu status final `replaced`. Record historis yang sudah salah berstatus `cancelled` tidak dapat dibedakan secara andal dari pembatalan nyata, sehingga tidak dilakukan backfill otomatis.

## Langkah Implementasi

1. Pisahkan endpoint ganti metode dari endpoint pembatalan di `routes/web.php` dan `app/Http/Controllers/CheckoutController.php`.
   - Ganti route/action ambigu `orders.cancel`/`cancel()` untuk tombol ganti metode menjadi route khusus, misalnya `POST /orders/{orderNumber}/change-payment`, nama `kiosk.orders.change-payment`, dan action `changePayment()`.
   - Pertahankan `orders.cancel-order`/`cancelOrder()` sebagai satu-satunya aksi pembatalan penuh dari UI pelanggan.
   - Pada `changePayment()`, izinkan hanya transaksi `pending` milik perangkat aktif, panggil operasi service khusus penggantian metode, verifikasi hasilnya `replaced`, tandai sesi sebagai payment-change, pulihkan item ke cart, isi kembali nama/catatan, lalu arahkan ke checkout dengan pesan yang tidak menyebut pesanan dibatalkan.
   - Jika status berubah secara konkuren atau pembatalan Midtrans gagal, jangan pulihkan cart dan jangan ubah status lokal; kembali ke halaman status dengan error agar tagihan/stok lama tetap konsisten.

2. Bentuk transisi domain khusus di `app/Services/OrderService.php`.
   - Tambahkan `replacePending()` yang lebih dulu mengklaim transaksi `pending` secara atomik sebagai `replacing`, tanpa melepas stok. Untuk transaksi Midtrans, setelah intent tersimpan, batalkan instruksi pembayaran lama; hanya setelah sukses, tutup record lokal sebagai `replaced` dan lepaskan stok. Untuk tunai, transisi dapat diselesaikan dalam database tanpa panggilan gateway.
   - Jika cancel Midtrans gagal, kembalikan `replacing` menjadi `pending`/`new` hanya bila belum ada transisi lain; reservasi stok tidak disentuh. Jika webhook sudah memenangkan race dan mengubah transaksi menjadi `paid` atau `replaced`, jangan menimpa hasil tersebut.
   - Ekstrak bagian atomik yang mengunci transaksi/item/menu dan melepaskan `reserved_stock` ke helper privat yang dapat dipakai oleh pembatalan dan replacement tanpa menduplikasi logika. Pemeriksaan status di bawah `lockForUpdate()` harus menjadi guard idempoten agar double-submit atau webhook konkuren tidak melepas stok dua kali.
   - Untuk replacement, tulis `payment_status = replaced`, `order_status = replaced`, dan jangan isi `cancelled_at`; untuk pembatalan eksplisit/staf/gateway, perilaku `cancelled` tetap mengisi status/timestamp pembatalan. Pertahankan semantik `failed` dan `expired` tanpa mengubahnya menjadi `cancelled`.
   - Pertahankan `cancelPending()` untuk pembatalan sungguhan dan expiry yang perlu menghubungi Midtrans. `confirmCash()` dan `acceptPaid()` tidak berubah; `markPaid()` hanya diperluas agar settlement valid dapat memenangkan race dari status `replacing`.
   - Jangan mengubah cleanup kegagalan pembuatan Midtrans di luar penyesuaian helper status yang diperlukan; khususnya, jika cleanup Midtrans gagal, transaksi dan reservasi harus tetap `pending` serta pengguna kembali ke halaman status seperti kontrak existing.

3. Tambahkan semantik status di `app/Models/Transaction.php`.
   - Tambahkan konstanta `PAYMENT_REPLACING`/`ORDER_REPLACING` bernilai `replacing` dan `PAYMENT_REPLACED`/`ORDER_REPLACED` bernilai `replaced`.
   - Petakan `replacing` ke label **Mengganti metode pembayaran** dan `replaced` ke **Metode pembayaran diganti**; tidak satu pun boleh memakai label **Dibatalkan**.
   - Masukkan `PAYMENT_REPLACED` ke status terminal untuk pelanggan agar polling tidak memperlakukan tagihan lama sebagai aktif; mekanisme `GuestSessionService::preserveForPaymentChange()` tetap mencegah rotasi sesi selama checkout pengganti berlangsung.

4. Jadikan webhook sadar terhadap intent replacement di `app/Http/Controllers/MidtransWebhookController.php`.
   - Jika notifikasi `cancel` tiba saat transaksi `replacing`, finalisasi sebagai `replaced`, bukan `cancelled`; ini menutup race saat Midtrans mengirim callback sebelum request ganti metode selesai.
   - Izinkan settlement/capture yang valid memenangkan race dari `pending` maupun `replacing`, mengubah transaksi menjadi `paid` dan mempertahankan stok sebagai penjualan; request ganti metode kemudian harus mendeteksi hasil non-`replaced` dan tidak membuat checkout/cart pengganti.
   - Notifikasi `cancel` tanpa intent `replacing` tetap menjadi `cancelled`; `deny` tetap `failed`, `expire` tetap `expired`, dan notifikasi berulang tetap idempoten.

5. Hubungkan UI ke intent yang benar dan tampilkan status baru.
   - Di `resources/views/kiosk/status.blade.php`, arahkan form **Pilih metode pembayaran lain** ke route `kiosk.orders.change-payment`; ubah teks konfirmasi/sukses agar menjelaskan instruksi pembayaran lama akan diganti tanpa menyebut pesanan dibatalkan.
   - Pertahankan form dan tombol overlay Snap **Batalkan pesanan** menuju `cancel-order-form`, sehingga hanya aksi tersebut menjalankan pembatalan penuh dari sisi pelanggan.
   - Tambahkan penanganan polling `replacing` dan `replaced`: selama `replacing`, nonaktifkan aksi duplikat dan tampilkan **Sedang mengganti metode pembayaran**; pada `replaced`, sembunyikan tombol bayar/VA/form pending, tampilkan informasi **Metode pembayaran diganti**, dan sediakan jalur kembali ke checkout tanpa menampilkan status error **Pesanan tidak berhasil**.
   - Tambahkan filter/label `replaced` sebagai **Metode Diganti** di `resources/views/admin/orders/index.blade.php` serta fallback eksplisit pada ringkasan mobile `resources/views/dashboard.blade.php`. Detail admin otomatis memakai accessor model untuk payment/order label dan tidak boleh lagi menampilkan **Dibatalkan** pada replacement.

6. Perbarui dan perluas `tests/Feature/DeviceAndOrderFlowTest.php` agar menguji kontrak perilaku, bukan nama implementasi lama.
   - Ubah test ganti metode tunai: route khusus mengarah ke checkout, transaksi lama menjadi `PAYMENT_REPLACED`/`ORDER_REPLACED`, `cancelled_at` null, cart dan reservasi dipulihkan tanpa mengubah stok fisik, serta sesi pelanggan tetap sama saat transaksi baru dibuat.
   - Ubah test ganti metode digital (QRIS/Snap) dan tambahkan cakupan VA: `MidtransService::cancelTransaction()` dipanggil tepat sekali, lalu status lokal menjadi `replaced`, bukan `cancelled`; checkout berikutnya tetap dapat memilih tunai, QRIS, atau bank transfer.
   - Tambahkan test bahwa kegagalan cancel Midtrans saat ganti metode mempertahankan `pending`, tidak membuat cart baru, tidak melepaskan reservasi, dan menampilkan error.
   - Tambahkan test race webhook: `cancel` pada transaksi `replacing` berakhir `replaced`, settlement pada transaksi `replacing` berakhir `paid`, stok hanya dilepas/dikomit sekali, dan respons controller tidak memulihkan checkout jika pembayaran sudah menang.
   - Pertahankan/pertegas test pembatalan penuh tunai dan digital: tombol/endpoint `cancel-order` menghasilkan `PAYMENT_CANCELLED`/`ORDER_CANCELLED`, mengisi `cancelled_at`, membersihkan cart, melepas reservasi, merotasi sesi, dan membatalkan Midtrans untuk pembayaran digital.
   - Tambahkan assertion halaman admin/filter untuk replacement menampilkan **Metode pembayaran diganti** dan tidak menampilkan **Dibatalkan** pada record tersebut.
   - Pertahankan test cleanup kegagalan pembuatan Midtrans, terutama kontrak bahwa cleanup yang gagal tetap menghasilkan `pending` dan tidak melepaskan reservasi.
   - Pertahankan test pembatalan Admin/Kasir dan webhook `cancel`, `deny`, `expire`, settlement idempoten, pembayaran tunai, serta penerimaan digital untuk memastikan sumber status sah dan alur lain tidak berubah.

## Validasi

1. Jalankan test fokus: `php artisan test --filter=DeviceAndOrderFlowTest`.
2. Jalankan seluruh suite: `php artisan test`.
3. Periksa format tanpa mengubah file: `vendor/bin/pint --test`.
4. Validasi route baru dan tidak adanya referensi route ambigu lama: `php artisan route:list --name=kiosk.orders` dan pencarian `kiosk.orders.cancel`/`cancelForPaymentChange`.
5. Jalankan `npm run build` untuk memastikan Blade/JavaScript yang memuat polling dan modal konfirmasi tetap terkompilasi.
6. Smoke-test manual untuk matriks: tunai -> QRIS, tunai -> VA, QRIS -> tunai, QRIS -> VA, VA -> tunai, pembatalan penuh dari halaman status, tombol pembatalan di dalam Snap, kegagalan cancel Midtrans, dan webhook settlement/cancel yang datang saat halaman status melakukan polling.

## Risiko dan Batasan

- Pembatalan Midtrans dan update database tidak dapat menjadi satu transaksi atomik. Status transisi `replacing` menjadi penanda intent sebelum efek eksternal; finalisasi/revert menggunakan lock dan compare-state. Ini mencegah callback `cancel` dari aksi ganti metode salah dicatat sebagai pembatalan serta mencegah pelepasan stok jika settlement memenangkan race.
- Stok dilepas dari transaksi lama lalu direservasi kembali oleh cart. `CartService` saat ini dapat gagal memulihkan sebagian item bila kiosk lain mengambil stok di sela-sela; perilaku defensif existing ini dipertahankan dan pesan/error cart perlu diverifikasi oleh test, bukan diubah di luar lingkup bug.
- Working tree sudah berisi banyak perubahan yang belum di-commit, termasuk file checkout terkait. Implementasi harus mengedit keadaan saat ini secara minimal dan tidak mengembalikan perubahan lain milik pengguna.
