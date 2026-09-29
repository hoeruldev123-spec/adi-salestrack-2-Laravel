# PRD — ADI SalesTrack 2.0 Frontend

**Project:** ADI SalesTrack 2.0 Frontend
**Architecture:** SPA (Single Page Application)
**Framework:** Vue 3 (Composition API)
**Build Tool:** Vite
**Language:** JavaScript (ESM)
**Styling:** Tailwind CSS 4
**State Management:** Pinia
**Routing:** Vue Router
**HTTP Client:** Axios
**Charting:** Chart.js / ECharts
**Backend:** Laravel REST API (`/api/v1`)
**Authentication:** Bearer Token (Laravel Sanctum)
**Version:** 1.0
**Status:** Draft

---

## 1. Product Overview

Frontend ADI SalesTrack 2.0 adalah aplikasi web SPA untuk **Sales Management, Analytics, dan Revenue Forecasting** yang mengkonsumsi REST API Laravel.

Frontend menampilkan:

1. Dashboard KPI penjualan.
2. Manajemen master data (customer, principal, product/service, user, role).
3. Manajemen opportunity beserta item, log, dan activity.
4. Manajemen sales transaction.
5. Revenue & pipeline analytics.
6. Forecasting dengan Monte Carlo simulation.
7. Scenario analysis (what-if).
8. Evaluasi forecast terhadap actual revenue.

Aplikasi berjalan penuh di sisi klien. Seluruh data diperoleh dari backend melalui endpoint `/api/v1`.

---

## 2. Product Vision

> Memberikan pengalaman visualisasi sales intelligence yang membantu pengguna memahami pipeline saat ini, memantau performa penjualan, serta melihat distribusi kemungkinan revenue dan probabilitas pencapaian target melalui simulasi Monte Carlo — semuanya dari browser tanpa perlu memahami kompleksitas di baliknya.

---

## 3. Objectives

### 3.1 Business Objectives

- Menyediakan UI yang mudah digunakan untuk seluruh role.
- Memvisualisasikan KPI, pipeline, dan performa sales.
- Menampilkan hasil Monte Carlo (P10/P50/P90, distribusi, probabilitas target) secara jelas.
- Mendukung keputusan berbasis data untuk management.
- Menjadi referensi UI untuk pengembangan aplikasi lain (mobile, dll).

### 3.2 Technical Objectives

- SPA Vue 3 + Vite dengan performa tinggi.
- Pemisahan clean: api client, stores (Pinia), components, views, router.
- Autentikasi token-based dengan interceptor Axios.
- Guard route berdasarkan role.
- Loading, error, dan empty state yang konsisten.
- Format mata uang (IDR) dan angka yang konsisten.
- Polling status forecast async (PROCESSING -> COMPLETED).
- Responsive untuk desktop utama (dashboard digunakan di internal).

---

## 4. Target Users

| Role | Kebutuhan utama |
|---|---|
| **ADMIN** | Kelola user, role, master data, lihat semua data & laporan |
| **SALES** | Kelola customer, opportunity, activity; lihat pipeline & forecast terkait |
| **SALES_MANAGER** | Monitoring pipeline & performa sales, buat forecast, jalankan simulasi & scenario |
| **MANAGEMENT** | Dashboard, analytics, forecast, hasil Monte Carlo, executive reports |

UI menyesuaikan menu dan akses berdasarkan role. Menu yang tidak diizinkan backend (403) disembunyikan atau di-disable.

---

## 5. System Architecture

```text
                     Browser (SPA)
                          |
                     Vue 3 + Vite
                          |
              +-----------+-----------+
              |           |           |
        Vue Router      Pinia      Components
              |           |
              |     +-----+-----+
              |     |  api client (Axios)  |
              |     +-----+-----+
              |           |
              +-----+-----+
                    |
               Bearer Token
                    |
            HTTPS / REST
                    |
               /api/v1/*
                    |
             Laravel REST API
```

---

## 6. Technology Stack

| Layer | Pilihan | Keterangan |
|---|---|---|
| Framework | Vue 3 (Composition API, `<script setup>`) | |
| Build | Vite | HMR, build cepat |
| Routing | Vue Router 4 | lazy loading per route |
| State | Pinia | auth store, master stores, forecast store |
| HTTP | Axios | interceptor token + error |
| Styling | Tailwind CSS 4 | utility-first, dark mode opsional |
| Chart | Chart.js atau ECharts | bar/line/pie + histogram distribusi |
| Component UI | Headless UI + custom, atau PrimeVue/Naive UI | konsisten dengan Tailwind |
| Validation | VeeValidate + Zod (opsional) | form validation |
| Env | `.env` (`VITE_API_BASE_URL`) | |

