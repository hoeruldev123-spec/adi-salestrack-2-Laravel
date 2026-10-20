# Business Rules — Sales Ranking

## 1. Tujuan

Menetapkan metode ranking sales ADI SalesTrack berdasarkan revenue yang memenuhi aturan pengakuan perusahaan.

## 2. Prinsip Ranking

Ranking utama menggunakan **revenue eligible**.

Jumlah aktivitas, jumlah laporan, atau jumlah pertemuan tidak menjadi komponen skor ranking utama. Metrik tersebut dapat ditampilkan sebagai indikator operasional terpisah.

## 3. Formula

### Revenue per sales

`Revenue Sales = SUM(Revenue Eligible yang diatribusikan kepada sales)`

### Pencapaian target

Jika target valid dan lebih besar dari nol:

`Target Achievement (%) = Revenue Aktual / Target Revenue × 100%`

Jika target nol atau tidak tersedia, tampilkan `N/A`.

### Urutan ranking

Urutkan revenue secara menurun:

- Rank 1: revenue tertinggi.
- Rank 2: revenue berikutnya.
- Dan seterusnya.

Secara default, gunakan ranking seri jika dua sales memiliki revenue yang sama. Contoh: 1, 2, 2, 4.

## 4. Periode dan Scope

Perbandingan hanya valid jika semua sales menggunakan:
- Rentang waktu yang sama.
- Definisi revenue yang sama.
- Mata uang pelaporan yang sama.
- Aturan atribusi yang sama.
- Cakupan organisasi yang sama atau telah dinyatakan jelas.

Ranking tahunan, kuartalan, dan bulanan harus dihitung secara terpisah.

## 5. Target Tidak Mengubah Urutan Utama

Target achievement merupakan metrik tambahan.

Contoh:
- Sales A: revenue Rp500 juta, target Rp1 miliar.
- Sales B: revenue Rp400 juta, target Rp400 juta.

Ranking revenue: Sales A di atas Sales B.

Pencapaian target: Sales B 100%, Sales A 50%.

Kedua informasi tersebut ditampilkan bersama agar manajemen dapat melihat nilai revenue sekaligus pencapaian target tanpa mengubah prinsip ranking.

## 6. Transaksi dan Koreksi

- Hanya revenue eligible yang masuk perhitungan.
- Pembatalan dan adjustment harus mengikuti aturan revenue recognition.
- Revenue transaksi bersama mengikuti aturan alokasi yang disepakati.
- Jika transaksi berubah setelah periode laporan, sistem harus dapat menjelaskan perubahan hasil.
- Snapshot ranking dapat digunakan untuk mempertahankan rekam historis saat periode ditutup.

## 7. Ranking Snapshot

Jika dibutuhkan, simpan:
- Periode ranking.
- Sales.
- Revenue eligible.
- Posisi ranking.
- Waktu perhitungan.
- Versi atau referensi data yang digunakan.

Snapshot tidak menggantikan transaksi sumber dan harus dapat direkonsiliasi.

## 8. Acceptance Criteria

- Ranking diurutkan berdasarkan revenue eligible.
- Aktivitas tidak memengaruhi urutan.
- Nilai seri ditangani konsisten.
- Target achievement dihitung terpisah.
- Data sales di luar scope tidak dapat masuk melalui manipulasi filter.
- Ranking dapat diverifikasi dari transaksi sumber.
- Perubahan data setelah cutoff ditandai atau direkonsiliasi sesuai kebijakan.
