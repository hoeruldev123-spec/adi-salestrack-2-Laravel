# PRD-01 — Audit, Authentication, dan RBAC

## 1. Tujuan
Mempertahankan aplikasi ADI SalesTrack yang masih layak, mengidentifikasi gap, dan menyediakan autentikasi JWT serta otorisasi yang konsisten untuk semua endpoint.

## 2. Scope
- Audit repository, database, route, middleware, model, controller, dan frontend.
- Klasifikasi fitur: Keep, Fix, Refactor, Extend, Replace.
- Login, logout, identitas pengguna, dan lifecycle token.
- Role Admin, Sales Manager, Sales Rep, dan Executive.
- Pembatasan akses berdasarkan role dan ownership/cakupan organisasi.
- Audit perubahan role dan status akun.

## 3. User Stories
- Sebagai pengguna, saya dapat login dan menerima token untuk mengakses API.
- Sebagai Admin, saya dapat mengaktifkan atau menonaktifkan akun dan menetapkan role.
- Sebagai Sales Rep, saya hanya dapat mengakses data yang menjadi hak saya.
- Sebagai Sales Manager, saya dapat mengakses data tim yang menjadi tanggung jawabnya.
- Sebagai Executive, saya dapat membaca laporan agregat yang diizinkan.

## 4. Aturan Bisnis
- Semua endpoint privat harus memvalidasi JWT.
- Perubahan role dan status akun hanya dilakukan oleh pihak berwenang.
- Frontend tidak boleh menjadi satu-satunya lapisan pengamanan.
- User nonaktif tidak dapat menggunakan sesi baru; token aktif harus ditolak sesuai strategi revocation yang dipilih.
- Token memiliki expiry yang ditetapkan melalui konfigurasi.
- Strategi refresh token dan revocation harus diputuskan sebelum implementasi final.
- Jangan menganggap role sebagai satu-satunya pembatasan: periksa kepemilikan record dan cakupan tim.
- Perubahan aplikasi eksisting harus memiliki hasil audit dan rencana rollback.

## 5. Database
Entitas minimum:
- `users`: identitas, email/username, password hash, status aktif, timestamp.
- `roles`: kode dan nama role.
- Relasi role-user sesuai model eksisting; gunakan pivot jika satu user boleh memiliki beberapa role.
- Tabel token atau refresh-token hanya bila dibutuhkan oleh implementasi JWT.
- `audit_logs`: aktor, aksi, entitas, ID entitas, waktu, dan metadata yang aman.

Jangan menyimpan token rahasia atau password plaintext. Hindari menyimpan credential di log.

## 6. API
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/refresh` jika refresh token diaktifkan
- `GET /api/v1/users`
- `POST /api/v1/users`
- `GET /api/v1/users/{id}`
- `PATCH /api/v1/users/{id}`
- `PATCH /api/v1/users/{id}/role` atau mekanisme role yang sesuai dengan model data.

Semua endpoint manajemen user harus menggunakan policy yang tepat.

## 7. Acceptance Criteria
- Login valid menghasilkan token; kredensial salah ditolak.
- Token invalid atau expired ditolak.
- User nonaktif tidak dapat mengakses endpoint privat.
- Sales Rep tidak dapat membaca atau mengubah transaksi sales lain dengan mengganti ID.
- Sales Manager tidak dapat mengakses tim di luar cakupannya.
- Executive tidak dapat mengubah transaksi.
- Perubahan role dan status akun tercatat.
- Pengujian mencakup akses yang diizinkan dan ditolak untuk setiap role.

## 8. Dependensi
Audit aplikasi eksisting, keputusan strategi JWT, model user saat ini, dan definisi cakupan organisasi.

## 9. Bukan Scope
Perubahan UI besar atau migrasi total aplikasi tanpa hasil audit yang membenarkannya.
