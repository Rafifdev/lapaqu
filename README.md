# Lapaqu - Integrated Restaurant Operating System

Platform manajemen restoran multi-tenant modern yang mengintegrasikan pemesanan mandiri pelanggan via scan QR meja (Self-Order), Point of Sale (POS Kasir), Kitchen Display System (KDS Dapur), manajemen resep dan inventori bahan baku (Bill of Materials), serta analitik bisnis back-office.

---

## Daftar Isi

- [Arsitektur Sistem](#arsitektur-sistem)
- [Teknologi Stack](#teknologi-stack)
- [Modul Utama](#modul-utama)
  - [1. Customer Self-Order (QR Dinamis Meja)](#1-customer-self-order-qr-dinamis-meja)
  - [2. Point of Sale (POS Kasir)](#2-point-of-sale-pos-kasir)
  - [3. Kitchen Display System (KDS Dapur)](#3-kitchen-display-system-kds-dapur)
  - [4. Dashboard Manajemen & Owner](#4-dashboard-manajemen--owner)
- [Sinkronisasi Real-Time & Event Broadcasting](#sinkronisasi-real-time--event-broadcasting)
- [Struktur Direktori Monorepo](#struktur-direktori-monorepo)
- [Prasyarat Sistem](#prasyarat-sistem)
- [Panduan Instalasi & Menjalankan Lokal](#panduan-instalasi--menjalankan-lokal)
  - [1. Konfigurasi Backend (Laravel 11)](#1-konfigurasi-backend-laravel-11)
  - [2. Konfigurasi Frontend (Vue 3 + Vite)](#2-konfigurasi-frontend-vue-3--vite)
  - [3. Server WebSocket Real-Time (Laravel Reverb)](#3-server-websocket-real-time-laravel-reverb)
- [Referensi Environment Variables](#referensi-environment-variables)
  - [Backend (.env)](#backend-pf-backendenv)
  - [Frontend (.env)](#frontend-pf-frontendenv)
- [Akun Demo Seeder](#akun-demo-seeder)
- [Referensi Endpoint REST API](#referensi-endpoint-rest-api)
- [Panduan Deployment Produksi](#panduan-deployment-produksi)

---

## Arsitektur Sistem

```text
[ Customer QR Scan ]       [ Cashier Terminal ]       [ Kitchen Displays ]
         |                          |                          |
         +--------------------------+--------------------------+
                                    |
                                    v
+------------------------------------------------------------------------+
|               Vue 3 Single Page Application (pf-frontend)              |
|        Pinia Stores / Vue Router / Tailwind CSS / Laravel Echo         |
+-----------------------------------+------------------------------------+
                                    | HTTP REST / WebSocket (WSS)
                                    v
+------------------------------------------------------------------------+
|                     Laravel 11 REST API (pf-backend)                   |
|     Sanctum Auth / RBAC / Multi-Tenant Scopes / Xendit Payment SDK     |
+-------------------+-------------------+--------------------+-----------+
                    |                   |                    |
                    v                   v                    v
          +-------------------+ +---------------+ +--------------------+
          | PostgreSQL 16     | | Redis Cache   | | Laravel Reverb     |
          | Database Relasional| | Cache & Queue | | WebSocket Server   |
          +-------------------+ +---------------+ +--------------------+
```

---

## Teknologi Stack

### Backend
- Framework: Laravel 11 (PHP 8.2+)
- Autentikasi: Laravel Sanctum (Token Bearer & Stateful Sessions)
- Otorisasi: Spatie Laravel-Permission (Role-Based Access Control)
- Database: PostgreSQL 16
- Caching, Session & Queue: Redis
- Real-Time WebSocket Engine: Laravel Reverb
- Payment Gateway: Xendit API (Dynamic QRIS, Virtual Account, & Webhook)

### Frontend
- Framework: Vue 3 (Composition API dengan script setup TypeScript)
- State Management: Pinia (auth, cart, pos)
- Build Tool: Vite 5
- Styling: Tailwind CSS dengan Custom Theme & Dark Mode Support
- WebSocket Client: Laravel Echo & Pusher-js
- Notifikasi & Audio: Web Audio API Synthesizer & Notyf
- Lokalisasi: Sistem Reaktif i18n (Bahasa Indonesia & English)

---

## Modul Utama

### 1. Customer Self-Order (QR Dinamis Meja)
- Akses langsung per sesi meja melalui QR Code (/order/:outletId/:tableToken).
- Navigasi katalog produk responsif dengan pencarian instan, filter kategori, dan varian pesanan (level gula, suhu es, porsi, opsi topping).
- Validasi stok otomatis dan pencegahan pemesanan jika bahan baku habis.
- Integrasi pembayaran otomatis:
  - Dynamic QRIS Xendit dengan hitung mundur masa berlaku dan auto-update status pembayaran via webhook atau polling.
  - Virtual Account Multi-Bank (BCA, Mandiri, BRI, BNI, Permata).
  - Pembayaran tunai di kasir dengan notifikasi langsung ke terminal kasir.
- Pelacak status pesanan waktu nyata: Pending Payment -> Received -> Preparing -> Ready to Serve -> Completed.

### 2. Point of Sale (POS Kasir)
- Workspace kasir cepat untuk menerima pesanan online dan transaksi manual di tempat (Dine-in / Takeaway).
- Antrean pesanan masuk waktu nyata disertai peringatan audio saat ada pesanan baru.
- Peta denah meja interaktif dengan indikator status keterisian (Tersedia, Terisi, Menunggu Pembayaran).
- Quick-Stock toggle untuk mengubah status menu (Tersedia / Habis) secara instan tanpa masuk menu pengaturan.
- Cetak struk pembayaran langsung ke printer termal via browser print service.

### 3. Kitchen Display System (KDS Dapur)
- Antrean tiket dapur berbasis First-In, First-Out (FIFO).
- Checklist per item hidangan untuk memantau proses masak setiap pesanan.
- Dukungan penyajian parsial (mengirim minuman atau hidangan pembuka terlebih dahulu).
- Indikator peringatan batas waktu masak (SLA timer) untuk mencegah keterlambatan penyajian.
- Sinkronisasi instan dua arah antara layar dapur, kasir, dan pelanggan melalui WebSocket.

### 4. Dashboard Manajemen & Owner
- Kartu metrik pendapatan: Penjualan Kotor, Penjualan Bersih, Total Pesanan, dan Rata-rata Nilai Transaksi.
- Analisis jam operasional tersibuk (Peak Hours) dan peringkat menu terlaris (Top Items).
- Manajemen Inventori & Resep (Bill of Materials / BOM):
  - Pengurangan kuantitas bahan baku otomatis saat pesanan selesai diproses.
  - Notifikasi stok menipis (Low Stock Alerts) berdasarkan ambang batas minimum.
  - Log riwayat penyesuaian stok dan modul Stock Opname berkala.
- Konfigurasi Multi-Tenant & Multi-Outlet:
  - Pengaturan nama restoran, logo brand, alamat lengkap, pajak (PB1 / PPN), dan biaya layanan.
  - Pembuatan dan pencetakan batch QR Code meja.
  - Manajemen paket langganan restoran (Starter, Pro, Enterprise).

---

## Sinkronisasi Real-Time & Event Broadcasting

Aplikasi menggunakan Laravel Reverb untuk menyiarkan event WebSocket ke seluruh client secara instan:

| Channel | Event | Pemicu Event | Konsumen Event |
| :--- | :--- | :--- | :--- |
| orders.{outletId} | NewOrderPlacedEvent | Pesanan baru dibuat pelanggan atau kasir | Antrean POS Kasir |
| orders.{outletId} | OrderStatusUpdatedEvent | Perubahan status pesanan oleh POS atau KDS | Layar POS, KDS, Pelanggan |
| kds.{outletId} | OrderSentToKitchenEvent | Pesanan terverifikasi dan masuk antrean masak | Layar KDS Dapur |
| table.{tableToken} | TableStatusChangedEvent | Sesi meja dibuka, ditutup, atau dibersihkan | Kunci Layar Pelanggan |

---

## Struktur Direktori Monorepo

```text
lapaqu/
|-- pf-backend/                      # Backend API berbasis Laravel 11
|   |-- app/
|   |   |-- Events/                  # Definisi Event WebSocket Broadcasting
|   |   |-- Http/
|   |   |   |-- Controllers/Api/     # Endpoint Controller (POS, KDS, Customer, Dashboard)
|   |   |   `-- Middleware/          # Middleware Auth, Tenant Scope, Cache
|   |   |-- Models/                  # Eloquent Models & Relasi Database
|   |   |-- Services/                # Service Xendit, Inventori, dan Notifikasi
|   |   `-- Traits/                  # Trait BelongsToTenant (Multi-Tenant Global Scope)
|   |-- config/                      # Konfigurasi Aplikasi, Reverb, dan Layanan
|   |-- database/
|   |   |-- migrations/              # Migrasi Skema Database
|   |   `-- seeders/                 # Seeder Data Master, Tenant, Menu, dan Akun Demo
|   `-- routes/
|       |-- api.php                  # Rute REST API
|       `-- channels.php             # Otorisasi Private Channel WebSocket
|
|-- pf-frontend/                     # Frontend SPA berbasis Vue 3 + Vite
|   |-- src/
|   |   |-- assets/                  # Aset Gambar, Logo Default, dan Format Styling
|   |   |-- components/
|   |   |   |-- layout/              # Komponen AppSidebar, AppTopbar, Footer
|   |   |   |-- settings/            # Modal Pengaturan Branding, Profil, dan Paket
|   |   |   `-- ui/                  # Komponen Reusable UI (AppTable, AppButton, AppModal)
|   |   |-- composables/             # Composable Hooks (Motion, Formatters, Events)
|   |   |-- i18n/                    # Kamus Lokalisasi (id.ts, en.ts)
|   |   |-- layouts/                 # CustomerLayout, PosLayout, KdsLayout, AuthLayout
|   |   |-- services/                # Instance Axios, apiCache, Sound & Notification Service
|   |   |-- stores/                  # Pinia Stores (auth, cart, pos)
|   |   |-- views/                   # Tampilan Rute Pelanggan, POS, KDS, & Dashboard
|   |   `-- router/                  # Definisi Vue Router & Navigation Guards
|   `-- vite.config.ts               # Konfigurasi Vite & Proxy API
|
`-- README.md
```

---

## Prasyarat Sistem

- PHP: 8.2 atau lebih tinggi
- Composer: Versi 2.x
- Node.js: Versi 18.x atau 20.x LTS
- Package Manager: npm atau pnpm
- Database: PostgreSQL 14+ (atau MySQL 8.0+)
- Redis Server: Versi 6.x atau lebih tinggi (disarankan untuk cache dan queue)

---

## Panduan Instalasi & Menjalankan Lokal

### 1. Konfigurasi Backend (Laravel 11)

```bash
# 1. Masuk ke direktori backend
cd pf-backend

# 2. Instal dependensi PHP
composer install

# 3. Buat file environment lokal
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Sesuaikan konfigurasi database pada file .env, lalu jalankan migrasi dan seeder:
php artisan migrate --seed

# 6. Buat tautan simbolik direktori penyimpanan file (logo dan foto menu)
php artisan storage:link

# 7. Jalankan server backend development
php artisan serve
# Server aktif pada: http://127.0.0.1:8000
```

### 2. Konfigurasi Frontend (Vue 3 + Vite)

```bash
# 1. Buka terminal baru dan masuk ke direktori frontend
cd pf-frontend

# 2. Instal dependensi JavaScript
npm install

# 3. Buat file environment lokal
cp .env.example .env

# 4. Jalankan Vite development server
npm run dev
# Aplikasi aktif pada: http://localhost:5173
```

### 3. Server WebSocket Real-Time (Laravel Reverb)

Jalankan daemon Reverb agar fitur realtime (POS, KDS, Order Status) aktif:

```bash
cd pf-backend
php artisan reverb:start
```

---

## Referensi Environment Variables

### Backend (pf-backend/.env)

```env
APP_NAME=Lapaqu
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:5173

# Konfigurasi Database PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lapaqu_db
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Konfigurasi Cache, Session, & Queue Redis
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Konfigurasi Broadcasting Laravel Reverb
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=lapaqu-app
REVERB_APP_KEY=lapaqu-reverb-key
REVERB_APP_SECRET=lapaqu-reverb-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME="http"

# Integrasi Payment Gateway Xendit
XENDIT_SECRET_KEY=xnd_development_...
XENDIT_WEBHOOK_TOKEN=...
```

### Frontend (pf-frontend/.env)

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
VITE_REVERB_APP_KEY=lapaqu-reverb-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

---

## Akun Demo Seeder

DatabaseSeeder menyediakan data demo restoran (Kopi Kenangan Senopati) dengan kredensial berikut:

| Peran (Role) | Email | Password | PIN Kasir/Dapur | Halaman Akses |
| :--- | :--- | :--- | :--- | :--- |
| Platform Superadmin | admin@lapaqu.id | LapaquAdmin2026! | - | /dashboard (Akses Superadmin) |
| Tenant Owner | owner@kopisenopati.id | RahasiaKopi123! | - | /dashboard |
| Store Manager | manager@kopisenopati.id | RahasiaKopi123! | 123456 | /dashboard & /pos |
| Kasir (POS) | kasir@kopisenopati.id | RahasiaKopi123! | 123456 | /pos |
| Kitchen Staff (KDS) | kitchen@kopisenopati.id | RahasiaKopi123! | 123456 | /kds |
| Pelanggan (Self-Order) | (Tanpa Login) | - | - | /order/:outletId/:tableToken |

---

## Referensi Endpoint REST API

### Autentikasi & Akun
- POST /api/auth/login - Login pengguna (mengembalikan Bearer Token dan profil)
- POST /api/auth/logout - Menghapus sesi token aktif
- GET  /api/auth/me - Mengambil data user yang sedang login dan tenant aktif
- PUT  /api/auth/profile - Memperbarui nama, email, password, dan foto profil

### Customer Self-Order (Akses Publik)
- GET  /api/public/outlets/{id}/menu - Mengambil daftar menu, kategori, dan varian aktif
- POST /api/public/orders - Membuat pesanan baru pelanggan dari meja
- GET  /api/public/orders/{id} - Memeriksa status pesanan, pembayaran, dan linimasa
- POST /api/public/orders/{id}/pay - Membuat invoice pembayaran QRIS / Virtual Account Xendit

### Kasir (POS) & Dapur (KDS)
- GET  /api/pos/orders - Mengambil daftar pesanan masuk dan transaksi sebelumnya
- POST /api/pos/orders - Membuat pesanan manual kasir (Dine-in / Takeaway)
- PUT  /api/pos/orders/{id}/status - Memperbarui status pesanan (cooking, ready, completed, cancelled)
- GET  /api/kds/orders - Mengambil antrean tiket aktif di dapur
- PUT  /api/kds/items/{id}/toggle - Menandai penyelesaian hidangan per item

### Manajemen & Back-Office
- GET  /api/dashboard/summary - Metrik ringkasan (Pendapatan, Penjualan Bersih, Total Pesanan)
- GET  /api/dashboard/reports/peak-hours - Laporan distribusi jam sibuk penjualan
- GET  /api/dashboard/reports/top-items - Laporan menu terlaris
- GET  /api/menu-items - CRUD manajemen katalog menu makanan dan minuman
- GET  /api/inventory/stocks - Pemantauan stok bahan baku dan peringatan sisa stok
- PUT  /api/outlets/{id} - Memperbarui branding outlet, logo, alamat, pajak, dan service charge

### Webhook
- POST /api/webhooks/xendit - Handler notifikasi pembayaran otomatis dari Xendit

---

## Panduan Deployment Produksi

1. Queue Worker: Jalankan worker di background untuk memproses pemotongan inventori dan sinkronisasi webhook:
   ```bash
   php artisan queue:work --tries=3 --timeout=90
   ```
2. Supervisor Laravel Reverb: Kelola daemon Reverb menggunakan systemd atau Supervisor agar selalu aktif:
   ```bash
   php artisan reverb:start --host=0.0.0.0 --port=8080
   ```
3. Reverse Proxy WSS (Nginx / Cloudflare): Pastikan header HTTP Upgrade dan Connection dialihkan ke port Reverb untuk koneksi WebSocket stabil.
4. Build Bundle Frontend: Kompilasi dan minifikasi aset frontend sebelum dideploy ke web server:
   ```bash
   cd pf-frontend && npm run build
   ```
