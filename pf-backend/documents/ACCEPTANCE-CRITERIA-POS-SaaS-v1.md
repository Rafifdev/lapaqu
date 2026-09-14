# Acceptance Criteria — Sistem POS Multi-Tenant SaaS
**Turunan dari:** PRD-POS-SaaS-v1.5, dipecah dari Feature Matrix (section 3.1) dan Ruang Lingkup MVP (section 4), memakai format *Given/When/Then* sesuai template di section 14 PRD.

**Cara pakai dokumen ini:**
- Setiap AC diberi ID unik (`AC-<AREA>-<NOMOR>`) agar bisa direferensikan di task tracker/PR.
- "Edge case" dicantumkan terpisah dari happy path karena sering jadi celah bug di implementasi nyata.
- Dokumen ini **tidak** mengulang detail non-fungsional (lihat `ARCHITECTURE-POS-SaaS-v1.md`) atau struktur data (lihat `SCHEMA-POS-SaaS-v1.md`) kecuali relevan langsung ke perilaku fitur.

---

## Daftar Isi
1. [Tenant Onboarding & Subdomain](#1-tenant-onboarding--subdomain)
2. [Subscription & Billing (Self-Service)](#2-subscription--billing-self-service)
3. [Lifecycle Status Tenant](#3-lifecycle-status-tenant)
4. [Manajemen Meja & QR Code](#4-manajemen-meja--qr-code)
5. [Manajemen Menu, Kategori & Stok](#5-manajemen-menu-kategori--stok)
6. [Self-Order Customer (QR Flow)](#6-self-order-customer-qr-flow)
7. [Order Manual Kasir](#7-order-manual-kasir)
8. [Order Status Lifecycle & Koreksi Item](#8-order-status-lifecycle--koreksi-item)
9. [Kitchen Display System (KDS)](#9-kitchen-display-system-kds)
10. [Pembayaran & Webhook Xendit](#10-pembayaran--webhook-xendit)
11. [Refund](#11-refund)
12. [Realtime & Fallback Koneksi](#12-realtime--fallback-koneksi)
13. [Laporan & Analytics](#13-laporan--analytics)
14. [Manajemen User & Role](#14-manajemen-user--role)
15. [Autentikasi & Keamanan](#15-autentikasi--keamanan)
16. [Superadmin (Filament)](#16-superadmin-filament)

---

## 1. Tenant Onboarding & Subdomain

### AC-ONB-01 — Signup tenant baru berhasil
- **Given** calon tenant berada di landing page (`namaapp.com/daftar`)
- **When** mengisi nama bisnis, email, password, dan subdomain yang tersedia, lalu submit
- **Then** sistem membuat 1 `Tenant` (status `trial`, `trial_ends_at` = now + 14 hari), 1 `Outlet` default, dan 1 `User` dengan role Owner, lalu mengarahkan owner langsung ke `tenantX.namaapp.com` tanpa menunggu verifikasi email

### AC-ONB-02 — Validasi real-time ketersediaan subdomain
- **Given** calon tenant sedang mengetik subdomain di form signup
- **When** subdomain memenuhi aturan format (3–30 karakter, `a-z`/`0-9`/`-`, tidak diawali/diakhiri hyphen, bukan reserved word)
- **Then** `POST /api/tenant/check-subdomain` mengembalikan status tersedia/tidak secara real-time
- **Edge case:** subdomain termasuk reserved word (`api`, `admin`, `www`, dst.) → ditolak dengan pesan spesifik, bukan pesan generik "subdomain tidak tersedia"

### AC-ONB-03 — Anti-spam signup
- **Given** satu alamat IP sudah melakukan signup lebih dari batas rate limit
- **When** IP yang sama mencoba signup lagi (`POST /api/tenant/register`)
- **Then** request ditolak HTTP 429 dengan header `Retry-After`, sesuai limit 5 request/IP/jam

### AC-ONB-04 — Verifikasi email tidak memblokir penggunaan
- **Given** owner baru saja signup dan email verifikasi sudah terkirim
- **When** owner login sebelum mengklik link verifikasi
- **Then** owner tetap bisa mengakses penuh Vue Owner Dashboard untuk mulai setup (menu, meja, rekening bank)

---

## 2. Subscription & Billing (Self-Service)

### AC-BILL-01 — Reminder H-3 sebelum trial habis
- **Given** `trial_ends_at` tenant tinggal 3 hari lagi
- **When** scheduled job harian berjalan
- **Then** sistem mengirim notifikasi ke owner lewat **WA (OpenWA) dan Email** sekaligus, dan banner countdown muncul di Owner Dashboard

### AC-BILL-02 — Trial habis tanpa pilih plan
- **Given** `trial_ends_at` sudah lewat dan tenant belum memilih plan
- **When** owner membuka Owner Dashboard
- **Then** sistem menampilkan halaman wajib "Pilih Paket Langganan" (Basic/Pro) yang mengarah ke checkout Xendit Platform Billing, memblokir akses ke fitur lain sampai plan dipilih

### AC-BILL-03 — Checkout & aktivasi plan sukses
- **Given** owner memilih tier dan menyelesaikan pembayaran via Xendit Platform Billing
- **When** webhook `/webhook/xendit/platform-billing` menerima status `PAID`/`SETTLED`
- **Then** status tenant otomatis berubah jadi `active`, `plan_id` ter-update, dan seluruh fitur langsung terbuka tanpa aksi manual dari Superadmin

### AC-BILL-04 — Upgrade plan otomatis saat limit tercapai
- **Given** tenant sudah mencapai batas meja/user sesuai plan saat ini
- **When** owner mengklik "Tambah Meja"/"Invite User"
- **Then** modal "Upgrade ke Pro" muncul berisi ringkasan tier baru & harga; setelah bayar sukses, limit baru langsung berlaku dan aksi yang tadi terblokir bisa langsung diulang tanpa refresh manual
- **Edge case:** request create meja/user yang lolos validasi frontend (race condition 2 tab) tetap ditolak backend dengan HTTP 422 pesan yang sama

### AC-BILL-05 — Penagihan berbasis jumlah outlet aktif
- **Given** tenant memiliki 1 outlet aktif di plan Basic, upgrade ke Pro
- **When** transaksi upgrade dibuat
- **Then** nominal tagihan = harga Pro × 1 outlet (jumlah outlet aktif saat ini), bukan × kapasitas maksimum plan Pro

### AC-BILL-06 — Invoice recurring otomatis
- **Given** siklus billing tenant akan jatuh tempo dalam 3 hari
- **When** scheduled job invoice berjalan
- **Then** sistem generate `BillingInvoice` baru via Xendit Invoice API dengan `outlet_count_billed` = jumlah outlet aktif saat invoice dibuat, dan link pembayaran dikirim ke owner

### AC-BILL-07 — Tidak ada prorata di MVP
- **Given** tenant upgrade plan di tengah siklus billing berjalan
- **When** transaksi upgrade diproses
- **Then** tenant ditagih harga penuh tier baru (tanpa perhitungan prorata sisa periode)

---

## 3. Lifecycle Status Tenant

### AC-LIFE-01 — Masuk status overdue
- **Given** tenant belum bayar sampai lewat jatuh tempo/trial
- **When** grace period 3 hari dimulai
- **Then** status tenant jadi `overdue`; Owner Dashboard tampil penuh dengan banner peringatan tagihan; Kasir/KDS/Customer tetap berjalan normal tanpa gangguan operasional

### AC-LIFE-02 — Suspend setelah grace period habis
- **Given** tenant masih `overdue` setelah 3 hari grace period tanpa pembayaran
- **When** job pengecekan status berjalan
- **Then** status jadi `suspended`; Owner hanya bisa login ke halaman billing/status; Kasir/KDS menampilkan pesan blocking dan order baru tidak bisa diproses; halaman customer QR menampilkan "outlet ini sedang tidak tersedia" dan tidak bisa checkout

### AC-LIFE-03 — Reaktivasi setelah suspend
- **Given** tenant berstatus `suspended` menyelesaikan pembayaran tertunggak
- **When** webhook pembayaran sukses diterima
- **Then** status tenant kembali `active` dan seluruh akses (Owner, Kasir, KDS, Customer) langsung pulih normal

### AC-LIFE-04 — Data tenant churned tidak langsung hilang
- **Given** tenant berstatus `churned`
- **When** owner mencoba login dalam masa 30 hari sejak `churned_at`
- **Then** login ditolak (akun dinonaktifkan), tapi data tetap tersimpan (soft-delete) dan owner bisa minta export data via kontak manual ke Superadmin
- **Edge case:** setelah 30 hari terlewati, job terjadwal melakukan hard-delete permanen dan data tidak bisa dipulihkan lagi

---

## 4. Manajemen Meja & QR Code

### AC-TBL-01 — Tambah meja & generate QR
- **Given** owner berada di Vue Owner Dashboard, menu Meja & QR Code
- **When** owner menambah meja baru dengan kode unik
- **Then** sistem generate QR code unik mengarah ke `https://tenantX.namaapp.com/order/{outlet_id}/{table_code}` dan tersedia untuk didownload PDF/PNG
- **Edge case:** owner menambah meja saat limit plan tercapai → mengikuti AC-BILL-04 (modal upgrade)

### AC-TBL-02 — Regenerate/invalidate QR token
- **Given** owner mencurigai QR meja tertentu disalahgunakan
- **When** owner menekan tombol regenerate QR untuk meja tersebut
- **Then** token QR lama langsung tidak valid (request dengan token lama ditolak), dan QR baru dengan token berbeda diterbitkan

### AC-TBL-03 — Atur timeout table session per outlet/meja
- **Given** owner ingin mengubah durasi idle timeout sesi meja
- **When** owner mengubah `session_timeout_minutes` di pengaturan outlet atau meja spesifik
- **Then** perubahan berlaku untuk sesi baru yang dibuat setelahnya (sesi yang sedang berjalan tetap memakai nilai lama)

### AC-TBL-04 — Tutup sesi meja manual (override)
- **Given** kasir/owner melihat meja sudah kosong tapi `TableSession` masih tercatat aktif
- **When** kasir/owner menekan "Tutup Sesi" manual
- **Then** `TableSession` berubah status `closed` dengan `closed_reason = manual_kasir`/`manual_owner`, order yang masih `awaiting_payment` di sesi tersebut otomatis di-cancel (lihat AC-ORD-04)

---

## 5. Manajemen Menu, Kategori & Stok

### AC-MENU-01 — CRUD kategori & item menu
- **Given** owner berada di halaman Manajemen Menu
- **When** owner membuat/mengedit item menu (nama, harga, foto, deskripsi)
- **Then** perubahan tersimpan dan langsung tercermin di halaman customer order (kecuali item ditandai habis)
- **Edge case:** upload foto > 2 MB atau format selain jpg/jpeg/png/webp → ditolak dengan pesan validasi jelas, tervalidasi di backend bukan hanya frontend

### AC-MENU-02 — Toggle stok habis/tersedia
- **Given** item menu berstatus `available`
- **When** owner atau kasir (quick toggle) menandai item habis
- **Then** status berubah `out_of_stock` dan item otomatis hilang dari tampilan customer, tanpa perlu masuk ke halaman menu management penuh (khusus kasir)

### AC-MENU-03 — Stok berbasis angka berkurang otomatis
- **Given** item menu memakai `stock_type = count` dengan `stock_qty = 5`
- **When** ada order yang berisi 2 unit item tersebut berhasil `confirmed`
- **Then** `stock_qty` berkurang menjadi 3; jika `stock_qty` mencapai 0, status otomatis `out_of_stock`

### AC-MENU-04 — Soft delete menu item yang sudah pernah dipesan
- **Given** sebuah `MenuItem` sudah pernah muncul di `OrderItem` historis
- **When** owner menghapus item menu tersebut
- **Then** item hilang dari customer view & menu management aktif (soft delete), tapi data `OrderItem` historis dan laporan menu terlaris tetap utuh merujuk ke item yang sudah dihapus

### AC-MENU-05 — Varian single-choice wajib dipilih
- **Given** item menu punya `MenuItemVariantGroup` bertipe `single` dengan `is_required = true` (mis. Ukuran)
- **When** customer mencoba submit order tanpa memilih salah satu opsi di grup tersebut
- **Then** sistem menolak submit dan menampilkan pesan bahwa varian wajib dipilih

### AC-MENU-06 — Harga varian/add-on terhitung benar
- **Given** item menu "Kopi Latte" harga dasar Rp 25.000, varian "Large" (+Rp 5.000), add-on "Boba" (+Rp 5.000)
- **When** customer memilih Large + Boba, qty 1
- **Then** subtotal item = Rp 35.000, dan `OrderItemOption` menyimpan snapshot nama & `price_modifier` masing-masing opsi

---

## 6. Self-Order Customer (QR Flow)

### AC-QR-01 — Scan QR membuat/melanjutkan table session
- **Given** customer scan QR meja yang sedang tidak punya sesi aktif (atau sudah timeout)
- **When** customer membuka halaman menu
- **Then** sistem otomatis membuat `TableSession` baru untuk meja tersebut
- **Alternate:** jika meja sudah punya sesi aktif, order baru masuk ke sesi yang sama tanpa membuat sesi baru

### AC-QR-02 — Submit order dengan item tersedia
- **Given** customer berada di `TableSession` aktif dengan keranjang berisi minimal 1 item yang stoknya tersedia
- **When** customer menekan "Submit Order"
- **Then** sistem membuat `Order` berstatus `awaiting_payment`, dan customer diarahkan ke halaman pembayaran Xendit dalam < 2 detik

### AC-QR-03 — Edge case stok habis saat submit
- **Given** customer sudah menambahkan item ke keranjang, tapi salah satu item habis stoknya tepat sebelum submit
- **When** customer menekan "Submit Order"
- **Then** order ditolak dan sistem menampilkan pesan spesifik item mana yang habis, tanpa membuat record `Order`

### AC-QR-04 — Minimum order per outlet
- **Given** outlet mengatur `min_order_amount = Rp 10.000`
- **When** customer submit order dengan subtotal di bawah Rp 10.000
- **Then** submit ditolak di backend dengan pesan nominal minimum yang berlaku
- **Edge case:** outlet tidak set minimum (default) → order berapapun nominalnya tetap diterima

### AC-QR-05 — Tracking status realtime hanya sesi aktif
- **Given** customer membuka halaman tracking (`/order/{outlet}/{table}`)
- **When** halaman dimuat
- **Then** hanya order-order dari `TableSession` yang sedang aktif di meja tersebut yang ditampilkan — order dari kunjungan/sesi sebelumnya tidak ikut tampil

### AC-QR-06 — Tambah pesanan selama sesi masih aktif
- **Given** `TableSession` customer masih aktif (belum timeout)
- **When** customer submit order tambahan
- **Then** order baru masuk ke `TableSession` yang sama dan langsung muncul di halaman tracking tanpa mengganggu order sebelumnya

### AC-QR-07 — Session timeout otomatis
- **Given** sebuah `TableSession` tidak ada aktivitas/order baru selama durasi timeout yang ditentukan (default 60 menit)
- **When** waktu timeout tercapai
- **Then** sesi otomatis `closed`; customer berikutnya yang scan QR meja yang sama memulai `TableSession` baru dan tidak melihat riwayat sesi sebelumnya

---

## 7. Order Manual Kasir

### AC-MAN-01 — Order manual dengan meja
- **Given** kasir membuka Vue POS dan memilih meja tujuan
- **When** meja belum punya `TableSession` aktif
- **Then** sistem otomatis membuat sesi baru (perilaku sama seperti alur QR), order manual masuk ke sesi tersebut

### AC-MAN-02 — Order manual tanpa meja (takeaway)
- **Given** kasir memilih opsi "Tanpa Meja"
- **When** kasir submit order
- **Then** `Order.table_session_id` = null, order tetap tercatat dengan `source = manual_kasir`

### AC-MAN-03 — Pembayaran cash langsung confirmed
- **Given** kasir memilih metode pembayaran cash
- **When** kasir input nominal diterima dan submit
- **Then** `Order` langsung berstatus `confirmed` (skip `awaiting_payment`), sistem menghitung & menampilkan kembalian, dan `Payment` tetap dibuat dengan `payment_method = cash`, `transaction_id = null`

### AC-MAN-04 — Pembayaran non-cash via Xendit
- **Given** kasir memilih metode QRIS/e-wallet/kartu
- **When** kasir submit order
- **Then** `Order` dibuat berstatus `awaiting_payment`, kasir diarahkan ke halaman/QRIS pembayaran Xendit; setelah webhook sukses, status berubah `confirmed` dan broadcast `OrderCreated` ke KDS

### AC-MAN-05 — Validasi stok tetap berlaku
- **Given** kasir input order manual dengan item yang stoknya habis
- **When** kasir submit
- **Then** backend menolak submit dengan hard-check stok, sama seperti alur QR (bukan hanya validasi UI)

---

## 8. Order Status Lifecycle & Koreksi Item

### AC-ORD-01 — Transisi status sesuai state machine
- **Given** order berstatus `confirmed`
- **When** kitchen staff menekan "Mulai Proses"
- **Then** status berubah `preparing`; transisi lain (`preparing→ready` oleh kitchen staff, `ready→completed` oleh kasir/staff) mengikuti tabel transisi di PRD 5.2.1 dan tidak bisa lompat status (mis. `confirmed` langsung ke `ready` ditolak)

### AC-ORD-02 — Auto-expire order belum dibayar
- **Given** order berstatus `awaiting_payment` selama 10 menit tanpa pembayaran
- **When** scheduler berjalan
- **Then** order otomatis `cancelled`, dan meja bisa dipakai order ulang

### AC-ORD-03 — Void item saat status confirmed
- **Given** order berstatus `confirmed` (belum `preparing`)
- **When** kasir membatalkan satu/beberapa item dari order
- **Then** item ditandai `is_voided = true`, total order dihitung ulang otomatis, dan tercatat di `AuditLog` (siapa, item apa, alasan)
- **Edge case:** item sudah dibayar non-cash via Xendit → void **tidak** otomatis memicu refund parsial, selisih dana harus lewat alur Refund Request terpisah (lihat section 11)

### AC-ORD-04 — Void/edit tidak diizinkan setelah preparing
- **Given** order sudah berstatus `preparing`
- **When** kasir mencoba void/edit item dari UI Kasir
- **Then** aksi ditolak — dapur sudah mulai proses; pembatalan harus lewat cancel penuh atau refund

### AC-ORD-05 — Cancel order karena tutup paksa sesi meja
- **Given** kasir menutup paksa sesi meja yang masih punya order `awaiting_payment`
- **When** sesi ditutup
- **Then** order-order `awaiting_payment` di sesi tersebut otomatis `cancelled`

---

## 9. Kitchen Display System (KDS)

### AC-KDS-01 — Order baru muncul realtime
- **Given** order baru berhasil `confirmed` (via QR maupun manual)
- **When** event `OrderCreated` di-broadcast ke channel outlet
- **Then** order langsung muncul di layar KDS tanpa refresh manual, dikelompokkan per meja & waktu masuk

### AC-KDS-02 — Update status per item
- **Given** kitchen staff melihat order di antrian
- **When** staff menandai status item `Preparing → Ready`
- **Then** perubahan otomatis terkirim ke tracking pelanggan dan layar kasir tanpa staff perlu aksi tambahan

### AC-KDS-03 — Highlight SLA warning
- **Given** sebuah order sudah menunggu lebih lama dari ambang waktu wajar
- **When** layar KDS di-render/refresh
- **Then** order tersebut ditandai visual (highlight) sebagai peringatan keterlambatan

---

## 10. Pembayaran & Webhook Xendit

### AC-PAY-01 — Split settlement otomatis ke tenant
- **Given** customer submit order dan sub-account tenant berstatus `verified`
- **When** sistem membuat transaksi Xendit
- **Then** transaksi dibuat dengan Split Rule yang mengarahkan dana (dikurangi MDR) ke `sub_account_id` tenant, hanya 1 route (tanpa potongan platform fee, sesuai keputusan #15)

### AC-PAY-02 — QR self-order diblokir jika sub-account belum verified
- **Given** `TenantPaymentAccount.verification_status` masih `pending` atau `rejected`
- **When** owner mencoba mempublikasikan fitur self-order QR untuk sebuah outlet
- **Then** tombol publish QR nonaktif, dan Owner Dashboard menampilkan status "Menunggu verifikasi rekening"

### AC-PAY-03 — Penanganan verifikasi rejected
- **Given** Xendit menolak verifikasi sub-account (mis. nama rekening tidak cocok KTP)
- **When** status berubah `rejected`
- **Then** Owner Dashboard menampilkan alasan penolakan (jika tersedia) dan tombol "Perbarui Data Rekening" untuk resubmit; tidak ada batas jumlah percobaan resubmit di MVP

### AC-PAY-04 — Webhook idempotent
- **Given** Xendit mengirim notifikasi webhook yang sama lebih dari sekali (retry)
- **When** endpoint webhook menerima payload duplikat (`transaction_id` sudah pernah diproses)
- **Then** sistem tidak memproses ulang perubahan status/side-effect (idempotent), tetap mengembalikan HTTP 200

### AC-PAY-05 — Verifikasi signature webhook
- **Given** request masuk ke `/webhook/xendit/customer-order` atau `/webhook/xendit/platform-billing`
- **When** header `x-callback-token` tidak cocok dengan token yang tersimpan untuk akun terkait
- **Then** request ditolak HTTP 401 tanpa diproses lebih lanjut
- **Edge case:** token untuk akun Customer Order tidak boleh divalidasi memakai token akun Platform Billing atau sebaliknya (2 akun terpisah total)

### AC-PAY-06 — Payment expired/failed
- **Given** transaksi Xendit customer order berstatus `EXPIRED`/`FAILED`
- **When** webhook diterima
- **Then** `Order` otomatis `cancelled`, meja bisa dipakai order ulang tanpa intervensi manual

---

## 11. Refund

### AC-REF-01 — Pengajuan refund request
- **Given** kasir/owner ingin mengajukan refund untuk order berumur ≤ 7 hari
- **When** mengisi form refund (alasan, nominal partial/full) dan submit
- **Then** `RefundRequest` tercatat berstatus `pending`
- **Edge case:** order berumur > 7 hari → tombol "Ajukan Refund" tidak muncul di UI

### AC-REF-02 — Approval Superadmin
- **Given** `RefundRequest` berstatus `pending`
- **When** Superadmin mereview dan menekan Approve di Filament
- **Then** status berubah `approved`, sistem (atau Superadmin) mengeksekusi refund via Xendit Refund API menggunakan akun master platform

### AC-REF-03 — Refund berhasil diproses
- **Given** Xendit mengonfirmasi refund sukses
- **When** konfirmasi diterima
- **Then** status `RefundRequest` berubah `processed`, dan `SettlementLog` mencatat pengurangan dari settlement berikutnya ke tenant (kompensasi dana yang sudah ditarik dari akun master)

### AC-REF-04 — Refund order cash
- **Given** order dibayar cash (tanpa transaksi Xendit)
- **When** kasir/owner mengajukan refund untuk order tersebut
- **Then** `RefundRequest` tetap tercatat (flag `payment_method = cash`) untuk audit trail, tanpa memanggil Xendit Refund API — kasir mengembalikan uang tunai langsung ke customer

### AC-REF-05 — Reject refund request
- **Given** Superadmin menilai refund request tidak valid
- **When** Superadmin menekan Reject
- **Then** status berubah `rejected`, kasir/owner yang mengajukan bisa melihat status penolakan di dashboard mereka

---

## 12. Realtime & Fallback Koneksi

### AC-RT-01 — Indikator status koneksi
- **Given** Vue POS/KDS terhubung ke Reverb
- **When** koneksi WebSocket putus
- **Then** badge status berubah dari "Live" menjadi "Terputus, mencoba menyambung ulang..." di header

### AC-RT-02 — Polling fallback tetap menampilkan order baru
- **Given** koneksi WebSocket sedang terputus/gagal menerima event
- **When** polling 30 detik (`GET /api/kitchen/queue` / `GET /api/orders?since=...`) berjalan
- **Then** order baru yang mungkin terlewat dari broadcast tetap muncul di layar Kasir/KDS

### AC-RT-03 — Fetch ulang state saat reconnect
- **Given** koneksi WebSocket baru saja tersambung kembali setelah terputus
- **When** event `connected` diterima
- **Then** client melakukan 1x fetch ulang state terkini (bukan hanya mengandalkan event yang terlewat) untuk memastikan konsistensi data

### AC-RT-04 — Otorisasi channel tidak bocor lintas tenant
- **Given** user dari tenant A mencoba subscribe ke channel `private-tenant.{tenantId_B}.outlet...`
- **When** broadcasting auth endpoint memvalidasi request
- **Then** akses ditolak — user hanya bisa subscribe channel milik tenant & outlet-nya sendiri

---

## 13. Laporan & Analytics

### AC-RPT-01 — Laporan penjualan per periode
- **Given** owner membuka halaman Laporan
- **When** memilih rentang harian/mingguan/bulanan
- **Then** sistem menampilkan total penjualan sesuai periode, dihitung berdasarkan `Order` berstatus `completed` (atau sesuai definisi "penjualan sah" yang disepakati tim)

### AC-RPT-02 — Menu terlaris & jam ramai
- **Given** data order historis tersedia untuk outlet tersebut
- **When** owner membuka laporan Menu Terlaris / Jam Ramai
- **Then** sistem menampilkan ranking `MenuItem` berdasarkan jumlah `OrderItem` terjual, dan distribusi order berdasarkan jam

### AC-RPT-03 — Ringkasan pendapatan per metode pembayaran
- **Given** ada campuran order cash dan non-cash dalam periode laporan
- **When** owner membuka ringkasan pendapatan per metode
- **Then** total pendapatan terpecah benar per `payment_method`, termasuk order cash (karena `Payment` selalu dibuat meski `transaction_id = null`)

### AC-RPT-04 — Export laporan
- **Given** owner ingin mengunduh laporan
- **When** menekan tombol export CSV/PDF
- **Then** file terunduh berisi data sesuai filter periode yang sedang aktif di layar

---

## 14. Manajemen User & Role

### AC-USR-01 — Invite user baru
- **Given** owner mengundang kasir/kitchen staff baru dalam batas `max_users_per_outlet` plan saat ini
- **When** owner submit form invite (nama, email, role)
- **Then** user baru dibuat dan menerima cara untuk set password/login (mis. link set password via email)
- **Edge case:** limit user tercapai → mengikuti AC-BILL-04 (modal upgrade)

### AC-USR-02 — Role membatasi akses fitur
- **Given** user login sebagai Kasir
- **When** user mencoba mengakses endpoint/menu khusus Owner (mis. Laporan, Pengaturan Payment)
- **Then** akses ditolak sesuai permission role yang di-assign

### AC-USR-03 — Owner scope tidak terikat 1 outlet, staff terikat
- **Given** tenant punya lebih dari 1 outlet
- **When** Owner login
- **Then** Owner bisa melihat/mengelola semua outlet miliknya; sebaliknya Kasir/Kitchen Staff hanya bisa mengakses outlet tempat mereka di-assign (`outlet_id` di profil user)

---

## 15. Autentikasi & Keamanan

### AC-AUTH-01 — Login sukses & rate limiting
- **Given** user memasukkan kredensial yang benar
- **When** login berhasil
- **Then** token Sanctum diterbitkan (berlaku 7 hari)
- **Edge case:** percobaan login gagal melebihi 5 request/email/5 menit → ditolak HTTP 429

### AC-AUTH-02 — Reset password
- **Given** user meminta reset password
- **When** link reset (berlaku 60 menit, sekali pakai) diklik dan password baru diisi (≥ 8 karakter, kombinasi huruf & angka)
- **Then** password ter-update, link/token reset menjadi invalid setelah dipakai
- **Edge case:** request forgot-password lebih dari 3x/email/jam → ditolak rate limit

### AC-AUTH-03 — 2FA wajib untuk Owner & Superadmin
- **Given** user dengan role Owner atau Superadmin belum mengaktifkan 2FA
- **When** user mencoba mengakses fitur sensitif (data rekening bank / panel Superadmin)
- **Then** sistem mewajibkan setup TOTP terlebih dahulu sebelum akses diberikan
- **Edge case:** role Kasir/Kitchen tidak diwajibkan 2FA

### AC-AUTH-04 — Auto-logout idle
- **Given** Superadmin login ke Filament
- **When** tidak ada aktivitas selama 30 menit
- **Then** sesi otomatis logout, berbeda dari sesi Owner/Kasir yang mengikuti idle timeout 8 jam

### AC-AUTH-05 — Rate limiting submit order dari QR
- **Given** satu `table_code` mengirim lebih dari 10 request submit order dalam 1 menit
- **When** request ke-11 masuk
- **Then** request ditolak HTTP 429 — mencegah abuse dari QR yang bocor/dishare

---

## 16. Superadmin (Filament)

### AC-SA-01 — Dashboard ringkasan platform
- **Given** Superadmin login ke Filament
- **When** membuka Dashboard
- **Then** menampilkan total tenant aktif, tenant baru per bulan, dan churn rate

### AC-SA-02 — Suspend/aktifkan tenant manual (override)
- **Given** Superadmin perlu menangani kasus khusus (di luar alur otomatis)
- **When** Superadmin menekan Suspend/Aktifkan pada tenant tertentu
- **Then** status tenant berubah sesuai aksi, dan aktivitas ini tercatat di `AuditLog`

### AC-SA-03 — Kelola plan tanpa deploy ulang
- **Given** Superadmin ingin mengubah harga tier (nilai masih dummy)
- **When** mengedit `Plan.price_per_outlet` di Filament
- **Then** perubahan langsung berlaku untuk transaksi checkout berikutnya, tanpa perlu deploy ulang aplikasi

### AC-SA-04 — Audit log akses data tenant
- **Given** Superadmin mengakses data tenant tertentu untuk keperluan support/debugging
- **When** akses dilakukan
- **Then** aktivitas tercatat di `AuditLog` (siapa, tenant mana, kapan) sesuai kepatuhan section 9.4 PRD

### AC-SA-05 — Kelola user internal platform
- **Given** Superadmin ingin menambah anggota tim internal platform
- **When** membuat user baru dengan role/permission tertentu di Filament
- **Then** user baru bisa login ke Filament dengan akses sesuai permission yang di-assign

---

## Catatan Cakupan

- Dokumen ini mencakup seluruh item **In-Scope MVP** (PRD section 4.1). Item **Out-of-Scope MVP** (React Native, custom domain, loyalty, multi-outlet konsolidasi, integrasi accounting, multi-bahasa/currency, prorata billing, auto-debit kartu, offline-first) **sengaja tidak dibuatkan AC** karena belum jadi bagian deliverable saat ini.
- AC di atas adalah level *feature behavior*, belum sampai skenario UI pixel-level atau test data spesifik — detail lebih granular (test data, mock payload webhook, dsb.) disusun saat breakdown task per sprint.
- Setiap AC idealnya jadi acuan langsung untuk automated test (feature test Laravel / E2E Vue), terutama yang menyentuh isolasi tenant (AC-RT-04), validasi stok (AC-QR-03, AC-MAN-05), dan idempotency webhook (AC-PAY-04).
