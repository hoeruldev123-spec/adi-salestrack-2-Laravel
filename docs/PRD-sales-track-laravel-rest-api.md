# PRD (Product Requirements Document) — Rebuild SalesTrack dengan Laravel + REST API

| Item | Detail |
|---|---|
| **Nama Produk** | SalesTrack (Sales Opportunity Tracking System) |
| **Versi Dokumen** | 1.0 |
| **Tanggal** | 2026-08-11 |
| **Status** | Draft untuk persetujuan |
| **Arsitektur Baru** | Backend: Laravel (REST API) + Frontend: Bebas (SPA / Mobile / Third-party) |

---

## 1. Ringkasan Eksekutif

Aplikasi **SalesTrack** saat ini dibangun menggunakan **CodeIgniter 4** dengan arsitektur monolitik (frontend + backend dalam satu aplikasi). Hal ini membuat frontend **terikat** pada backend, sehingga aplikasi tidak dapat digunakan oleh frontend lain (mobile app, dashboard lain, atau integrasi pihak ketiga).

**Tujuan utama PRD ini:**

1. Membangun ulang backend menggunakan **Laravel** sebagai **REST API murni** (headless).
2. Menyediakan API endpoint yang lengkap, terdokumentasi, dan aman (token-based).
3. Memisahkan frontend sepenuhnya — **siapa pun** dapat membuat frontend (Vue, React, Svelte, mobile, dll.) dengan mengonsumsi API yang sama.
4. Mempertahankan seluruh fitur bisnis yang sudah ada (opportunity tracking, master data, laporan, dashboard, multi-role) tanpa kehilangan fungsionalitas.

---

## 2. Analisis Aplikasi Existing (CodeIgniter 4)

### 2.1 Fitur yang Ada Saat Ini

| # | Modul | Deskripsi | Role yang Punya Akses |
|---|---|---|---|
| 1 | **Autentikasi** | Login (username/email + password), logout, session-based auth | Semua |
| 2 | **User Management** | CRUD users, ubah role, aktif/nonaktif | Admin (full), Manager/BOD (terbatas ke sales) |
| 3 | **Profile** | Lihat & update profil sendiri | Semua |
| 4 | **Dashboard Sales** | Ringkasan opportunity miliknya, pipeline, target | Sales |
| 5 | **Dashboard Manager** | Metrik perusahaan, performa tim, pipeline by status, performa bulanan, performa principal, top sales | Manager |
| 6 | **Dashboard BOD** | Laporan eksekutif tingkat tinggi | BOD |
| 7 | **Opportunity CRUD** | Create/read/update/delete (soft delete) opportunity | Sales (milik sendiri), Manager/BOD (semua) |
| 8 | **Update Status Opportunity** | Ubah status & progress %, tercatat di log | Sales/Manager/BOD |
| 9 | **Audit Trail / Activity Log** | Log setiap aksi (create, update, status_updated, delete) pada opportunity | Semua |
| 10 | **Master: Accounts** | CRUD + toggle aktif, nama perusahaan pelanggan | Manager, BOD |
| 11 | **Master: Principals** | CRUD + toggle aktif, vendor/principal | Manager, BOD |
| 12 | **Master: Solution Architects** | CRUD + toggle aktif | Manager, BOD |
| 13 | **Master: License Types** | CRUD + toggle aktif | Manager, BOD |
| 14 | **Sales Profile** | Profil tambahan sales (telepon, join date, lokasi, spesialisasi, foto) | Manager, BOD |
| 15 | **Sales Performance** | Ringkasan performa per sales (win rate, revenue, rank, quota) — kalkulasi | Manager, BOD |
| 16 | **Laporan Sales** | Activity, performance, pipeline per sales; export | Sales, Manager |
| 17 | **Laporan Revenue** | Overview, forecast, comparison (periode, YoY, kuartal) | Manager, BOD |
| 18 | **Ranking Report** | Ranking per sales, principal, account + tren pertumbuhan | Manager, BOD |

### 2.2 Struktur Database Existing (untuk dipetakan ke Laravel Migration)

**Tabel `users`**
- `id`, `username` (unique), `name`, `email` (unique), `password` (bcrypt hash),
  `role` (ENUM: `sales`, `manager`, `bod`, `admin`), `is_active` (0/1), `created_at`, `updated_at`

