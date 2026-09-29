# PRD — ADI SalesTrack 2.0

**Project:** ADI SalesTrack 2.0  
**Architecture:** REST API First  
**Backend:** Laravel  
**Database:** MySQL  
**Authentication:** Laravel Sanctum  
**Core Method:** Monte Carlo Simulation  
**Application Type:** Sales Management, Analytics & Revenue Forecasting Platform  
**Version:** 1.0  
**Status:** Draft

---

## 1. Product Overview

ADI SalesTrack 2.0 adalah pengembangan dari ADI SalesTrack sebagai platform **Sales Management, Analytics, dan Revenue Forecasting** berbasis REST API.

Backend dirancang **API-first/headless**, sehingga dapat digunakan oleh:

- Web application
- Mobile application
- Desktop application
- Internal dashboard
- Third-party applications
- Future frontend menggunakan Vue, React, Flutter, atau framework lain

Sistem memiliki fitur **Revenue Prediction & Forecasting** menggunakan metode **Monte Carlo Simulation**.

Tujuan sistem adalah membantu perusahaan:

1. Mengelola customer.
2. Mengelola principal, product, dan service.
3. Mengelola opportunity.
4. Memantau sales pipeline.
5. Menganalisis performa penjualan.
6. Memprediksi potensi revenue.
7. Melakukan forecasting berdasarkan data historis.
8. Melakukan simulasi probabilitas opportunity.
9. Mengukur kemungkinan pencapaian target revenue.
10. Mengevaluasi forecast terhadap actual revenue.

---

## 2. Product Vision

> ADI SalesTrack menjadi platform sales intelligence yang membantu perusahaan memahami kondisi pipeline saat ini sekaligus memperkirakan kemungkinan pendapatan di masa depan berdasarkan data historis dan probabilitas opportunity.

Sistem tidak hanya menjawab:

> Berapa revenue yang sudah diperoleh?

Tetapi juga:

> Berapa kemungkinan revenue yang akan diperoleh?

dan:

> Seberapa besar kemungkinan target revenue tercapai?

---

## 3. Objectives

### 3.1 Business Objectives

- Meningkatkan visibilitas sales pipeline.
- Memusatkan data opportunity.
- Membantu sales melakukan monitoring opportunity.
- Membantu management melihat potensi revenue.
- Memberikan estimasi revenue berdasarkan data.
- Mendukung pengambilan keputusan berbasis data.

### 3.2 Technical Objectives

- Menggunakan Laravel sebagai backend.
- Menggunakan RESTful API.
- Backend bersifat platform-independent.
- Menggunakan Laravel Sanctum untuk authentication.
- Menggunakan API versioning.
- Memisahkan business logic dari controller.
- Membuat Monte Carlo Engine sebagai service terpisah.
- Menyiapkan arsitektur yang dapat dikembangkan menjadi forecasting service terpisah.

### 3.3 Research Objectives

Implementasi sistem digunakan sebagai media penelitian untuk:

> Mengimplementasikan metode Monte Carlo untuk melakukan prediksi dan forecasting pendapatan penjualan produk dan jasa.

Hasil prediksi dibandingkan dengan data aktual untuk mengevaluasi performa metode.

---

## 4. Target Users

### 4.1 Administrator

- Mengelola user.
- Mengelola role.
- Mengelola master data.
- Melihat seluruh data.
- Mengakses laporan.
- Mengakses forecasting.

### 4.2 Sales

- Melihat customer.
- Membuat opportunity.
- Mengubah opportunity miliknya.
- Membuat activity.
- Membuat follow-up.
- Melihat pipeline.
- Melihat forecast yang berkaitan dengan opportunity.

### 4.3 Sales Manager

- Melihat seluruh opportunity.
- Monitoring pipeline.
- Monitoring performa sales.
- Membuat forecast.
- Menjalankan simulasi.
- Melihat probabilitas pencapaian target.
- Melakukan scenario analysis.

### 4.4 Management

- Melihat dashboard.
- Melihat revenue analytics.
- Melihat sales performance.
- Melihat forecast.
- Melihat hasil Monte Carlo simulation.
- Melihat probability target achievement.
- Melihat executive reports.

---

## 5. System Architecture

