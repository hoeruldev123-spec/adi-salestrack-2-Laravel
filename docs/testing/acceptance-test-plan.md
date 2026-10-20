# Acceptance Test Plan — ADI SalesTrack

## 1. Tujuan

Memastikan fitur ADI SalesTrack sesuai kebutuhan bisnis, aman dari akses tidak sah, mempertahankan fungsi eksisting yang disepakati, dan menghasilkan perhitungan revenue, ranking, serta forecast yang benar.

## 2. Peran Pengujian

- **Unit test:** formula dan fungsi bisnis.
- **Feature/API test:** endpoint, validasi, dan alur request.
- **Authorization test:** role, ownership, dan cakupan data.
- **Integration test:** interaksi database dan service.
- **Regression test:** fungsi eksisting yang dipertahankan.
- **User acceptance test:** validasi alur oleh pengguna bisnis.

## 3. Matriks Skenario

| ID | Area | Skenario | Hasil yang diharapkan |
|---|---|---|---|
| AUTH-01 | JWT | Login dengan kredensial valid | Token diterbitkan sesuai kebijakan |
| AUTH-02 | JWT | Token invalid/expired | Request privat ditolak |
| AUTH-03 | RBAC | Sales Rep membaca transaksi sales lain | Akses ditolak |
| AUTH-04 | RBAC | Manager membaca tim di luar cakupan | Akses ditolak |
| AUTH-05 | RBAC | Executive mencoba mengubah transaksi | Akses ditolak |
| MASTER-01 | Master data | Membuat kode principal duplikat | Validasi menolak |
| MASTER-02 | Master data | Memilih produk nonaktif untuk transaksi baru | Request ditolak |
| ACT-01 | Aktivitas | Sales Rep membuat aktivitas miliknya | Aktivitas tersimpan |
| ACT-02 | Aktivitas | Aktivitas difilter berdasarkan tanggal | Hasil sesuai rentang |
| REV-01 | Revenue | Transaksi eligible | Masuk perhitungan sesuai aturan |
| REV-02 | Revenue | Draft/rejected/cancelled | Tidak masuk revenue eligible |
| REV-03 | Revenue | Adjustment disetujui | Revenue diperbarui tanpa double counting |
| REV-04 | Revenue | Transaksi dengan beberapa sales | Atribusi sesuai kebijakan |
| TARGET-01 | Target | Target bernilai nol | Pencapaian ditampilkan N/A |
| RANK-01 | Ranking | Dua sales dengan revenue berbeda | Urutan sesuai revenue menurun |
| RANK-02 | Ranking | Dua sales memiliki revenue sama | Aturan nilai seri diterapkan |
| RANK-03 | Ranking | Aktivitas sales berbeda, revenue sama | Aktivitas tidak mengubah ranking |
| REPORT-01 | Reporting | Total principal dibanding total perusahaan | Rekonsiliasi sesuai cakupan data |
| MC-01 | Forecast | Simulasi dengan dataset terkontrol | Statistik hasil sesuai perhitungan referensi |
| MC-02 | Forecast | Data historis tidak memadai | Peringatan kualitas data ditampilkan |
| MC-03 | Forecast | Target tidak tersedia | Probabilitas target tidak diklaim |
| MC-04 | Forecast | Kuantil hasil simulasi | P10 ≤ P50 ≤ P90 |
| MC-05 | Forecast | Total perusahaan dan principal | Total dihitung dari distribusi agregat per iterasi |
| AUD-01 | Audit | Perubahan revenue/role/target | Aksi dan aktor tercatat |
| DEP-01 | Deployment | Migrasi skema | Data historis tetap utuh |
| REG-01 | Regression | Fitur lama yang dipertahankan | Tidak mengalami regresi yang tidak disetujui |

## 4. Dataset Uji Ranking

Gunakan dataset sederhana untuk memastikan formula.

| Sales | Revenue eligible | Target | Target achievement |
|---|---:|---:|---:|
| A | Rp500.000.000 | Rp1.000.000.000 | 50% |
| B | Rp400.000.000 | Rp400.000.000 | 100% |
| C | Rp250.000.000 | Tidak tersedia | N/A |

Hasil ranking yang diharapkan:
1. A
2. B
3. C

Urutan didasarkan pada revenue, bukan persentase target atau jumlah aktivitas.

## 5. Pengujian Forecasting

Gunakan dataset sintetis dengan nilai yang diketahui untuk memverifikasi:
- Pengambilan sampel.
- Jumlah iterasi.
- Expected revenue.
- P10/P50/P90.
- Probabilitas target jika target tersedia.
- Rekonsiliasi hasil principal dan total.
- Perilaku saat data kosong atau tidak memadai.
- Reproducibility menggunakan seed jika implementasi mendukungnya.

Jangan menetapkan hasil forecast produksi dari dataset sintetis. Dataset ini hanya untuk menguji kebenaran implementasi.

Lakukan backtesting menggunakan data historis yang tersedia. Pilih metrik evaluasi yang sesuai, seperti MAE atau WAPE, dengan penanganan eksplisit untuk periode bernilai nol.

## 6. Pengujian Keamanan

- Coba mengakses resource menggunakan ID milik pengguna lain.
- Coba mengubah role melalui request yang tidak berwenang.
- Coba mengakses endpoint tanpa token dan dengan token kedaluwarsa.
- Coba mengirim field tambahan yang seharusnya tidak dapat diedit.
- Periksa apakah response atau log membocorkan secret.
- Pastikan ekspor laporan tidak melewati pembatasan data.

## 7. Kriteria Go-Live

Rilis dapat disetujui jika:
- Seluruh test kritis lulus.
- Tidak ada bug kritis pada autentikasi, otorisasi, dan perhitungan revenue.
- Hasil ranking dan laporan dapat direkonsiliasi.
- Fitur eksisting yang dipertahankan lulus regression test.
- Migrasi dan rollback telah diuji.
- Backup dan prosedur pemulihan tersedia.
- Keterbatasan forecasting diketahui dan didokumentasikan.
- Pengguna bisnis menyetujui hasil UAT.

## 8. Defect Severity

- **Critical:** kebocoran data, akses tidak sah, kerusakan data, atau revenue salah secara material.
- **High:** fitur utama tidak dapat digunakan atau laporan inti salah.
- **Medium:** sebagian alur terganggu tetapi tersedia workaround.
- **Low:** masalah tampilan atau kenyamanan dengan dampak terbatas.

Critical dan High harus diselesaikan atau secara formal diblokir dari rilis sesuai kebijakan perusahaan.
