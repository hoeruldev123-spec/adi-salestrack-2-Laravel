# ADI SalesTrack – Forecasting, Prediction Results & Recommendation Module

**Project:** ADI SalesTrack  
**Application:** ADI SalesTrack  
**Backend:** Laravel REST API  
**Database:** MySQL  
**Algorithm:** Monte Carlo Simulation  
**Research Method:** Software Development and Algorithm Implementation  
**Data Period:** 2020–2025  
**User Access:** Manager Sales (Forecasting & Recommendations), Sales Team (Opportunity Management)

---

# 1. Overview

## 1.1 Background

Modul Forecasting pada ADI SalesTrack dikembangkan untuk membantu PT All Data International dalam memprediksi potensi pendapatan penjualan berdasarkan data historis dan sales opportunity.

Permasalahan utama yang ingin diselesaikan adalah proses estimasi pendapatan yang masih cenderung subjektif dan bergantung pada pengalaman masing-masing sales.

Untuk mengatasi permasalahan tersebut, penelitian ini menerapkan algoritma Monte Carlo yang memanfaatkan distribusi probabilitas berdasarkan data historis penjualan untuk melakukan simulasi berbagai kemungkinan hasil penjualan.

Modul ini menghasilkan informasi berupa:

1. Prediksi potensi pendapatan penjualan.
2. Distribusi hasil simulasi Monte Carlo.
3. Skenario pendapatan Best Case, Worst Case, dan Most Likely.
4. Prediksi berdasarkan principal.
5. Prediksi berdasarkan kategori produk dan jasa.
6. Rekomendasi prioritas opportunity.
7. Evaluasi akurasi prediksi menggunakan MAPE.

## 1.2 Objectives

Tujuan pengembangan modul:

- Mengotomatisasi proses prediksi pendapatan penjualan.
- Mengurangi ketergantungan terhadap estimasi subjektif sales.
- Memanfaatkan data historis opportunity sebagai dasar probabilitas.
- Menghasilkan prediksi pendapatan menggunakan Monte Carlo.
- Memberikan gambaran risiko dan potensi pendapatan.
- Membantu Manager Sales menentukan prioritas opportunity.
- Mengukur akurasi hasil prediksi dibandingkan pendapatan aktual.

# 2. Module Architecture

Modul forecasting terdiri dari tiga fitur utama.

## 2.1 Forecasting

Digunakan untuk mengatur parameter dan menjalankan proses simulasi Monte Carlo.

Fitur:
- Pemilihan periode forecasting.
- Pemilihan periode data historis.
- Pemilihan cakupan data.
- Pengaturan jumlah iterasi simulasi.
- Pengolahan data opportunity.
- Perhitungan distribusi probabilitas.
- Eksekusi algoritma Monte Carlo.

## 2.2 Prediction Results

Digunakan untuk menampilkan hasil prediksi pendapatan.

Fitur:
- Total estimasi pendapatan.
- Best Case.
- Worst Case.
- Most Likely.
- Expected Revenue.
- Distribusi hasil simulasi.
- Prediksi berdasarkan principal.
- Prediksi berdasarkan kategori License, Hardware, Cloud, dan Service.
- Perbandingan prediksi dengan pendapatan aktual.
- Evaluasi MAPE.

## 2.3 Recommendations

Digunakan untuk memberikan rekomendasi berdasarkan hasil pengolahan data opportunity.

Fitur:
- Prioritas opportunity berdasarkan nilai transaksi.
- Prioritas berdasarkan probabilitas keberhasilan.
- Identifikasi opportunity dengan potensi pendapatan tinggi.
- Identifikasi opportunity dengan risiko kegagalan tinggi.
- Identifikasi opportunity yang mendekati closing date.
- Rekomendasi tindak lanjut sales.
- Analisis kontribusi principal terhadap potensi pendapatan.

# 3. Database Structure

Modul menggunakan struktur database yang telah dirancang sebelumnya.

## 3.1 Existing Tables

| Table | Function |
|---|---|
| principals | Master data principal |
| product_categories | Kategori produk |
| products | Master data produk |
| product_specifications | Spesifikasi teknis produk |
| price_lists | Harga produk dan jasa |
| opportunities | Data sales opportunity |
| opportunity_items | Detail produk dan jasa dalam opportunity |
| sales_activities | Aktivitas sales |
| forecast_runs | Riwayat proses forecasting |
| forecast_results | Hasil simulasi Monte Carlo |