```text
                     CLIENT APPLICATIONS
                              |
             +----------------+----------------+
             |                |                |
          Web App         Mobile App      Other Apps
             |                |                |
             +----------------+----------------+
                              |
                         HTTPS / REST
                              |
                    /api/v1/*
                              |
                    Laravel REST API
                              |
       +----------------------+----------------------+
       |                      |                      |
 Authentication          Business Logic        API Resources
    Sanctum                    |                      |
                              |
       +----------------------+----------------------+
       |                      |                      |
   Sales Services      Forecast Services      Analytics
                              |
                    Monte Carlo Engine
                              |
                         MySQL Database
                              |
                       Redis / Queue
```

---

## 6. Technology Stack

### Backend

- Laravel
- PHP 8.3+
- Laravel Sanctum
- Laravel API Resources
- Laravel Form Requests
- Laravel Policies
- Laravel Service Classes
- Laravel Jobs
- Laravel Scheduler
- Laravel Events & Listeners

### Database

- MySQL 8+

### Cache & Queue

- Redis

### API Documentation

- OpenAPI
- Swagger

### Testing

- PHPUnit
- Laravel Feature Test
- Laravel HTTP Test
- Postman
- Thunder Client

---

## 7. Authentication

ADI SalesTrack menggunakan **Laravel Sanctum** sebagai authentication mechanism.

> Catatan: Laravel tidak menyediakan JWT secara native. Sanctum adalah token-based authentication resmi Laravel dan dipilih karena sederhana serta sesuai untuk REST API multi-platform. Jika kebutuhan akademik atau interoperabilitas secara eksplisit mensyaratkan JWT, authentication layer dapat diganti menggunakan package JWT tanpa mengubah business module.

### Authentication Flow

```text
Client
  |
  | POST /api/v1/auth/login
  |
  v
Laravel
  |
  | Validate credentials
  |
  v
Sanctum
  |
  v
Access Token
  |
  v
Client
```

Request berikutnya:

```http
Authorization: Bearer {token}
```

Endpoint:

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

---

## 8. API Architecture

Base URL:

```text
/api/v1
```

Contoh endpoint:

```text
GET    /api/v1/opportunities
GET    /api/v1/opportunities/{id}
POST   /api/v1/opportunities
PUT    /api/v1/opportunities/{id}
DELETE /api/v1/opportunities/{id}
```

Forecast:

```text
GET  /api/v1/forecasts
POST /api/v1/forecasts
GET  /api/v1/forecasts/{id}

POST /api/v1/forecast-runs
GET  /api/v1/forecast-runs/{id}
GET  /api/v1/forecast-runs/{id}/results
```

---

# 9. Functional Modules

## 9.1 Authentication Module

Fitur:

- Login
- Logout
- Current user
- Token management
- Password management

---

## 9.2 User & Role Management

Fitur:

- User list
- Create user
- Update user
- Delete user
- Role assignment
- User status

Role minimum:

```text
ADMIN
SALES
SALES_MANAGER
MANAGEMENT
```

---

## 9.3 Customer Management

Fitur:

- Customer list
- Customer detail
- Create customer
- Update customer
- Delete customer
- Customer opportunity history
- Customer transaction history

---

## 9.4 Principal Management

Fitur:

- Principal list
- Principal detail
- Create principal
- Update principal
- Product mapping
- Opportunity mapping
- Active/inactive status

---

## 9.5 Product & Service Management

Fitur:

- Product
- Service
- Category
- Principal mapping
- SKU
- Price
- Active/inactive status

`type`:

```text
PRODUCT
SERVICE
```

---

## 9.6 Opportunity Management

Opportunity merupakan core transaction module.

Data utama:

- Customer
- Sales owner
- Principal
- Product/service
- Opportunity value
- Stage
- Probability
- Expected close date
- Status
- Source

Lifecycle:

```text
Lead
  |
  v
Qualification
  |
  v
Proposal
  |
  v
Negotiation
  |
  v
Closing
  |
  +------> Won
  |
  +------> Lost
```

---

## 9.7 Opportunity Status

```text
OPEN
WON
LOST
CANCELLED
ON_HOLD
```

Stage:

```text
LEAD
QUALIFICATION
PROPOSAL
NEGOTIATION
CLOSING
```

Default probability:

| Stage | Probability |
|---|---:|
| Lead | 10% |
| Qualification | 30% |
| Proposal | 50% |
| Negotiation | 70% |
| Closing | 90% |
| Won | 100% |
| Lost | 0% |

Probability dapat disesuaikan oleh user berdasarkan kondisi opportunity.

---

## 9.8 Activity & Follow-up

Jenis activity:

- Call
- Meeting
- Email
- Follow-up
- Note
- Task

Data:

```text
activity_type
subject
description
activity_date
due_date
status
user_id
opportunity_id
```

---

## 9.9 Sales Transaction

Sales transaction merepresentasikan revenue aktual yang telah terjadi.

Data:

- Transaction number
- Opportunity
- Customer
- Sales owner
- Transaction date
- Total amount
- Status

Transaction menjadi sumber utama untuk:

- Actual revenue
- Historical forecasting
- Forecast evaluation

---

# 10. Analytics Module

Dashboard menyediakan:

### Revenue Analytics

- Revenue monthly
- Revenue yearly
- Revenue by principal
- Revenue by customer
- Revenue by product
- Revenue by salesperson

### Pipeline Analytics

- Total pipeline
- Open opportunity
- Won
- Lost
- Win rate

### Performance Analytics

- Sales by user
- Sales by principal
- Sales by product
- Average deal size
- Average sales cycle

---

# 11. Forecasting Module

Forecasting memiliki empat tipe:

```text
HISTORICAL
OPPORTUNITY
COMBINED
SCENARIO
```

---

## 11.1 Historical Forecast

Sumber:

```text
sales_transactions
```

Contoh:

```text
January   350M
February  420M
March     390M
April     510M
May       450M
June      620M
July      580M
```

Data digunakan untuk membentuk distribusi historis sebagai dasar simulasi.

---

## 11.2 Opportunity Forecast

Sumber:

```text
Open Opportunities
```

Contoh:

| Opportunity | Value | Probability |
|---|---:|---:|
| A | 500M | 80% |
| B | 300M | 60% |
| C | 700M | 40% |
| D | 250M | 70% |

Monte Carlo menentukan apakah setiap opportunity:

```text
WIN
atau
LOSE
```

berdasarkan probability.

---

## 11.3 Combined Forecast

Menggabungkan:

```text
Historical Revenue
+
Open Opportunity Pipeline
+
Opportunity Probability
```

Kemudian diproses oleh Monte Carlo Engine.

---

# 12. Monte Carlo Design

## 12.1 Objective

Monte Carlo Engine menghasilkan distribusi kemungkinan revenue, bukan hanya satu angka forecast.

Output:

```text
Minimum
Maximum
Mean
Median
P10
P25
P50
P75
P90
P95
Probability Target Achievement
```

---

## 12.2 Monte Carlo Process

```text
Historical / Opportunity Data
            |
            v
      Data Preparation
            |
            v
   Probability Distribution
            |
            v
 Random Number Generation
            |
            v
    Simulation Iteration
            |
            v
       Revenue Result
            |
            v
 Repeat N Times
            |
            v
 Distribution of Results
            |
            v
 Statistical Analysis
            |
            v
 Forecast Result
```

---

## 12.3 Opportunity Simulation

Contoh:

```text
Opportunity A
Value = 500M
Probability = 80%
```

Random number:

```text
0.73
```

Karena:

```text
0.73 <= 0.80
```

hasil:

```text
WIN
Revenue = 500M
```

Jika:

```text
Random = 0.92
```

maka:

```text
0.92 > 0.80
```

hasil:

```text
LOSE
Revenue = 0
```

---

## 12.4 One Simulation

```text
A = WIN  → 500M
B = WIN  → 300M
C = LOSE → 0
D = WIN  → 250M
```

Total:

```text
1.050B
```

Simulation berikutnya dapat menghasilkan nilai berbeda.

Contoh:

```text
A = WIN
B = LOSE
C = WIN
D = WIN

Total = 1.450B
```

Proses dilakukan berulang kali.

Default:

```text
10,000 simulations
```

---

# 13. Forecast Result

Contoh hasil:

```text
Minimum = Rp250M
Maximum = Rp2.1B
Mean    = Rp1.02B
Median  = Rp980M

P50 = Rp980M
P75 = Rp1.25B
P90 = Rp1.55B
P95 = Rp1.72B
```

Target:

```text
Rp1.2B
```