---

## 7. Project Structure

```text
frontend/
├── index.html
├── vite.config.js
├── package.json
├── .env
└── src/
    ├── main.js
    ├── App.vue
    ├── api/
    │   ├── http.js               # axios instance + interceptor
    │   ├── auth.js
    │   ├── users.js
    │   ├── roles.js
    │   ├── companies.js
    │   ├── principals.js
    │   ├── products.js
    │   ├── opportunities.js
    │   ├── activities.js
    │   ├── transactions.js
    │   ├── analytics.js
    │   └── forecasts.js
    ├── stores/
    │   ├── auth.js
    │   ├── opportunity.js
    │   ├── forecast.js
    │   └── ui.js
    ├── router/
    │   ├── index.js              # route definitions
    │   └── guards.js             # auth & role guard
    ├── composables/
    │   ├── useFormat.js          # formatRupiah, formatNumber, formatDate
    │   ├── usePagination.js
    │   └── usePolling.js         # polling forecast status
    ├── layouts/
    │   ├── AuthLayout.vue        # login page layout
    │   └── DefaultLayout.vue     # sidebar + navbar + main content
    ├── components/
    │   ├── common/               # BaseButton, BaseModal, BaseTable, BaseBadge, ...
    │   ├── opportunity/          # OpportunityForm, StageBadge, PipelineKanban, ...
    │   ├── analytics/            # KpiCard, RevenueChart, PipelineChart, ...
    │   └── forecast/             # ForecastForm, DistributionChart, PercentileTable, ...
    ├── views/
    │   ├── auth/LoginView.vue
    │   ├── dashboard/DashboardView.vue
    │   ├── master/               # User, Role, Company, Principal, Product views
    │   ├── opportunity/          # list, detail, form views
    │   ├── activity/
    │   ├── transaction/
    │   ├── analytics/
    │   └── forecast/             # list, detail, run, scenarios, evaluation
    └── assets/
```

---

## 8. Authentication & Authorization

### 8.1 Login Flow

```text
User -> Login Form -> POST /api/v1/auth/login
   -> success: simpan { token, user } di Pinia + localStorage (token)
   -> redirect sesuai role
   -> gagal: tampilkan error (invalid credentials / akun nonaktif)
```

- Token disimpan di `localStorage` / `sessionStorage` dengan key `adtoken`.
- Axios interceptor menambahkan header `Authorization: Bearer {token}`.
- Response `401` -> hapus sesi dan redirect ke `/login`.
- Response `403` -> tampilkan halaman/aksi tidak diizinkan.
- Route guard memeriksa token dan role (`meta.roles`).

### 8.2 Endpoints

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

### 8.3 Role-Based Menu

| Menu | ADMIN | SALES | SALES_MANAGER | MANAGEMENT |
|---|---|---|---|---|
| Dashboard | ✔ | ✔ | ✔ | ✔ |
| Master Data (user/role/company/principal/product) | ✔ | – | – | – |
| Opportunity | ✔ | ✔ (own) | ✔ (all) | ✔ (read) |
| Activity | ✔ | ✔ (own) | ✔ | ✔ (read) |
| Transaction | ✔ | ✔ (own) | ✔ | ✔ (read) |
| Analytics | ✔ | – | ✔ | ✔ |
| Forecast | ✔ | – | ✔ | ✔ (read) |
| Scenario Analysis | ✔ | – | ✔ | – |
| Forecast Evaluation | ✔ | – | ✔ | ✔ |

---

## 9. Pages / Screens

### 9.1 Login (`/login`)

- Form email + password.
- Validasi wajib, error dari backend ditampilkan (422/401/403).
- Loading state pada tombol submit.

### 9.2 Dashboard (`/`)

- KPI cards: Total Revenue, Total Pipeline, Won Revenue, Win Rate, Average Deal Size, Forecast Revenue, Target Probability.
- Grafik revenue bulanan (bar/line).
- Grafik pipeline per stage.
- List opportunity terbaru / top performance sales.
- Data dari `GET /api/v1/analytics/dashboard?year=`.

### 9.3 Master Data

CRUD standar: tabel (search, filter, pagination), form modal/halaman, hapus dengan konfirmasi.

- **Users** (`/users`): name, email, role, status (ACTIVE/INACTIVE), password saat create.
- **Roles** (`/roles`): read-only list.
- **Companies** (`/companies`): name, industry, email, phone, address, status.
- **Principals** (`/principals`): name, description, website, status.
- **Products/Services** (`/products`): principal_id, name, sku, type (PRODUCT/SERVICE), price, status.