## 3.2 Additional Tables

Untuk mendukung proses forecasting dan rekomendasi secara lebih terstruktur, diperlukan tabel tambahan.

### A. opportunity_stage_histories

Menyimpan riwayat perubahan tahapan opportunity.

Tabel ini diperlukan agar probabilitas keberhasilan dapat dihitung berdasarkan histori perjalanan opportunity, bukan hanya berdasarkan progress terakhir.

| Field | Type | Description |
|---|---|---|
| id | BIGINT | Primary Key |
| opportunity_id | BIGINT | Foreign Key |
| previous_stage | VARCHAR(100) | Tahapan sebelumnya |
| current_stage | VARCHAR(100) | Tahapan terbaru |
| previous_probability | DECIMAL(5,2) | Probabilitas sebelumnya |
| current_probability | DECIMAL(5,2) | Probabilitas terbaru |
| changed_at | DATETIME | Waktu perubahan |
| created_at | TIMESTAMP | Waktu dibuat |

### B. forecast_recommendations

Menyimpan rekomendasi yang dihasilkan dari proses forecasting.

| Field | Type | Description |
|---|---|---|
| id | BIGINT | Primary Key |
| forecast_run_id | BIGINT | Foreign Key ke forecast_runs |
| opportunity_id | BIGINT | Foreign Key ke opportunities, nullable |
| recommendation_type | VARCHAR(100) | Jenis rekomendasi |
| priority | ENUM | HIGH, MEDIUM, LOW |
| recommendation_title | VARCHAR(200) | Judul rekomendasi |
| recommendation_description | TEXT | Penjelasan rekomendasi |
| estimated_value | DECIMAL(18,2) | Nilai opportunity terkait |
| probability | DECIMAL(5,2) | Probabilitas keberhasilan |
| action_suggestion | TEXT | Saran tindakan |
| created_at | TIMESTAMP | Waktu dibuat |

### C. forecast_runs (Updated)

Tabel ini digunakan untuk menyimpan setiap proses forecasting.

| Field | Type | Description |
|---|---|---|
| id | BIGINT | Primary Key |
| user_id | BIGINT | Manager yang menjalankan forecasting |
| forecast_name | VARCHAR(150) | Nama forecasting |
| historical_start_date | DATE | Awal periode data historis |
| historical_end_date | DATE | Akhir periode data historis |
| forecast_start_date | DATE | Awal periode prediksi |
| forecast_end_date | DATE | Akhir periode prediksi |
| simulation_count | INT | Jumlah iterasi simulasi |
| predicted_revenue | DECIMAL(18,2) | Nilai ekspektasi prediksi |
| best_case | DECIMAL(18,2) | Skenario terbaik |
| worst_case | DECIMAL(18,2) | Skenario terburuk |
| most_likely | DECIMAL(18,2) | Skenario paling mungkin |
| actual_revenue | DECIMAL(18,2) | Pendapatan aktual |
| mape | DECIMAL(8,4) | Tingkat kesalahan prediksi |
| status | VARCHAR(30) | Status forecasting |
| created_at | TIMESTAMP | Waktu dibuat |

### D. forecast_results (Updated)

Menyimpan detail kontribusi opportunity dalam setiap iterasi simulasi.

| Field | Type | Description |
|---|---|---|
| id | BIGINT | Primary Key |
| forecast_run_id | BIGINT | Foreign Key |
| opportunity_id | BIGINT | Foreign Key |
| simulation_iteration | INT | Nomor iterasi |
| random_number | DECIMAL(10,8) | Bilangan acak |
| probability | DECIMAL(5,2) | Probabilitas keberhasilan |
| simulated_value | DECIMAL(18,2) | Nilai pendapatan hasil simulasi |
| is_success | BOOLEAN | Hasil simulasi berhasil atau tidak |
| created_at | TIMESTAMP | Waktu dibuat |

Catatan: Untuk menyimpan distribusi hasil keseluruhan iterasi, dapat ditambahkan tabel `forecast_iteration_results` dengan kolom `forecast_run_id`, `simulation_iteration`, dan `total_revenue`. Tabel tersebut bersifat opsional jika hasil iterasi dihitung langsung dan hanya statistik akhirnya yang disimpan.

