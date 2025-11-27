# Panduan Menjalankan POS Microservices

Dokumen ini menjelaskan langkah lengkap untuk menyalakan seluruh layanan POS (Gateway, Auth, Product, Order, Payment, Reporting) menggunakan Docker dan RabbitMQ.

## 1. Prasyarat
- Docker dan Docker Compose terpasang.
- Port 8000-8005, 5672, dan 15672 kosong.
- Opsional: Composer/PHP 8.2 jika ingin menjalankan perintah `composer` di host.

## 2. Persiapan Lingkungan
Semua perintah dijalankan dari root repo `pos-microservices`.

1. Salin file environment (jika belum):
   ```bash
   cp gateway/laravel/.env.example gateway/laravel/.env
   cp auth-service/laravel/.env.example auth-service/laravel/.env
   cp product-service/laravel/.env.example product-service/laravel/.env
   cp order-service/laravel/.env.example order-service/laravel/.env
   cp payment-service/laravel/.env.example payment-service/laravel/.env
   cp reporting-service/laravel/.env.example reporting-service/laravel/.env
   ```
   Nilai default sudah diset untuk koneksi MySQL/Mongo/RabbitMQ via docker-compose. Sesuaikan jika perlu.

2. Pastikan `APP_KEY` terisi. Jika kosong di suatu service, jalankan:
   ```bash
   docker-compose exec <service> php artisan key:generate
   ```

## 3. Build dan Jalankan Layanan
```bash
docker-compose build        # pertama kali atau jika ada perubahan dependency
docker-compose up -d        # menyalakan semua kontainer
```

## 4. Jalankan Migrasi dan Seeder
```bash
# Order Service
docker-compose exec order-service php artisan migrate

# Product Service
docker-compose exec product-service php artisan migrate

# Payment Service
docker-compose exec payment-service php artisan migrate
docker-compose exec payment-service php artisan db:seed --class=PaymentMethodSeeder

# Auth Service (jika ada migrasi)
docker-compose exec auth-service php artisan migrate

# Reporting Service (MongoDB) tidak membutuhkan migrasi
```

## 5. Menyalakan Konsumer RabbitMQ
Biarkan perintah ini berjalan (gunakan terminal terpisah atau supervisor):
```bash
# Konsumsi order.created / order.cancelled untuk penyesuaian stok
docker-compose exec product-service php artisan rabbitmq:consume-orders

# Konsumsi payment.completed untuk pembaruan laporan harian
docker-compose exec reporting-service php artisan rabbitmq:consume-payments
```

## 6. Verifikasi Cepat
- Cek health endpoint: `curl http://localhost:8000/up` (gateway) atau `/up` masing-masing service.
- Alur dasar:
  1) Login: `POST http://localhost:8000/api/auth/login` dengan kredensial admin yang tersedia. Ambil token JWT.
  2) Tambah ke cart: `POST http://localhost:8000/api/cart/add` (Bearer token).
  3) Checkout: `POST http://localhost:8000/api/cart/{userId}/checkout`.
  4) Proses pembayaran: `POST http://localhost:8000/api/payments/process`.
  5) Cek laporan: `GET http://localhost:8000/api/reports/sales/daily`.

Contoh singkat menggunakan curl:
```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@pos.test","password":"password"}' | jq -r '.data.token')

curl -X POST http://localhost:8000/api/cart/add \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"product_id":1,"quantity":2,"price":25000}'

curl -X POST http://localhost:8000/api/cart/1/checkout \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"notes":"Test order"}'

curl -X POST http://localhost:8000/api/payments/process \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"order_id":1,"payment_method_id":1,"amount":50000}'
```

## 7. Troubleshooting Singkat
- Jika kontainer gagal start, jalankan `docker-compose logs <service>` untuk melihat log.
- Pastikan database kontainer (`auth-db`, `product-db`, `order-db`, `payment-db`, `mongodb`) berjalan sebelum migrasi.
- Jika event tidak terkonsumsi, cek koneksi RabbitMQ (user/host/port) dan pastikan konsumer sedang berjalan.
- Port bentrok: ubah mapping port host di `docker-compose.yml`.

## 8. Ringkasan Port
- Gateway: 8000
- Auth: 8001
- Product: 8002
- Order: 8003
- Payment: 8004
- Reporting: 8005
- RabbitMQ AMQP: 5672, Console: 15672

## 9. Analisis Perbandingan dengan JMeter
Gunakan Apache JMeter untuk uji beban dan perbandingan skenario (misal sebelum/sesudah perubahan, atau membandingkan 1 vs 2 worker konsumer).

Langkah cepat:
1. Siapkan token login (lihat contoh curl di atas) lalu simpan sebagai JMeter User Defined Variable `TOKEN`.
2. Buat Test Plan dengan Thread Group (misal 20-100 users, ramp-up 10-30 detik).
3. Tambahkan HTTP Header Manager berisi `Authorization: Bearer ${TOKEN}` dan `Content-Type: application/json`.
4. Skenario utama (bisa dipisah per sampler atau dalam Transaction Controller):
   - `POST /api/cart/add` (body JSON produk/qty).
   - `POST /api/cart/{userId}/checkout`.
   - `POST /api/payments/process`.
   - Opsional: `GET /api/reports/sales/daily` untuk melihat dampak read.
5. Tambahkan Assertions sederhana (HTTP 2xx, waktu respon < 2s) untuk memvalidasi keberhasilan.
6. Jalankan tes untuk dua kondisi yang dibandingkan (misal konsumer RabbitMQ aktif vs non-aktif, atau 1 vs 2 replica service).
7. Rekap metrik utama dari Summary Report / Aggregate Report:
   - Rata-rata, p95, p99 response time.
   - Error rate (%).
   - Throughput (req/s) dan KB/s.
   - CPU/mem kontainer (gunakan `docker stats`) sebagai catatan observasi.
8. Catat konfigurasi uji (jumlah thread, ramp-up, durasi, dataset) agar hasil bisa direplikasi.

Tips:
- Gunakan CSV Data Set Config untuk variasi produk/user.
- Pisahkan test plan per service (Order/Payment/Reporting) jika ingin melihat bottleneck spesifik.
- Jika butuh penyesuaian concurrency, skala thread group dan durasi soak (misal 10-15 menit) untuk melihat stabilitas.