Jika 8.350 dari 10.000 simulasi mencapai target:

```text
Probability Target Achievement
= 8,350 / 10,000
= 83.5%
```

---

# 14. Scenario Analysis

User dapat membuat skenario What-If.

Contoh:

```text
Scenario:
Increase Win Rate

Current:
60%

Scenario:
70%
```

Output:

```text
Current Forecast:
Rp1.05B

Scenario Forecast:
Rp1.28B

Potential Increase:
Rp230M
```

Scenario dapat berupa:

- Increase win rate
- Decrease win rate
- Add opportunity
- Remove opportunity
- Change opportunity value
- Delay opportunity
- Change target revenue

---

# 15. Forecast Evaluation

Forecast dibandingkan dengan actual revenue.

Contoh:

```text
Forecast:
Rp500M

Actual:
Rp470M
```

Metrik evaluasi:

### Absolute Error

```text
|Actual - Forecast|
```

### MAE

```text
Mean Absolute Error
```

### MAPE

```text
Mean Absolute Percentage Error
```

### RMSE

```text
Root Mean Square Error
```

Tujuan:

> Mengukur performa prediksi yang dihasilkan sistem.

---

# 16. User Flow

## 16.1 Login

```text
User
 |
 v
Login
 |
 v
Validate Credential
 |
 +---- Failed ----> Error
 |
 v
Generate Sanctum Token
 |
 v
Dashboard
```

## 16.2 Create Opportunity

```text
Sales
 |
 v
Opportunity
 |
 v
Create
 |
 v
Select Customer
 |
 v
Select Principal
 |
 v
Select Product/Service
 |
 v
Input Value
 |
 v
Select Stage
 |
 v
Set Probability
 |
 v
Set Expected Close Date
 |
 v
Save
 |
 v
Opportunity Created
 |
 v
Opportunity Log Created
```

## 16.3 Update Opportunity

```text
Sales
 |
 v
Opportunity Detail
 |
 v
Edit
 |
 v
Change Stage / Value / Probability
 |
 v
Save
 |
 v
Update Opportunity
 |
 v
Create Opportunity Log
```

## 16.4 Forecast

```text
Manager
 |
 v
Forecast
 |
 v
Create Forecast
 |
 v
Select Period
 |
 v
Select Forecast Type
 |
 v
Set Simulation Count
 |
 v
Set Target
 |
 v
Run Simulation
 |
 v
Monte Carlo Engine
 |
 v
Generate Results
 |
 v
Show Distribution
 |
 v
Show P10/P50/P90
 |
 v
Show Target Probability
 |
 v
Save Forecast
```

---

# 17. ERD

```text
roles
  |
  | 1:N
  v
users
  |
  +--------------------+
  |                    |
  v                    v
opportunities       activities
  |
  +----------+----------+-------------+
  |          |          |             |
  v          v          v             v
companies  principals products    opportunity_logs
              |
              v
           products

sales_transactions
 |
 +----------------------+
 |                      |
 v                      v
sales_transaction_items products

forecast_runs
 |
 +-----------------------+
 |                       |
 v                       v
forecast_results    forecast_scenarios
```

---

# 18. Database Schema

## roles

```text
id
name
description
created_at
updated_at
```

## users

```text
id
name
email
password
role_id
status
created_at
updated_at
```

## companies

```text
id
name
industry
email
phone
address
status
created_at
updated_at
```

## master_principals

```text
id
name
description
website
status
created_at
updated_at
```

## products

```text
id
principal_id
name
sku
type
description
price
status
created_at
updated_at
```

## opportunities

```text
id
company_id
user_id
principal_id
title
description
value
stage
status
probability
expected_close_date
source
created_at
updated_at
```

## opportunity_items

```text
id
opportunity_id
product_id
quantity
unit_price
discount
subtotal
created_at
updated_at
```

## opportunity_logs

```text
id
opportunity_id
user_id
field_name
old_value
new_value
description
created_at
```

## activities

```text
id
opportunity_id
user_id
type
subject
description
activity_date
due_date
status
created_at
updated_at
```

## sales_transactions

```text
id
opportunity_id
company_id
user_id
transaction_date
transaction_number
total_amount
status
created_at
updated_at
```

## sales_transaction_items