# 4. Forecasting Feature

## 4.1 Forecasting Configuration

Manager Sales dapat menjalankan forecasting melalui halaman konfigurasi.

Parameter yang disediakan:

| Parameter | Description | Example |
|---|---|---|
| Forecast Name | Nama proses prediksi | Revenue Forecast Q1 2026 |
| Historical Period | Periode data historis | 2020–2025 |
| Forecast Period | Periode yang diprediksi | Januari–Maret 2026 |
| Forecast Scope | Cakupan prediksi | Sales Opportunity |
| Simulation Count | Jumlah iterasi | 10.000 |
| Revenue Category | Kategori pendapatan | All Categories |
| Principal Filter | Filter principal | All Principals |

Konfigurasi harus memastikan bahwa data historis tidak memasukkan data dari periode yang sedang diprediksi agar evaluasi tidak mengalami data leakage.

## 4.2 Data Source

Data yang digunakan berasal dari tabel:

**A. opportunities**

Informasi:
- Project Name
- Client Name
- Principal
- Progress
- Probability
- Estimated Value
- Status
- Closing Date

**B. opportunity_items**

Informasi:
- Product
- Product Type
- Quantity
- Unit Price
- Subtotal

**C. opportunity_stage_histories**

Informasi:
- Previous Stage
- Current Stage
- Previous Probability
- Current Probability
- Change Date

**D. sales_activities**

Informasi:
- Activity Type
- Activity Date
- Sales PIC
- Activity Description

Data tersebut digunakan untuk membentuk distribusi probabilitas dan menentukan nilai transaksi yang menjadi dasar simulasi.

## 4.3 Data Preprocessing

Sebelum simulasi dilakukan, sistem menjalankan proses preprocessing.

Tahapan:

1. Mengambil data opportunity berdasarkan periode historis.
2. Memisahkan opportunity berdasarkan status WON, LOST, dan OPEN.
3. Memvalidasi nilai transaksi.
4. Mengidentifikasi tahapan penjualan.
5. Menghitung jumlah opportunity yang berhasil dan gagal.
6. Menghitung probabilitas berdasarkan data historis.
7. Mengelompokkan nilai transaksi berdasarkan kategori produk.
8. Menentukan opportunity yang masuk dalam periode forecasting.

Opportunity dengan status OPEN tidak digunakan sebagai hasil aktual dalam pembentukan tingkat keberhasilan historis.

## 4.4 Probability Calculation

Probabilitas keberhasilan dihitung berdasarkan frekuensi keberhasilan opportunity pada masing-masing tahapan penjualan.

Rumus:

```text
P(Won | Stage) =
Jumlah Opportunity WON pada Stage
/
Jumlah Opportunity WON dan LOST pada Stage
```

Contoh:

| Stage | WON | LOST | Total | Probability |
|---|---:|---:|---:|---:|
| Introduction | 10 | 40 | 50 | 20% |
| Discovery | 15 | 35 | 50 | 30% |
| Presentation | 25 | 25 | 50 | 50% |
| Proposal | 35 | 15 | 50 | 70% |
| Negotiation | 40 | 10 | 50 | 80% |

Angka di atas merupakan data simulasi.

Sistem dapat menggunakan probabilitas empiris tersebut sebagai dasar simulasi. Jika data historis pada suatu tahapan belum mencukupi, sistem perlu memberikan peringatan dan menggunakan parameter fallback yang telah ditetapkan, bukan menganggap probabilitasnya otomatis 0%.

# 5. Monte Carlo Algorithm Implementation

## 5.1 Algorithm Flow

Proses simulasi Monte Carlo:

1. Mengambil data historis dan opportunity aktif.
2. Menghitung probabilitas keberhasilan berdasarkan histori.
3. Mengambil nilai estimasi setiap opportunity.
4. Menentukan jumlah iterasi.
5. Menghasilkan bilangan acak.
6. Membandingkan bilangan acak dengan probabilitas.
7. Menentukan hasil berhasil atau gagal.
8. Menghitung total pendapatan setiap iterasi.
9. Mengulangi proses hingga seluruh iterasi selesai.
10. Menghitung distribusi hasil simulasi.
11. Menyimpan hasil forecasting.

