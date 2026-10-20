# PRD-07 — Auditability, API Standards, dan Deployment

## 1. Tujuan
Menjamin aplikasi dapat dioperasikan dengan aman, mudah diuji, mudah dikembangkan, dan dapat ditelusuri ketika terjadi perubahan data atau masalah produksi.

## 2. Scope
- Standar response dan error API.
- Validasi request.
- Audit log.
- Logging aplikasi dan penanganan error.
- Dokumentasi API.
- Pengujian otomatis.
- Backup, migrasi, deployment, dan rollback.
- Scheduler/queue jika dibutuhkan forecasting.
- Monitoring aplikasi dan database sesuai kemampuan hosting.

## 3. Standar API
Seluruh endpoint baru menggunakan prefix `/api/v1`.

Response sukses dan error harus konsisten. Format persisnya mengikuti standar aplikasi eksisting jika sudah baik; jika belum, tetapkan satu standar untuk semua modul.

Prinsip wajib:
- HTTP status code sesuai hasil operasi.
- Validasi input menghasilkan error yang dapat ditindaklanjuti.
- List endpoint menggunakan pagination.
- Filter dan sorting memakai parameter yang diizinkan.
- Detail internal, stack trace, password, token, dan secret tidak boleh masuk response.
- Endpoint sensitif harus memiliki autentikasi, otorisasi, validasi, dan audit yang sesuai.
- Dokumentasikan parameter, response, error, dan contoh request.

## 4. Audit Log
Catat setidaknya:
- Login/logout dan kegagalan autentikasi yang relevan.
- Perubahan role dan status akun.
- Perubahan master data penting.
- Pembuatan, persetujuan, penolakan, dan koreksi transaksi.
- Perubahan target.
- Eksekusi forecasting dan perubahan konfigurasi forecasting.
- Aksi administratif yang berdampak pada data atau akses.

Field baseline:
- actor_user_id jika diketahui.
- action.
- entity_type dan entity_id.
- timestamp.
- metadata perubahan yang aman.
- request/correlation ID jika tersedia.

Jangan menyimpan password, JWT lengkap, atau data rahasia ke audit log. Batasi akses dan tetapkan kebijakan retensi.

## 5. Pengujian
Wajib tersedia:
- Unit test untuk formula revenue, target achievement, ranking, dan fungsi statistik.
- Feature/API test untuk autentikasi dan otorisasi.
- Test ownership dan akses lintas tim.
- Test transaksi eligible versus non-eligible.
- Test rekonsiliasi revenue.
- Test agregasi forecast per principal dan total.
- Regression test untuk fitur eksisting yang dipertahankan.
- Migration test dan pemeriksaan rollback.
- Test performa untuk laporan yang paling sering digunakan.

## 6. Deployment
Sebelum deployment:
1. Backup database dan source aplikasi.
2. Pastikan konfigurasi environment terpisah dari source code.
3. Jalankan migrasi pada lingkungan uji.
4. Jalankan test dan validasi laporan.
5. Pastikan versi PHP, ekstensi, database, dan dependensi sesuai kebutuhan Laravel.
6. Verifikasi permission direktori, HTTPS, dan konfigurasi produksi.
7. Deploy dengan rencana rollback yang jelas.
8. Verifikasi login, akses role, transaksi, laporan, dan forecasting setelah deployment.

## 7. Scheduler dan Queue
Jangan mengasumsikan server mendukung worker permanen atau scheduler tanpa batasan.

Saat audit hosting:
- Periksa dukungan cron.
- Periksa dukungan proses background.
- Tentukan apakah forecast dijalankan manual, sinkron, atau terjadwal.
- Jika menggunakan cron, cegah job ganda dan catat status eksekusi.
- Jika proses terlalu lama untuk request HTTP dan worker tidak tersedia, tentukan alternatif deployment yang realistis sebelum fitur dirilis.

## 8. Acceptance Criteria
- API mengikuti standar response dan error yang terdokumentasi.
- Log tidak membocorkan secret.
- Perubahan data penting dapat ditelusuri.
- Test akses lintas role berhasil.
- Backup dapat diverifikasi dan prosedur pemulihan didokumentasikan.
- Deployment tidak menghilangkan data historis.
- Forecasting tidak bergantung pada layanan background yang belum tersedia.
- Dokumentasi instalasi, konfigurasi, migrasi, dan deployment dapat diikuti oleh developer lain.

## 9. Dependensi
Seluruh PRD fitur, lingkungan hosting, serta standar operasional yang disepakati.
