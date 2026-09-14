# 📖 Dokumentasi API Backend POS SaaS (Lapaqu v1)

Selamat datang di dokumentasi resmi Backend REST API & Realtime WebSocket **Lapaqu POS SaaS v1**.
Dokumen ini dirancang sebagai panduan integrasi lengkap untuk tim pengembang Frontend (**Web Customer Self-Order, POS Web Kasir, KDS Kitchen Display System, Back-Office Owner, dan Superadmin Panel**).

---

## 📑 Daftar Isi
1. [Arsitektur & Konvensi Global](#1-arsitektur--konvensi-global)
2. [Demo Accounts & Lingkungan Pengembangan](#2-demo-accounts--lingkungan-pengembangan)
3. [Modul Onboarding & Registrasi Tenant](#3-modul-onboarding--registrasi-tenant)
4. [Modul Autentikasi & Akun Pengguna](#4-modul-autentikasi--akun-pengguna)
5. [Modul Customer Self-Order (Scan QR Meja)](#5-modul-customer-self-order-scan-qr-meja)
6. [Modul Point of Sale (POS Kasir)](#6-modul-point-of-sale-pos-kasir)
7. [Modul Kitchen Display System (KDS) & Realtime WebSocket](#7-modul-kitchen-display-system-kds--realtime-websocket)
8. [Modul Katalog Menu, Meja & QR Generator](#8-modul-katalog-menu-meja--qr-generator)
9. [Modul Rekening Bank & xenPlatform](#9-modul-rekening-bank--xenplatform)
10. [Modul Manajemen Staff & Multi-Outlet](#10-modul-manajemen-staff--multi-outlet)
11. [Modul Refund & Pembatalan Transaksi](#11-modul-refund--pembatalan-transaksi)
12. [Modul Laporan & Analytics Penjualan](#12-modul-laporan--analytics-penjualan)
13. [Modul Notifikasi In-App](#13-modul-notifikasi-in-app)
14. [Modul Webhooks Xendit Payment Gateway](#14-modul-webhooks-xendit-payment-gateway)
15. [Superadmin Panel (Filament)](#15-superadmin-panel-filament)

---

## 1. Arsitektur & Konvensi Global

### Base URL
- **Lokal / WSL**: `http://127.0.0.1:8000/api` atau `http://localhost:8000/api`

### Header Standar
```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {SANCTUM_PLAIN_TEXT_TOKEN}
```

### Multi-Tenancy Resolution
Konteks tenant diidentifikasi secara otomatis melalui:
1. **Otentikasi Sanctum Token**: User yang login otomatis mengikat semua operasi ke `tenant_id` miliknya (Global Scope).
2. **Public Table QR Token**: Untuk pelanggan umum tanpa login, cukup menyertakan `table_token` pada payload request order.

### Format Standar Error Response
```json
{
  "message": "Pesan deskripsi error yang ramah pengguna.",
  "errors": {
    "field_name": ["Detail validasi error."]
  }
}
```

---

## 2. Demo Accounts & Lingkungan Pengembangan

Database telah diisi dengan data demo siap pakai:

| Role | Email Login | Password | Akses & Kegunaan |
|---|---|---|---|
| **Superadmin** | `admin@lapaqu.id` | `LapaquAdmin2026!` | Panel `/pf-admin` (Kelola seluruh tenant & paket) |
| **Owner Resto** | `owner@kopisenopati.id` | `RahasiaKopi123!` | Dashboard Tenant, Menu, Staff, Laporan, Billing |
| **Kasir** | `kasir@kopisenopati.id` | `RahasiaKopi123!` | POS Kasir, Bayar Tunai, Void Item |
| **Kitchen Staff** | `kitchen@kopisenopati.id` | `RahasiaKopi123!` | KDS Dapur (Antrean Masak & Status Menu) |

- **Subdomain Demo**: `kopi-senopati`
- **Meja Demo**: `Meja 01` (QR Token: `qr_senopati_01`) s/d `Meja 05` (QR Token: `qr_senopati_05`)

---

## 3. Modul Onboarding & Registrasi Tenant

### `POST /api/tenant/check-subdomain`
Pengecekan ketersediaan subdomain resto sebelum registrasi (Rate limit: 30/min).
```json
// Request
{
  "subdomain": "kopi-senopati"
}

// Response (200 OK)
{
  "subdomain": "kopi-senopati",
  "available": false
}
```

### `POST /api/onboarding/register`
Registrasi mandiri tenant baru (Atomic 1-step registration). Otomatis membuat Tenant, Trial 14 hari, Outlet Utama, 5 Meja, 3 Kategori, dan Token Sanctum.
```json
// Request
{
  "restaurant_name": "Kopi Mantap Jiwa",
  "subdomain": "kopi-mantap",
  "owner_name": "Ahmad Dani",
  "owner_email": "ahmad@kopimantap.test",
  "owner_password": "PasswordKuat123!",
  "phone": "081299998888",
  "plan_code": "basic"
}

// Response (201 Created)
{
  "message": "Registrasi restoran berhasil! Selamat datang di Lapaqu.",
  "tenant": {
    "id": "uuid",
    "name": "Kopi Mantap Jiwa",
    "subdomain": "kopi-mantap",
    "status": "trial",
    "trial_ends_at": "2026-09-11T08:00:00.000000Z"
  },
  "user": {
    "id": "uuid",
    "name": "Ahmad Dani",
    "email": "ahmad@kopimantap.test",
    "roles": ["owner"]
  },
  "outlet": {
    "id": "uuid",
    "name": "Cabang Utama"
  },
  "token": "1|sanctum_plain_text_token...",
  "token_type": "Bearer"
}
```

---

## 4. Modul Autentikasi & Akun Pengguna

### `POST /api/auth/login`
```json
// Request
{
  "email": "owner@kopisenopati.id",
  "password": "RahasiaKopi123!"
}

// Response (200 OK)
{
  "message": "Login berhasil.",
  "token": "2|sanctum_token...",
  "user": {
    "id": "uuid",
    "name": "Budi Santoso (Owner)",
    "email": "owner@kopisenopati.id",
    "roles": ["owner"]
  },
  "tenant": {
    "id": "uuid",
    "name": "Kopi Kenangan Senopati",
    "subdomain": "kopi-senopati",
    "status": "active"
  }
}
```

### `GET /api/auth/me` (Protected)
Mengambil profil akun yang sedang login beserta role, outlet, dan detail tenant.

### `POST /api/auth/logout` (Protected)
Menghapus token sesi Sanctum saat ini.

### `POST /api/auth/2fa/setup` & `POST /api/auth/2fa/verify` (Protected)
Aktivasi Two-Factor Authentication berbasis TOTP (Google Authenticator).

### `POST /api/auth/forgot-password` & `POST /api/auth/reset-password`
Alur lupa password dengan token reset terenkripsi.

---

## 5. Modul Customer Self-Order (Scan QR Meja)

### `GET /api/public/tables/{qr_code_token}`
Resolusi data resto, outlet, nomor meja, kategori, dan seluruh menu aktif saat pelanggan membuka link QR meja.
```json
// Response (200 OK)
{
  "table": {
    "id": "uuid",
    "table_number": "Meja 01",
    "capacity": 4
  },
  "outlet": {
    "id": "uuid",
    "name": "Cabang Senopati Utama",
    "address": "Jl. Senopati No. 45",
    "timezone": "Asia/Jakarta"
  },
  "tenant": {
    "id": "uuid",
    "name": "Kopi Kenangan Senopati",
    "subdomain": "kopi-senopati"
  },
  "categories": [...],
  "menu_items": [
    {
      "id": "uuid",
      "name": "Kopi Kenangan Mantan",
      "base_price": 18000,
      "variant_groups": [
        {
          "name": "Level Gula",
          "is_required": true,
          "options": [
            {"id": "uuid", "name": "Normal Sugar (100%)", "price_modifier": 0},
            {"id": "uuid", "name": "Less Sugar (50%)", "price_modifier": 0}
          ]
        }
      ]
    }
  ]
}
```

### `POST /api/public/orders`
Checkout pesanan mandiri meja pelanggan.
```json
// Request
{
  "table_token": "qr_senopati_01",
  "customer_name": "Budi",
  "customer_phone": "08123456789",
  "notes": "Pesanan tidak pakai es batu",
  "items": [
    {
      "menu_item_id": "uuid_menu_item",
      "quantity": 2,
      "notes": "Less sugar",
      "selected_option_ids": ["uuid_option_less_sugar"]
    }
  ]
}

// Response (201 Created)
{
  "message": "Pesanan berhasil dibuat. Silakan selesaikan pembayaran.",
  "order": {
    "id": "uuid_order",
    "order_number": "ORD-20260828-A1B2",
    "total_amount": 39600,
    "status": "pending_payment",
    "payment_status": "unpaid"
  },
  "payment": {
    "id": "uuid_payment",
    "payment_method": "qris",
    "qr_string": "00020101021226580014ID.LINKAJA...",
    "amount": 39600
  }
}
```

### `GET /api/public/orders/{id}/status`
Polling status pesanan dan pembayaran realtime dari browser smartphone pelanggan.

---

## 6. Modul Point of Sale (POS Kasir)

*Akses: Role `owner` atau `kasir`*

### `GET /api/pos/orders`
Daftar seluruh pesanan hari ini di outlet (filter `status`, `payment_status`, `date`).

### `POST /api/pos/orders`
Kasir membuat pesanan langsung di kasir (mendukung Dine-in atau Takeaway).
```json
// Request
{
  "outlet_id": "uuid_outlet",
  "order_type": "dine_in",
  "table_id": "uuid_table",
  "customer_name": "Pak Joko",
  "payment_method": "cash",
  "cash_received": 50000,
  "items": [
    {
      "menu_item_id": "uuid",
      "quantity": 1,
      "selected_option_ids": []
    }
  ]
}
```

### `POST /api/pos/orders/{id}/pay-cash`
Mencatat pelunasan uang tunai untuk pesanan yang sebelumnya unpaid.
```json
// Request
{
  "cash_received": 100000
}

// Response (200 OK)
{
  "message": "Pembayaran tunai berhasil dicatat.",
  "cash_received": 100000,
  "change_amount": 25000,
  "order": {...}
}
```

### `POST /api/pos/orders/{id}/void-item`
Membatalkan item tertentu dari pesanan dan otomatis menghitung ulang total tagihan.
```json
// Request
{
  "order_item_id": "uuid_order_item",
  "void_reason": "Salah input pesanan pelanggan"
}
```

### `POST /api/pos/tables/{id}/close-session`
Menutup sesi meja saat pelanggan selesai makan agar meja berstatus kosong.

---

## 7. Modul Kitchen Display System (KDS) & Realtime WebSocket

*Akses: Role `owner`, `kitchen_staff`, `kasir`*

### `GET /api/kds/orders`
Mengambil antrean pesanan aktif dapur (`processing`, `cooking`, `ready`).

### `PATCH /api/kds/items/{id}/status`
Mengubah progres pengerjaan menu dapur: `pending` $\rightarrow$ `cooking` $\rightarrow$ `ready` $\rightarrow$ `served`.

###  Laravel Reverb WebSocket Broadcasts
- **Host**: `ws://localhost:8080` (Port konfigurasi Reverb)
- **Private Channel**: `outlet.{outlet_id}`
- **Events**:
  - `order.created`: Saat order baru dibuat (Customer Self-Order atau POS).
  - `order.paid`: Saat pembayaran QRIS / Tunai telah lunas.
  - `order.status.updated`: Saat status order berubah.
  - `kitchen.item.status.updated`: Saat status pengerjaan menu diupdate oleh chef dapur.

---

## 8. Modul Katalog Menu, Meja & QR Generator

- `GET /api/menu-categories`: List kategori menu outlet.
- `POST /api/menu-categories`: Buat kategori baru.
- `PUT /api/menu-categories/{id}` & `DELETE /api/menu-categories/{id}`.
- `GET /api/menu-items`: List item menu (filter kategori, search).
- `POST /api/menu-items`: Buat menu + grup varian + opsi varian.
- `PATCH /api/menu-items/{id}/toggle-availability`: Toggle instan menu habis / tersedia.
- `GET /api/tables`: List meja & status sesi aktif.
- `POST /api/tables`: Tambah meja baru.
- `POST /api/tables/{id}/regenerate-qr`: Regenerate token QR meja baru.
- `GET /api/tables/{id}/qr-code`: Download / render gambar SVG QR code meja native.

---

## 9. Modul Rekening Bank & xenPlatform

*Akses: Role `owner`*

- `GET /api/payment-account`: Tampilkan nomor rekening tersimpan (masked `******4567`).
- `POST /api/payment-account`: Konfigurasi rekening bank toko (BCA, Mandiri, BRI, BNI, dll).
- `GET /api/payment-account/settlement-logs`: Riwayat pencairan dana & potongan fee.

---

## 10. Modul Manajemen Staff & Multi-Outlet

*Akses: Role `owner`*

- `GET /api/staff`: List seluruh staf toko.
- `POST /api/staff`: Buat akun kasir / kitchen staff baru.
- `PUT /api/staff/{id}` & `DELETE /api/staff/{id}`.
- `GET /api/outlets` & `POST /api/outlets`: Manajemen cabang outlet toko.

---

## 11. Modul Refund & Pembatalan Transaksi

- `POST /api/orders/{id}/refund`: Kasir/Staff mengajukan refund atas pesanan lunas.
- `GET /api/refunds`: Monitoring daftar permohonan refund.
- `POST /api/refunds/{id}/approve` (*Owner only*): Setujui refund, update status order/payment, dan catat audit log.
- `POST /api/refunds/{id}/reject` (*Owner only*): Tolak pengajuan refund dengan alasan penolakan.

---

## 12. Modul Laporan & Analytics Penjualan

*Akses: Role `owner`*

- `GET /api/reports/sales-summary?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`: Ringkasan omzet kotor, diskon, omzet bersih, total transaksi, AOV, dan breakdown metode bayar.
- `GET /api/reports/top-items`: Peringkat 10 menu terlaris.
- `GET /api/reports/hourly-sales?date=YYYY-MM-DD`: Distribusi grafik penjualan 24 jam (*peak hours analysis*).
- `GET /api/reports/export-csv`: Unduh file rekapan transaksi dalam format `.csv`.

---

## 13. Modul Notifikasi In-App

- `GET /api/notifications`: Daftar notifikasi pengguna + jumlah pesan belum dibaca.
- `PATCH /api/notifications/{id}/read`: Tandai notifikasi telah dibaca.

---

## 14. Modul Webhooks Xendit Payment Gateway

Endpoint callback untuk menerima notifikasi otomatis dari Xendit:
1. `POST /api/webhooks/xendit/platform-billing`: Notifikasi invoice langganan platform lunas (`PAID`).
2. `POST /api/webhooks/xendit/order-payment`: Notifikasi pembayaran QRIS pesanan pelanggan lunas (`COMPLETED`).

*Semua webhook memvalidasi header token `x-callback-token`.*

---

## 15. Superadmin Panel (Filament)

- **URL Panel**: `http://localhost:8000/pf-admin`
- **Kredensial**: `admin@lapaqu.id` / `LapaquAdmin2026!`
- **Fitur Tersedia**:
  - `Tenants`: Monitoring status tenant, extend masa trial +7 hari, suspend toko, atau aktivasi ulang.
  - `Plans`: Konfigurasi paket langganan dan harga bulanan per outlet.
  - `Billing Invoices`: Monitoring riwayat tagihan invoice platform.
  - `Audit Logs`: Rekam jejak seluruh aktivitas penting di platform.
