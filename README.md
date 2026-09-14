# 🍽️ Lapaqu — All-in-One Restaurant Platform

<div align="center">

![Lapaqu Logo](https://raw.githubusercontent.com/yoapipp/lapaqu/main/pf-frontend/src/assets/logo/lapaqu-logo.png)

**Sistem Manajemen Restoran, POS Kasir, Kitchen Display System (KDS), dan Self-Order QR Terintegrasi**

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white)](https://vuejs.org)
[![Vite](https://img.shields.io/badge/Vite-5.x-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-316192?style=for-the-badge&logo=postgresql&logoColor=white)](https://www.postgresql.org)
[![Xendit](https://img.shields.io/badge/Payment-Xendit_QRIS_%26_VA-00A4D6?style=for-the-badge)](https://www.xendit.co)

</div>

---

## 📌 Ringkasan Proyek

**Lapaqu** adalah platform F&B modern yang dirancang untuk mempercepat alur operasional restoran, kedai kopi, dan kafe. Terintegrasi secara *real-time* dari pemesanan mandiri oleh pelanggan lewat scan QR meja, kasir (POS), antrean dapur (KDS), hingga pelaporan bisnis dan manajemen stok di dasbor owner/manajemen.

Repository ini menggunakan struktur **Monorepo** yang memuat:
- **`pf-backend/`** : RESTful API dibangun dengan **Laravel 11**, otentikasi Sanctum, integrasi payment gateway Xendit, dan sinkronisasi realtime.
- **`pf-frontend/`** : Single Page Application dibangun dengan **Vue 3 (Composition API)**, **Pinia**, **Vite**, dan **Tailwind CSS** dengan *DashStack Design System*.

---

## 🚀 Fitur & Modul Utama

### 1. 📱 Customer Self-Order (QR Meja)
- **Scan & Order Cepat**: Pelanggan cukup scan QR Code meja tanpa perlu unduh aplikasi.
- **Katalog Menu Interaktif**: Pemilihan varian (level gula, es, porsi, opsi ekstra), pencarian menu, dan filter kategori.
- **Kode Promo & Diskon**: Validasi kupon promo otomatis saat checkout.
- **Pembayaran Fleksibel**:
  - **QRIS Dinamis (Xendit)** dengan hitung mundur waktu bayar dan auto-check webhook / polling.
  - **Virtual Account Bank** (BCA, Mandiri, BRI, BNI, Permata).
  - **Bayar Tunai di Kasir**.
- **Pelacakan Status Pesanan Real-Time**: Status stepper (*Pesanan Dibuat* ➔ *Menunggu Bayar* ➔ *Sedang Dimasak* ➔ *Siap Saji*).

### 2. 💻 Point of Sale (Kasir)
- **Order Masuk**: Notifikasi pesanan realtime dengan SLA timer, filter status, dan aksi pembatalan / penyelesaian.
- **Order Manual**: Kasir dapat membuat pesanan langsung untuk pelanggan dine-in maupun takeaway.
- **Quick Stok**: Kelola ketersediaan menu secara cepat (*Tersedia* / *Habis*) dengan tabel terpadu dan perhitungan sisa porsi bahan baku.
- **Denah & Sesi Meja**: Pantau status meja (*Tersedia*, *Terisi*, *Billing*).
- **Riwayat Transaksi & Cetak Struk**: Cetak struk pesanan langsung ke printer termal POS.

### 3. 🍳 Kitchen Display System (KDS)
- **Antrean Dapur FIFO**: Kartu pesanan tersusun rapi berdasarkan urutan waktu masuk.
- **Checklist Menu**: Koki dapat mencentang hidangan per item yang telah selesai dimasak.
- **Partial Saji**: Dukungan penyajian parsial (mengantar minuman atau hidangan yang sudah siap terlebih dahulu).
- **Indikator SLA Masak**: Peringatan visual warna kartu jika proses masak melebihi batas waktu toleransi.

### 4. 📊 Dashboard Manajemen & Owner
- **Statistik & Metrik Bisnis**: Pendapatan, jumlah pesanan, pelanggan unik, dan grafik tren penjualan.
- **Manajemen Menu & Kategori**: Manajemen produk, foto, harga, dan varian hidangan.
- **Manajemen Inventori & Resep (BOM)**:
  - Manajemen bahan baku dan satuan.
  - Pengurangan stok otomatis berdasarkan resep hidangan yang dipesan.
  - Peringatan stok menipis (*Low Stock Alert*).
  - Stok Opname berkala dan riwayat penyesuaian stok.
- **Manajemen Meja & Generate QR Code**: Cetak QR Code unik per meja untuk self-ordering.
- **Laporan Jam Sibuk (Peak Hours)** & Item Terlaris (*Top Selling*).
- **Manajemen Staf & Hak Akses Multi-Role** (*Owner*, *Kasir*, *Kitchen Staff*).

---

## 📂 Struktur Direktori

```text
lapaqu/
├── pf-backend/               # Laravel 11 Backend API
│   ├── app/
│   │   ├── Http/Controllers/ # REST API Controllers (POS, KDS, Customer, Dashboard)
│   │   ├── Models/          # Eloquent ORM Models
│   │   └── Services/        # Payment (Xendit) & Inventory Stock Services
│   ├── config/              # Konfigurasi aplikasi
│   ├── database/            # Migrations & Seeders
│   └── routes/              # api.php & web.php
│
├── pf-frontend/              # Vue 3 Frontend (Vite + Tailwind CSS)
│   ├── src/
│   │   ├── assets/          # Logo, SVG, font, dan ilustrasi empty state
│   │   ├── components/      # Reusable UI (AppTable, AppButton, AppModal, AppBadge, dll)
│   │   ├── composables/     # Shared logic & composables
│   │   ├── layouts/         # PosLayout, KdsLayout, CustomerLayout, DashboardLayout
│   │   ├── router/          # Vue Router configuration
│   │   ├── stores/          # Pinia stores (pos, cart, auth)
│   │   └── views/           # Halaman POS, KDS, Customer, & Dashboard
│   └── vite.config.ts
│
├── .gitignore                # Root Git ignore
└── README.md                 # Dokumentasi proyek
```

---

## 🛠️ Panduan Instalasi & Menjalankan Lokal

### Prasyarat:
- **PHP** >= 8.2 & **Composer**
- **Node.js** >= 18.x & **pnpm** (atau **npm**)
- **PostgreSQL** atau **MySQL**

---

### 1. Setup Backend (`pf-backend`)

Masuk ke direktori backend:
```bash
cd pf-backend
```

Install dependensi PHP:
```bash
composer install
```

Salin file environment:
```bash
cp .env.example .env
```

Sesuaikan konfigurasi database dan kredensial di file `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lapaqu_db
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Integrasi Xendit Payment Gateway (Opsional untuk testing sandbox)
XENDIT_SECRET_KEY=xnd_development_...
```

Generate App Key & jalankan migrasi database:
```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Jalankan server backend:
```bash
php artisan serve
# Server akan berjalan di: http://127.0.0.1:8000
```

---

### 2. Setup Frontend (`pf-frontend`)

Buka terminal baru, masuk ke direktori frontend:
```bash
cd pf-frontend
```

Install dependensi Node.js:
```bash
pnpm install
# atau: npm install
```

Salin file konfigurasi environment:
```bash
cp .env.example .env
```

Pastikan URL API mengarah ke backend lokal:
```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

Jalankan server development:
```bash
pnpm run dev
# atau: npm run dev
# Frontend akan berjalan di: http://localhost:5173
```

---

## 🔐 Kredensial Pengguna Demo (Default Seeder)

| Role | Email | Password | Akses Halaman |
| :--- | :--- | :--- | :--- |
| **Owner / Admin** | `owner@lapaqu.com` | `password` | `/dashboard` |
| **Kasir** | `kasir@lapaqu.com` | `password` | `/pos` |
| **Kitchen Staff** | `kitchen@lapaqu.com` | `password` | `/kds` |
| **Pelanggan** | *(Tanpa Login)* | - | `/order/outlet-001/M01` |

---

## 📄 Lisensi

Proyek ini dikembangkan di bawah lisensi [MIT](LICENSE).