### 9.4 Opportunity (`/opportunities`)

**List**: tabel dengan filter stage, status, sales, principal, search title; pagination; total value per halaman.

**Detail** (`/opportunities/:id`): informasi opportunity, item (product, qty, unit price, discount, subtotal), activity timeline, log perubahan.

**Create/Edit**: form customer, sales owner, principal, title, description, value, stage, probability (default sesuai stage, dapat diedit), expected close date, source, items.

**Lifecycle**: LEAD → QUALIFICATION → PROPOSAL → NEGOTIATION → CLOSING → WON/LOST. Stage diwakili badge & pipeline view.

### 9.5 Activity (`/opportunities/:id/activities`)

- List activity per opportunity; create/update/delete.
- Tipe: CALL, MEETING, EMAIL, FOLLOW_UP, NOTE, TASK.
- Status: OPEN, DONE, CANCELLED.

### 9.6 Sales Transaction (`/transactions`)

- List dengan filter periode & status; detail termasuk items.
- Create: pilih opportunity, isi transaction date, items, total amount dihitung otomatis.
- Menjadi sumber actual revenue.

### 9.7 Analytics (`/analytics`)

- **Revenue**: grafik bulanan/tahunan, filter year/from/to; breakdown by principal, customer, product, salesperson.
- **Pipeline**: total pipeline, open/won/lost count, win rate.
- **Performance**: sales by user, average deal size, average sales cycle.

### 9.8 Forecast (`/forecasts`)

**List**: tabel forecast runs dengan filter type & status, status badge (PENDING/PROCESSING/COMPLETED/FAILED).

**Create** (`POST /api/v1/forecast-runs`): name, forecast_type (HISTORICAL/OPPORTUNITY/COMBINED), period_start, period_end, simulation_count (default 10.000), confidence_level, target_revenue.

**Detail** (`/forecasts/:id`): polling status sampai COMPLETED/FAILED, lalu tampilkan:

- Summary: min, max, mean, median, P10, P25, P50, P75, P90, P95.
- Probability Target Achievement (progres bar / donut).
- Histogram distribusi revenue (Monte Carlo).
- Percentile line chart.

**Scenario Analysis** (dari detail run):

- Buat scenario: name, win_rate_adjustment, revenue_adjustment, target_revenue.
- Jalankan `GET /forecast-runs/:id/scenarios/:sid/results` → tampilkan perbandingan current vs scenario (potential increase).
- Hapus scenario.

**Forecast Evaluation**:

- `GET /forecast-runs/:id/evaluate` → MAE, MAPE, RMSE, actual vs forecast.
- `GET /forecast-runs/:id/backtest` → perbandingan historis.
- `GET /forecast-runs/:id/scenarios/:sid/compare`.

---

## 10. State Management (Pinia)

### auth store

```text
state : token, user, isAuthenticated
getters: isAdmin, isSales, isSalesManager, isManagement, roleName
actions: login(), logout(), fetchMe()
```

### forecast store

```text
state : runs, currentRun, result, scenarios, loading
actions: fetchRuns(filters), fetchRun(id), createRun(payload),
         fetchResult(id), fetchScenarios(id), createScenario(...),
         fetchScenarioResults(runId, scenarioId), pollUntilComplete(id)
```

### ui store

```text
state : sidebarOpen, toasts, confirmDialog
actions: notify(type, message), confirm(...)
```

---

## 11. API Integration

### 11.1 Axios Instance

```text
baseURL : import.meta.env.VITE_API_BASE_URL || '/api/v1'
timeout : 30000
interceptor request  : tambahkan Authorization Bearer token
interceptor response : unwrap { success, message, data, meta };
                       error 401 -> logout + redirect login;
                       error 422 -> kembalikan { errors } untuk form;
                       error 429/500 -> toast error
```

### 11.2 Response Standard (dari backend)

```json
{
    "success": true,
    "message": "Data retrieved successfully",
    "data": {},
    "meta": { "current_page": 1, "last_page": 5, "total": 100 }
}
```

