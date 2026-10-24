# ADR-0011: Gerbang Approval Lintas-Department pada Work Order

- **Status:** Accepted (grill dengan user, 2026-10-24)
- **Terhubung:** ADR-0004 (hak-akses), ADR-0009 (WhatsApp gateway), OQ-1 (requester bukan role), OQ-6 (fallback rantai HOD)

## Konteks

Saat ini requester membuat Work Order langsung ke department tujuan — tanpa
sepengetahuan atasan di department-nya sendiri. Untuk WO lintas department
(FR-4.x), department pengaju ingin ada titik kendali: permintaan dari stafnya
harus disetujui dulu sebelum membebani department lain, dan duplikasi WO
(permintaan isi sama yang masuk ke dua department berbeda) harus bisa
dihentikan.

## Keputusan

1. **Status baru** pada `work_orders`: `pending_dept_approval`,
   `rejected_by_dept`, `cancelled` (terminal) — ditambah `released_at`
   (timestamp rilis ke department tujuan) dan `request_rejection_reason`.
2. **Gerbang hanya untuk lintas-department** (R5): WO ke department sendiri,
   dan WO dari akun tanpa department (Admin Tenant), langsung masuk alur
   lama. Gerbang tetap berlaku untuk WO urgent (R6); aturan kategori
   (Accident tanpa jadwal, Owner boleh jadwal) tidak berubah.
3. **Approver** = rantai department pengaju: `hod_user_id` →
   `manager_user_id` → role `hod` di department → role baru
   **`staf_admin_dept`** (keputusan user OQ-8) → fallback Admin Tenant +
   log kritikal. Requester **tidak pernah** bisa memutuskan permintaannya
   sendiri (R9), dipaksa server-side, bukan cuma UI. Self-approval tetap
   terblokir walau requester ber-role HOD — lapisan berikutnya yang jalan.
4. **Visibilitas (R8):** WO belum-rilis (`released_at` NULL) tak terlihat
   department tujuan — dipaksa di `WorkOrder::scopeVisibleFor` dan Gate
   `work-order.view`. Notifikasi `WorkOrderCreated` ke dept tujuan baru
   dikirim saat approve (R11).
5. **Reject** wajib alasan (tersimpan + masuk histori + tracker requester);
   requester bisa **revisi** lalu **resubmit** (tanpa batas percobaan,
   OQ-5) atau **cancel** (terminal, R12).
6. **Cancel-duplikat (OQ-4b):** reviewer department tujuan dapat membatalkan
   WO pada tahap review **hanya sebagai duplikat** — wajib menunjuk WO
   duplikatnya; kandidat duplikat ditandai otomatis (judul sama
   case-insensitive dalam 30 hari, lintas department), keputusan tetap
   manusia.
7. **Timer durasi kerja (R16–R18):** tabel `work_time_logs` satu baris per
   (WO, pelaksana) — `accepted_at`, `started_at`, `finished_at`,
   `paused_seconds`, `paused_at`, `pause_log`. Semua timestamp server-side;
   elapsed dihitung dari data tersimpan (benar setelah refresh/perangkat
   lain). `start` mengaktifkan status `in_progress`; `finish` dipicu
   otomatis oleh submit hasil. Benchmark: deadline SLA + durasi planning
   bila ada (OQ-9).
8. **Notifikasi WA wajib** untuk 4 event baru (`request_submitted`,
   `request_approved`, `request_rejected`, `cancelled`) — melengkapi daftar
   4 event ADR-0009 sesuai klausul "event lain menyusul bersama fiturnya";
   pola sesi per tenant tidak berubah.
9. **Izin baru** `work-order.approve-request` (verb katalog work-order):
   default ke `hod`, `staf_admin_dept` (baru), `admin_tenant`;
   super_admin beroleh lewat bypass Gate (ADR-0004).

## Konsekuensi

- WO lama di-backfill `released_at = created_at` (semua lahir sebelum
  gerbang) — visibilitas historis tidak berubah.
- Database dev existing perlu `php artisan migrate` + `php artisan
  app:grant-default-permissions` (izin baru tidak otomatis menempel ke role
  yang sudah ada).
- Halaman/muka (approval inbox, tracker, tampilan pelaksana + timer)
  menyusul di Stage 3–4; payload server sudah menyertakan datanya.
- PRD: alur FR-4.x berubah — matrix UAT diperbarui di Stage 6 (R24).

## Referensi

- `app/Services/WorkOrderRequestService.php` — gerbang + keputusan
- `app/Services/WorkTimeLogService.php` — timer durasi
- `database/migrations/2026_10_24_142826_add_request_gate_and_work_time_logs_to_work_orders.php`
- `database/seeders/RoleAndPermissionSeeder.php` — role + verb baru