## 5.2 Random Number Generation

Sistem menghasilkan bilangan acak antara 0 sampai 1.

```text
Random Number = RAND(0,1)
```

Contoh:

| Opportunity | Probability | Random Number | Result |
|---|---:|---:|---|
| Project A | 80% | 0.25 | Success |
| Project B | 60% | 0.75 | Failed |
| Project C | 50% | 0.35 | Success |
| Project D | 30% | 0.85 | Failed |
| Project E | 90% | 0.15 | Success |

Aturan:

```text
IF Random Number <= Probability
    Result = SUCCESS
    Revenue = Opportunity Value
ELSE
    Result = FAILED
    Revenue = 0
```

## 5.3 Revenue Calculation

Setiap opportunity memiliki nilai transaksi yang dihitung berdasarkan seluruh item produk dan jasa.

```text
Opportunity Value =
SUM(Quantity × Unit Price)
```

Pendapatan pada setiap iterasi:

```text
Iteration Revenue =
SUM(Successful Opportunity Values)
```

Contoh:

| Opportunity | Value | Result | Revenue |
|---|---:|---|---:|
| Project A | Rp500.000.000 | Success | Rp500.000.000 |
| Project B | Rp300.000.000 | Failed | Rp0 |
| Project C | Rp700.000.000 | Success | Rp700.000.000 |

Total Revenue Iteration = Rp1.200.000.000.

## 5.4 Expected Revenue

Nilai ekspektasi dihitung dari rata-rata seluruh iterasi.

```text
Expected Revenue =
Total Revenue All Iterations
/
Number of Iterations
```

Nilai ini menjadi estimasi pendapatan berdasarkan hasil simulasi.

## 5.5 Best Case, Worst Case, Most Likely

Hasil simulasi ditampilkan menggunakan distribusi persentil.

| Scenario | Calculation | Description |
|---|---|---|
| Worst Case | P10 | Pendapatan pada persentil ke-10 |
| Most Likely | P50 | Median hasil simulasi |
| Best Case | P90 | Pendapatan pada persentil ke-90 |

P10, P50, dan P90 digunakan agar skenario tidak hanya bergantung pada satu nilai minimum atau maksimum yang ekstrem.

# 6. Prediction Results Feature

## 6.1 Dashboard Overview

Halaman hasil prediksi menampilkan ringkasan hasil simulasi.

Komponen:

| Component | Description |
|---|---|
| Expected Revenue | Rata-rata hasil simulasi |
| Best Case | Prediksi pada P90 |
| Most Likely | Prediksi pada P50 |
| Worst Case | Prediksi pada P10 |
| Total Opportunity | Jumlah opportunity yang disimulasikan |
| Simulation Count | Jumlah iterasi |
| Forecast Period | Periode prediksi |

## 6.2 Scenario Analysis

Contoh tampilan:

| Scenario | Predicted Revenue |
|---|---:|
| Worst Case | Rp1.200.000.000 |
| Most Likely | Rp1.850.000.000 |
| Best Case | Rp2.500.000.000 |
| Expected Revenue | Rp1.900.000.000 |

Angka merupakan ilustrasi, bukan hasil penelitian aktual.

## 6.3 Simulation Distribution

Sistem menampilkan grafik distribusi hasil simulasi.

Informasi yang ditampilkan:

- Sumbu X: Nilai pendapatan.
- Sumbu Y: Frekuensi hasil simulasi.
- Garis P10: Batas skenario terburuk.
- Garis P50: Nilai median.
- Garis P90: Batas skenario terbaik.

Tujuannya untuk memperlihatkan sebaran kemungkinan pendapatan berdasarkan hasil iterasi Monte Carlo.

## 6.4 Revenue by Principal

Menampilkan kontribusi potensi pendapatan setiap principal.

| Principal | Opportunity | Estimated Value | Expected Revenue |
|---|---:|---:|---:|
| AWS | 5 | Rp1.000.000.000 | Rp650.000.000 |
| Denodo | 3 | Rp1.500.000.000 | Rp900.000.000 |
| Snowflake | 4 | Rp800.000.000 | Rp480.000.000 |
| Dell | 2 | Rp1.200.000.000 | Rp720.000.000 |

Nilai Expected Revenue merupakan ilustrasi berdasarkan nilai transaksi dan probabilitas keberhasilan.