Error:

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": { "email": ["The email field is required."] }
}
```

### 11.3 List Endpoint yang Dipakai

```text
GET/POST/PUT/DELETE /api/v1/users
GET  /api/v1/roles
GET/POST/PUT/DELETE /api/v1/companies
GET/POST/PUT/DELETE /api/v1/principals
GET/POST/PUT/DELETE /api/v1/products
GET/POST/PUT/DELETE /api/v1/opportunities
GET  /api/v1/opportunities/{id}/items
GET  /api/v1/opportunities/{id}/logs
GET/POST/PUT/DELETE /api/v1/opportunities/{id}/activities
GET/POST/PUT/DELETE /api/v1/transactions
GET  /api/v1/transactions/{id}/items
GET  /api/v1/analytics/dashboard?year=
GET  /api/v1/analytics/revenue?year=&from=&to=
GET  /api/v1/analytics/pipeline
GET  /api/v1/analytics/performance?year=&from=&to=
GET/POST/DELETE    /api/v1/forecast-runs
GET  /api/v1/forecast-runs/{id}
GET  /api/v1/forecast-runs/{id}/results
GET/POST/DELETE    /api/v1/forecast-runs/{id}/scenarios
GET  /api/v1/forecast-runs/{id}/scenarios/{sid}/results
GET  /api/v1/forecast-runs/{id}/evaluate
GET  /api/v1/forecast-runs/{id}/backtest
GET  /api/v1/forecast-runs/{id}/scenarios/{sid}/compare
```

---

## 12. Konstanta & Label (Enum)

Semua enum diambil dari backend:

```text
OpportunityStage  : LEAD, QUALIFICATION, PROPOSAL, NEGOTIATION, CLOSING
OpportunityStatus : OPEN, WON, LOST, CANCELLED, ON_HOLD
ActivityType      : CALL, MEETING, EMAIL, FOLLOW_UP, NOTE, TASK
ActivityStatus    : OPEN, DONE, CANCELLED
ProductType       : PRODUCT, SERVICE
EntityStatus      : ACTIVE, INACTIVE
UserStatus        : ACTIVE, INACTIVE
TransactionStatus : (lihat backend)
ForecastType      : HISTORICAL, OPPORTUNITY, COMBINED, SCENARIO
ForecastRunStatus : PENDING, PROCESSING, COMPLETED, FAILED
Role              : ADMIN, SALES, SALES_MANAGER, MANAGEMENT
```

Default probability per stage (referensi saat form opportunity):

| Stage | Probability |
|---|---:|
| LEAD | 10% |
| QUALIFICATION | 30% |
| PROPOSAL | 50% |
| NEGOTIATION | 70% |
| CLOSING | 90% |

---

## 13. UI/UX Guidelines

- Layout utama: sidebar navigasi + topbar (nama user, role, logout) + konten.
- Bahasa: Indonesia.
- Format uang: Rupiah (`Rp1.250.000.000`), tanpa desimal untuk nilai besar.
- Badge warna untuk status/stage (konsisten di seluruh halaman).
- Empty state dengan aksi CTA.
- Konfirmasi modal sebelum delete.
- Toast untuk notifikasi sukses/error.
- Tabel: loading skeleton, pagination, search, filter.
- Responsive minimum: desktop-first (1024px+), mobile diprioritaskan belakangan.

---

## 14. Chart & Visualization

- **Revenue monthly/yearly**: bar chart.
- **Pipeline by stage**: bar / funnel.
- **Win rate / performance**: pie/donut, horizontal bar.
- **Distribusi Monte Carlo**: histogram revenue (bins), marker P10/P50/P90.
- **Target probability**: radial gauge / progress.
- **Percentile**: horizontal range line.
- **Forecast evaluation**: line actual vs forecast.

---

## 15. Asynchronous Forecast Handling

Karena simulasi besar (10.000 iterasi) diproses via queue:

```text
Create run -> status PROCESSING -> UI tampil spinner + progress
Poll GET /forecast-runs/{id} setiap 3-5 detik (max N menit)
  -> COMPLETED -> render result
  -> FAILED    -> tampilkan error
