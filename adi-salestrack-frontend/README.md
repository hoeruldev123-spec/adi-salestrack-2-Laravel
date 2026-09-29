# ADI SalesTrack 2.0 — Frontend

SPA Sales Management, Analytics & Revenue Forecasting (Monte Carlo) yang mengkonsumsi
Laravel REST API (`/api/v1`). Dokumentasi lengkap: [prd.md](./prd.md).

## Tech Stack

- Vue 3 (Composition API, `<script setup>`) + TypeScript
- Vite + Tailwind CSS 4
- Vue Router 4 (lazy loading + role guard)
- Pinia (auth, ui, opportunity, forecast)
- Axios (Bearer token, response interceptor)
- Chart.js (bar/line/histogram)

## Setup

```bash
npm install
```

Konfigurasi di `.env`:

```env
VITE_API_BASE_URL=/api/v1
```

Untuk development, proxy `/api` di `vite.config.ts` mengarah ke
`http://localhost:8000` (dapat diubah via `VITE_PROXY_TARGET`).

## Scripts

```bash
npm run dev       # dev server
npm run build     # typecheck (vue-tsc) + production build
npm run preview   # preview production build
```

> Catatan: skrip memanggil binary langsung via `node ./node_modules/...`
> agar tetap berjalan walau path project mengandung karakter khusus (spasi/kolon).

## Struktur

```text
src/
├── api/          # axios instance + endpoint modules
├── stores/       # Pinia stores
├── router/       # route definitions + auth/role guards
├── composables/  # useFormat, usePagination, usePolling
├── layouts/      # AuthLayout, DefaultLayout (sidebar + topbar)
├── components/   # common, analytics, forecast, charts
└── views/        # auth, dashboard, master, opportunity, transaction,
                  # analytics, forecast, error
```

## Fitur

- Login/Logout dengan Bearer token (localStorage key `adtoken`)
- Guard route berbasis role (ADMIN, SALES, SALES_MANAGER, MANAGEMENT)
- Master data CRUD: users, roles, companies, principals, products
- Opportunity: CRUD + item + activity + log perubahan
- Sales transaction (sumber actual revenue)
- Dashboard KPI + grafik revenue/pipeline + top sales
- Analytics: revenue breakdown, pipeline, performa sales
- Forecast Monte Carlo: create run, polling status (PENDING → PROCESSING →
  COMPLETED/FAILED), P10–P95, target probability, histogram distribusi
- Scenario analysis (what-if) + compare current vs scenario
- Evaluasi forecast: MAE / MAPE / RMSE + backtest
