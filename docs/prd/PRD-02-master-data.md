# PRD-02 — Master Data dan Struktur Organisasi

## 1. Tujuan
Menjaga data pengguna, principal, produk, dan struktur organisasi tetap konsisten sebagai referensi pencatatan aktivitas, transaksi, target, ranking, dan forecasting.

## 2. Scope
- Manajemen principal.
- Manajemen produk dan relasinya dengan principal.
- Manajemen user dan status akun.
- Manajemen tim atau organisasi jika diperlukan oleh model bisnis.
- Validasi referensi dan status aktif.
- Pencarian, filter, pagination, dan audit perubahan.

## 3. User Stories
- Admin dapat menambahkan atau menonaktifkan principal dan produk.
- Pengguna dapat memilih principal/produk yang valid ketika mencatat transaksi.
- Sales Manager dapat melihat daftar sales yang berada dalam cakupannya.
- Sistem mencegah transaksi baru memakai referensi yang tidak aktif.

## 4. Aturan Bisnis
- Kode principal harus unik.
- Kode produk harus unik sesuai kebijakan penomoran yang dipilih.
- Produk harus memiliki principal yang valid jika hubungan produk-principal diwajibkan.
- Record yang sudah dipakai dalam transaksi historis tidak boleh dihapus permanen secara sembarangan.
- Gunakan status aktif/nonaktif untuk menjaga histori.
- Perubahan principal atau produk tidak boleh mengubah arti transaksi historis secara diam-diam.
- Keanggotaan tim harus memiliki aturan tanggal berlaku jika perpindahan sales perlu ditelusuri.
- Cakupan master data yang dapat diedit oleh Manager harus ditetapkan secara eksplisit.

## 5. Data Utama
- `principals`: ID, kode, nama, status, timestamps.
- `products`: ID, principal_id, kode, nama, status, timestamps.
- `teams`: ID, nama, status jika tim digunakan.
- `team_members`: team_id, user_id, tanggal mulai/akhir jika dibutuhkan.
- `users`: mengikuti PRD-01.

Kolom tambahan seperti kategori, mata uang, atau kode eksternal ditambahkan jika memang dibutuhkan oleh alur operasional.

## 6. API
- `GET|POST /api/v1/principals`
- `GET|PATCH /api/v1/principals/{id}`
- `GET|POST /api/v1/products`
- `GET|PATCH /api/v1/products/{id}`
- Endpoint teams dan memberships bila digunakan.

Gunakan filter `active`, pencarian nama/kode, dan pagination.

## 7. Acceptance Criteria
- Kode duplikat ditolak.
- Produk tidak dapat dikaitkan dengan principal yang tidak valid.
- Master nonaktif tidak tersedia untuk transaksi baru.
- Histori transaksi lama tetap dapat ditampilkan setelah master dinonaktifkan.
- User tanpa hak akses tidak dapat mengubah master data.
- Perubahan penting tercatat di audit log.
- API mengembalikan validasi yang jelas dan tidak mengekspos stack trace.

## 8. Dependensi
PRD-01, hasil audit skema, dan konfirmasi struktur principal/produk serta tim.

## 9. Bukan Scope
Sinkronisasi otomatis dengan ERP atau sistem principal eksternal sebelum integrasi tersebut disepakati.
