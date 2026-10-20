# PRD-06 — Monte Carlo Revenue Forecasting

## 1. Tujuan
Menyediakan estimasi probabilistik revenue untuk mendukung perencanaan Sales Manager dan manajemen, berdasarkan histori revenue yang tersedia dan tervalidasi.

## 2. Scope
- Forecast sisa tahun berjalan.
- Forecast tahun berikutnya.
- Forecast total perusahaan dan per principal.
- Eksekusi manual oleh pengguna berwenang.
- Eksekusi terjadwal jika scheduler dan kebijakan operasional mendukung.
- Penyimpanan metadata simulasi dan hasil.
- Rentang hasil, probabilitas target, dan informasi kualitas data.
- Evaluasi forecast melalui backtesting.

## 3. Batasan Input
Baseline menggunakan historical revenue. Pipeline sales tidak digunakan sebagai input probabilistik sampai terdapat data pipeline yang dapat dipercaya, termasuk nilai, tahap, tanggal estimasi, dan probabilitas atau aturan konversi yang disepakati.

Sebelum simulasi, validasi:
- Histori yang tersedia.
- Kelengkapan periode.
- Data kosong, duplikat, atau anomali.
- Perubahan definisi revenue.
- Perubahan bisnis atau principal.
- Ketersediaan target.

## 4. Metode Baseline
Gunakan historical bootstrap sebagai pendekatan awal:
1. Ambil histori revenue yang sesuai dengan granularitas forecast.
2. Tentukan kelompok sampel yang relevan berdasarkan periode dan principal.
3. Ambil sampel berulang dengan metode bootstrap.
4. Bentuk hasil revenue untuk setiap iterasi.
5. Hitung distribusi hasil simulasi.
6. Simpan metadata dan ringkasan hasil.

Metode harus dipilih berdasarkan kualitas dan panjang histori. Bootstrap sederhana tidak otomatis menangkap tren pertumbuhan, musiman, atau perubahan struktural. Jangan memaksakan metode ini pada data yang tidak memadai tanpa peringatan.

## 5. Perhitungan Periode
- Kuartal yang selesai: gunakan aktual yang telah diakui.
- Kuartal berjalan: aktual sampai cutoff + estimasi sisa kuartal.
- Kuartal mendatang: gunakan simulasi.
- Tahun berjalan: aktual yang telah diakui + forecast sisa periode.
- Tahun berikutnya: forecast seluruh periode tahun tersebut.

Jangan mengganti angka aktual yang sudah final dengan hasil simulasi.

## 6. Output
- `P10`: persentil ke-10, skenario rendah/konservatif.
- `P50`: median simulasi.
- `P90`: persentil ke-90, skenario tinggi/optimistis.
- `expected_revenue`: rata-rata hasil simulasi.
- `target_achievement_probability`: proporsi simulasi yang mencapai target, hanya jika target valid tersedia.
- Periode, principal/scope, cutoff, jumlah simulasi, metode, timestamp, dan peringatan kualitas data.

Label UI harus menjelaskan bahwa nilai adalah estimasi, bukan jaminan.

## 7. Aturan Agregasi
Setiap iterasi simulasi harus menghasilkan revenue per principal dan total perusahaan yang konsisten. Hitung kuantil total dari distribusi total setiap iterasi.

**Jangan menjumlahkan P10, P50, atau P90 dari tiap principal untuk memperoleh kuantil total.** Kuantil tidak bersifat aditif.

Jika suatu principal tidak memiliki histori yang cukup, sistem harus menampilkan status keterbatasan data atau memakai metode alternatif yang disetujui. Jangan menyajikan angka yang tampak presisi tanpa dasar data yang memadai.

## 8. Database
`forecast_runs`:
- ID, created_by, forecast year/range, cutoff.
- Metode dan parameter.
- Jumlah simulasi.
- Input data version/snapshot atau referensi data yang digunakan.
- Status, started_at, completed_at, error summary.

`forecast_results`:
- forecast_run_id.
- Scope perusahaan/principal.
- Periode.
- Actual amount jika relevan.
- Expected revenue, P10, P50, P90.
- Target dan probabilitas target jika tersedia.
- Data quality warning dan metadata pendukung.

Simpan cukup informasi agar hasil dapat dijelaskan dan diaudit tanpa menyimpan data sensitif yang tidak diperlukan.

## 9. API
- `POST /api/v1/forecasts`
- `GET /api/v1/forecasts`
- `GET /api/v1/forecasts/{id}`
- `GET /api/v1/forecasts/{id}/results`

POST harus memvalidasi periode, parameter, dan izin pengguna. Gunakan mekanisme asynchronous jika proses terlalu lama untuk request HTTP; jika hosting tidak mendukung worker, tentukan pendekatan sinkron atau scheduler yang realistis.

## 10. Acceptance Criteria
- Forecast tidak mengubah transaksi aktual.
- Setiap run memiliki cutoff, metode, parameter, dan timestamp.
- P10 ≤ P50 ≤ P90 untuk setiap distribusi yang valid.
- Probabilitas target berada pada rentang 0–100% dan hanya tersedia jika target valid.
- Total perusahaan konsisten dengan hasil agregasi setiap iterasi.
- Hasil per principal dan total memakai definisi revenue yang sama.
- Data histori tidak memadai menghasilkan peringatan.
- Hasil dapat diuji menggunakan dataset simulasi yang terkontrol.
- Backtesting menghasilkan metrik error yang terdokumentasi.
- Hasil lama dapat ditelusuri ke run yang menghasilkannya.

## 11. Dependensi
PRD-04, data historis yang bersih, definisi target, dan kesiapan infrastruktur eksekusi.
