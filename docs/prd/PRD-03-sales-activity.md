# PRD-03 — Sales Activity Tracking

## 1. Tujuan
Menyediakan pencatatan aktivitas sales yang konsisten untuk kebutuhan pelaporan dan pemantauan operasional. Aktivitas menjadi indikator aktivitas kerja, bukan komponen skor ranking revenue.

## 2. Scope
- Membuat dan melihat aktivitas.
- Memperbarui aktivitas sesuai status dan hak akses.
- Kategori aktivitas yang dikonfigurasi.
- Tanggal aktivitas, ringkasan, hasil, tindak lanjut, dan relasi ke principal/pelanggan jika relevan.
- Status draft/submitted/approved bila workflow approval diaktifkan.
- Filter berdasarkan tanggal, sales, principal, kategori, dan status.

## 3. User Stories
- Sales Rep dapat mencatat aktivitasnya.
- Sales Rep dapat melihat riwayat aktivitasnya.
- Sales Manager dapat meninjau aktivitas tim.
- Manajemen dapat melihat ringkasan aktivitas berdasarkan periode.

## 4. Aturan Bisnis
- Setiap aktivitas memiliki pemilik dan tanggal aktivitas.
- Aktivitas tidak boleh memiliki tanggal atau referensi yang tidak valid.
- Aktivitas yang sudah disetujui tidak boleh diedit diam-diam jika approval digunakan.
- Koreksi aktivitas harus tercatat sesuai kebijakan.
- Kategori aktivitas dapat dikonfigurasi Admin.
- Aktivitas tidak menghasilkan revenue otomatis.
- Jumlah aktivitas tidak mengubah ranking revenue.
- Hindari mewajibkan data pelanggan jika alur kerja eksisting tidak memerlukannya.

## 5. Database
`sales_activities` dapat berisi:
- ID dan user_id.
- principal_id atau customer_id, nullable jika sesuai proses.
- activity_type.
- activity_date.
- title/summary dan outcome.
- follow_up_date, nullable.
- status.
- submitted_at, approved_at, approved_by jika approval digunakan.
- created_at dan updated_at.

Kolom final mengikuti hasil audit dan kebutuhan pelaporan.

## 6. API
- `GET /api/v1/activities`
- `POST /api/v1/activities`
- `GET /api/v1/activities/{id}`
- `PATCH /api/v1/activities/{id}`
- `POST /api/v1/activities/{id}/submit`
- `POST /api/v1/activities/{id}/approve` jika approval diaktifkan.

Gunakan filter dan pagination. Sales Rep hanya dapat melihat atau mengubah data miliknya sesuai status.

## 7. Acceptance Criteria
- Sales Rep tidak dapat membuat aktivitas atas nama user lain tanpa izin.
- Sales Manager dapat melihat aktivitas timnya sesuai cakupan.
- Filter tanggal dan kategori menghasilkan data yang benar.
- Aktivitas tidak memengaruhi nilai revenue atau ranking.
- Perubahan status dan approval dapat diaudit.
- Validasi mencegah tanggal, referensi, dan status yang tidak diperbolehkan.

## 8. Dependensi
PRD-01, PRD-02, dan definisi kategori aktivitas yang digunakan aplikasi saat ini.

## 9. Bukan Scope
Otomatisasi kalender, email, atau integrasi CRM eksternal kecuali dimasukkan ke backlog berikutnya.
