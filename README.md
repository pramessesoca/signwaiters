# SignWaiters - Sistem Permohonan File TTE

Aplikasi fullstack Laravel untuk mengelola alur permohonan file TTE:
- User upload file permohonan + isi data.
- Sistem generate token unik untuk pelacakan.
- Admin memproses permohonan dan upload file TTE.
- User download file berdasarkan token.

Penyimpanan file menggunakan MinIO (S3-compatible), cache/session menggunakan Redis, dan proses bulk menggunakan queue worker.

## Fitur Utama

### User (Publik)
- Form permohonan file (`nama`, `tim`, `file PDF`).
- Generate token otomatis.
- Cek status berdasarkan token.
- Download file TTE jika status `siap`.
- Badge status berwarna di halaman cek token.

### Admin
- Login admin berbasis `username + password`.
- Dashboard dengan:
  - filter status, tim, tanggal
  - pencarian nama/tim/token
  - sorting kolom
  - pagination
- Aksi per permohonan:
  - setujui / tolak
  - upload file TTE
  - generate ulang token (rename file mengikuti token baru)
  - hapus data
- Generate TXT dari hasil filter (dengan numbering 1,2,3,...).
- Bulk Upload ZIP (dengan progress live polling).
- Bulk Download ZIP hasil filter (dengan progress live + auto download).
- Clear riwayat bulk dan clear all bulk.

## Arsitektur Singkat

- Framework: Laravel
- Database: MySQL
- File storage: MinIO via disk `s3`
- Cache/Session: Redis (Predis)
- Queue: `redis` driver + `php artisan queue:work redis`
- Frontend: Blade + Tailwind

## Struktur Alur Sistem

1. User submit permohonan PDF.
2. Sistem simpan file ke MinIO (`request/YYYY/MM/DD/...`) dan buat token 8 karakter.
3. Admin review data di dashboard.
4. Admin upload file TTE (manual atau bulk ZIP).
5. Sistem simpan file TTE ke MinIO (`tte/YYYY/MM/DD/...`) dan update status jadi `siap`.
6. User download hasil dengan token.

## Daftar Status

- `tunggu`
- `setuju`
- `tolak`
- `siap`

## Contoh Daftar Tim

- ITSA
- Monitoring
- Proteksi
- Analisis Malware
- Threat Hunting
- Cyber Threat Intelligence
- Digital Forensic
- Incident Response
- Infrastruktur

## Requirement

- PHP 8.2+
- Composer 2+
- Node.js 18+ dan npm
- MySQL 8+
- Redis
- MinIO
- Ekstensi PHP penting: `zip`, `xml`, `tokenizer`, `mbstring`, `openssl`, `pdo_mysql`

Catatan upload besar:
- `upload_max_filesize` dan `post_max_size` di `php.ini` harus cukup besar (mis. 100M/120M).

## Instalasi Lokal

1. Clone project lalu install dependency.

```bash
composer install
npm install
```

2. Copy environment file.

```bash
cp .env.example .env
```

3. Generate app key.

```bash
php artisan key:generate
```

4. Atur `.env` (contoh inti):

```env
APP_NAME=SignWaiters
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=signwaiters
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=null
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=ttedir21
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=http://127.0.0.1:9000/ttedir21

ADMIN_NAME=Admin
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin12345
ADMIN_EMAIL=admin@local.test
```

5. Siapkan tabel DB + queue.

```bash
php artisan migrate
```

6. Seed admin default.

```bash
php artisan db:seed
```

7. Build asset frontend.

```bash
npm run dev
```

8. Jalankan aplikasi.

```bash
php artisan serve
```

9. Jalankan worker queue (wajib untuk proses bulk).

```bash
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=1800
```

## Menjalankan Banyak Worker (Windows PowerShell)

Contoh 4 worker sekaligus:

```powershell
1..4 | ForEach-Object {
  Start-Process powershell -ArgumentList "-NoExit","-Command","cd 'D:\Web Porto\signwaiters'; php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=1800"
}
```

## Cara Penggunaan

### User
- Buka `/permohonan`.
- Isi form dan upload PDF.
- Simpan token yang dihasilkan.
- Cek status di `/cek-token`.
- Jika status `siap`, download dari tombol unduh.

### Admin
- Login di `/admin/login`.
- Dashboard di `/admin/dashboard`.
- Gunakan filter/pencarian/sort sesuai kebutuhan.
- Proses tiap request dari tombol `Detail`.

### Bulk Upload ZIP
- Masuk halaman `/admin/bulk-upload`.
- Upload ZIP berisi PDF (boleh nested ZIP).
- Format file: `TOKEN_namafile.pdf`.
- Sistem proses maksimal 100 file per batch.
- Pantau progress live sampai `done/failed`.

### Bulk Download ZIP
- Dari dashboard, trigger bulk download sesuai filter aktif.
- Proses berjalan via queue.
- Pantau di `/admin/bulk-download?bulk_download_id=...`.
- Saat selesai, ZIP auto terunduh.

### Generate TXT
- Dari dashboard admin, klik generate TXT.
- File TXT berisi daftar nama file sesuai filter aktif.
- Setiap baris bernomor (`1.`, `2.`, dst).

## Endpoint Penting

### Web
- `GET /permohonan`
- `POST /permohonan`
- `GET /cek-token`
- `POST /cek-token`
- `GET /unduh/{token}`
- `GET /admin/login`
- `POST /admin/login`
- `GET /admin/dashboard`
- `GET|POST /admin/bulk-upload`
- `GET|POST /admin/bulk-download`

### API
- `POST /api/request`
- `POST /api/status`
- `GET /api/download/{token}`
- `POST /api/admin/login`

## Deploy Production

## 1. Server Requirement
- Linux server (Ubuntu/Debian disarankan)
- Nginx/Apache
- PHP-FPM 8.2+
- MySQL
- Redis
- MinIO (self-hosted) atau object storage S3-compatible
- Supervisor/systemd untuk queue worker

## 2. Langkah Deploy

1. Upload source code ke server.
2. Jalankan:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

3. Set `.env` production:
- `APP_ENV=production`
- `APP_DEBUG=false`
- konfigurasi DB/Redis/MinIO yang valid

4. Generate key + migrate + seed admin:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
```

5. Optimasi Laravel:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. Jalankan queue worker via Supervisor (contoh command):

```bash
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=1800
```

7. Setup scheduler (opsional jika nanti ada auto cleanup):

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## 3. Checklist Production

- Pastikan bucket MinIO sudah ada.
- Pastikan kredensial MinIO benar.
- Pastikan worker queue selalu running.
- Pastikan `storage/` dan `bootstrap/cache/` writable.
- Pastikan limit upload di PHP/Nginx cukup untuk ZIP.

## Troubleshooting Cepat

- Bulk tidak jalan: cek worker `queue:work`.
- Upload ZIP gagal: cek `upload_max_filesize` dan `post_max_size`.
- Error Redis connection refused: pastikan Redis service aktif.
- File download not found: cek object path di DB dan bucket MinIO.
- Progress mentok queued: cek tabel `jobs`/`failed_jobs` dan log `storage/logs/laravel.log`.