```text
id
sales_transaction_id
product_id
quantity
unit_price
subtotal
created_at
updated_at
```

---

# 19. Forecast Database

## forecast_runs

```text
id
name
forecast_type
period_start
period_end
simulation_count
confidence_level
target_revenue
status
started_at
completed_at
created_by
created_at
updated_at
```

Forecast type:

```text
HISTORICAL
OPPORTUNITY
COMBINED
SCENARIO
```

Status:

```text
PENDING
PROCESSING
COMPLETED
FAILED
```

## forecast_results

```text
id
forecast_run_id
minimum
maximum
mean
median
p10
p25
p50
p75
p90
p95
target_probability
created_at
updated_at
```

## forecast_scenarios

```text
id
forecast_run_id
name
description
win_rate_adjustment
revenue_adjustment
opportunity_adjustment
target_revenue
created_at
updated_at
```

## forecast_simulations

Tabel ini bersifat optional.

```text
id
forecast_run_id
simulation_number
total_revenue
target_achieved
created_at
```

Untuk production, seluruh raw simulation tidak wajib disimpan karena dapat menghasilkan volume data besar.

---

# 20. Database Relationships

```text
roles
  |
  | 1:N
  v
users
  |
  | 1:N
  v
opportunities
  |
  +-------- N:1 -------- companies
  |
  +-------- N:1 -------- master_principals
  |
  +-------- 1:N -------- opportunity_items
  |                         |
  |                         N:1
  |                         |
  |                      products
  |
  +-------- 1:N -------- opportunity_logs
  |
  +-------- 1:N -------- activities
  |
  +-------- 1:N -------- sales_transactions
                            |
                            +---- 1:N ---- sales_transaction_items

users
  |
  | 1:N
  v
forecast_runs
  |
  +---- 1:1 ---- forecast_results
  |
  +---- 1:N ---- forecast_scenarios
  |
  +---- 1:N ---- forecast_simulations
```

---

# 21. API Module Structure

```text
/api/v1

/auth
/users
/roles

/companies
/principals
/products
/services

/opportunities
/opportunity-items
/opportunity-logs

/activities

/sales
/transactions

/analytics

/forecasts
/forecast-runs
/forecast-results
/forecast-scenarios
```

---

# 22. API Response Standard

Success:

```json
{
    "success": true,
    "message": "Data retrieved successfully",
    "data": {},
    "meta": {}
}
```

Validation error:

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "email": [
            "The email field is required."
        ]
    }
}
```

---

# 23. Example API

## Login

```http
POST /api/v1/auth/login
```

Request:

```json
{
    "email": "user@example.com",
    "password": "password"
}
```

Response:

```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {},
        "token": "..."
    }
}
```

---

## Create Opportunity

```http
POST /api/v1/opportunities
Authorization: Bearer {token}
```

Request:

```json
{
    "company_id": 1,
    "principal_id": 2,
    "title": "Data Warehouse Project",
    "value": 850000000,
    "stage": "PROPOSAL",
    "probability": 60,
    "expected_close_date": "2026-09-30"
}
```

---

## Run Monte Carlo

```http
POST /api/v1/forecast-runs
Authorization: Bearer {token}
```

Request:

```json
{
    "name": "September 2026 Revenue Forecast",
    "forecast_type": "OPPORTUNITY",
    "period_start": "2026-09-01",
    "period_end": "2026-09-30",
    "simulation_count": 10000,
    "confidence_level": 90,
    "target_revenue": 600000000
}
```

Response:

```json
{
    "success": true,
    "data": {
        "forecast_run_id": 15,
        "status": "PROCESSING"
    }
}
```

Simulation besar dijalankan secara asynchronous menggunakan Laravel Queue.

---

# 24. Asynchronous Forecast Architecture

```text
API Request
    |
    v
Create Forecast Run
    |
    v
Dispatch Job
    |
    v
Redis Queue
    |
    v
Monte Carlo Worker
    |
    v
Calculate 10,000 simulations
    |
    v
Aggregate Result
    |
    v
Save forecast_results
    |
    v
