# Lapaqu - Integrated Restaurant Operating System

A modern multi-tenant restaurant management platform combining customer QR table self-ordering, Point of Sale (POS), Kitchen Display System (KDS), recipe-based inventory management (Bill of Materials), and back-office business analytics.

---

## Table of Contents

- [System Architecture](#system-architecture)
- [Technology Stack](#technology-stack)
- [Core Modules](#core-modules)
  - [1. Customer Self-Order (Dynamic Table QR)](#1-customer-self-order-dynamic-table-qr)
  - [2. Point of Sale (POS Cashier)](#2-point-of-sale-pos-cashier)
  - [3. Kitchen Display System (KDS Kitchen)](#3-kitchen-display-system-kds-kitchen)
  - [4. Management & Owner Dashboard](#4-management--owner-dashboard)
- [Real-Time Synchronization & Event Broadcasting](#real-time-synchronization--event-broadcasting)
- [Monorepo Directory Layout](#monorepo-directory-layout)
- [Prerequisites](#prerequisites)
- [Installation & Local Setup](#installation--local-setup)
  - [1. Backend Setup (Laravel 11)](#1-backend-setup-laravel-11)
  - [2. Frontend Setup (Vue 3 + Vite)](#2-frontend-setup-vue-3--vite)
  - [3. Real-Time WebSocket Server (Laravel Reverb)](#3-real-time-websocket-server-laravel-reverb)
- [Environment Variables Reference](#environment-variables-reference)
  - [Backend (.env)](#backend-pf-backendenv)
  - [Frontend (.env)](#frontend-pf-frontendenv)
- [Seeded Demonstration Accounts](#seeded-demonstration-accounts)
- [REST API Endpoints Reference](#rest-api-endpoints-reference)
- [Production Deployment Notes](#production-deployment-notes)

---

## System Architecture

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
          | Relational DB     | | Cache & Queue | | WebSocket Server   |
          +-------------------+ +---------------+ +--------------------+
```

---

## Technology Stack

### Backend
- Framework: Laravel 11 (PHP 8.2+)
- Authentication: Laravel Sanctum (Bearer Token & Stateful Sessions)
- Authorization: Spatie Laravel-Permission (Role-Based Access Control)
- Database: PostgreSQL 16
- Caching, Session & Queue: Redis
- Real-Time WebSocket Engine: Laravel Reverb
- Payment Gateway: Xendit API (Dynamic QRIS, Virtual Accounts, & Webhooks)

### Frontend
- Framework: Vue 3 (Composition API with script setup TypeScript)
- State Management: Pinia (auth, cart, pos)
- Build Tool: Vite 5
- Styling: Tailwind CSS with Custom Theme Tokens & Dark Mode Support
- WebSocket Client: Laravel Echo & Pusher-js
- Audio & Alerts: Web Audio API Synthesizer & Notyf
- Localization: Reactive i18n Engine (Indonesian & English)

---

## Core Modules

### 1. Customer Self-Order (Dynamic Table QR)
- Direct table access via printed QR Codes (/order/:outletId/:tableToken).
- Responsive catalog navigation with instant search, category filtering, and variant customization (sugar levels, ice levels, portion sizes, add-on toppings).
- Automated inventory validation preventing orders when raw ingredients are depleted.
- Automated payment routing:
  - Dynamic QRIS via Xendit with countdown timer and automatic status updates via webhooks and polling.
  - Multi-Bank Virtual Accounts (BCA, Mandiri, BRI, BNI, Permata).
  - Cash at cashier option triggering real-time cashier terminal alerts.
- Live order tracking timeline: Pending Payment -> Received -> Preparing -> Ready to Serve -> Completed.

### 2. Point of Sale (POS Cashier)
- High-efficiency cashier workspace for accepting incoming online orders and manual on-premise transactions (Dine-in / Takeaway).
- Real-time incoming order queue with distinct audio alerts on new order arrival.
- Interactive table map displaying real-time occupancy status (Available, Occupied, Billing).
- Quick-Stock toggle to instantly update menu availability (Available / Sold Out) without navigating deep settings.
- Thermal receipt printing with direct browser print preview integration.

### 3. Kitchen Display System (KDS Kitchen)
- First-In, First-Out (FIFO) kitchen order ticket queue.
- Item-by-item preparation checklist for kitchen staff.
- Partial dispatch support (serving drinks or appetizers before main courses).
- Time-elapsed SLA threshold warnings with color-coded alerts to mitigate fulfillment delays.
- Instant two-way synchronization across kitchen displays, cashier terminals, and customer views via WebSockets.

### 4. Management & Owner Dashboard
- Real-time financial metrics: Gross Sales, Net Revenue, Total Orders, and Average Order Value.
- Peak operational hours analysis and top-selling menu items ranking.
- Raw Materials & Recipe Cost Management (Bill of Materials / BOM):
  - Automatic ingredient deduction upon order completion.
  - Low stock alerts based on configurable minimum threshold levels.
  - Audit trail for inventory adjustments and periodic Stock Opname tracking.
- Multi-Tenant & Multi-Outlet Administration:
  - Custom branding, restaurant logo, business address, tax rates (PB1 / PPN), and service charges.
  - Dynamic batch table QR Code generator.
  - Restaurant subscription tier management (Starter, Pro, Enterprise).

---

## Real-Time Synchronization & Event Broadcasting

The platform leverages Laravel Reverb to broadcast events to connected clients instantaneously:

| Channel | Event | Event Trigger | Event Consumer |
| :--- | :--- | :--- | :--- |
| orders.{outletId} | NewOrderPlacedEvent | New order submitted by customer or cashier | Cashier POS Queue |
| orders.{outletId} | OrderStatusUpdatedEvent | Order status transition by POS or KDS | POS, KDS, Customer View |
| kds.{outletId} | OrderSentToKitchenEvent | Order verified and dispatched to kitchen | Kitchen KDS Screen |
| table.{tableToken} | TableStatusChangedEvent | Table session opened, closed, or cleared | Customer Screen Lock |

---

## Monorepo Directory Layout

```text
lapaqu/
|-- pf-backend/                      # Backend API built with Laravel 11
|   |-- app/
|   |   |-- Events/                  # WebSocket Broadcasting Event Definitions
|   |   |-- Http/
|   |   |   |-- Controllers/Api/     # REST Controllers (POS, KDS, Customer, Dashboard)
|   |   |   `-- Middleware/          # Auth, Tenant Scope, and Cache Middleware
|   |   |-- Models/                  # Eloquent Entities & Database Relationships
|   |   |-- Services/                # Xendit, Inventory, and Notification Services
|   |   `-- Traits/                  # BelongsToTenant (Multi-Tenant Global Query Scope)
|   |-- config/                      # Framework, Reverb, and Service Configurations
|   |-- database/
|   |   |-- migrations/              # Database Schema Migrations
|   |   `-- seeders/                 # Master Data, Tenant, Menu, and Demo Seeders
|   `-- routes/
|       |-- api.php                  # REST API Routes
|       `-- channels.php             # Private WebSocket Channel Authorization
|
|-- pf-frontend/                     # Frontend SPA built with Vue 3 + Vite
|   |-- src/
|   |   |-- assets/                  # Brand Assets, Default Avatars, and Illustrations
|   |   |-- components/
|   |   |   |-- layout/              # AppSidebar, AppTopbar, Footer Components
|   |   |   |-- settings/            # Branding, Profile, and Subscription Settings Modals
|   |   |   `-- ui/                  # Reusable UI Library (AppTable, AppButton, AppModal)
|   |   |-- composables/             # Shared Hooks (Motion, Formatters, Event Listeners)
|   |   |-- i18n/                    # Localization Dictionaries (id.ts, en.ts)
|   |   |-- layouts/                 # CustomerLayout, PosLayout, KdsLayout, AuthLayout
|   |   |-- services/                # Axios Instance, apiCache, Sound & Notification Services
|   |   |-- stores/                  # Pinia Stores (auth, cart, pos)
|   |   |-- views/                   # Customer, POS, KDS, and Dashboard Views
|   |   `-- router/                  # Vue Router Definitions & Navigation Guards
|   `-- vite.config.ts               # Vite Configuration & API Proxy Rules
|
`-- README.md
```

---

## Prerequisites

- PHP: 8.2 or higher
- Composer: Version 2.x
- Node.js: Version 18.x or 20.x LTS
- Package Manager: npm or pnpm
- Database: PostgreSQL 14+ (or MySQL 8.0+)
- Redis Server: Version 6.x or higher (recommended for caching and queues)

---

## Installation & Local Setup

### 1. Backend Setup (Laravel 11)

```bash
# 1. Navigate to backend directory
cd pf-backend

# 2. Install PHP dependencies
composer install

# 3. Create local environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Configure database credentials in .env, then run migrations and seeders:
php artisan migrate --seed

# 6. Create storage symlink for uploaded brand logos and menu images
php artisan storage:link

# 7. Start the Laravel development server
php artisan serve
# Server will run on: http://127.0.0.1:8000
```

### 2. Frontend Setup (Vue 3 + Vite)

```bash
# 1. Open a new terminal and navigate to frontend directory
cd pf-frontend

# 2. Install JavaScript dependencies
npm install

# 3. Create local environment file
cp .env.example .env

# 4. Start the Vite development server
npm run dev
# Application will run on: http://localhost:5173
```

### 3. Real-Time WebSocket Server (Laravel Reverb)

Run the Reverb daemon to enable real-time order and status dispatch:

```bash
cd pf-backend
php artisan reverb:start
```

---

## Environment Variables Reference

### Backend (pf-backend/.env)

```env
APP_NAME=Lapaqu
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:5173

# PostgreSQL Database Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lapaqu_db
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Redis Cache, Session, & Queue Configuration
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Laravel Reverb WebSocket Broadcasting
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=lapaqu-app
REVERB_APP_KEY=lapaqu-reverb-key
REVERB_APP_SECRET=lapaqu-reverb-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME="http"

# Xendit Payment Gateway Integration
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

## Seeded Demonstration Accounts

The default database seeder (DatabaseSeeder) prepares an active demo outlet (Kopi Kenangan Senopati) with the following credentials:

| Role | Email | Password | PIN (POS/KDS) | Target Interface |
| :--- | :--- | :--- | :--- | :--- |
| Platform Superadmin | admin@lapaqu.id | LapaquAdmin2026! | - | /dashboard (Superadmin View) |
| Tenant Owner | owner@kopisenopati.id | RahasiaKopi123! | - | /dashboard |
| Store Manager | manager@kopisenopati.id | RahasiaKopi123! | 123456 | /dashboard & /pos |
| Cashier (POS) | kasir@kopisenopati.id | RahasiaKopi123! | 123456 | /pos |
| Kitchen Staff (KDS) | kitchen@kopisenopati.id | RahasiaKopi123! | 123456 | /kds |
| Customer (Self-Order) | (Public Access) | - | - | /order/:outletId/:tableToken |

---

## REST API Endpoints Reference

### Authentication & Account
- POST /api/auth/login - Authenticate user credentials (returns Bearer Token and profile)
- POST /api/auth/logout - Revoke active bearer token
- GET  /api/auth/me - Retrieve current authenticated user and active tenant context
- PUT  /api/auth/profile - Update user profile information, password, and avatar

### Customer Self-Order (Public Access)
- GET  /api/public/outlets/{id}/menu - Retrieve active menu catalog, categories, and item variants
- POST /api/public/orders - Submit new customer table self-order
- GET  /api/public/orders/{id} - Retrieve order status, payment receipt, and timeline
- POST /api/public/orders/{id}/pay - Generate Xendit QRIS / Virtual Account invoice

### Cashier (POS) & Kitchen (KDS)
- GET  /api/pos/orders - List active incoming orders and past sales transactions
- POST /api/pos/orders - Create manual cashier orders (Dine-in / Takeaway)
- PUT  /api/pos/orders/{id}/status - Update order lifecycle status (cooking, ready, completed, cancelled)
- GET  /api/kds/orders - Retrieve active kitchen ticket queue
- PUT  /api/kds/items/{id}/toggle - Toggle individual item preparation completion

### Management & Back-Office
- GET  /api/dashboard/summary - Retrieve executive financial metrics (Revenue, Net Sales, Order Volume)
- GET  /api/dashboard/reports/peak-hours - Retrieve hourly sales density distribution
- GET  /api/dashboard/reports/top-items - Retrieve best-performing items ranking
- GET  /api/menu-items - Full CRUD menu and catalog management
- GET  /api/inventory/stocks - Real-time ingredient tracking and low stock notifications
- PUT  /api/outlets/{id} - Update outlet branding, logo, address, tax rate, and service charge

### Webhook
- POST /api/webhooks/xendit - Automated payment settlement callback receiver from Xendit

---

## Production Deployment Notes

1. Queue Worker: Run a supervised queue worker to handle asynchronous inventory deductions and webhooks:
   ```bash
   php artisan queue:work --tries=3 --timeout=90
   ```
2. Supervisor Laravel Reverb: Manage the Reverb daemon using systemd or Supervisor to ensure continuous operation:
   ```bash
   php artisan reverb:start --host=0.0.0.0 --port=8080
   ```
3. Reverse Proxy WSS (Nginx / Cloudflare): Ensure HTTP Upgrade and Connection headers are forwarded to the Reverb port for stable WebSocket connectivity.
4. Production Frontend Build: Compile and minify production frontend assets:
   ```bash
   cd pf-frontend && npm run build
   ```
