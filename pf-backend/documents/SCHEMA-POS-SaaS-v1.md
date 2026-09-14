# Database Schema — Sistem POS Multi-Tenant SaaS
**Turunan dari:** `ERD-POS-SaaS-v1.md` dan `ARCHITECTURE-POS-SaaS-v1.md`, disusun sebagai DDL PostgreSQL siap dipakai acuan migration Laravel.

---

## 1. Konvensi Umum

| Aspek | Aturan |
|---|---|
| **Primary key** | `UUID` (`gen_random_uuid()` / `uuid_generate_v4()`), bukan auto-increment integer — mempermudah generate ID di frontend/queue tanpa round-trip DB, aman dari enumerasi cross-tenant |
| **Timestamp** | Selalu `TIMESTAMPTZ` (`TIMESTAMP WITH TIME ZONE`), disimpan UTC; konversi ke timezone outlet dilakukan di layer presentasi (Architecture 6) |
| **Uang/nominal** | `INTEGER`, satuan Rupiah penuh (bukan sen, bukan `DECIMAL`) — mis. Rp 25.000 = `25000` (Architecture 6) |
| **Soft delete** | Kolom `deleted_at TIMESTAMPTZ NULL` (pola Laravel `SoftDeletes`), wajib di entity yang direferensikan `Order` |
| **Multi-tenancy** | Kolom `tenant_id UUID NOT NULL` (atau nullable khusus baris platform-level) di semua tabel tenant-scoped, wajib diindeks & wajib masuk **global scope** Eloquent |
| **Enum** | Diimplementasikan sebagai PostgreSQL `TYPE ... AS ENUM` agar tervalidasi di level DB, bukan hanya `VARCHAR` + validasi aplikasi |
| **Penamaan tabel** | `snake_case`, jamak (`orders`, `menu_items`) — standar konvensi Laravel Eloquent |
| **Foreign key** | Eksplisit dengan `ON DELETE` sesuai kebutuhan bisnis (lihat catatan per tabel); default `RESTRICT` kecuali dinyatakan lain |

---

## 2. Enum Types

```sql
CREATE TYPE tenant_status AS ENUM ('trial', 'active', 'overdue', 'suspended', 'churned');
CREATE TYPE subscription_status AS ENUM ('trial', 'active', 'overdue', 'suspended', 'churned');
CREATE TYPE invoice_status AS ENUM ('unpaid', 'paid', 'expired');
CREATE TYPE verification_status AS ENUM ('pending', 'verified', 'rejected');
CREATE TYPE settlement_type AS ENUM ('settlement', 'refund_deduction');
CREATE TYPE table_session_status AS ENUM ('active', 'closed');
CREATE TYPE table_session_closed_reason AS ENUM ('timeout', 'manual_kasir', 'manual_owner');
CREATE TYPE stock_type AS ENUM ('unlimited', 'count');
CREATE TYPE menu_item_status AS ENUM ('available', 'out_of_stock');
CREATE TYPE variant_group_type AS ENUM ('single', 'multi');
CREATE TYPE order_source AS ENUM ('qr', 'manual_kasir');
CREATE TYPE order_status AS ENUM ('awaiting_payment', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled');
CREATE TYPE payment_method AS ENUM ('xendit_qris', 'xendit_ewallet', 'xendit_card', 'cash');
CREATE TYPE payment_status AS ENUM ('pending', 'paid', 'expired', 'failed');
CREATE TYPE refund_status AS ENUM ('pending', 'approved', 'rejected', 'processed');
```

---

## 3. Platform & Billing

### 3.1 `plans`
Master paket berlangganan (Basic/Pro). Harga bersifat dummy/placeholder, editable via Filament tanpa deploy ulang.