**Tabel `master_accounts`**
- `id`, `account_name`, `is_active`, `created_at`

**Tabel `master_principals`**
- `id`, `principal_name`, `is_active`, `created_at`

**Tabel `master_solution_architects`**
- `id`, `sa_name`, `is_active`, `created_at`

**Tabel `master_license_types`**
- `id`, `license_type`, `is_active`, `created_at`

**Tabel `opportunities`**
- `id`, `sales_id` (FK → users), `account_id` (FK), `principal_id` (FK),
  `solution_architect_id` (FK, nullable), `pic`, `project_name`, `license_type_id` (FK, nullable),
  `services_license` (TEXT), `closing_date` (DATE), `progress` (VARCHAR 50),
  `opportunity_status` (ENUM: `introduction`, `qualification`, `proposal`, `negotiation`,
  `closed_won`, `closed_lost`), `progress_percentage` (INT 0-100), `deal_reg`,
  `estimated_value` (DECIMAL 15,2), `remarks` (TEXT), `created_at`, `updated_at`, `deleted_at` (soft delete)

**Tabel `opportunity_logs`**
- `id`, `opportunity_id` (FK), `user_id` (FK), `action` (`created`/`updated`/`status_updated`/`deleted`),
  `old_value` (TEXT), `new_value` (TEXT), `created_at`

**Tabel `sales_profiles`**
- `id`, `sales_id` (FK unique), `sales_name`, `sales_email` (unique), `sales_phone`,
  `join_date` (DATE), `location`, `specialization`, `profile_picture`, `is_active`,
  `created_at`, `updated_at`

**Tabel `sales_performance`**
- `id`, `sales_id` (FK unique), `total_deals`, `won_deals`, `lost_deals`, `win_rate` (DECIMAL 5,2),
  `total_revenue` (DECIMAL 15,2), `avg_deal_size`, `performance_rank`, `quota_achievement`,
  `last_updated`, `created_at`, `updated_at`

### 2.3 Masalah pada Arsitektur Existing (alasan rebuild)

1. **Frontend tidak bisa dipisah** — semua tampilan berbasis server-side rendering CI4.
2. **Auth berbasis session** — tidak cocok untuk integrasi dengan frontend lain / mobile.
3. **Belum ada API terstruktur** — tidak ada endpoint JSON, tidak ada dokumentasi API.
4. **Business logic campur dengan controller** — query kompleks laporan ditulis inline di controller, sulit diuji (unit test).
5. **Tidak ada standar error handling / response format** — frontend baru kesulitan konsumsi data.
6. **Tidak ada pagination standar** — list data dikirim penuh tanpa pagination/filter/sort.

---

## 3. Tujuan & Non-Tujuan

### 3.1 Tujuan (Goals)

- Backend Laravel murni sebagai REST API (tidak render HTML).
- Autentikasi berbasis token (**Laravel Sanctum**) yang mendukung web SPA maupun mobile.
- Seluruh fitur existing (Tabel 2.1) tersedia sebagai endpoint API dengan akses per-role.
- Dokumentasi API otomatis (OpenAPI/Swagger).
- Pagination, filtering, dan sorting standar pada seluruh endpoint list.
- Struktur kode terpisah (Service Layer) sehingga logic laporan dapat di-unit-test.
- CORS dikonfigurasi untuk mengizinkan frontend mana pun.

### 3.2 Non-Tujuan (Non-Goals) — Phase 1

- Tidak membangun frontend resmi (akan dibuat terpisah).
- Tidak menambah fitur bisnis baru (import/export, notifikasi, upload) — **dapat masuk Phase 2** (lihat Bagian 10).
- Tidak melakukan migrasi data otomatis antar database CI4 → Laravel (disediakan script, tapi data existing cukup di-export manual jika diperlukan).

---

## 4. Arsitektur Target

### 4.1 Diagram Tingkat Tinggi