Status = COMPLETED
```

Hal ini mencegah request API timeout ketika jumlah simulation besar.

---

# 25. Laravel Application Structure

```text
app/
|
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │
│   ├── Requests/
│   │
│   └── Resources/
│
├── Models/
│
├── Services/
│   ├── Sales/
│   ├── Opportunity/
│   ├── Analytics/
│   └── Forecast/
│       ├── MonteCarloService.php
│       ├── HistoricalForecastService.php
│       ├── OpportunityForecastService.php
│       ├── CombinedForecastService.php
│       └── ScenarioForecastService.php
│
├── Jobs/
│   └── RunMonteCarloSimulation.php
│
├── Policies/
│
└── Enums/
```

---

# 26. Separation of Concerns

Controller:

```text
Receive Request
      |
      v
Validate
      |
      v
Call Service
      |
      v
Return API Resource
```

Service:

```text
Business Logic
```

Data layer:

```text
Service
   |
   v
Model
   |
   v
Database
```

Monte Carlo:

```text
Controller
    |
    v
Forecast Service
    |
    v
MonteCarloService
    |
    v
Simulation Result
```

---

# 27. Monte Carlo Service

Contoh struktur:

```php
class MonteCarloService
{
    public function simulate(
        array $opportunities,
        int $iterations
    ): array {

        $results = [];

        for ($i = 0; $i < $iterations; $i++) {

            $totalRevenue = 0;

            foreach ($opportunities as $opportunity) {

                $random = mt_rand(0, 10000) / 10000;

                if ($random <= $opportunity['probability']) {
                    $totalRevenue += $opportunity['value'];
                }
            }

            $results[] = $totalRevenue;
        }

        return $this->calculateStatistics($results);
    }
}
```

Production implementation perlu memperhatikan:

- Random number generation
- Reproducibility untuk penelitian bila diperlukan
- Floating point
- Performance
- Memory usage
- Large simulation count
- Statistical calculation

---

# 28. Forecast Visualization

Frontend dapat menyediakan:

### Revenue Distribution

```text
Revenue
 ^
 |             ███
 |           ███████
 |        ███████████
 |     ███████████████
 |  ███████████████████
 +---------------------------->
              Revenue
```

### Percentile

```text
P10 ──────
P25 ──────────
P50 ───────────────
P75 ───────────────────
P90 ───────────────────────
```

### Target Probability

```text
Target:
Rp600M

Probability:
87.4%
```

---

# 29. Dashboard KPI

Dashboard utama:

```text
Total Revenue
Total Pipeline
Won Revenue
Win Rate
Average Deal Size
Forecast Revenue
Target Probability
```

---

# 30. Research Flow

```text
Data Historical Sales
        |
        v
Data Cleaning
        |
        v
Data Grouping
        |
        v
Frequency Distribution
        |
        v
Probability
        |
        v
Cumulative Probability
        |
        v
Random Number
        |
        v
Monte Carlo Simulation
        |
        v
Prediction
        |
        v
Actual Revenue
        |
        v
Accuracy Evaluation
```

---

# 31. Research Variables

### Input

- Historical revenue
- Opportunity value
- Opportunity probability
- Sales stage
- Transaction date
- Product/service
- Principal

### Process

- Frequency
- Probability
- Cumulative probability
- Random number
- Number of simulations

### Output

- Forecast revenue
- Mean revenue
- Median revenue
- P10
- P50
- P90
- Probability target achievement

### Evaluation

- MAE
- MAPE
- RMSE

---

# 32. MVP Scope

## Phase 1 — Foundation

```text
Authentication
User
Role
Customer
Principal
Product
```

## Phase 2 — Sales

```text
Opportunity
Opportunity Items
Opportunity Logs
Activities
Transactions
```

## Phase 3 — Analytics

```text
Revenue Dashboard
Pipeline
Win Rate
Sales Performance
```

## Phase 4 — Monte Carlo

```text
Forecast
Simulation
Distribution
P10/P50/P90
Target Probability
```

## Phase 5 — Research

```text
Actual vs Forecast
MAE
MAPE
RMSE
Forecast History
```

## Phase 6 — Advanced

```text
Scenario Analysis
Combined Forecast
Scheduled Forecast
Export Report
```

---

# 33. Development Priority

```text
1. Laravel Project Setup
        |
2. Database & Migration
        |
3. Sanctum Authentication
        |
4. Role & Authorization
        |
5. Master Data
        |
6. Opportunity Management
        |
7. Sales Transaction
        |
8. Analytics
        |
9. Monte Carlo Engine
        |
