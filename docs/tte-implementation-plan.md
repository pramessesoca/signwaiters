# Rencana Implementasi Website Fullstack Laravel - Permohonan File TTE

## 1. Ringkasan Arsitektur

Aplikasi dibangun dengan Laravel (server-rendered Blade) dengan dua area utama:

- User publik (tanpa login) untuk submit permohonan, cek status, dan download file TTE via token.
- Admin (wajib login) untuk memproses permohonan: approve/reject dan upload file TTE final.

Penyimpanan file menggunakan MinIO (S3-compatible) dengan objek private.

## 2. Alur Bisnis End-to-End

### 2.1 Alur User Publik

1. User membuka halaman permohonan.
2. User mengisi form: `nama`, `tim kerja`, dan upload file PDF.
3. Sistem menyimpan permohonan dengan status `pending`, menghasilkan token 8 karakter, lalu menampilkan token ke user.
4. User dapat membuka halaman cek status dan memasukkan token.
5. Jika status sudah `ready` dan token belum expired, user dapat download file TTE.

### 2.2 Alur Admin

1. Admin login ke sistem.
2. Admin melihat daftar permohonan.
3. Admin membuka detail permohonan dan memilih:
   - `approve` untuk menyetujui proses.
   - `reject` untuk menolak (dengan catatan penolakan).
4. Jika file TTE final sudah tersedia, admin mengupload file PDF hasil TTE.
5. Sistem mengubah status menjadi `ready` agar dapat diunduh user.

### 2.3 Masa Berlaku Token

- Token berlaku 7 hari dihitung dari waktu submit permohonan.
- Setelah expired, status dianggap `expired` untuk akses download.

## 3. Desain Penyimpanan MinIO (S3-Compatible)

### 3.1 Konfigurasi Environment

Gunakan disk `s3` Laravel dengan endpoint MinIO pada `.env`:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

### 3.2 Struktur Object Key

Satu bucket, dengan dua root folder:

- File permohonan user:
  - `request/YYYY/MM/DD/{unique}_{originalName}.pdf`
- File hasil TTE admin:
  - `tte/YYYY/MM/DD/{unique}_{originalName}.pdf`

Semua objek private dan diakses melalui backend Laravel.

## 4. Kontrak Data Utama (`tte_requests`)

Tabel utama: `tte_requests`

Kolom minimum yang direkomendasikan:

- `id` (bigint)
- `requester_name` (string)
- `team_name` (string)
- `token` (string, unique, length 8)
- `status` (enum/string): `pending`, `approved`, `rejected`, `ready`
- `request_file_key` (string, nullable=false)
- `signed_file_key` (string, nullable=true)
- `admin_note` (text, nullable=true)
- `approved_at` (timestamp, nullable=true)
- `rejected_at` (timestamp, nullable=true)
- `expires_at` (timestamp, nullable=false)
- `created_at`, `updated_at`

Aturan status:

- Saat submit: `pending`
- Saat admin setujui: `approved`
- Saat admin tolak: `rejected`
- Saat admin upload file final: `ready`

## 5. Daftar Route

### 5.1 Public Routes

- `GET /request` - halaman form permohonan.
- `POST /request` - simpan permohonan dan generate token.
- `GET /status` - halaman input token.
- `POST /status` - tampilkan status berdasarkan token.
- `GET /download/{token}` - download file TTE jika valid.

### 5.2 Admin Routes (middleware `auth`)

- `GET /admin/requests` - daftar permohonan.
- `GET /admin/requests/{id}` - detail permohonan.
- `PATCH /admin/requests/{id}/approve` - set status approve.
- `PATCH /admin/requests/{id}/reject` - set status reject.
- `PATCH /admin/requests/{id}/signed-file` - upload file TTE final.

## 6. Aturan Validasi

- `requester_name`: required, string.
- `team_name`: required, string.
- `source_file` (user upload): required, file, mimetype/pdf, max 10MB.
- `signed_file` (admin upload): required, file, mimetype/pdf, max 10MB.
- `token`: required, panjang 8, uppercase alfanumerik.
- `reject` wajib `admin_note`.
- Download hanya valid jika:
  - token ditemukan,
  - status `ready`,
  - `expires_at` belum lewat.

## 7. Rencana Pengujian (Feature Scenarios)

1. Submit permohonan valid menghasilkan token unik 8 karakter.
2. Submit dengan file non-PDF ditolak validasi.
3. Submit dengan file >10MB ditolak validasi.
4. Cek status token valid menampilkan data permohonan yang benar.
5. Token tidak ditemukan menghasilkan respons not found.
6. Admin tanpa login tidak bisa akses route `/admin/*`.
7. Admin dapat approve permohonan.
8. Admin reject tanpa catatan gagal validasi.
9. Admin upload file signed mengubah status menjadi `ready`.
10. Download berhasil untuk token `ready` yang belum expired.
11. Download ditolak untuk token expired.
12. Download ditolak untuk status selain `ready`.

## 8. Asumsi dan Default v1

- User publik tidak perlu registrasi/login.
- Admin menggunakan auth bawaan Laravel.
- Tidak ada notifikasi email/WhatsApp pada v1.
- Akses file tetap melalui backend Laravel (streaming), bukan URL public MinIO.
- Masa berlaku token fixed 7 hari dari waktu submit.