```
┌──────────────────────────────┐
│  FRONTEND (bebas)            │
│  Web SPA (Vue/React),        │
│  Mobile (Flutter/ReactNat),  │
│  Third-party system          │
└──────────────┬───────────────┘
               │ HTTPS + JSON
               │ Authorization: Bearer <token>
┌──────────────▼───────────────┐
│  LARAVEL REST API            │
│  ┌─────────────────────────┐ │
│  │ Routes (api.php)        │ │
│  │ Middleware: auth:sanctum│ │
│  │ Role-based access       │ │
│  │ Controllers (thin)      │ │
│  │ Service Layer (logic)   │ │
│  │ Eloquent Models         │ │
│  │ Form Requests (validation)│ │
│  │ API Resources (response)│ │
│  │ Exception Handler       │ │
│  └─────────────────────────┘ │
└──────────────┬───────────────┘
               │
       ┌───────▼───────┐
       │  MySQL DB     │
       └───────────────┘
```

### 4.2 Teknologi yang Disarankan

| Komponen | Pilihan |
|---|---|
| PHP Framework | Laravel 11/12 (versi terbaru yang stabil) |
| Database | MySQL 8.x |
| Autentikasi API | Laravel Sanctum (token) — lebih sederhana & aman daripada JWT manual |
| Dokumentasi API | `dedoc/scramble` (OpenAPI 3, auto-generate) atau L5-Swagger |
| Validasi | Form Request |
| Response Format | API Resource + standar envelope (Bagian 7) |
| CORS | Konfigurasi bawaan Laravel |
| Pagination | Laravel LengthAwarePaginator |
| Testing | PHPUnit (atau Pest) + `RefreshDatabase` |

### 4.3 Struktur Folder Laravel (rekomendasi)

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── ProfileController.php
│   │   ├── Dashboard/
│   │   │   ├── SalesDashboardController.php
│   │   │   ├── ManagerDashboardController.php
│   │   │   └── BodDashboardController.php
│   │   ├── Opportunity/
│   │   │   ├── OpportunityController.php
│   │   │   └── OpportunityLogController.php
│   │   ├── Master/
│   │   │   ├── AccountController.php
│   │   │   ├── PrincipalController.php
│   │   │   ├── SolutionArchitectController.php
│   │   │   └── LicenseTypeController.php
│   │   └── Report/
│   │       ├── SalesReportController.php
│   │       ├── RevenueReportController.php
│   │       └── RankingController.php
│   ├── Middleware/
│   │   └── RoleMiddleware.php
│   ├── Requests/
│   │   ├── Auth/
│   │   ├── Opportunity/
│   │   ├── Master/
│   │   └── User/
│   └── Resources/
│       ├── OpportunityResource.php
│       ├── OpportunityLogResource.php
│       ├── UserResource.php
│       ├── AccountResource.php
│       ├── PrincipalResource.php
│       ├── SolutionArchitectResource.php
│       ├── LicenseTypeResource.php
│       ├── DashboardResource.php
│       └── ReportResource.php
├── Models/
│   ├── User.php
│   ├── Opportunity.php
│   ├── OpportunityLog.php
│   ├── Account.php
│   ├── Principal.php
│   ├── SolutionArchitect.php
│   ├── LicenseType.php
│   ├── SalesProfile.php
│   └── SalesPerformance.php
├── Services/
│   ├── AuthService.php
│   ├── OpportunityService.php
│   ├── SalesPerformanceService.php
│   ├── DashboardService.php
│   ├── RevenueReportService.php
│   └── RankingService.php
└── Enums/
    ├── UserRole.php
    └── OpportunityStatus.php
