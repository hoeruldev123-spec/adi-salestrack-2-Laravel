# PRD-05 — Dashboard, Reporting, dan Revenue Ranking

## 1. Tujuan
Memberikan gambaran kinerja bisnis yang konsisten dan transparan, dengan ranking sales berdasarkan revenue eligible.

## 2. Scope
- Dashboard ringkasan perusahaan.
- Laporan revenue berdasarkan waktu, sales, principal, dan tim bila tersedia.
- Laporan aktivitas sebagai indikator operasional terpisah.
- Target dan pencapaian.
- Ranking sales berdasarkan revenue.
- Filter, pagination, dan ekspor sesuai hak akses.
- Pemisahan actual, target, dan forecast.

## 3. Formula
### Revenue sales
`Revenue Sales = SUM(revenue eligible yang diatribusikan ke sales)`

### Pencapaian target
`Target Achievement (%) = Revenue Aktual / Target Revenue × 100%`

### Ranking
Urutkan sales berdasarkan revenue eligible secara menurun untuk periode dan cakupan yang sama.

Jika revenue sama, gunakan peringkat seri secara default. Jika perusahaan memilih metode pemecah seri, metode tersebut harus didokumentasikan dan diterapkan konsisten.

## 4. Aturan Bisnis
- Aktivitas sales bukan komponen skor ranking.
- Target achievement ditampilkan sebagai metrik pendamping.
- Sales tanpa revenue eligible tidak mendapatkan revenue positif hanya karena aktivitasnya tinggi.
- Jangan membandingkan ranking dengan periode, mata uang, atau cakupan berbeda.
- Ranking harus menggunakan status transaksi eligible yang sama dengan laporan revenue.
- Bila laporan menggunakan snapshot, tampilkan tanggal snapshot dan versi perhitungan.
- Semua filter harus diterapkan di backend.
- Pengguna tidak boleh mengakses data di luar cakupannya melalui parameter filter.

## 5. Endpoint
- `GET /api/v1/dashboard/summary`
- `GET /api/v1/reports/revenue`
- `GET /api/v1/reports/activities`
- `GET /api/v1/rankings/sales`

Parameter filter yang mungkin digunakan:
- `from`, `to`
- `year`, `quarter`
- `principal_id`
- `sales_id`
- `team_id`
- `status`
- `page`, `per_page`

API harus memvalidasi kombinasi parameter dan membatasi ukuran page/ekspor.

## 6. Database dan Performa
Laporan harus menggunakan sumber transaksi yang tervalidasi. Pertimbangkan indeks untuk tanggal pengakuan revenue, status, principal, sales owner, dan foreign key yang sering dipakai dalam filter.

`ranking_snapshots` bersifat opsional. Gunakan jika ranking historis harus dapat direproduksi persis meskipun data transaksi berubah setelah periode ditutup.

## 7. Acceptance Criteria
- Ranking sesuai urutan revenue eligible tertinggi ke terendah.
- Perhitungan dapat diverifikasi dari transaksi sumber.
- Total per principal konsisten dengan total perusahaan jika seluruh transaksi tercakup.
- Filter tanggal dan principal bekerja dengan benar.
- Dashboard tidak mencampur actual dengan forecast.
- Ekspor menerapkan batas dan otorisasi yang sama dengan tampilan.
- Query laporan diuji terhadap dataset dengan hasil yang diketahui.
- Performa diuji dengan volume data yang representatif.

## 8. Dependensi
PRD-01, PRD-02, PRD-04, dan definisi revenue resmi.