```sql
CREATE TABLE plans (
    id                     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name                   VARCHAR(50) NOT NULL,              -- 'Basic', 'Pro'
    price_per_outlet       INTEGER NOT NULL,                  -- IDR, dummy/placeholder
    max_outlets            INTEGER NOT NULL,
    max_tables_per_outlet  INTEGER NOT NULL,
    max_users_per_outlet   INTEGER NOT NULL,
    is_active              BOOLEAN NOT NULL DEFAULT true,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at             TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 3.2 `tenants`
Root isolasi data. `status` mengikuti state machine section 2.4b PRD.

```sql
CREATE TABLE tenants (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_name  VARCHAR(150) NOT NULL,
    subdomain      VARCHAR(30) NOT NULL UNIQUE,               -- aturan validasi: lihat Architecture 6
    status         tenant_status NOT NULL DEFAULT 'trial',
    trial_ends_at  TIMESTAMPTZ NOT NULL,
    churned_at     TIMESTAMPTZ NULL,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at     TIMESTAMPTZ NULL                           -- soft delete, dieksekusi hard-delete oleh job 30 hari pasca-churned
);

CREATE INDEX idx_tenants_status ON tenants (status);
```

### 3.3 `subscriptions`
Jembatan Tenant ↔ Plan. Histori disimpan (1 tenant bisa punya banyak baris sepanjang waktu) agar riwayat upgrade/downgrade tercatat.

```sql
CREATE TABLE subscriptions (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id     UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    plan_id       UUID NOT NULL REFERENCES plans(id) ON DELETE RESTRICT,
    status        subscription_status NOT NULL,
    period_start  DATE NOT NULL,
    period_end    DATE NOT NULL,
    due_date      DATE NOT NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_subscriptions_tenant_id ON subscriptions (tenant_id);
-- Hanya boleh 1 subscription berstatus aktif per tenant pada satu waktu (divalidasi di aplikasi + partial index sbg pengaman):
CREATE UNIQUE INDEX uidx_subscriptions_one_active_per_tenant
    ON subscriptions (tenant_id)
    WHERE status IN ('trial', 'active', 'overdue', 'suspended');
```

### 3.4 `billing_invoices`
Riwayat invoice Xendit Platform Billing, termasuk `outlet_count_billed` (model pricing per-outlet, section 2.4c).

```sql
CREATE TABLE billing_invoices (
    id                   UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_id      UUID NOT NULL REFERENCES subscriptions(id) ON DELETE CASCADE,
    xendit_invoice_id    VARCHAR(100) NOT NULL UNIQUE,
    amount               INTEGER NOT NULL,                    -- IDR
    outlet_count_billed  INTEGER NOT NULL,
    status               invoice_status NOT NULL DEFAULT 'unpaid',
    due_date             DATE NOT NULL,
    paid_at              TIMESTAMPTZ NULL,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_billing_invoices_subscription_id ON billing_invoices (subscription_id);
CREATE INDEX idx_billing_invoices_status ON billing_invoices (status);
```

---

## 4. Tenant Payment (xenPlatform)

### 4.1 `tenant_payment_accounts`
Relasi 1:1 ke tenant. Menyimpan `sub_account_id` Xendit xenPlatform + status verifikasi KYC ringan (PRD 13.1).

```sql
CREATE TABLE tenant_payment_accounts (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id               UUID NOT NULL UNIQUE REFERENCES tenants(id) ON DELETE CASCADE,
    xendit_sub_account_id   VARCHAR(100) NULL UNIQUE,         -- null sebelum sub-account berhasil dibuat
    bank_name               VARCHAR(100) NOT NULL,
    bank_account_number     VARCHAR(100) NOT NULL,            -- encrypted casting (Laravel) di level aplikasi
    bank_account_holder     VARCHAR(150) NOT NULL,
    verification_status     verification_status NOT NULL DEFAULT 'pending',
    rejection_reason        TEXT NULL,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

> **Catatan keamanan:** `bank_account_number` disimpan terenkripsi di level aplikasi (Laravel Encrypted Casting) sesuai Architecture 5 — kolom tetap `VARCHAR` di DB karena ciphertext, bukan `TEXT` yang perlu pencarian.

### 4.2 `settlement_logs`
Riwayat pencairan dana & pemotongan akibat refund (PRD 6.6).

```sql
CREATE TABLE settlement_logs (
    id                          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_payment_account_id   UUID NOT NULL REFERENCES tenant_payment_accounts(id) ON DELETE CASCADE,
    type                         settlement_type NOT NULL,
    amount                       INTEGER NOT NULL,             -- IDR, bisa negatif untuk refund_deduction
    xendit_reference_id          VARCHAR(100) NULL,
    raw_payload                  JSONB NULL,
    settled_at                   TIMESTAMPTZ NOT NULL,
    created_at                   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_settlement_logs_account_id ON settlement_logs (tenant_payment_account_id);
```

---

## 5. Outlet, Meja & Session

### 5.1 `outlets`
Unit operasional & unit billing utama (PRD 2.4c). `timezone` dipakai untuk konversi tampilan & perhitungan jatuh tempo (Architecture 6).

```sql
CREATE TABLE outlets (
    id                              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id                       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name                            VARCHAR(150) NOT NULL,
    branding_display_name           VARCHAR(150) NULL,
    branding_logo_url               VARCHAR(255) NULL,
    timezone                        VARCHAR(50) NOT NULL DEFAULT 'Asia/Jakarta',
    min_order_amount                INTEGER NOT NULL DEFAULT 0,   -- IDR, 0 = tidak ada minimum
    default_session_timeout_minutes INTEGER NOT NULL DEFAULT 60,
    is_active                       BOOLEAN NOT NULL DEFAULT true,
    created_at                      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_outlets_tenant_id ON outlets (tenant_id);
```

### 5.2 `tables`
Meja fisik dengan QR unik per outlet. Nama tabel `tables` berpotensi bentrok kata kunci SQL di sebagian tooling — gunakan nama fisik `restaurant_tables` bila diperlukan; dokumen ini memakai `tables` mengikuti istilah PRD.

```sql
CREATE TABLE tables (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outlet_id               UUID NOT NULL REFERENCES outlets(id) ON DELETE CASCADE,
    code                    VARCHAR(20) NOT NULL,                -- mis. 'A1', 'B12'
    qr_token                VARCHAR(100) NOT NULL UNIQUE,
    session_timeout_minutes INTEGER NULL,                        -- null = pakai default outlet
    is_active                BOOLEAN NOT NULL DEFAULT true,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uidx_tables_outlet_code ON tables (outlet_id, code);
CREATE INDEX idx_tables_outlet_id ON tables (outlet_id);
```

### 5.3 `table_sessions`
Pengelompokan kunjungan customer per meja (PRD 5.1). Hanya boleh 1 sesi `active` per meja pada satu waktu.

```sql
CREATE TABLE table_sessions (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    table_id      UUID NOT NULL REFERENCES tables(id) ON DELETE CASCADE,
    status        table_session_status NOT NULL DEFAULT 'active',
    started_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    closed_at     TIMESTAMPTZ NULL,
    closed_reason table_session_closed_reason NULL
);

CREATE INDEX idx_table_sessions_table_id ON table_sessions (table_id);
-- Pengaman di level DB: 1 meja hanya boleh punya 1 sesi aktif pada satu waktu
CREATE UNIQUE INDEX uidx_table_sessions_one_active_per_table
    ON table_sessions (table_id)
    WHERE status = 'active';
```

---

## 6. Menu & Varian
*Semua tabel di grup ini **wajib soft delete** karena direferensikan `order_items` (PRD section 8).*

### 6.1 `menu_categories`

```sql
CREATE TABLE menu_categories (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    outlet_id   UUID NOT NULL REFERENCES outlets(id) ON DELETE CASCADE,
    name        VARCHAR(100) NOT NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at  TIMESTAMPTZ NULL
);

CREATE INDEX idx_menu_categories_outlet_id ON menu_categories (outlet_id);
```

### 6.2 `menu_items`
`outlet_id` didenormalisasi dari `menu_categories.outlet_id` untuk menghindari join berlapis saat query per-outlet (ERD section 3, poin 2).

```sql
CREATE TABLE menu_items (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    menu_category_id  UUID NOT NULL REFERENCES menu_categories(id) ON DELETE RESTRICT,
    outlet_id         UUID NOT NULL REFERENCES outlets(id) ON DELETE CASCADE,
    name              VARCHAR(150) NOT NULL,
    description       TEXT NULL,
    base_price        INTEGER NOT NULL,                       -- IDR, sudah termasuk pajak (kebijakan Opsi A, PRD 6.3)
    photo_url         VARCHAR(255) NULL,
    stock_type        stock_type NOT NULL DEFAULT 'unlimited',
    stock_qty         INTEGER NULL,                            -- wajib diisi jika stock_type = 'count'
    status            menu_item_status NOT NULL DEFAULT 'available',
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at        TIMESTAMPTZ NULL,

    CONSTRAINT chk_menu_items_stock_qty
        CHECK (stock_type = 'unlimited' OR stock_qty IS NOT NULL)
);

CREATE INDEX idx_menu_items_outlet_id ON menu_items (outlet_id);
CREATE INDEX idx_menu_items_category_id ON menu_items (menu_category_id);
```

### 6.3 `menu_item_variant_groups`
Mis. "Ukuran" (single, required), "Topping" (multi, optional) — lihat PRD 6.3.1.

```sql
CREATE TABLE menu_item_variant_groups (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    menu_item_id  UUID NOT NULL REFERENCES menu_items(id) ON DELETE CASCADE,
    name          VARCHAR(100) NOT NULL,
    type          variant_group_type NOT NULL,
    is_required   BOOLEAN NOT NULL DEFAULT false,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at    TIMESTAMPTZ NULL
);

CREATE INDEX idx_variant_groups_menu_item_id ON menu_item_variant_groups (menu_item_id);
```

### 6.4 `menu_item_variant_options`

```sql
CREATE TABLE menu_item_variant_options (
    id                 UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    variant_group_id   UUID NOT NULL REFERENCES menu_item_variant_groups(id) ON DELETE CASCADE,
    name               VARCHAR(100) NOT NULL,
    price_modifier     INTEGER NOT NULL DEFAULT 0,             -- IDR, tambahan harga (PRD 6.3.1)
    sort_order         INTEGER NOT NULL DEFAULT 0,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at         TIMESTAMPTZ NULL
);

CREATE INDEX idx_variant_options_group_id ON menu_item_variant_options (variant_group_id);
```

---

## 7. User, Role & Permission
*Mengikuti pola standar `spatie/laravel-permission` — tabel pivot polymorphic agar 1 model bisa dipakai untuk berbagai guard (misal jika suatu saat ada tipe actor lain).*

### 7.1 `users`

```sql
CREATE TABLE users (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID NULL REFERENCES tenants(id) ON DELETE CASCADE,   -- null khusus Superadmin platform
    outlet_id           UUID NULL REFERENCES outlets(id) ON DELETE SET NULL, -- null untuk Owner/Superadmin
    name                VARCHAR(150) NOT NULL,
    email               VARCHAR(150) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    phone               VARCHAR(30) NULL,
    two_factor_enabled  BOOLEAN NOT NULL DEFAULT false,        -- wajib true untuk Superadmin & Owner (enforced di aplikasi)
    two_factor_secret   VARCHAR(255) NULL,                     -- encrypted casting
    is_active            BOOLEAN NOT NULL DEFAULT true,
    email_verified_at    TIMESTAMPTZ NULL,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at            TIMESTAMPTZ NULL
);

-- Email unik secara global (Sanctum auth pakai email), bukan unik per tenant,
-- karena 1 orang idealnya tidak dobel akun lintas tenant dengan email sama.
CREATE UNIQUE INDEX uidx_users_email ON users (email) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_tenant_id ON users (tenant_id);
CREATE INDEX idx_users_outlet_id ON users (outlet_id);
```

### 7.2 `roles` & `permissions`

```sql
CREATE TABLE roles (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name        VARCHAR(50) NOT NULL,          -- 'superadmin', 'owner', 'kasir', 'kitchen_staff'
    guard_name  VARCHAR(50) NOT NULL DEFAULT 'sanctum',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uidx_roles_name_guard ON roles (name, guard_name);

CREATE TABLE permissions (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name        VARCHAR(100) NOT NULL,         -- mis. 'menu.create', 'refund.approve'
    guard_name  VARCHAR(50) NOT NULL DEFAULT 'sanctum',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uidx_permissions_name_guard ON permissions (name, guard_name);
```

### 7.3 Tabel pivot RBAC

```sql
CREATE TABLE model_has_roles (
    role_id     UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    model_type  VARCHAR(100) NOT NULL DEFAULT 'App\\Models\\User',
    model_id    UUID NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type)
);

CREATE INDEX idx_model_has_roles_model ON model_has_roles (model_id, model_type);

CREATE TABLE model_has_permissions (
    permission_id  UUID NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    model_type      VARCHAR(100) NOT NULL DEFAULT 'App\\Models\\User',
    model_id        UUID NOT NULL,
    PRIMARY KEY (permission_id, model_id, model_type)
);

CREATE TABLE role_has_permissions (
    permission_id  UUID NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    role_id         UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, role_id)
);
```

---

## 8. Order & Transaksi

### 8.1 `orders`
`table_session_id` nullable untuk order takeaway "Tanpa Meja" (PRD 5.5). `cashier_user_id` hanya terisi jika `source = 'manual_kasir'`.

```sql
CREATE TABLE orders (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id         UUID NOT NULL REFERENCES tenants(id) ON DELETE RESTRICT,
    outlet_id         UUID NOT NULL REFERENCES outlets(id) ON DELETE RESTRICT,
    table_session_id  UUID NULL REFERENCES table_sessions(id) ON DELETE SET NULL,
    cashier_user_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    source            order_source NOT NULL,
    status            order_status NOT NULL DEFAULT 'awaiting_payment',
    payment_method    payment_method NOT NULL,
    subtotal          INTEGER NOT NULL,                       -- IDR
    total             INTEGER NOT NULL,                       -- IDR
    confirmed_at      TIMESTAMPTZ NULL,
    cancelled_at      TIMESTAMPTZ NULL,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_orders_cashier_required_for_manual
        CHECK (source <> 'manual_kasir' OR cashier_user_id IS NOT NULL)
);

CREATE INDEX idx_orders_tenant_id ON orders (tenant_id);
CREATE INDEX idx_orders_outlet_id ON orders (outlet_id);
CREATE INDEX idx_orders_table_session_id ON orders (table_session_id);
CREATE INDEX idx_orders_status ON orders (status);
CREATE INDEX idx_orders_created_at ON orders (created_at);   -- untuk laporan harian/mingguan/bulanan
```

### 8.2 `order_items`
Snapshot nama & harga dasar saat order dibuat, tidak berubah retroaktif jika owner edit menu (PRD 6.3.1).

```sql
CREATE TABLE order_items (
    id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id              UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    menu_item_id          UUID NOT NULL REFERENCES menu_items(id) ON DELETE RESTRICT, -- soft-deleted, referensi tetap valid
    item_name_snapshot    VARCHAR(150) NOT NULL,
    base_price_snapshot   INTEGER NOT NULL,                   -- IDR
    qty                   INTEGER NOT NULL CHECK (qty > 0),
    notes                 TEXT NULL,
    subtotal              INTEGER NOT NULL,                   -- IDR, (base_price_snapshot + sum(price_modifier)) * qty
    is_voided             BOOLEAN NOT NULL DEFAULT false,
    voided_by_user_id     UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    voided_at             TIMESTAMPTZ NULL,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_order_items_order_id ON order_items (order_id);
CREATE INDEX idx_order_items_menu_item_id ON order_items (menu_item_id);   -- untuk laporan menu terlaris
```

### 8.3 `order_item_options`
Snapshot varian/add-on yang dipilih customer per item (PRD 6.3.1).

```sql
CREATE TABLE order_item_options (
    id                        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_item_id             UUID NOT NULL REFERENCES order_items(id) ON DELETE CASCADE,
    variant_option_id         UUID NULL REFERENCES menu_item_variant_options(id) ON DELETE SET NULL,
    option_name_snapshot      VARCHAR(100) NOT NULL,
    price_modifier_snapshot   INTEGER NOT NULL DEFAULT 0,     -- IDR
    created_at                TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_order_item_options_order_item_id ON order_item_options (order_item_id);
```

### 8.4 `payments`
Dibuat untuk semua metode termasuk cash (`xendit_transaction_id = NULL`), agar laporan per metode pembayaran konsisten (PRD 5.5).

```sql
CREATE TABLE payments (
    id                       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id                 UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    xendit_transaction_id    VARCHAR(100) NULL UNIQUE,        -- null untuk cash
    payment_method           payment_method NOT NULL,
    amount                   INTEGER NOT NULL,                -- IDR
    status                   payment_status NOT NULL DEFAULT 'pending',
    raw_payload               JSONB NULL,
    paid_at                   TIMESTAMPTZ NULL,
    created_at                TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_payments_order_id ON payments (order_id);
CREATE INDEX idx_payments_status ON payments (status);
```

### 8.5 `refund_requests`
Alur approval manual oleh Superadmin (PRD 6.6).

```sql
CREATE TABLE refund_requests (
    id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id              UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    requested_by_user_id  UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    reviewed_by_user_id   UUID NULL REFERENCES users(id) ON DELETE SET NULL,   -- superadmin
    amount                INTEGER NOT NULL,                  -- IDR, partial/full
    reason                TEXT NOT NULL,
    status                refund_status NOT NULL DEFAULT 'pending',
    xendit_refund_id      VARCHAR(100) NULL,
    reviewed_at           TIMESTAMPTZ NULL,
    processed_at          TIMESTAMPTZ NULL,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_refund_requests_order_id ON refund_requests (order_id);
CREATE INDEX idx_refund_requests_status ON refund_requests (status);
```

---

## 9. Audit

### 9.1 `audit_logs`

```sql
CREATE TABLE audit_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NULL REFERENCES tenants(id) ON DELETE CASCADE,  -- null untuk aksi level platform
    actor_user_id   UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    action          VARCHAR(100) NOT NULL,               -- mis. 'order_item.void', 'tenant.suspend', 'refund.approve'
    target_type     VARCHAR(100) NOT NULL,               -- mis. 'Order', 'Tenant'
    target_id       UUID NOT NULL,
    metadata        JSONB NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_audit_logs_tenant_id ON audit_logs (tenant_id);
CREATE INDEX idx_audit_logs_target ON audit_logs (target_type, target_id);
CREATE INDEX idx_audit_logs_created_at ON audit_logs (created_at);
```

---

## 10. Ringkasan Tabel

| # | Tabel | Grup | Tenant-scoped? | Soft delete? |
|---|---|---|---|---|
| 1 | `plans` | Platform & Billing | Tidak (global) | Tidak |
| 2 | `tenants` | Platform & Billing | — (root) | Ya |
| 3 | `subscriptions` | Platform & Billing | Ya | Tidak |
| 4 | `billing_invoices` | Platform & Billing | Ya (via subscription) | Tidak |
| 5 | `tenant_payment_accounts` | Tenant Payment | Ya | Tidak |
| 6 | `settlement_logs` | Tenant Payment | Ya (via account) | Tidak |
| 7 | `outlets` | Outlet & Meja | Ya | Tidak |
| 8 | `tables` | Outlet & Meja | Ya (via outlet) | Tidak |
| 9 | `table_sessions` | Outlet & Meja | Ya (via table) | Tidak |
| 10 | `menu_categories` | Menu & Varian | Ya (via outlet) | **Ya** |
| 11 | `menu_items` | Menu & Varian | Ya | **Ya** |
| 12 | `menu_item_variant_groups` | Menu & Varian | Ya (via item) | **Ya** |
| 13 | `menu_item_variant_options` | Menu & Varian | Ya (via group) | **Ya** |
| 14 | `users` | User & RBAC | Ya (nullable utk Superadmin) | Ya |
| 15 | `roles` | User & RBAC | Tidak (global) | Tidak |
| 16 | `permissions` | User & RBAC | Tidak (global) | Tidak |
| 17 | `model_has_roles` | User & RBAC | — (pivot) | Tidak |
| 18 | `model_has_permissions` | User & RBAC | — (pivot) | Tidak |
| 19 | `role_has_permissions` | User & RBAC | — (pivot) | Tidak |
| 20 | `orders` | Order & Transaksi | Ya | Tidak |
| 21 | `order_items` | Order & Transaksi | Ya (via order) | Tidak |
| 22 | `order_item_options` | Order & Transaksi | Ya (via order_item) | Tidak |
| 23 | `payments` | Order & Transaksi | Ya (via order) | Tidak |
| 24 | `refund_requests` | Order & Transaksi | Ya (via order) | Tidak |
| 25 | `audit_logs` | Audit | Ya (nullable utk platform) | Tidak |

---

## 11. Strategi Indexing & Multi-Tenancy

- **Setiap kolom `tenant_id` langsung diindeks** — global scope Eloquent akan menambahkan filter `WHERE tenant_id = ?` di hampir semua query, sehingga index ini kritikal untuk performa (Architecture 5: isolasi tenant wajib + automated test).
- **Foreign key ke tabel tenant-scoped juga diindeks** (`outlet_id`, `order_id`, dst.) karena dipakai untuk join dan filter turunan tenant.
- **Partial unique index** dipakai untuk 2 invariant bisnis penting yang idealnya dijaga di level DB, bukan hanya aplikasi:
  1. `table_sessions`: hanya 1 sesi `active` per meja (mencegah race condition dua request submit order bersamaan membuat 2 sesi aktif).
  2. `subscriptions`: hanya 1 subscription non-`churned` per tenant.
- **Index `created_at`** di `orders` dan `audit_logs` untuk mempercepat query laporan berbasis rentang tanggal (harian/mingguan/bulanan, section 6.4 PRD).
- **`orders.status`** dan **`payments.status`** diindeks karena dashboard Kasir/KDS/Owner sering memfilter berdasarkan status (order masuk, order aktif, dsb).

---

## 12. Catatan Migrasi & Retensi

- **Retensi data:** `orders`, `order_items`, `payments` disimpan minimal 1 tahun (Architecture 5); tidak ada mekanisme hard-delete otomatis di MVP — hanya kandidat archive ke cold storage di fase berikutnya.
- **Penghapusan tenant churned:** job terjadwal melakukan hard-delete pada `tenants` dan seluruh data turunannya (via `ON DELETE CASCADE`) 30 hari setelah `churned_at` — pastikan urutan FK `CASCADE` di atas sudah benar sebelum job ini diaktifkan di production.
- **Enkripsi:** `tenant_payment_accounts.bank_account_number` dan `users.two_factor_secret` memakai Laravel Encrypted Casting di level model — tidak ada enkripsi native di kolom DB, jadi field ini **tidak bisa di-`WHERE`/di-index langsung** dalam bentuk plaintext.
- **Migration awal disarankan per grup** (mengikuti urutan section 3–9 dokumen ini) agar foreign key terpenuhi secara berurutan: Platform & Billing → Tenant Payment → Outlet & Meja → Menu & Varian → User & RBAC → Order & Transaksi → Audit.

---

**Catatan:** Dokumen ini adalah DDL referensi, bukan file migration final — penyesuaian kecil (nama constraint, index tambahan) wajar terjadi saat implementasi Laravel migration. Untuk gambaran relasi visual, lihat `ERD-POS-SaaS-v1.md`; untuk konteks infrastruktur & keamanan, lihat `ARCHITECTURE-POS-SaaS-v1.md`.