```

---

## 5. Model Data & Migrasi

Semua tabel existing (2.2) dipertahankan **1:1** dengan penyesuaian:

1. **`users.role`** → gunakan string biasa + enum di sisi aplikasi (`App\Enums\UserRole`: SALES, MANAGER, BOD, ADMIN), bukan ENUM MySQL (mudah dikelola).
2. **`opportunities.opportunity_status`** → `App\Enums\OpportunityStatus`: INTRODUCTION, QUALIFICATION, PROPOSAL, NEGOTIATION, CLOSED_WON, CLOSED_LOST.
3. `sales_performance` dan `win_rate` **tidak diisi manual** — dihitung otomatis oleh Service (Bagian 6.6) setiap kali opportunity berubah status.
4. Semua tabel mendapat `created_at`/`updated_at`; `opportunities` tetap soft delete (`deleted_at`).

Relasi Eloquent:

```
User (1) ──── (N) Opportunity ──── (N) OpportunityLog
User (1) ──── (1) SalesProfile
User (1) ──── (1) SalesPerformance
Account (1) ─ (N) Opportunity
Principal (1) ─ (N) Opportunity
SolutionArchitect (1) ─ (N) Opportunity
LicenseType (1) ─ (N) Opportunity
```

---

## 6. Spesifikasi Modul API

> Semua endpoint di bawah mengembalikan JSON (Bagian 7), memerlukan header
> `Authorization: Bearer <token>`, kecuali `POST /api/v1/auth/login`.

### 6.1 Autentikasi & Profil

| Method | Endpoint | Deskripsi | Role |
|---|---|---|---|
| POST | `/api/v1/auth/login` | Login (username/email + password) → return token + user | Public |
| POST | `/api/v1/auth/logout` | Revoke token saat ini | Auth |
| GET | `/api/v1/auth/me` | Info user yang login + profil/role | Auth |
| GET | `/api/v1/profile` | Detail profil (termasuk SalesProfile jika role sales) | Auth |
| PUT | `/api/v1/profile` | Update profil sendiri | Auth |

**Response login:**
```json
{
  "status": "success",
  "message": "Login berhasil",
  "data": {
    "token": "1|xxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "username": "sales1",
      "name": "Budi",
      "email": "budi@company.com",
      "role": "sales",
      "is_active": true
    }
  }
}
```

**Validasi login:** `username` (required), `password` (required, min 6).
Cek `is_active = 1` → jika nonaktif, tolak dengan 403 dan pesan "Akun dinonaktifkan".

### 6.2 User Management (Admin)

| Method | Endpoint | Deskripsi | Role |
|---|---|---|---|
| GET | `/api/v1/users` | List users (pagination, filter by role/status/search) | admin |
| POST | `/api/v1/users` | Buat user baru | admin |
| GET | `/api/v1/users/{id}` | Detail user | admin |
| PUT | `/api/v1/users/{id}` | Update user | admin |
| PATCH | `/api/v1/users/{id}/toggle-active` | Aktif/nonaktif | admin |
| DELETE | `/api/v1/users/{id}` | Hapus user | admin |
| GET | `/api/v1/users/sales` | List sales aktif (untuk dropdown opportunity) | admin, manager, bod |

**Aturan role (dipertahankan dari aplikasi existing):**
- **Admin** dapat membuat/mengelola semua role (admin, manager, bod, sales).
- **Manager** hanya dapat membuat/mengelola user dengan role `sales` (validasi di Form Request).
- **BOD** read-only (lihat list user).

**Validasi (Form Request):** `username` unique, `email` unique + valid, `password` min 6 (di-hash bcrypt), `role` harus salah satu enum.

### 6.3 Opportunity

| Method | Endpoint | Deskripsi | Role |
|---|---|---|---|
| GET | `/api/v1/opportunities` | List (pagination, filter: status, sales_id, account_id, principal_id, date range, search; sort) | sales, manager, bod |
| POST | `/api/v1/opportunities` | Buat opportunity (sales_id otomatis = user login jika role sales) | sales, manager, bod |
| GET | `/api/v1/opportunities/{id}` | Detail + relasi (account, principal, SA, license, sales) | sales, manager, bod |
| PUT | `/api/v1/opportunities/{id}` | Update penuh | pemilik (sales) / manager / bod |
| PATCH | `/api/v1/opportunities/{id}/status` | Update status + progress_percentage + progress (log otomatis) | pemilik (sales) / manager / bod |
| DELETE | `/api/v1/opportunities/{id}` | Soft delete | pemilik (sales) / manager / bod |

**Aturan akses data (row-level):**
- **Sales** hanya melihat/mengubah opportunity `sales_id = dirinya` → return 403 jika bukan miliknya.
- **Manager & BOD** melihat semua.

**Validasi create/update:**
- `project_name` required min 3
- `account_id` required (harus ada & aktif)
- `principal_id` required (harus ada & aktif)
- `estimated_value` required numeric ≥ 0
- `closing_date` format Y-m-d
- `progress_percentage` integer 0-100
- `opportunity_status` salah satu enum status

**Log otomatis** (tetap dipertahankan) — setiap create/update/status/delete menulis ke `opportunity_logs`:
- create → `action=created`
- update → `action=updated`, `old_value`/`new_value` per field yang berubah
- status → `action=status_updated`
- delete → `action=deleted`

### 6.4 Opportunity Logs (Audit Trail)

| Method | Endpoint | Deskripsi | Role |
|---|---|---|---|
| GET | `/api/v1/opportunities/{id}/logs` | Riwayat log opportunity (desc) | pemilik / manager / bod |
| GET | `/api/v1/activity-logs` | Aktivitas terbaru seluruh sistem (pagination) | manager, bod |

### 6.5 Master Data

Pola sama untuk 4 modul: **Accounts**, **Principals**, **Solution Architects**, **License Types**.

| Method | Endpoint | Deskripsi | Role |
|---|---|---|---|
| GET | `/api/v1/accounts` | List (filter: is_active, search; pagination) | sales (aktif saja), manager, bod |
| POST | `/api/v1/accounts` | Buat | manager, bod |
| GET | `/api/v1/accounts/{id}` | Detail | manager, bod |
| PUT | `/api/v1/accounts/{id}` | Update | manager, bod |
| PATCH | `/api/v1/accounts/{id}/toggle-active` | Aktif/nonaktif | manager, bod |
| DELETE | `/api/v1/accounts/{id}` | Hapus (hanya jika tidak dipakai opportunity, jika dipakai → 409 Conflict) | manager, bod |

Endpoint setara untuk:
- `/api/v1/principals`
- `/api/v1/solution-architects`
- `/api/v1/license-types`

### 6.6 Sales Profile & Performance

| Method | Endpoint | Deskripsi | Role |
|---|---|---|---|
| GET | `/api/v1/sales-profiles` | List sales + profil (termasuk ringkasan performa) | manager, bod |
| GET | `/api/v1/sales-profiles/{id}` | Detail sales profile + performa | manager, bod, sales (diri sendiri) |
| PUT | `/api/v1/sales-profiles/{id}` | Update profil (foto upload → storage Laravel) | manager, bod; sales hanya milik sendiri |
| GET | `/api/v1/sales-performance/leaderboard` | Top 5 sales by win rate + revenue | manager, bod |
| GET | `/api/v1/sales-performance/team-summary` | Ringkasan tim (avg win rate, total revenue, total sales) | manager, bod |

**Service `SalesPerformanceService`** — dihitung ulang otomatis (event `OpportunityUpdated` atau dipanggil setelah PATCH status):
- `total_deals`, `won_deals`, `lost_deals` → hitung dari opportunities
- `win_rate = won / (won + lost) x 100`
- `total_revenue = Σ estimated_value status closed_won`
- `avg_deal_size = total_revenue / won_deals`
- `performance_rank` → urutan by total_revenue
- `quota_achievement` → opsional (dapat di-set manual oleh manager, default 0)

### 6.7 Dashboard per Role

**Sales Dashboard — `GET /api/v1/dashboard/sales`**
- Total opportunity miliknya, total pipeline value (aktif), jumlah won/lost
- Pipeline by status (chart data)
- 5 opportunity terbaru
- Closing date terdekat (list)

**Manager Dashboard — `GET /api/v1/dashboard/manager`**
- Total opportunity perusahaan, total revenue (closed_won), win rate perusahaan
- Team performance per sales (total, won, revenue) — diurutkan revenue desc
- Pipeline by status (count + value)
- Monthly performance 6 bulan terakhir (opportunities + revenue)
- Principal performance top 8
- Top performer (dari sales_profiles + sales_performance)
- Jumlah sales aktif

**BOD Dashboard — `GET /api/v1/dashboard/bod`**
- Ringkasan eksekutif: total revenue, total pipeline, win rate, jumlah sales
- Trend revenue bulanan
- Forecast 6 bulan (dari RevenueReportService)
- Ranking sales top N

### 6.8 Laporan — Sales

| Method | Endpoint | Deskripsi | Role |
|---|---|---|---|
| GET | `/api/v1/reports/sales/activity` | Aktivitas per sales (jumlah create/update/status) dalam periode | sales, manager |
| GET | `/api/v1/reports/sales/performance` | Performa per sales (deals, win rate, revenue) dalam periode | sales, manager |
| GET | `/api/v1/reports/sales/pipeline` | Pipeline per status dalam periode | sales, manager |
| GET | `/api/v1/reports/sales/export` | Export CSV/Excel (stream download) | sales, manager |

**Query params umum:** `start_date` (default: awal bulan), `end_date` (default: akhir bulan), `sales_id`, `status`.

### 6.9 Laporan — Revenue

| Method | Endpoint | Deskripsi | Role |
|---|---|---|---|
| GET | `/api/v1/reports/revenue/overview` | Revenue by month (12 bulan), by principal, by account + metrik (total revenue, deals, avg deal size, growth) | manager, bod |
| GET | `/api/v1/reports/revenue/forecast` | Forecast by status, forecast 6 bulan ke depan, win probability per status, weighted forecast | manager, bod |
| GET | `/api/v1/reports/revenue/comparison` | Perbandingan periode (monthly/quarterly), YoY growth, performa kuartal | manager, bod |

**Logika yang dipertahankan dari existing:**
- Win probability: introduction 10%, qualification 30%, proposal 60%, negotiation 80%, won 100%, lost 0%
- Weighted forecast: total potensi × bobot per status
- Historical win rate untuk forecast bulanan: 30% (konfigurasi di env/config)

### 6.10 Laporan — Ranking

| Method | Endpoint | Deskripsi | Role |
|---|---|---|---|
| GET | `/api/v1/reports/ranking/sales` | Ranking sales + win rate, avg deal size, pipeline velocity, tren vs periode sebelumnya | manager, bod |
| GET | `/api/v1/reports/ranking/principals` | Ranking principal + win rate, account penetration, growth analysis | manager, bod |
| GET | `/api/v1/reports/ranking/accounts` | Ranking account + value segmentation (platinum/gold/silver/bronze), growth potential | manager, bod |

**Value segmentation (dipertahankan):**
- Platinum: revenue ≥ 5 Miliar
- Gold: 1 Miliar – 5 Miliar
- Silver: 500 Juta – 1 Miliar
- Bronze: < 500 Juta

---

## 7. Standar Response & Error Handling

### 7.1 Envelope Format

Semua response API menggunakan format konsisten:

**Sukses:**
```json
{
  "status": "success",
  "message": "Opportunity created successfully",
  "data": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 120,
    "last_page": 8
  }
}
```

**Error:**
```json
{
  "status": "error",
  "message": "Validasi gagal",
  "errors": {
    "project_name": ["Project name wajib diisi."],
    "estimated_value": ["Estimated value harus berupa angka."]
  }
}
```

### 7.2 HTTP Status Codes yang Digunakan

| Code | Penggunaan |
|---|---|
| 200 | Sukses GET/PUT/PATCH |
| 201 | Sukses POST (created) |
| 204 | Sukses DELETE (no content) |
| 400 | Request tidak valid (bad request) |
| 401 | Token tidak ada / tidak valid / expired |
| 403 | Akses ditolak (role tidak berhak / bukan pemilik data) |
| 404 | Resource tidak ditemukan |
| 409 | Konflik (mis. hapus master data yang sedang dipakai) |
| 422 | Validasi gagal (berisi detail errors) |
| 429 | Terlalu banyak request (rate limiting) |
| 500 | Internal server error |

### 7.3 Aturan Umum

- Semua response JSON, kecuali endpoint export (file stream).
- `errors` hanya muncul jika ada error validasi.
- `meta` hanya muncul pada response list (pagination).
- Message disarankan bilingual (EN untuk API, frontend bebas menerjemahkan).
- Tidak pernah mengirim password/hash ke response.
- Timestamp format ISO 8601; tanggal bisnis format `Y-m-d`.

---

## 8. Keamanan & Non-Fungsional

### 8.1 Keamanan

- **Autentikasi:** Laravel Sanctum — token personal access per user. Token di-revoke saat logout.
- **Rate limiting:** `throttle:api` pada semua route auth (mis. 5 percobaan login/menit).
- **CORS:** daftar origin yang diizinkan di konfigurasi (default: `*` untuk dev, whitelist untuk production).
- **Validasi:** Form Request untuk semua input.
- **SQL injection:** aman via Eloquent (query builder parameterized).
- **Mass assignment:** `$fillable` / `$guarded` pada semua model.
- **Audit trail:** log setiap perubahan data sensitif.
- **HTTPS wajib** di production (Middleware `forceScheme`).

### 8.2 Performa & Non-Fungsional

- **Pagination wajib** pada semua endpoint list (`per_page` max 100, default 15).
- **Eager loading** relasi untuk menghindari N+1.
- **Cache** untuk data laporan yang jarang berubah (opsional, phase 1 boleh skip).
- **Waktu respons target:** p95 < 500ms untuk endpoint list sederhana, < 2s untuk laporan agregat.
- **Logging** semua error + request penting via Laravel Log.
- **Ketersediaan:** deployment di server yang sama dengan app CI4 saat ini (cPanel), atau migrasi ke server baru.

---

## 9. Pengujian (Testing Strategy)

| Level | Tools | Cakupan |
|---|---|---|
| Unit Test | PHPUnit/Pest | Service layer: perhitungan win rate, forecast, ranking, segmentation |
| Feature/API Test | PHPUnit/Pest + `RefreshDatabase` | Semua endpoint: auth, CRUD, RBAC (403), validasi (422), pagination |
| Test Data | Factory + Seeder | Data dummy realistis untuk semua entitas |

**Kasus penting yang wajib ditest:**
1. Login sukses / gagal / akun nonaktif (403).
2. Sales tidak bisa akses opportunity milik sales lain (403).
3. Manager tidak bisa buat user role admin (403).
4. Status update menghasilkan log yang benar.
5. Sales performance ter-update setelah status berubah.
6. Soft delete tidak menghapus data permanen (list tidak menampilkan, detail 404).
7. Validasi opportunity (semua rule).
8. Laporan revenue: forecast & weighted forecast sesuai logika existing.

---

## 10. Roadmap

### Phase 1 — Fondasi & Paritas Fitur (4-6 minggu)

| Minggu | Deliverable |
|---|---|
| 1 | Setup Laravel project, database migrations + seeder, sanctum setup, auth API, response helper, exception handler |
| 2 | User management + profile API + RBAC middleware |
| 3 | Master data API (accounts, principals, SA, license types) |
| 4 | Opportunity API + log + status update + sales performance service |
| 5 | Dashboard API (sales, manager, bod) |
| 6 | Report API (sales, revenue, ranking) + dokumentasi OpenAPI + testing lengkap |

### Phase 2 — Pengembangan Lanjutan (opsional, 2-4 minggu)

- Import/Export Excel (Laravel Excel).
- Upload dokumen opportunity (Laravel Storage + SoftDeletes file).
- Notifikasi & reminder (closing date mendekat) via email/DB notification.
- Notifikasi realtime (WebSocket/Laravel Reverb).
- Setting quota & target sales per periode.
- Frontend resmi (Vue 3 + Vite, konsumsi API ini).

---

## 11. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Migrasi data CI4 → Laravel kehilangan data | Data historis hilang | Export SQL + script verifikasi; test dengan staging sebelum production |
| Perubahan logika bisnis saat porting | Angka laporan berbeda | Golden test: bandingkan output laporan CI4 vs Laravel dengan dataset sama |
| Overload endpoint laporan | Performa menurun | Agregasi query SQL, indexing kolom yang sering difilter, cache opsional |
| Token bocor | Akses tidak sah | HTTPS wajib, short-lived token + refresh, revoke on logout |
| RBAC tidak konsisten | Data bocor antar role | Unit test khusus otorisasi untuk setiap endpoint |

---

## 12. Definisi Selesai (Definition of Done)

Fitur dianggap selesai jika:

1. Seluruh endpoint Phase 1 berjalan sesuai spesifikasi Bagian 6.
2. Semua test lulus (unit + feature + otorisasi).
3. Dokumentasi API (OpenAPI) tersedia dan up-to-date.
4. Format response konsisten (Bagian 7).
5. Manual test dengan Postman/curl dari frontend dummy (mis. frontend Vue sederhana atau Postman collection) berhasil.
6. Environment production siap (deploy ke server).