10. Forecast API
        |
11. Queue & Asynchronous Simulation
        |
12. Forecast Dashboard
        |
13. Forecast Evaluation
        |
14. API Documentation
        |
15. Testing
```

---

# 34. Definition of Done — MVP

MVP dianggap selesai apabila:

- [ ] User dapat login.
- [ ] User dapat logout.
- [ ] Role dapat diterapkan.
- [ ] Customer dapat dikelola.
- [ ] Principal dapat dikelola.
- [ ] Product/service dapat dikelola.
- [ ] Opportunity dapat dibuat.
- [ ] Opportunity dapat diperbarui.
- [ ] Opportunity history tercatat.
- [ ] Sales transaction dapat dicatat.
- [ ] Revenue dapat dihitung.
- [ ] Pipeline dapat ditampilkan.
- [ ] Forecast dapat dibuat.
- [ ] Monte Carlo dapat dijalankan.
- [ ] Minimal 10.000 simulation dapat diproses.
- [ ] P10/P50/P90 dapat dihitung.
- [ ] Probability target dapat dihitung.
- [ ] Forecast dapat disimpan.
- [ ] Forecast dapat dibandingkan dengan actual.
- [ ] MAE/MAPE/RMSE dapat dihitung.
- [ ] Semua fungsi utama tersedia melalui REST API.

---

# 35. Long-Term Architecture

Tahap awal:

```text
                    Laravel API
                         |
              +----------+----------+
              |                     |
        Business Logic        Monte Carlo Engine
              |                     |
              +----------+----------+
                         |
                       MySQL
                         |
                       Redis
```

Jika workload meningkat:

```text
                    API Gateway
                         |
              +----------+----------+
              |                     |
        Laravel API            Forecast Service
              |                     |
              |               Monte Carlo Engine
              |                     |
              +----------+----------+
                         |
                      MySQL
                         |
                       Redis
```

Dengan desain ini, Monte Carlo Engine dapat dipisahkan menjadi service tersendiri tanpa mengubah client application secara signifikan.

---

# 36. Product Concept

ADI SalesTrack 2.0 bukan sekadar CRM sederhana, tetapi:

> **Sales Management + Analytics + Revenue Forecasting + Decision Support System**

Core:

```text
                  ADI SALES TRACK 2.0
                          |
            +-------------+-------------+
            |             |             |
           CRM         ANALYTICS    FORECASTING
            |             |             |
       Opportunity      Revenue      Monte Carlo
       Customer         Pipeline     Simulation
       Principal        Win Rate     Scenario
       Product          Performance   Probability
            |             |             |
            +-------------+-------------+
                          |
                    REST API / v1
                          |
              +-----------+-----------+
              |           |           |
             Web        Mobile      Other
```

---

# 37. Next Technical Deliverables

Setelah PRD ini disetujui, development dilanjutkan dengan urutan:

1. Formal ERD.
2. Normalisasi database.
3. PK/FK dan indexing strategy.
4. Laravel migrations.
5. Laravel Models dan relationships.
6. Enum untuk status/stage/type.
7. Sanctum authentication.
8. Role & authorization.
9. REST API specification.
10. OpenAPI/Swagger documentation.
11. Opportunity module.
12. Sales transaction module.
13. Analytics service.
14. Monte Carlo Engine.
15. Forecast service.
16. Queue untuk simulation.
17. Forecast evaluation.
18. Automated testing.
19. Postman collection.
20. Deployment configuration.

---

## Architectural Decision

Untuk versi pertama ADI SalesTrack 2.0:

- **Laravel** digunakan sebagai headless REST API backend.
- **Laravel Sanctum** digunakan sebagai authentication default.
- **MySQL** digunakan sebagai relational database.
- **Redis + Laravel Queue** digunakan untuk proses simulation asynchronous.
- **Monte Carlo Engine** ditempatkan di service layer.
- **Forecast result** disimpan terpisah dari raw simulation.
- Raw simulation bersifat optional untuk menghindari pertumbuhan database yang tidak perlu.
- API menggunakan `/api/v1`.
- Business logic tidak ditempatkan di Controller.
- Forecast dapat dikembangkan menjadi service terpisah pada fase berikutnya.
- Sistem dirancang agar dapat digunakan oleh web, mobile, maupun platform lain.