## 6.5 Revenue by Product Category

Kategori pendapatan:

- License
- Hardware
- Cloud
- Service

Contoh:

| Category | Estimated Value | Expected Revenue |
|---|---:|---:|
| License | Rp1.500.000.000 | Rp900.000.000 |
| Hardware | Rp1.200.000.000 | Rp720.000.000 |
| Cloud | Rp800.000.000 | Rp520.000.000 |
| Service | Rp500.000.000 | Rp350.000.000 |

Perhitungan kategori dilakukan berdasarkan rincian opportunity_items, sehingga pendapatan dari license dan service tidak tercampur.

## 6.6 Actual vs Forecast

Sistem menyediakan perbandingan prediksi dengan pendapatan aktual.

| Period | Forecast | Actual | Error |
|---|---:|---:|---:|
| Januari | Rp500 jt | Rp480 jt | 4,17% |
| Februari | Rp600 jt | Rp550 jt | 9,09% |
| Maret | Rp700 jt | Rp750 jt | 6,67% |

Contoh angka simulasi.

## 6.7 MAPE Evaluation

Pengukuran akurasi menggunakan Mean Absolute Percentage Error.

```text
MAPE =
(1/n) × SUM(
    ABS((Actual - Forecast) / Actual)
) × 100%
```

Interpretasi:

| MAPE | Interpretation |
|---|---|
| < 10% | Akurasi sangat baik |
| 10%–20% | Akurasi baik |
| 20%–50% | Akurasi cukup |
| > 50% | Akurasi rendah |

Kategori tersebut digunakan sebagai interpretasi umum dan bukan standar mutlak untuk semua jenis penelitian.

MAPE dihitung hanya untuk periode yang memiliki pendapatan aktual lebih besar dari nol. Periode dengan actual revenue nol harus ditangani secara terpisah.

# 7. Recommendation Feature

## 7.1 Recommendation Objectives

Fitur rekomendasi dikembangkan untuk membantu Manager Sales dalam menentukan prioritas tindak lanjut terhadap opportunity.

Rekomendasi dihasilkan berdasarkan data opportunity, nilai transaksi, probabilitas keberhasilan, tahapan penjualan, closing date, dan aktivitas sales.

Fitur ini merupakan mekanisme rekomendasi berbasis aturan (*rule-based recommendation*), bukan algoritma machine learning tambahan.

## 7.2 Recommendation Categories

### A. High Potential Opportunity

Kondisi:
- Nilai transaksi relatif tinggi.
- Probabilitas keberhasilan tinggi.
- Status opportunity masih OPEN.

Rekomendasi:
- Prioritaskan opportunity tersebut.
- Percepat proses negosiasi.
- Pastikan kebutuhan klien telah terpenuhi.

### B. High Value – Low Probability

Kondisi:
- Nilai transaksi tinggi.
- Probabilitas keberhasilan rendah.

Rekomendasi:
- Lakukan evaluasi terhadap kebutuhan klien.
- Identifikasi hambatan penjualan.
- Libatkan Solution Architect jika diperlukan.

### C. Closing Soon

Kondisi:
- Closing date mendekati periode prediksi.
- Status opportunity masih OPEN.

Rekomendasi:
- Tingkatkan frekuensi follow-up.
- Konfirmasi kesiapan pembelian klien.
- Percepat proses administrasi dan negosiasi.

### D. Inactive Opportunity

Kondisi:
- Tidak terdapat aktivitas sales dalam periode tertentu.
- Opportunity masih OPEN.

Rekomendasi:
- Lakukan follow-up kepada klien.
- Perbarui status opportunity.
- Evaluasi kembali kemungkinan keberhasilan.

### E. Low Potential Opportunity

Kondisi:
- Nilai transaksi rendah.
- Probabilitas keberhasilan rendah.

Rekomendasi:
- Evaluasi biaya dan waktu yang diperlukan.
- Pertimbangkan alokasi sumber daya sales.
- Tentukan strategi tindak lanjut yang sesuai.

## 7.3 Recommendation Priority

| Priority | Criteria | Action |
|---|---|---|
| HIGH | Potensi tinggi atau closing segera | Prioritas tindak lanjut |
| MEDIUM | Potensi menengah atau membutuhkan evaluasi | Monitoring dan follow-up |
| LOW | Potensi rendah dan belum mendesak | Evaluasi berkala |

