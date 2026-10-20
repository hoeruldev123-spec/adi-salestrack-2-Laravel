# API Conventions — ADI SalesTrack

## 1. Base URL dan Versioning

Semua endpoint API baru menggunakan prefix:

`/api/v1`

Contoh:
- `/api/v1/auth/me`
- `/api/v1/transactions`
- `/api/v1/reports/revenue`

Perubahan yang merusak kontrak API harus dipertimbangkan sebagai perubahan versi, bukan dilakukan diam-diam.

## 2. HTTP Method

| Method | Penggunaan |
|---|---|
| GET | Membaca data |
| POST | Membuat resource atau menjalankan aksi |
| PATCH | Memperbarui sebagian data |
| PUT | Mengganti resource jika memang diperlukan |
| DELETE | Menghapus atau menonaktifkan sesuai kebijakan |

Untuk aksi bisnis, gunakan endpoint aksi yang jelas, misalnya `POST /transactions/{id}/approve`.

## 3. Authentication dan Authorization

- Endpoint privat memerlukan JWT.
- Token dikirim melalui header `Authorization: Bearer <token>`.
- Backend memeriksa expiry dan validitas token.
- Policy memeriksa role, ownership, dan cakupan data.
- Jangan mengandalkan ID dari frontend sebagai bukti kepemilikan.
- Strategi refresh dan revocation ditetapkan dalam PRD autentikasi.
- Token dan credential tidak boleh dicatat dalam log.

## 4. Format Response

Gunakan format response yang konsisten dengan implementasi eksisting apabila sudah baik. Jika belum, format berikut dapat dijadikan standar:

```json
{
  "data": {},
  "meta": {}
}
```

Contoh daftar dengan pagination:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 0,
    "last_page": 1
  }
}
```

Contoh error validasi:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "transaction_date": [
      "The transaction date field is required."
    ]
  }
}
```

Jangan mengubah response endpoint eksisting tanpa memeriksa dependensi frontend dan integrasi lain.

## 5. HTTP Status Code

- `200`: request berhasil.
- `201`: resource berhasil dibuat.
- `204`: berhasil tanpa response body.
- `400`: request tidak valid secara umum.
- `401`: belum terautentikasi atau token tidak valid.
- `403`: terautentikasi tetapi tidak memiliki izin.
- `404`: resource tidak ditemukan atau tidak dapat diakses sesuai kebijakan.
- `409`: konflik state atau duplikasi.
- `422`: validasi input gagal.
- `429`: rate limit tercapai.
- `500`: kesalahan internal yang tidak diungkapkan secara rinci ke klien.

## 6. Pagination, Filter, dan Sorting

Contoh:

`GET /api/v1/transactions?page=1&per_page=20&status=approved`

Ketentuan:
- Tetapkan batas maksimum `per_page`.
- Validasi setiap filter.
- Gunakan whitelist untuk kolom sorting.
- Filter akses berdasarkan role diterapkan di backend.
- Hindari menerima nama kolom SQL mentah dari pengguna.
- Endpoint laporan harus menggunakan filter tanggal yang konsisten.

## 7. Validasi dan Transaksi

- Gunakan Form Request atau validator.
- Validasi tipe, rentang, format, foreign key, dan status.
- Gunakan database transaction untuk operasi multi-tabel.
- Gunakan unique constraint sebagai lapisan pencegah duplikasi.
- Hindari mempercayai nilai revenue turunan dari frontend tanpa perhitungan/validasi backend.

## 8. Format Tanggal dan Nilai Uang

- Gunakan format ISO 8601 untuk timestamp API.
- Dokumentasikan timezone yang digunakan.
- Tetapkan format tanggal bisnis dan cutoff laporan.
- Nilai uang menggunakan presisi decimal yang sesuai.
- Mata uang wajib jelas; jangan menjumlahkan nilai lintas mata uang tanpa aturan konversi yang disetujui.

## 9. Idempotency dan Aksi Sensitif

Untuk endpoint yang mungkin dikirim ulang karena retry, pertimbangkan idempotency key atau nomor referensi unik.

Aksi approval, koreksi revenue, dan pembuatan forecast harus mencegah duplikasi yang tidak disengaja.

## 10. Dokumentasi

Setiap endpoint perlu mendokumentasikan:
- Method dan path.
- Tujuan.
- Role yang diizinkan.
- Parameter path/query.
- Request body.
- Response sukses.
- Error yang mungkin terjadi.
- Efek samping dan audit log.
- Contoh request.

## 11. Acceptance Criteria

- Semua endpoint baru mengikuti konvensi yang disepakati.
- Validasi error konsisten.
- Pagination dan filter tidak membocorkan data lintas scope.
- Token invalid ditolak.
- Perubahan API tidak merusak konsumen eksisting tanpa rencana migrasi.
