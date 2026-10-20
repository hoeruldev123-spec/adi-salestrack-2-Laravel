# Business Rules — Revenue Recognition

## 1. Tujuan

Menetapkan satu definisi revenue eligible yang digunakan oleh dashboard, laporan, ranking, dan Monte Carlo forecasting.

## 2. Prinsip Utama

Sistem tidak boleh mengasumsikan bahwa semua transaksi yang dibuat sales langsung menjadi revenue aktual.

Perusahaan harus menyetujui:
1. Dasar pengakuan revenue.
2. Status transaksi yang eligible.
3. Tanggal yang menentukan periode revenue.
4. Perlakuan pembatalan, refund, dan koreksi.
5. Atribusi revenue untuk transaksi bersama.
6. Kebijakan mata uang.

Sampai keputusan tersebut disahkan, aturan di bawah merupakan baseline desain, bukan kebijakan akuntansi resmi ADI.

## 3. Status Transaksi

Contoh lifecycle yang dapat diterapkan jika sesuai alur eksisting:

`draft → submitted → approved → recognized`

Status alternatif seperti `rejected`, `cancelled`, atau `reversed` harus ditangani secara eksplisit.

Baseline perhitungan:
- Draft: tidak eligible.
- Submitted: tidak eligible sebelum persetujuan yang diwajibkan.
- Rejected: tidak eligible.
- Cancelled: tidak eligible.
- Approved/recognized: eligible hanya jika status tersebut memenuhi definisi revenue resmi.

Status `approved` tidak otomatis berarti revenue sudah diakui secara akuntansi. Definisi final harus sesuai proses bisnis perusahaan.

## 4. Formula Revenue

Secara konseptual:

`Revenue Eligible = nilai revenue yang diakui − koreksi/pembatalan yang sah`

Implementasi harus menentukan apakah koreksi direpresentasikan sebagai nilai negatif, reversal, atau adjustment terpisah. Jangan menghitung satu koreksi dua kali.

Jika revenue diakui pada tanggal tertentu, transaksi masuk ke laporan berdasarkan tanggal pengakuan tersebut. Jika perusahaan memilih tanggal invoice atau pembayaran, semua modul harus mengikuti keputusan yang sama.

## 5. Atribusi Sales dan Principal

- Setiap revenue yang masuk ranking harus memiliki aturan atribusi yang jelas.
- Jika transaksi memiliki beberapa sales, tentukan pembagian nominal atau pemilik utama.
- Total atribusi per transaksi harus sesuai dengan total revenue yang diakui, kecuali ada aturan alokasi lain yang terdokumentasi.
- Setiap transaksi harus dikaitkan ke principal yang benar.
- Perubahan atribusi harus tercatat dan tidak mengubah ranking historis secara diam-diam.

## 6. Mata Uang

- Nilai transaksi menyimpan kode mata uang.
- Jangan menjumlahkan nominal dengan mata uang berbeda secara langsung.
- Jika laporan membutuhkan mata uang pelaporan tunggal, tentukan sumber kurs, tanggal kurs, dan aturan pembulatan.
- Simpan informasi konversi jika hasilnya perlu diaudit.

## 7. Periode dan Cutoff

- Setiap laporan menentukan rentang tanggal yang jelas.
- Cutoff laporan harus menggunakan timezone bisnis yang disepakati.
- Transaksi yang terlambat dicatat harus mengikuti kebijakan periode.
- Perubahan setelah periode ditutup harus tercatat dan tidak mengubah laporan historis tanpa penanda revisi.

## 8. Penggunaan oleh Modul Lain

- Dashboard memakai definisi revenue ini.
- Ranking mengurutkan sales berdasarkan revenue eligible.
- Target achievement membandingkan revenue eligible dengan target pada scope dan periode yang sama.
- Monte Carlo memakai histori revenue eligible, bukan nilai draft atau pipeline yang belum tervalidasi.
- Laporan principal dan perusahaan harus dapat direkonsiliasi.

## 9. Acceptance Criteria

- Semua status transaksi memiliki aturan perhitungan yang eksplisit.
- Koreksi dan pembatalan tidak dihitung ganda.
- Periode revenue konsisten di seluruh modul.
- Pembagian revenue antar-sales dapat direkonsiliasi.
- Nilai lintas mata uang tidak dijumlahkan tanpa konversi yang sah.
- Setiap perubahan terhadap transaksi eligible dapat diaudit.