Batas nilai dan probabilitas untuk setiap prioritas harus dapat dikonfigurasi oleh Manager Sales.

## 7.4 Recommendation Display

Contoh tampilan rekomendasi:

| Opportunity | Value | Probability | Priority | Recommendation |
|---|---:|---:|---|---|
| Enterprise Data Platform | Rp900 jt | 80% | HIGH | Prioritaskan closing |
| Cloud Migration | Rp750 jt | 40% | MEDIUM | Evaluasi kebutuhan klien |
| Data Analytics Project | Rp300 jt | 20% | LOW | Lakukan follow-up |
| Server Infrastructure | Rp1,2 M | 70% | HIGH | Percepat negosiasi |

Nilai di atas merupakan data ilustrasi.

# 8. User Interface Requirements

## 8.1 Forecasting Page

Komponen halaman:

- Forecast Name
- Historical Period
- Forecast Period
- Principal Filter
- Revenue Category
- Simulation Iteration
- Run Forecast Button

## 8.2 Prediction Results Page

Komponen halaman:

- Revenue Summary Cards
- Best Case / Worst Case / Most Likely
- Simulation Distribution Chart
- Revenue by Principal Chart
- Revenue by Category Chart
- Forecast vs Actual Chart
- MAPE Evaluation
- Opportunity Detail Table

## 8.3 Recommendations Page

Komponen halaman:

- Recommendation Summary
- High Priority Opportunities
- Medium Priority Opportunities
- Low Priority Opportunities
- Opportunity Value
- Probability
- Closing Date
- Last Activity
- Recommended Action

# 9. API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/forecasting/configuration | Get forecasting configuration |
| POST | /api/forecasting/run | Execute Monte Carlo |
| GET | /api/forecasting | List forecast history |
| GET | /api/forecasting/{id} | Forecast detail |
| GET | /api/forecasting/{id}/results | Prediction results |
| GET | /api/forecasting/{id}/distribution | Simulation distribution |
| GET | /api/forecasting/{id}/principals | Revenue by principal |
| GET | /api/forecasting/{id}/categories | Revenue by category |
| GET | /api/forecasting/{id}/evaluation | MAPE evaluation |
| GET | /api/forecasting/{id}/recommendations | Forecast recommendations |
| GET | /api/recommendations | Recommendation list |
| GET | /api/recommendations/{id} | Recommendation detail |

# 10. Access Control

| Feature | Sales Team | Manager Sales |
|---|---|---|
| View Opportunities | Yes | Yes |
| Create Opportunity | Yes | Yes |
| Update Opportunity | Yes | Yes |
| View Sales Activities | Yes | Yes |
| Run Forecasting | No | Yes |
| View Prediction Results | No | Yes |
| View Recommendations | No | Yes |
| View Forecast History | No | Yes |
| Evaluate Forecast Accuracy | No | Yes |

Manager Sales memiliki akses terhadap seluruh data opportunity untuk kebutuhan analisis dan prediksi perusahaan.

Sales Team hanya dapat mengelola opportunity sesuai dengan hak akses yang diberikan.

# 11. Software Testing Requirements

Pengujian dilakukan untuk memastikan fitur forecasting, hasil prediksi, dan rekomendasi berjalan sesuai kebutuhan.

Metode pengujian yang digunakan adalah Black Box Testing.

## 11.1 Forecasting Testing

| No | Test Scenario | Expected Result |
|---|---|---|
| 1 | Menjalankan forecasting dengan parameter valid | Sistem berhasil menjalankan simulasi |
| 2 | Menggunakan periode historis yang tidak tersedia | Sistem menampilkan peringatan |
| 3 | Menjalankan simulasi dengan jumlah iterasi valid | Sistem menghasilkan hasil prediksi |
| 4 | Menggunakan periode prediksi yang tidak valid | Sistem menolak parameter |
| 5 | Menjalankan forecasting tanpa data opportunity | Sistem menampilkan informasi data tidak tersedia |

## 11.2 Prediction Results Testing