Polling dihentikan saat halaman ditinggalkan (onUnmounted).
```

---

## 16. Routing Plan

```text
/login                        -> AuthLayout / LoginView (public)
/                             -> DashboardView (auth)
/users                        -> UsersView         [ADMIN]
/roles                        -> RolesView         [ADMIN]
/companies                    -> CompaniesView     [ADMIN]
/principals                   -> PrincipalsView    [ADMIN]
/products                     -> ProductsView      [ADMIN]
/opportunities                -> OpportunityListView   [auth]
/opportunities/new            -> OpportunityFormView   [auth]
/opportunities/:id            -> OpportunityDetailView [auth]
/opportunities/:id/edit       -> OpportunityFormView   [auth]
/transactions                 -> TransactionListView   [auth]
/transactions/new             -> TransactionFormView   [auth]
/analytics                    -> AnalyticsView     [ADMIN, SALES_MANAGER, MANAGEMENT]
/forecasts                    -> ForecastListView  [ADMIN, SALES_MANAGER, MANAGEMENT]
/forecasts/new                -> ForecastFormView  [ADMIN, SALES_MANAGER]
/forecasts/:id                -> ForecastDetailView [ADMIN, SALES_MANAGER, MANAGEMENT]
/forecasts/:id/scenarios/new  -> ScenarioFormView  [ADMIN, SALES_MANAGER]
/forecasts/:id/evaluation     -> ForecastEvaluationView [ADMIN, SALES_MANAGER, MANAGEMENT]
/404                          -> NotFoundView
```

Semua route kecuali `/login` dan `/404` dilindungi guard; role diperiksa dari `meta.roles`.

---

## 17. MVP Scope (Frontend)

## Phase 1 — Foundation

```text
Setup Vue 3 + Vite + Tailwind
Router + Layout + Auth Guard
Login / Logout
Axios interceptor
```

## Phase 2 — Master Data

```text
Users & Roles
Companies
Principals
Products/Services
```

## Phase 3 — Sales

```text
Opportunity CRUD + Items + Logs
Activity
Transactions
Pipeline list
```

## Phase 4 — Analytics

```text
Dashboard KPI
Revenue charts
Pipeline charts
Performance
```

## Phase 5 — Forecasting

```text
Forecast run (create/list/detail)
Polling status
Result: min/max/mean/median/P10-P95
Target probability
Distribution histogram
```

## Phase 6 — Advanced

```text
Scenario analysis
Compare current vs scenario
Forecast evaluation (MAE/MAPE/RMSE)
Backtest
Export (CSV/print)
```

---

## 18. Definition of Done — MVP (Frontend)

- [ ] User dapat login dan logout.
- [ ] Token dikelola dengan interceptor Axios.
- [ ] Guard route berdasarkan role berfungsi.
- [ ] Master data (user, role, company, principal, product) dapat dikelola.
- [ ] Opportunity dapat dibuat, dilihat, diedit, dihapus.
- [ ] Item dan log opportunity dapat ditampilkan.
- [ ] Activity CRUD berfungsi.
- [ ] Sales transaction CRUD berfungsi.
- [ ] Dashboard KPI dan grafik analytics tampil dari API.
- [ ] Forecast dapat dibuat dengan tipe HISTORICAL/OPPORTUNITY/COMBINED.
- [ ] Status forecast dipolling sampai COMPLETED/FAILED.
- [ ] P10/P50/P90, mean, median, min/max, dan target probability ditampilkan.
- [ ] Histogram distribusi Monte Carlo divisualisasikan.
- [ ] Scenario dapat dibuat, dijalankan, dan dibandingkan.
- [ ] Evaluasi forecast (MAE/MAPE/RMSE) ditampilkan.
- [ ] Seluruh state menampilkan loading/error/empty dengan benar.
- [ ] Build production (`npm run build`) berhasil tanpa error.

---

## 19. Non-Functional Requirements

- Time to interactive halaman utama < 3 detik (koneksi normal).
- Lazy loading route dan chunk splitting.
- Tidak ada data sensitif di localStorage selain token.
- Token dihapus saat logout.
- Handle error global (network error, 500, 429).
- Aksesibilitas dasar: label form, kontras warna, keyboard navigation.
- Mendukung Chrome, Edge, Firefox, Safari (2 versi terakhir).

---

## 20. Development Priority (Frontend)

```text
1. Project setup (Vite + Vue 3 + Tailwind + Router + Pinia + Axios)
2. Auth (login, guard, interceptor)
3. Layout & navigation (sidebar, topbar)
4. Master data CRUD
5. Opportunity module
6. Transaction module
7. Dashboard & analytics
8. Forecast module
9. Polling & async status
10. Scenario analysis
11. Forecast evaluation
12. Chart refinement & polish
13. Error/loading/empty states
14. Build & deployment
```

---

## 21. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| API berubah (response shape) | Api wrapper terpusat di `src/api`; perbaikan di satu tempat |
| Token expired di tengah sesi | Interceptor 401 → redirect login otomatis |
| Simulasi lama (PROCESSING) | Polling + indikator status; cancel polling saat unmount |
| Role berubah setelah login | `fetchMe()` / refresh user setelah relogin |
| Format uang salah | Komposable `useFormat` dipakai di semua komponen |
| Large list (pagination) | Pagination server-side dari `meta` |
