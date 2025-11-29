# Catatan Arsitektur Monolithik (Alternatif untuk Laporan)

Dokumen ini menggambarkan implementasi arsitektur monolithik sebagai pembanding terhadap microservices pada proyek POS.

## Gambaran Singkat
- **Single codebase & deployment**: Semua domain (Auth, Product, Order, Payment, Reporting) berada dalam satu aplikasi Laravel.
- **Single DB**: Satu schema MySQL berisi tabel lintas domain; opsional memisah schema per bounded context namun tetap satu server.
- **In-process call**: Integrasi antar domain via service class/domain layer, bukan HTTP antar service atau RabbitMQ.
- **Synchronous by default**: Flow lintas domain (checkout -> payment -> stok -> laporan) berjalan dalam satu request/transaction; queue lokal (database/redis) hanya jika perlu async ringan.

## Struktur Monolith Laravel (modular monolith)
- **Modules/Packages** per domain (disarankan namespace terpisah):
  - Auth (User, Role/Permission)
  - Product (Product, Category, Inventory)
  - Order (Order, Cart, OrderItem)
  - Payment (Payment, PaymentMethod, Receipt)
  - Reporting (SalesReport, ProductReport)
- **Routing**: Satu `routes/api.php` dengan prefix per domain atau file route per modul yang di-include dari satu kernel.
- **Service Layer**: `OrderService`, `PaymentService`, `InventoryService`, `ReportingService` dipanggil langsung antar modul untuk menjaga batas konteks tanpa HTTP.
- **Event/Listener internal**: Event Laravel (`OrderCreated`, `PaymentCompleted`, `StockAdjusted`) dengan listener in-process; tidak memakai AMQP.
- **Queue lokal (opsional)**: Gunakan driver `database`/`redis` Laravel jika butuh async ringan (misal pengiriman email/receipt).

## Contoh Alur Transaksi Monolith
1) User checkout (API Order).  
2) `InventoryService` validasi stok lewat query langsung.  
3) Order dibuat dalam DB, event `OrderCreated` dipicu.  
4) Listener `AdjustStock` menurunkan stok produk.  
5) `PaymentService` dipanggil langsung; jika sukses, status order `completed`, event `PaymentCompleted` dipicu.  
6) Listener `UpdateReports` menulis agregat harian/mingguan ke tabel laporan (atau tetap Mongo jika hybrid).  
7) Response dikembalikan tanpa hop jaringan antar service.

## Kelebihan
- Deploy/pipeline lebih sederhana (satu artefak).
- Observabilitas mudah (satu log/trace).
- Transaksi lintas domain lebih mudah (bisa dalam satu DB transaction).
- Overhead jaringan nol untuk komunikasi antar domain.

## Kekurangan
- Skalabilitas per domain terbatas (scale-all, bukan scale-per-service).
- Risiko big ball of mud jika disiplin modularisasi lemah.
- Rilis besar (big bang) berisiko; blast radius tinggi.
- Penguncian teknologi (sulit beda stack per domain).

## Langkah Migrasi Microservices -> Monolith (ringkas)
1. Satukan codebase: gabungkan modul/domain ke satu repo Laravel (modular folder/namespace).  
2. Gabungkan migrasi: satukan skema, sesuaikan FK antar domain.  
3. Ganti HTTP call jadi service call in-process; hapus client HTTP antar domain.  
4. Ganti event AMQP jadi event Laravel + listener lokal; RabbitMQ tidak dipakai untuk jalur utama.  
5. Sederhanakan konfigurasi: satu `.env`, satu DB, satu Redis/queue driver.  
6. Routing: gateway/prefix langsung dilayani aplikasi monolith (opsional tetap pakai reverse proxy untuk SSL/rate-limit).  
7. Uji regresi penuh (end-to-end) sebelum cutover.

## Catatan Desain
- Jaga **bounded context** via namespace, folder, dan service layer untuk mencegah erosi batas domain.
- Terapkan **modular monolith**: setiap modul punya route, config, provider sendiri tapi tetap satu deployable.
- Untuk beban baca tinggi, pertimbangkan **read model terpisah** (CQRS ringan) tanpa memecah layanan.
- Gunakan **feature flag** saat memindah jalur microservices ke monolith secara bertahap agar risiko terkontrol.