| No | Test Scenario | Expected Result |
|---|---|---|
| 1 | Melihat hasil prediksi | Sistem menampilkan nilai prediksi |
| 2 | Melihat Best Case | Sistem menampilkan nilai P90 |
| 3 | Melihat Worst Case | Sistem menampilkan nilai P10 |
| 4 | Melihat Most Likely | Sistem menampilkan nilai P50 |
| 5 | Melihat pendapatan berdasarkan principal | Sistem menampilkan agregasi yang sesuai |
| 6 | Melihat pendapatan berdasarkan kategori | Sistem menampilkan agregasi License, Hardware, Cloud, dan Service |
| 7 | Melihat MAPE | Sistem menampilkan hasil evaluasi yang sesuai |

## 11.3 Recommendation Testing

| No | Test Scenario | Expected Result |
|---|---|---|
| 1 | Opportunity bernilai tinggi dan probability tinggi | Sistem memberikan prioritas tinggi |
| 2 | Opportunity bernilai tinggi dan probability rendah | Sistem memberikan rekomendasi evaluasi |
| 3 | Opportunity mendekati closing date | Sistem memberikan rekomendasi follow-up |
| 4 | Opportunity tidak memiliki aktivitas terbaru | Sistem memberikan rekomendasi monitoring |
| 5 | Opportunity berstatus WON atau LOST | Sistem tidak memasukkannya sebagai opportunity OPEN yang perlu ditindaklanjuti |

# 12. Research Alignment

Modul forecasting dikembangkan untuk mendukung tujuan penelitian dengan judul implementasi algoritma Monte Carlo dalam memprediksi pendapatan penjualan pada sistem ADI SalesTrack.

| Research Component | Implementation |
|---|---|
| Masalah Penelitian | Prediksi pendapatan masih subjektif |
| Metode | Monte Carlo |
| Data Penelitian | Data historis opportunity |
| Variabel | Status, progress, probability, transaction value |
| Preprocessing | Validasi dan pengelompokan data |
| Probability | Distribusi probabilitas keberhasilan |
| Simulation | Simulasi Monte Carlo |
| Prediction | Best Case, Worst Case, Most Likely |
| Evaluation | Perbandingan forecast dan actual menggunakan MAPE |
| Output | Prediksi pendapatan dan rekomendasi pengambilan keputusan |

# 13. Important Implementation Notes

1. Algoritma Monte Carlo harus menjadi metode utama dalam proses prediksi, bukan hanya menghitung nilai transaksi dikalikan probability.
2. Probabilitas keberhasilan harus dibentuk berdasarkan data historis WON dan LOST.
3. Opportunity OPEN menjadi objek yang diprediksi untuk periode mendatang.
4. Nilai transaksi harus berasal dari penjumlahan opportunity_items.
5. Opportunity WON dan LOST tidak boleh dihitung kembali sebagai peluang penjualan OPEN.
6. Pendapatan aktual harus menggunakan data transaksi yang benar-benar terealisasi sesuai definisi revenue recognition perusahaan.
7. Hasil simulasi harus disimpan agar dapat dibandingkan dan dievaluasi.
8. Evaluasi MAPE harus membandingkan prediksi dan aktual untuk periode yang sama.
9. Rekomendasi harus berdasarkan aturan yang transparan dan dapat dijelaskan kepada Manager Sales.
10. Data dummy yang digunakan dalam pengujian harus mempertahankan struktur dan karakteristik data penelitian tanpa menggunakan informasi rahasia perusahaan.

---

# 14. Expected Final Output

Setelah modul selesai dikembangkan, ADI SalesTrack diharapkan memiliki kemampuan:

1. Mengolah data historis penjualan dan opportunity secara terstruktur.
2. Menghitung distribusi probabilitas keberhasilan berdasarkan histori penjualan.
3. Menjalankan simulasi Monte Carlo secara otomatis.
4. Menghasilkan prediksi pendapatan berdasarkan kemungkinan hasil penjualan.
5. Menampilkan skenario Best Case, Worst Case, dan Most Likely.
6. Menampilkan kontribusi pendapatan berdasarkan principal dan kategori produk.
7. Membandingkan hasil prediksi dengan pendapatan aktual.
8. Mengukur tingkat kesalahan prediksi menggunakan MAPE.
9. Memberikan rekomendasi prioritas opportunity kepada Manager Sales.

**End of Forecasting, Prediction Results & Recommendation Specification**