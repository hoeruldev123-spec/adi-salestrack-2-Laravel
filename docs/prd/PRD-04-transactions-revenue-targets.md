# PRD-04 — Sales Transactions, Revenue, dan Targets

## 1. Tujuan
Menetapkan sumber data revenue yang dapat dipercaya untuk dashboard, ranking, laporan, dan forecasting.

## 2. Scope
- Pencatatan transaksi sales.
- Relasi transaksi dengan sales, principal, produk, dan pelanggan bila digunakan.
- Status transaksi.
- Validasi nilai transaksi dan periode pengakuan.
- Koreksi, pembatalan, dan histori perubahan.
- Target revenue per periode.
- Perhitungan realisasi dan pencapaian target.

## 3. User Stories
- Sales Rep dapat mencatat transaksi yang menjadi tanggung jawabnya.
- Sales Manager dapat meninjau transaksi tim.
- Pengguna berwenang dapat menyetujui atau menolak transaksi jika approval diaktifkan.
- Manajemen dapat membandingkan target dengan revenue aktual.
- Perubahan revenue dapat ditelusuri.

## 4. Aturan Bisnis Revenue
Definisi revenue wajib ditetapkan sebelum perhitungan final digunakan.

Baseline:
- Draft, rejected, dan cancelled tidak masuk revenue eligible.
- Transaksi baru masuk perhitungan setelah mencapai status yang ditentukan oleh kebijakan perusahaan.
- Transaksi yang sudah diakui tidak boleh diubah diam-diam.
- Koreksi menggunakan mekanisme adjustment yang menyimpan alasan, pelaku, waktu, dan nilai perubahan.
- Transaksi dengan beberapa produk harus dapat diagregasi secara konsisten.
- Pembagian revenue untuk transaksi bersama harus mengikuti aturan atribusi yang disetujui.
- Semua laporan harus menggunakan aturan pengakuan dan periode yang sama.

Perusahaan harus menetapkan apakah revenue berdasarkan kontrak, invoice, pembayaran, atau nilai lain yang berlaku secara internal. PRD ini tidak menetapkan standar akuntansi perusahaan secara sepihak.

## 5. Aturan Target
Target harus memiliki:
- Periode yang jelas.
- Nilai target dan mata uang yang konsisten.
- Cakupan target: perusahaan, tim, sales, principal, atau kombinasi yang disepakati.
- Pembuat dan waktu perubahan.
- Aturan untuk target yang direvisi.

Jika target bernilai nol atau tidak tersedia, persentase pencapaian ditampilkan sebagai N/A.

## 6. Database
`sales_transactions`:
- ID, nomor referensi, sales owner.
- Principal/customer sesuai kebutuhan.
- Transaction date dan revenue recognition date jika berbeda.
- Status dan mata uang.
- Nilai transaksi sesuai definisi bisnis.
- Metadata submit/approval bila digunakan.
- Created/updated timestamps.

`sales_transaction_items` jika transaksi memiliki banyak item:
- transaction_id, product_id, quantity, unit price, subtotal, serta atribut pajak/diskon jika relevan.

`revenue_adjustments`:
- transaction_id, jenis adjustment, nilai, alasan, status, created_by, approved_by jika approval digunakan, timestamps.

`sales_targets`:
- Periode, target amount, currency.
- Scope target yang jelas.
- Referensi sales/team/principal sesuai cakupan.
- Status dan histori perubahan.

Hindari menyimpan total turunan tanpa kebutuhan yang jelas. Jika snapshot diperlukan untuk audit, dokumentasikan cara rekonsiliasinya.

## 7. API
- `GET|POST /api/v1/transactions`
- `GET /api/v1/transactions/{id}`
- `PATCH /api/v1/transactions/{id}`
- `POST /api/v1/transactions/{id}/submit`
- `POST /api/v1/transactions/{id}/approve` jika approval diaktifkan.
- `POST /api/v1/transactions/{id}/adjustments`
- `GET|POST /api/v1/targets`
- `GET|PATCH /api/v1/targets/{id}`

## 8. Acceptance Criteria
- Transaksi draft/rejected/cancelled tidak masuk revenue eligible.
- Status eligible diterapkan konsisten pada semua laporan.
- Perhitungan target achievement sesuai rumus `actual revenue / target × 100%`.
- Target nol atau tidak tersedia tidak menyebabkan pembagian dengan nol.
- Perubahan transaksi yang sudah diakui memiliki histori.
- Rekap per principal dan perusahaan dapat direkonsiliasi.
- Transaksi yang sama tidak dapat diimpor atau dibuat ganda jika memiliki identifier unik.
- Akses baca dan tulis mengikuti RBAC.

## 9. Dependensi
PRD-01, PRD-02, dan konfirmasi aturan pengakuan revenue, target, approval, mata uang, serta atribusi transaksi.
