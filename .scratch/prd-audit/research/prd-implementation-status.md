---
created: 2026-10-21
tags: [research, prd, audit, wms-mvp, status]
status: draft
---

# Audit Implementasi PRD vs Kode — Status per Bagian/FR (2026-10-21)

> **Scope:** audit read-only `PRD.md` (§1–§10 + Lampiran B) terhadap kondisi kode aktual per 2026-10-21, dengan pemetaan tiap bagian PRD ke heading `TODO.md` agar progres ke depan cukup dilacak lewat TODO.
>
> **Status:** research/draft. Tidak ada kode/dokumen aplikasi yang diubah — file ini satu-satunya tulisan.
>
> **Method (code-first verification):**
> - Membaca penuh `PRD.md`, `TODO.md`, `AGENTS.md`, `docs/adr/0001–0010`.
> - Verifikasi langsung ke kode: `routes/*.php`, `app/Http/Controllers/**`, `app/Http/Requests/**`, `app/Services/**`, `app/Console/Commands/**`, `app/Models/**`, `app/Notifications/**`, `database/migrations/**`, `database/seeders/**`, `resources/js/pages/**`, `tests/Feature/**`.
> - Perintah read-only: `php artisan route:list --path=work-orders` (10 route terdaftar) dan `php artisan migrate:status` (27 migrasi, **semua Ran, 0 pending**).
> - **Suite TIDAK dijalankan ulang** (larangan tugas); klaim "suite hijau 492 = 484 passed + 8 skipped" dikutip dari `TODO.md:5`. Setiap status di bawah berbukti kode/test yang sudah ada di repo, bukan hasil eksekusi.
>
> **Evidence legend:**
> - **[CODE]** perilaku terverifikasi di kode repositori (path:line).
> - **[TEST]** ada test committed yang menutupnya (kelas::nama test).
> - **[DOC]** keputusan tertulis (ADR id / AGENTS.md:line / TODO.md:line).
> - **[NONE]** tidak ditemukan di kode (pencarian menyeluruh `app/`, `database/`, `routes/`, `resources/js/`, `tests/`).
> - **BELUM VERIFIKASI** = klaim yang tidak bisa dibuktikan dari baca kode saja.

---

## 1. Ringkasan Eksekutif

**Total 89 baris status** (§1, §3, §5, §6 FR, §7 entitas, §8 edge case, §9, §10 OQ; §2 & §4 tidak dihitung terpisah karena tercakup FR §6. Catatan hitung: §7 dihitung 14 entitas PRD — 3 baris "(baru)" di tabel §7 (`work_order_events`, `audit_logs`, `wa_messages`/`tenant_wa_sessions`) bersifat informatif dan tidak dihitung; baris §5 "Integrasi WA/email" dihitung sebagai `DONE` untuk bagian WA):

| Status | Jumlah |
| --- | ---: |
| `DONE` (implementasi + test) | **26** |
| `PARTIAL` (sebagian, sisanya disebut) | **15** |
| `NOT STARTED` (nol kode) | **28** |
| `OUT OF SCOPE` (v2 / "Nanti" / Non-Goal) | **8** |
| `SETTLED` (keputusan; sub-status implementasi dicatat) | **12** |
| **Total** | **89** |

**5 gap terpenting:**

1. **Alur inti WO berhenti di `assigned` — submit hasil & verifikasi HOD tidak ada** (FR-1.7, FR-1.8 `NOT STARTED`). Enum `WorkOrderStatus` punya `InProgress`/`PendingVerify`/`Revision`/`Closed` (`app/WorkOrderStatus.php:15-23`) tetapi **tidak ada endpoint/UI yang pernah mencapainya** [NONE] — Goals §3 #1 ("menutup WO") dan FR-3.x (Work Data lahir dari WO closed) mentok di sini.
2. **Seluruh modul "lanjutan" nol kode:** Daily Work (FR-5.1–5.4), Work Data Management (FR-3.1–3.3), `extend_requests` untuk extend deadline/schedule (FR-2.3–2.5), `work_planning`. Pencarian `extend_requests|work_planning|work_daily|work_data|schedule_wd` di `app/`, `database/`, `routes/`, `resources/js/` = **tidak ada satupun** [NONE]; migrasi `extend_requests` yang disebut SETTLED (OQ-9) belum pernah dibuat (`php artisan migrate:status` — tidak terdaftar).
3. **Eskalasi H+6 tidak sampai ke DGM/GM.** `CheckDeadlineEscalation::resolveRecipients()` mengirim H+5 **dan** H+6 ke rantai fallback HOD yang sama (`app/Console/Commands/CheckDeadlineEscalation.php:141-153`) — role `general_manager`/`deputy_general_manager` yang ada di seeder (`RoleAndPermissionSeeder.php:39-40`) tidak pernah jadi penerima. Aturan bisnis AGENTS.md (H+6 → DGM/GM, `AGENTS.md:81-85`) belum terpenuhi → FR-2.2 `PARTIAL`.
4. **Dua defect/risiko tersembunyi di WO Update & revisi assign:** `Route::resource('work-orders')` mendaftarkan `edit/update/destroy` (`routes/web.php:134-136`, terkonfirmasi `route:list`) tetapi `WorkOrderController` **tidak punya method** `edit()/update()/destroy()` (file berakhir di `attachment()`, `WorkOrderController.php:198`) → request ke route itu = 500. Terkait: UI menampilkan panel revisi-assign saat status `assigned` (`show.tsx:49` `ASSIGNABLE = ['hod_approved','scheduled','assigned']`) tapi backend menolak dengan 409 (`WorkOrderService.php:122-124`) — tidak ada test yang menutup alur revisi pasc-assign (`AssignWorkOrderTest.php:158` menguji dari status `hod_approved`).
5. **Dokumen keputusan stale:** `AGENTS.md` **tidak ter-amend** soal WA gateway padahal `TODO.md:52-55` mengklaim 4 titik amendmen — file di disk masih mencantumkan "Integrasi eksternal (WA/email gateway…) → Nanti" (`AGENTS.md:57`), "WA/email gateway → fase berikutnya" (`AGENTS.md:108`), dan OQ-7 "in-app only" tanpa pengecualian (`AGENTS.md:180`), sedangkan **ADR-0009 status `accepted`** dan WA slice 1–2 sudah shipped + test (lihat §3 Peta PRD ↔ TODO). PRD §3/§5 juga stale di titik yang sama. Detail di §4 laporan ini.

Catatan pendukung: sisi server notifikasi realtime (broadcast Reverb + tabel DB) sudah jalan, tetapi **frontend tidak punya listener Echo** — hanya `window.Echo` diinisialisasi (`resources/js/echo.js:10-18`, diimpor `app.tsx:2`); pencarian `.notification(`/`Echo.private(` di `resources/js` = nol. Badge bell di-update lewat shared props Inertia saat navigasi (`HandleInertiaRequests.php:132`), bukan push WS.

---

## 2. Tabel Status per FR / Bagian

### 2.1 §1 Problem Statement — klaim audit trail

| ID (PRD) | Status | Bukti (file:line / test) | Catatan |
| --- | --- | --- | --- |
| §1 — "Tidak ada audit trail: siapa assign siapa, kapan revisi…, kapan deadline di-extend… oleh siapa" | `PARTIAL` | `work_order_events` append-only: migrasi `2026_10_17_030526_create_work_order_events_table.php:16-29`; tulis event di `WorkOrderService.php:87-94` (reviewed) & `167-180` (assigned); test `ReviewWorkOrderTest` (`assertDatabaseHas('work_order_events')` :85-87) + `AssignWorkOrderTest:108-110`. `audit_logs` untuk aksi akun/hak-akses: migrasi `2026_10_21_000004_create_audit_logs_table.php:27-45`, 7 test `AuditTrailTest` (:44,58,73,91,111,131,146) | Yang belum: riwayat **di tampilan** (tidak ada UI pembaca `work_order_events` — `WorkOrderController::show` :134-171 hanya load `requester`,`targetDepartment`), UI reader `audit_logs` (TODO `:11` "UI reader & input reason = lanjutan"), dan sisi **extend** (alurnya belum ada). |

### 2.2 §3 Goals & Non-Goals

| ID (PRD) | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| G1 — Satu sistem terpusat create/assign/jadwalkan/kerja/**tutup** WO lintas department | `PARTIAL` | Create: `WorkOrderController.php:60-98` + test `WorkOrderTest:32`; review (jadwal/eksekusi): `WorkOrderService.php:57-100` + `ReviewWorkOrderTest:67,93`; assign: `WorkOrderService.php:113-195` + `AssignWorkOrderTest:83` | **Missing:** eksekusi (submit hasil), verifikasi, **close** WO (FR-1.7/1.8), dan Update WO (method controller tidak ada). |
| G2 — Eskalasi berjenjang TL → HOD → DGM/GM otomatis | `PARTIAL` | `app:check-deadline-escalation` `CheckDeadlineEscalation.php:19-60`, penjadwalan `bootstrap/app.php:32` (daily 09:00), H+3 TL `:158-174`, fallback `:181-224`, test `EscalationTest` (:75,138,299,321) | **Missing:** penerima H+6 = rantai HOD, **bukan DGM/GM** (`:141-153`). |
| G3 — Visibilitas Daily Work per karyawan | `NOT STARTED` | [NONE] — `database/`, `app/`, `resources/js/` tanpa `work_daily`/`schedule_wd` | Issue `.scratch/wms-mvp/issues/07-daily-work-templates.md` status `ready-for-agent`. |
| G4 — Notifikasi realtime tiap perubahan status relevan | `PARTIAL` | `via()` = `database`,`broadcast`,WA: `WorkOrderCreated.php:20-23`; Echo init `echo.js:10-18`; bell+count `app-header.tsx:225-241`, `HandleInertiaRequests.php:132`; halaman history `NotificationController.php:12-41` | **Missing:** listener WS di frontend [NONE]; event submit/revisi/extend/daily-work belum ada (modulnya belum ada). |
| G5 — RBAC Spatie, akses per role | `DONE` | Seeder 10 role `RoleAndPermissionSeeder.php:36-50`; `Gate::before` superadmin `AppServiceProvider.php:91-93`; gate flags `HandleInertiaRequests.php:86-102`; test `GateTest` (11 test), `PermissionConsistencyTest`, `RoleControllerTest`, `PermissionControllerTest`; ADR-0004/0005/0008 `accepted` | — |
| G6 — Work Data Management sebagai histori/audit trail per WO | `NOT STARTED` | [NONE] `work_data`/`work_data_pekerja` di `database/` & `app/` | Hanya disebut di komentar `2026_10_17_035140:10-11` ("rows come later… issue 04/08"). |
| NG1 — Inventory, Kelompok Barang, Surat Masuk/Keluar | `OUT OF SCOPE` | `AGENTS.md:55` (Non-Goals); PRD `:54` | Sesuai Non-Goal; tidak ada kode [NONE]. |
| NG2 — Management Tenant (CRUD penyewa tempat) → v2 | `OUT OF SCOPE` | `AGENTS.md:56`; PRD `:55` | Yang ADA: halaman Unit Bisnis **read + sync** superadmin (`routes/web.php:74-78`, `TenantController`, `TenantManagementTest`) — bukan CRUD penyewa; bukan pelanggaran Non-Goal. |
| NG3 — Integrasi eksternal (WA/email gateway, absensi) | `OUT OF SCOPE` *(kualifikasi: WA di-amend jadi in-scope & sudah `DONE`)* | PRD `:56` & `AGENTS.md:57` masih menyatakan di luar scope → **stale**; realita: ADR-0009 `status: accepted` (`docs/adr/0009-whatsapp-gateway.md:2`), WA slice 1–2 DONE (`TODO.md:18,69`), 18 test WA (`WahaGatewayTest`, `WahaTenantSessionTest`, `WahaClientTest`) | Email gateway & sistem absensi tetap `OUT OF SCOPE`. Lihat §5 PRD stale. |
| NG4 — Analytics/reporting dashboard lanjutan | `OUT OF SCOPE` | `AGENTS.md:58`; allowlist `PermissionConsistencyTest.php:153-155` (`dashboard.read`/`reports.read` "gated when reporting lands") | Dashboard saat ini demo statis (lihat §9). |

### 2.3 §5 Daftar Fitur (14 baris)

| ID (PRD) | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| Work Order (Create/Update/Review/Assign) | `PARTIAL` | Create/Review/Assign: `WorkOrderController.php:60-132`, test `WorkOrderTest`/`ReviewWorkOrderTest`/`AssignWorkOrderTest` | **Update belum** — route `work-orders.update/edit/destroy` terdaftar tapi method tidak ada (`route:list` vs `WorkOrderController.php`); tidak ada `work-orders/edit.tsx`. |
| Work Order Terjadwal (planning + eksekusi) | `PARTIAL` | Keputusan jadwal HOD: `scheduled_date` migrasi `2026_10_17_024806.php:19`, `WorkOrderService.php:78-85`, status `Scheduled`, test `ReviewWorkOrderTest:93` | **Missing:** modul `work_planning` + eksekusi planning + Extend Work Schedule [NONE]. |
| Work Data Management (histori per WO) | `NOT STARTED` | [NONE] — lihat G6 | Issue `.scratch/wms-mvp/issues/08-work-data-management.md` `ready-for-agent`. |
| Lintas Department Workorder | `DONE` | `target_department_id` validasi lintas-cabang `WorkOrderController.php:66-69,82`; test `WorkOrderTest:191` (target dept cabang lain → gagal), `:227` (index requester + dept yang di-review) | — |
| Daily Work Management | `NOT STARTED` | [NONE] | Issue `.scratch/wms-mvp/issues/07-daily-work-templates.md` `ready-for-agent`. |
| RBAC (Spatie) | `DONE` | Lihat G5; ADR-0004/0005/0006/0008 | Hak-akses UI: `routes/hak-akses.php:10-16`, `routes/settings.php:31-35`. |
| Realtime Notification | `PARTIAL` | `via('database','broadcast',…)` `WorkOrderCreated.php:22`; `notifications` table migrasi `2026_10_03_000000.php:14-21`; bell `app-header.tsx:241`; history `notifications/index` + `NotificationTest` (:37,57,72,88) | Server push OK; **client WS listener tidak ada** [NONE]; toast hanya flash (`use-flash-toast.ts:3`). |
| Eskalasi deadline berjenjang (TL → HOD → DGM/GM) | `PARTIAL` | `CheckDeadlineEscalation.php:79-97` + scheduler `bootstrap/app.php:32` + `EscalationTest` 11 test | H+6 ≠ DGM/GM (`:141-153`) — lihat G2. |
| Extend deadline WO telat (approval berjenjang) | `NOT STARTED` | Kolom `extend_count`/`extend_reason`/`extended_at` sudah ada (`2026_10_03_000100.php:29-31`) tetapi **tabel `extend_requests` + endpoint/approval tidak ada** [NONE]; `migrate:status` tidak menampilkan migrasi extend | Issue `.scratch/wms-mvp/issues/06-extend-work-order-schedule.md` `ready-for-agent`. |
| Extend Work Schedule (approval DGM/GM) | `NOT STARTED` | [NONE] — sama seperti di atas; `work_planning` tidak ada | — |
| Management Tenant (CRUD penyewa tempat) — v2 | `OUT OF SCOPE` | `AGENTS.md:56` | Halaman Unit Bisnis read+sync = fitur lain (Optigate), bukan CRUD. |
| Integrasi WA/email notification — PRD: "Nanti" | `DONE` (WA) / `OUT OF SCOPE` (email) | ADR-0009 `accepted`; `WahaWhatsAppChannel`, `SendWaMessageJob`, `WahaClient`, outbox `wa_messages` (migrasi `2026_10_21_000001`), sesi `tenant_wa_sessions` (`2026_10_21_000002`), halaman `settings/whatsapp.tsx`, tests `WahaGatewayTest` (14) + `WahaTenantSessionTest` (23) + `WahaClientTest` (4) | **PRD stale** (baris kategori "Nanti" sudah tidak berlaku untuk WA). Email belum ada [NONE]. |
| Reporting/analytics dashboard lanjutan | `OUT OF SCOPE` | `AGENTS.md:58` | — |
| Inventory, Kelompok Barang, Surat Masuk/Keluar | `OUT OF SCOPE` | `AGENTS.md:55` | — |

### 2.4 §6 Functional Requirements

#### FR-1.x — Work Order (Create/Update/Review/Assign)

| ID | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| FR-1.1 | `PARTIAL` | Form create: `create.tsx` field `title`(:70), `category`(:81), `target_department_id`(:109), `description`(:137), `attachments[]`(:171); validasi `WorkOrderController.php:62-80`; test `WorkOrderTest:32` (create + nomor WO) & `:62` (attachments) | **Missing sesuai teks FR:** field **prioritas**, **jumlah personel dibutuhkan**, dan **jenis pekerjaan/kerusakan** sebagai field tersendiri (hanya `title`+`description`; tidak ada kolom `priority` di migrasi `2026_10_03_000100`). Lampiran gambar ✓ (maks 3, jpg/png/webp ≤2MB, `:78-79`). Update WO (bagian judul §6.1) → lihat baris §5 "Create/Update…". |
| FR-1.2 | `DONE` | `WorkOrderCategory::allowsScheduling()` (`app/WorkOrderCategory.php:37-40`); form sembunyikan jadwal utk Accident (`create.tsx:148-163`); backend `prohibitedIf` (`WorkOrderController.php:74-77`); guard review (`WorkOrderService.php:72-76`); test `WorkOrderTest:83` + `ReviewWorkOrderTest:117` | Opsi jadwal tidak ditampilkan + ditolak server-side. |
| FR-1.3 | `DONE` | Owner → `allowsScheduling()` true (`WorkOrderCategory.php:37-40`); `ReviewWorkOrderRequest.php:28` aksi `execute_immediately`/`schedule`; test `ReviewWorkOrderTest:93` (schedule) & `:67` (langsung) | Tidak ada test khusus kategori Owner; jalur kodenya sama dengan Normal. |
| FR-1.4 | `DONE` | Sama seperti FR-1.3; test `ReviewWorkOrderTest:93,133` | `scheduled_date` wajib hari ini/napas depan. |
| FR-1.5 | `DONE` | `WorkOrderService::create` notify HOD (`:41-43`) via `HodNotificationResolver::resolve` (`app/Services/HodNotificationResolver.php:20-46`); test `WorkOrderTest:32,123,146,169` (rantai fallback lengkap termasuk admin tenant + log kritikal) | Broadcast realtime via `via()` (`WorkOrderCreated.php:22`). |
| FR-1.6 | `DONE` | `WorkOrderService::assign` (`:113-195`): validasi dept, `assigned_at`+deadline sekali saja, notify akun tertaut (`:189-191`); test `AssignWorkOrderTest:83,133,158,183,203,217` (termasuk 403 tanpa `work-order.assign`) | **Defect terkait (di luar teks FR):** revisi setelah status `assigned` → backend 409 (`WorkOrderService.php:122-124`) padahal panel revisi tampil (`show.tsx:49,249`); tidak ada test revisi pasc-assign. |
| FR-1.7 | `NOT STARTED` | [NONE] — route hanya 10 (`route:list`), tidak ada `submit`; status `PendingVerify`/`Revision` hanya enum (`WorkOrderStatus.php:19-21`) | Issue `.scratch/wms-mvp/issues/04-submit-verify-work-data.md` `ready-for-agent`. |
| FR-1.8 | `NOT STARTED` | [NONE] — tidak ada endpoint verify/approve/reject pasca-submit; tidak ada test | Sama seperti di atas; `work-order.verify` masih di allowlist (`PermissionConsistencyTest.php:113`). |
| FR-1.9 | `PARTIAL` | Event append-only tercatat & diuji: `work_order_events` (`ReviewWorkOrderTest:85-87` action `reviewed`; `AssignWorkOrderTest:108-110` action `assigned`, meta `:151-153`) | **Missing:** tampilan histori di halaman WO (tidak ada relasi/load events di `WorkOrderController::show:134-171`) dan integrasi ke Work Data (FR-3.x belum ada). |

#### FR-2.x — Work Order Terjadwal + Eskalasi Deadline

| ID | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| FR-2.1 | `DONE` | `DeadlineCalculator` (`app/Services/DeadlineCalculator.php:15-45`; urgent 3 / normal 6 hari kerja, `:17-19`), dihitung saat **assign pertama** (`WorkOrderService.php:146-156`), libur `config/holidays.php` (ada); test `DeadlineCalculatorTest` 7 test (:11,20,29,38,48,58,69) + `AssignWorkOrderTest:116` | OQ-2 sudah settle; nilai 3/6 dipakai persis. |
| FR-2.2 | `PARTIAL` | H+3 → TL bila ada, fallback HOD: `CheckDeadlineEscalation.php:146-174`, test `EscalationTest:138` (TL), `:75` (HOD tanpa TL); H+5 → HOD test `:299`; marker anti-kirim-ulang `:79-97` + test `:114`; status on-progress `:31-36` (Assigned/InProgress/PendingVerify/Revision) | **Missing:** H+6 dikirim ke **rantai fallback HOD**, bukan DGM/GM (`:151-152` — `resolveRecipients` tidak membedakan h5/h6); test `EscalationTest:321` justru meng-assert HOD menerima. Role `general_manager`/`deputy_general_manager` (`RoleAndPermissionSeeder.php:39-40`) belum pernah dipakai sebagai penerima eskalasi. |
| FR-2.3 | `NOT STARTED` | [NONE] — `extend_requests` tidak ada di migrasi/manapun; hanya kolom `extend_count` (`2026_10_03_000100.php:29-31`) | Aturan "maks 3 hari / maks 3 approved / blokir pending duplikat" (`AGENTS.md:89-95`) belum ada kodenya. |
| FR-2.4 | `NOT STARTED` | [NONE] — `work_planning` tidak ada; alur extend schedule terpisah tidak ada | — |
| FR-2.5 | `NOT STARTED` | [NONE] — pencatatan approver/timestamp/alasan extend tidak ada (tabelnya saja belum) | — |

#### FR-3.x — Work Data Management

| ID | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| FR-3.1 | `NOT STARTED` | [NONE] — `work_data`/`work_data_pekerja` tidak ada di `database/migrations/` & `app/Models/` | Bergantung FR-1.8 (WO closed). |
| FR-3.2 | `NOT STARTED` | [NONE] — tidak ada controller/query/filter Work Data | — |
| FR-3.3 | `NOT STARTED` | [NONE] | Sub-OQ "alur revisi post-closing" di teks FR **belum tercantum di daftar OQ settled** — masih terbuka saat modul dibangun. |

#### FR-4.x — Lintas Department

| ID | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| FR-4.1 | `PARTIAL` | Wajib pilih `target_department_id` (validasi ada-hanya dalam tenant): `WorkOrderController.php:66-69`; dropdown semua department `create.tsx:109` + `WorkOrderController::create:55` | **Missing kecil:** filter **department aktif** tidak diterapkan — `create():55` mengambil semua department tanpa `where('is_active', true)` (kolom `is_active` ada, migrasi `2026_10_19_000001`; scope global pun tidak ada di `app/Models/Department.php`). |
| FR-4.2 | `DONE` | Satu alur untuk semua: `WorkOrderService` berbasis `target_department_id`; routing notifikasi ke HOD dept tujuan (`HodNotificationResolver`); test lintas-cabang `WorkOrderTest:191,212,311,336` | Tidak ada cabang alur khusus lintas department. |

#### FR-5.x — Daily Work Management

| ID | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| FR-5.1 | `NOT STARTED` | [NONE] — `work_daily`/`schedule_wd` tidak ada | OQ-4 settle (template per karyawan, `AGENTS.md:110-114`) — keputusan siap, kode belum. |
| FR-5.2 | `NOT STARTED` | [NONE] | — |
| FR-5.3 | `NOT STARTED` | [NONE] | — |
| FR-5.4 | `NOT STARTED` | [NONE] | Checklist status+lokasi ada di issue 07 (belum dikerjakan). |

#### FR-6.x — RBAC (Spatie)

| ID | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| FR-6.1 | `DONE` | 9 role kanonik + `guest` = 10: `RoleAndPermissionSeeder.php:36-50`; test `RoleAndPermissionSeederTest:29` ("creates exactly the ten canonical system roles"); Requester bukan role (create WO tanpa permission khusus — `WorkOrderController::store` tanpa `Gate::authorize`, test `WorkOrderTest:32`); Direksi tidak dibuat [NONE]; superadmin bypass `AppServiceProvider.php:91-93` + ADR-0004 §5/§7, ADR-0008 | OQ-1 & OQ-11 SETTLED + terimplementasi. |
| FR-6.2 | `PARTIAL` | Katalog permission per-resource verb map + konsistensi dua arah: `PermissionConsistencyTest.php:11-39,47-53`; UI konfigurasi tanpa deploy: `/hak-akses` (`routes/hak-akses.php:10-16`) + `/settings/permissions` (`routes/settings.php:31-35`), sync per role `PUT roles/{role}/permissions`; per-release grant `app:grant-default-permissions` (TODO:245) | **Missing:** gating per permission belum lengkap — `work-order.create/read/update/delete/submit/verify` masih di **forward-looking allowlist** ("gated when the WO flow lands", `PermissionConsistencyTest.php:104-113`); master data (`division/department/tenant.*`) juga (`:115-131`). Review+assign sudah digate (`WorkOrderController.php:102,119`). |
| FR-6.3 | `PARTIAL` | 403 saat tanpa izin: `Gate::authorize` di `WorkOrderController.php:102,119`; test `assertForbidden` `ReviewWorkOrderTest:186-194`, `AssignWorkOrderTest:217` | **Missing:** "tercatat di log" — pencarian `AccessDenied|403.*log|forbidden` di `app/` = **tidak ada** [NONE]; tidak ada middleware/logging khusus percobaan akses ditolak. |

#### FR-7.x — Realtime Notification

| ID | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| FR-7.1 | `PARTIAL` | Event tersedia: **WO baru** (`WorkOrderCreated`, test `WorkOrderTest:32`), **di-assign** (`WorkOrderAssigned`, test `AssignWorkOrderTest:133`), **review ke requester** (`WorkOrderReviewed`, test `ReviewWorkOrderTest:200`), **WO telat 3/5/6** (`WorkOrderDeadlineEscalated`, `EscalationTest:75,299,321` + outbox WA per penerima (TODO:47-48)) | **Missing:** "hasil kerja disubmit", "hasil kerja direvisi", "pengajuan extend", "approval/reject extend", "Daily Work baru" — modulnya belum ada [NONE]. |
| FR-7.2 | `DONE` | In-app minimal: bell (`app-header.tsx:241`), toast (`use-flash-toast.ts:3` + `Toaster` `app.tsx:3`), history page `notifications/index` (route `web.php:39-43`); broadcast Reverb (`echo.js:10-18`, channel `routes/channels.php:5-7`); **WA gateway** 4 event (ADR-0009 accepted; `toWhatsApp`+`waEvent` di 4 notifikasi) | **PRD stale:** teks "channel tambahan (WA/email) — Open Question, lihat §3" sudah settle (OQ-7: in-app only) **lalu di-amend**: WA masuk scope & shipped. Email belum ada. |
| FR-7.3 | `DONE` | Tersimpan di DB (migrasi `2026_10_03_000000.php:14-21`), tandai dibaca `NotificationController.php:24-41` (per-item + read-all), riwayat terpaginasi `:12-22`; test `NotificationTest:37` (isolation antar user), `:57` (mark all), `:72` (unread count shared), `:88` (urut terbaru) | — |

### 2.5 §7 Sketsa Data Model — keberadaan tabel (mapping nama PRD `tb_*`/`fld_*` → Laravel)

| Entitas PRD | Status | Bukti (migrasi) | Catatan |
| --- | --- | --- | --- |
| `tb_user` → `users` | `DONE` | `0001_01_01_000200_create_users_table.php` (+ `employee_id` unik `2026_10_20_155833`) | `fld_hak_akses` jadi tabel Spatie (`2026_10_02_000000_create_permission_tables.php`). |
| `tb_karyawan` → `employees` | `DONE` | `2026_10_02_000000_create_organization_tables.php:58-74` | Tanpa FK department di ERD lama → kini `department_id` FK (sync Optigate, `SyncEmployees`); `call_sign`/`user_image` ERD tidak dipakai — kolom aktual: `nik_employee`,`nama_employee`,`email`,`number`,`photo_url`. |
| `tb_divisi` → `divisions` | `DONE` | idem `:19-30` | Arah relasi disesuaikan portal (ADR-0007). |
| `tb_department` → `departments` | `DONE` | idem `:33-44` (+`hod_user_id`,`manager_user_id`,`hod_employee_id` `2026_10_19_000006`) | `fld_id_hod` → `hod_user_id`/`hod_employee_id`. |
| `tb_tenant` → `tenants` | `DONE` | `0001_01_01_000100_create_tenants_table.php:22-28` | **Residual OQ-10 `fld_logo`: MOOT** — kolom `logo` tidak ada dan entity ini = Unit Bisnis synced, bukan penyewa tempat v2. |
| `tb_work_order` → `work_orders` | `DONE` | `2026_10_03_000100.php:12-36` (+`2026_10_17_024806` review/assign, `2026_10_17_035140` assigned_employees) | Field eskalasi/extend sesuai catatan PRD §7 (`deadline_date`…`extended_at`, :24-31). Tanpa `prioritas`/`jumlah personel` (lihat FR-1.1). |
| `tb_work_planning` → `work_planning` | `NOT STARTED` | [NONE] | — |
| `tb_work_daily` → `work_daily` | `NOT STARTED` | [NONE] | **Residual OQ-10 (FK `fld_id_work_daily`): MOOT** — tabelnya belum dibuat; saat dibuat akan pakai konvensi Laravel (FK eksplisit) sesuai keputusan Round 1 (`PRD.md:186`). |
| `tb_work_data` → `work_data` | `NOT STARTED` | [NONE] | — |
| `tb_work_data_pekerja` → `work_data_pekerja` | `NOT STARTED` | [NONE] | Dijanjikan di `2026_10_17_035140.php:10-11`. |
| `tb_schedule_wd` → `schedule_wd` | `NOT STARTED` | [NONE] | — |
| Extend Request → `extend_requests` (SETTLED OQ-9) | `NOT STARTED` | [NONE] — tidak ada di `migrate:status` | Keputusan ada (`AGENTS.md:182`), tabel belum. |
| Notification → **`app_notifications` (PRD) = `notifications` (aktual)** | `DONE` | `2026_10_03_000000_create_notifications_table.php:14-21` | **Koreksi nama:** praktek pakai tabel bawaan Laravel `notifications` (bukan `app_notifications` seperti opti-work2) — PRD §7 catatan perlu dianotasikan. |
| (baru, tidak ada di PRD) `work_order_events` | `DONE` | `2026_10_17_030526.php:16-29` | Histori append-only per WO (FR-1.9). |
| (baru) `audit_logs` | `DONE` | `2026_10_21_000004.php:27-45` + `AuditLogger` + 7 test `AuditTrailTest` | Cakupan awal: aksi akun/hak-akses/ban; UI reader belum. |
| (baru) `wa_messages`, `tenant_wa_sessions` | `DONE` | `2026_10_21_000001`, `2026_10_21_000002`, `2026_10_21_000003` | Outbox + sesi WA per tenant (ADR-0009). |

### 2.6 §8 Edge Case & Failure State (9 bullet)

| # (PRD §8) | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| 1. WO Urgent by Accident tapi dept tujuan tanpa HOD aktif | `DONE` | OQ-6 SETTLED (`AGENTS.md:97-101`); `HodNotificationResolver.php:20-46` (hod → manager → role hod → admin tenant + `Log::critical`); test `WorkOrderTest:123,146,169`; fallback eskalasi `CheckDeadlineEscalation.php:181-224` + test `EscalationTest:178,219,258` | Siapa yang **menerima & assign** sudah terjawab (penerima notifikasi); assign tetap butuh hak `work-order.assign`. |
| 2. Karyawan resign/nonaktif (di-ban) sebelum WO selesai → re-assign | `NOT STARTED` | Ban hanya memblokir login: `EnsureEmployeeNotBanned` (TODO:7); tidak ada mekanisme re-assign/override WO aktif [NONE]; ditambah defect revisi pasc-assign (FR-1.6) | Masih terbuka. |
| 3. Extend >1x untuk WO sama | `SETTLED` *(implementasi: `NOT STARTED`)* | OQ-3 SETTLED: maks 3 hari/request, maks 3 approved, blokir pending duplikat (`AGENTS.md:89-95`, `PRD.md:271`) | Kode extend belum ada (FR-2.3). |
| 4. WO direject HOD berkali-kali tanpa batas/SLA | `NOT STARTED` | Alur submit/verify belum ada [NONE]; keputusan pun belum ada (tidak masuk daftar OQ settled) | Masih terbuka — jangan diambil keputusan sendiri (aturan OQ `AGENTS.md:164-170`). |
| 5. Dua requester bersamaan, urutan pengerjaan (asumsi FIFO) | `NOT STARTED` | Tidak ada field prioritas/antrean; index hanya `orderByDesc('created_at')` (`WorkOrderController.php:43`) | Kebijakan FIFO belum didefinisikan/diimplementasikan. |
| 6. Department tanpa Team Leader | `DONE` | Deteksi TL = ada user role `team_leader` di dept: `CheckDeadlineEscalation.php:158-174` (`whereHasRoles('team_leader')`); OQ-5 SETTLED (`AGENTS.md:178`); test `EscalationTest:138` (dengan TL) & `:75` (tanpa TL → HOD) | — |
| 7. Notifikasi realtime gagal / user offline | `DONE` | Channel `database` selalu ikut (`WorkOrderCreated.php:22`); history + unread count (`NotificationController.php:12-41`, `HandleInertiaRequests.php:132`); test `NotificationTest:72,88`; "muncul saat login kembali" via halaman notifikasi | Sesuai OQ-7 (in-app + DB). |
| 8. Approval Extend Work Schedule menunggu DGM/GM cuti — delegasi approver | `NOT STARTED` | Tidak ada mekanisme delegasi [NONE]; modul extend schedule pun belum ada | Masih terbuka (dan terkait gap penerima H+6). |
| 9. Upload gambar bukti gagal / terlalu besar | `DONE` | Validasi `WorkOrderController.php:78-79`: maks 3 file, `mimes:jpg,jpeg,png,webp`, `max:2048` (2MB); pesan error per-file; test `WorkOrderTest:62` (tersimpan) — invalid ditolak lewat rule (pengujian format eksplisit tidak ditemukan → batas jelas di kode) | Batas format/ukuran kini terspesifikasi (PRD minta "belum dispesifikasikan" → sudah ada). |

### 2.7 §9 Success Metrics

| ID | Status | Bukti | Catatan |
| --- | --- | --- | --- |
| §9 — 6 metrik usulan + kriteria "selesai" kualitatif | `OUT OF SCOPE` | Belum ada instrumentasi metrik [NONE]; dashboard = data hardcoded (`resources/js/pages/dashboard.tsx:325` `priorityTickets`, `:393` `auditEntries`) diroute tanpa props (`routes/web.php:132` `Route::inertia('dashboard','dashboard')`) | PRD sendiri menandai metrik sebagai "usulan… perlu dikonfirmasi" (`PRD.md:256`). Kriteria "selesai" sudah dipisah jadi OQ-8 (lihat §10). |

### 2.8 §10 Open Questions 1–11 (semua `SETTLED` per AGENTS.md:172-184)

| OQ | Status | Settled di | Implementasi |
| --- | --- | --- | --- |
| 1. Requester role terpisah? | `SETTLED` | `AGENTS.md:174` (Round 1); `PRD.md:269` | **Terimplementasi** — create WO tanpa gate khusus (`WorkOrderController::store:60`), test `WorkOrderTest:32`. |
| 2. Nilai deadline 3–6 hari | `SETTLED` | `AGENTS.md:175`; `PRD.md:270` | **Terimplementasi** — `DeadlineCalculator.php:17-19` + `config/holidays.php` + `DeadlineCalculatorTest` 7 test. |
| 3. Batas jumlah extend | `SETTLED` | `AGENTS.md:176`; `PRD.md:271` | **Belum** — alur extend tidak ada (FR-2.3). |
| 4. Mekanisme template Daily Work | `SETTLED` (opsi A) | `AGENTS.md:110-114,177`; `PRD.md:272` | **Belum** — modul Daily Work tidak ada. |
| 5. Team Leader = role resmi? | `SETTLED` | `AGENTS.md:178`; `PRD.md:273` | **Terimplementasi** — role `team_leader` (`RoleAndPermissionSeeder.php:42`) + deteksi per-dept (`CheckDeadlineEscalation.php:161-166`) + test `EscalationTest:138`. |
| 6. HOD tidak aktif saat WO masuk | `SETTLED` | `AGENTS.md:97-101,179`; `PRD.md:274` | **Terimplementasi** — `HodNotificationResolver` + test `WorkOrderTest:123,146,169`. |
| 7. Channel notifikasi: in-app vs WA/email | `SETTLED` *(di-amend ADR-0009)* | `AGENTS.md:180,103-108`; `PRD.md:275` | **Terimplementasi** in-app (FR-7.x); **amendmen:** WA gateway kini in-scope & shipped (ADR-0009 `accepted`, TODO WhatsApp sections). **AGENTS.md & PRD belum mencerminkan amendmen** → lihat §4 (PRD stale items). Email tetap ditunda. |
| 8. Definisi "selesai" objektif | `SETTLED` (UAT kritikal + suite hijau + pilot 1 dept) | `AGENTS.md:154-160`; `PRD.md:276` | **`PARTIAL`** — suite hijau ✓ (492, `TODO.md:5`); **artefak 13 skenario UAT tidak ditemukan di repo** [NONE] (pencarian `UAT`); pilot live belum. |
| 9. Extend Request = tabel terpisah? | `SETTLED` | `AGENTS.md:182`; `PRD.md:277` | **Belum** — `extend_requests` tidak ada di migrasi. |
| 10. Verifikasi field ERD (?) | `SETTLED` | `AGENTS.md:183`; `PRD.md:278` | **Residual MOOT saat ini:** FK `tb_work_daily` (tabel belum ada) & `fld_logo` `tb_tenant` (kolom tidak ada di `tenants` — `0001_01_01_000100:22-28`). Koreksi `call_sign`/`business_plan`/tanpa FK dept tercatat di `PRD.md:186,194,210`. |
| 11. Super Admin vs Direksi | `SETTLED` | `AGENTS.md:184`; `PRD.md:279` | **Terimplementasi** — superadmin = pengelola konfigurasi (bypass `AppServiceProvider.php:91-93`, ADR-0004 §7, ADR-0008); role Direksi tidak dibuat [NONE]; grant/revoke role superadmin-only (ADR-0008). |

---

## 3. Peta PRD ↔ TODO.md

Kolom "Heading TODO" memuat **teks heading persis** dari `TODO.md` (siap dijadikan anchor/tautan dari PRD). Sub-entri diberi `→` bila berada di bawah heading tersebut.

| Bagian / FR group PRD | Heading `TODO.md` yang cocok | Keterangan |
| --- | --- | --- |
| §3 Non-Goals — amendmen WA gateway | `## WhatsApp Gateway slice 1 — outbound (2026-10-21) — DONE` dan `## WhatsApp Gateway slice 2 — sesi per tenant (2026-10-21) — DONE` | Termasuk klaim amendmen AGENTS.md (`TODO.md:52-55`) — **di disk belum terlihat, lihat §4 baris AGENTS.md** |
| §3 Goal 5 / §5 RBAC / FR-6.1 | `## Next session — 2026-10-14 (RBAC v2: hak akses slices 6–8 frontend)` → `### Done today` (slices 6–8, Ticket 01–03) dan `## Guest role + akun platform (2026-10-17) — spec .scratch/guest-role/, commits 7e5fe00 + 0a34c48` | Role baseline + assignment + guest |
| §3 Goal 5 / FR-6.2 (grant per-release) | `## Next session — 2026-10-14 (RBAC v2: hak akses slices 6–8 frontend)` → `app:grant-default-permissions` (Commit `5071fec`) | Katalog vs role existing |
| FR-6.3 (honesty test) | `## Next session — 2026-10-14 (RBAC v2: hak akses slices 6–8 frontend)` → `PermissionConsistencyTest` entries | Allowlist forward-looking |
| §1 audit trail (akun/hak-akses) | `## Rencana hari ini (2026-10-21, diperbarui pasca-grill) - DONE (2026-10-21 21:13)` → item "Audit trail durable" | `audit_logs` + 7 test; UI reader = lanjutan |
| §5 Work Order Create/Review/Assign, FR-1.1 s.d. FR-1.6, FR-1.9, FR-2.1, FR-4.x | `## Next session — 2026-10-14 (RBAC v2: hak akses slices 6–8 frontend)` → `### Done today` → bullet "**WO slice 1 selesai 2026-10-17** (issue 03 wms-mvp = review → assign + deadline + histori)" | 4 commit `6e47cf3`/`ea6779d`/`937a474`/`553c775` |
| §5 Lintas Department + §7 departments/HOD | `## Department/HOD sync relations (2026-10-17) — DONE, commit b0be63f` + `## Paritas sync Department+HOD vs opti-work2 (2026-10-20) — DONE` + `## Frontend HOD department (2026-10-20) — DONE` | Sync + fallback HOD data |
| FR-2.2 eskalasi H+3/5/6 | **belum ada entri TODO** (khusus) | Jejak tersebar: foundation `DeadlineCalculator` di entri WO slice 1; `EscalationTest` disebut sebagai "WIP sesi paralel" di `## Paritas sync Department+HOD vs opti-work2 (2026-10-20) — DONE` (`TODO.md:161`); test eskalasi→outbox di `## WhatsApp Gateway slice 1 — outbound (2026-10-21) — DONE`. **Rekomendasi: buat entri TODO tersendiri untuk eskalasi + sebut gap H+6.** |
| §5 Realtime Notification / FR-7.1–7.3 | **belum ada entri TODO** tersendiri | Sebaran: notifikasi DB+bell di entri WO slice 1, broadcast defect di `## Rencana hari ini …`, WA di dua section WhatsApp. Listener WS frontend tidak tercatat di TODO mana pun. |
| §5/§3 WA notification (baru) | `## WhatsApp Gateway slice 1 — outbound (2026-10-21) — DONE`, `## WhatsApp Gateway slice 2 — sesi per tenant (2026-10-21) — DONE` | 4 event outbound + sesi per tenant + halaman settings |
| §5 Management Tenant / Unit Bisnis | `## Unit Bisnis page revamp + URL move (2026-10-19) — DONE` + `## Sprint A — commit slicing + review fixes (2026-10-19) — DONE, commits 47014af…8b80440` + `## Completed` | Read+sync, bukan CRUD |
| §5/§7 sync Optigate (tenants/departments/divisions/employees/positions) | `## Optigate sync — DONE 2026-10-10 (Phase 4 completion)` + `## Department/HOD sync relations (2026-10-17) — DONE, commit b0be63f` + `## Paritas sync … (2026-10-20) — DONE` | — |
| §8 #1 fallback HOD (data) | `## Frontend HOD department (2026-10-20) — DONE` + `## Paritas sync … (2026-10-20) — DONE` (gap `manager_user_id`) | Resolusi notifikasi sendiri tercakup WO slice 1 |
| §10 OQ (semua) | `## Next session — 2026-10-14 (RBAC v2: hak akses slices 6–8 frontend)` → catatan `TODO.md:306`: "item 'Resolve PRD Open Questions' dihapus — sudah SETTLED di AGENTS.md" + `## Rencana hari ini (2026-10-21, diperbarui pasca-grill) - DONE (2026-10-21 21:13)` (grill Q7/Q4/Q14 — **nomor Q grill ≠ nomor OQ PRD**) | — |
| FR-1.1 (kelengkapan field: prioritas, jumlah personel), FR-1.7, FR-1.8, FR-1.9 (UI histori), FR-2.3–2.5, FR-3.x, FR-5.x, §5 Work Data, Daily Work, Extend deadline/schedule, WO Terjadwal (planning), Update WO | **belum ada entri TODO** | Issue terkait sudah ada tapi belum dikerjakan: `.scratch/wms-mvp/issues/04-submit-verify-work-data.md`, `06-extend-work-order-schedule.md`, `07-daily-work-templates.md`, `08-work-data-management.md` (semua status `ready-for-agent`) |
| §9 Success metrics | **belum ada entri TODO** | — |
| Ban karyawan (pendukung §8 #2) | `## Rencana hari ini (2026-10-21, diperbarui pasca-grill) - DONE (2026-10-21 21:13)` → "Q7 - semantik ban/login" | Blokir login, bukan re-assign WO |
| Filter/search karyawan (pendukung master data) | `## Rencana hari ini (2026-10-21, diperbarui pasca-grill) - DONE (2026-10-21 21:13)` → "Employee page filters/search" | — |
| Queue/infra (pendukung realtime) | `## DONE — 2026-10-10: Queue Tenant Awareness Fix + Full Suite Green` + "Defect broadcast" di `## Rencana hari ini …` | Broadcast job tenant-aware |

Catatan pemakaian: 4 entri `TODO.md` yang masih berupa pekerjaan rumah/kadaluarsa dan jangan ditautkan sebagai bukti selesai: `### Work plan — next session (role assignment: tickets 02–03)` (sudah dilakukan, tapi teksnya rencana), `### Deferred` (`TODO.md:300-305` — termasuk "Start the core WO flow" yang sebagian sudah lewat), `### Deferred (do not start yet)` (`TODO.md:314-320` — masih menyebut "External notifications (WA/email gateway)" padahal WA sudah shipped → inkonsistensi internal TODO), dan `## Files Modified` (`TODO.md:402`, daftar basi era 2026-10-10).

---

## 4. PRD Stale Items (teks PRD yang bertentangan dengan kenyataan saat ini)

| # | Lokasi PRD | Teks saat ini | Kenyataan / koreksi yang diusulkan |
| --- | --- | --- | --- |
| 1 | `PRD.md:3` (header) | "Versi 1.0 **Draft**… Status: Draft untuk review — beberapa item… BELUM boleh dianggap final" | Semua OQ 1–11 sudah SETTLED (`AGENTS.md:172-184`). Usulan: ubah status jadi "Draft — OQ settled (lihat §10); audit implementasi per 2026-10-21 di `.scratch/prd-audit/research/prd-implementation-status.md`". |
| 2 | `PRD.md:56` (§3 Non-Goals) | "Integrasi eksternal (**WhatsApp/email gateway**, sistem absensi…) — tidak ada di scope MVP…" | **Stale untuk WA.** ADR-0009 `accepted` + WA slice 1–2 shipped (2026-10-21). Usulan: "email gateway & sistem absensi tetap di luar scope MVP; **WhatsApp gateway masuk scope (lihat ADR-0009) dan sudah diimplementasikan slice 1–2**". |
| 3 | `PRD.md:121` (§5 baris) | "Integrasi WA/email notification \| **Nanti** (belum dikonfirmasi…)" | Sama seperti di atas: kategori "Nanti" tidak lagi berlaku untuk WA → ubah jadi "WA: **MVP (DONE)**; email: Nanti". |
| 4 | `PRD.md:179` (FR-7.2) | "channel tambahan (WA/email) — **Open Question**, lihat §3 Non-Goals" | OQ-7 sudah settle (in-app only) **dan** di-amend: WA kini channel aktif (`via()` = database+broadcast+WA). Usulan: hapus rujukan OQ, catat WA = 4 event outbound per ADR-0009, email = masih ditunda. |
| 5 | `PRD.md:143` (FR-2.1) | "Minimal 3 hari kerja, maksimal 6 hari kerja (**nilai pasti… lihat Open Question §10**)" | OQ-2 settle: urgent 3 / normal 6 + `config/holidays.php`. Hapus rujukan OQ; tegaskan "dihitung dari tanggal assign" (sudah sesuai kode). |
| 6 | `PRD.md:165` (FR-5.1) | "mekanisme pasti template — **Open Question**" | OQ-4 settle (template per karyawan, dikelola HOD — `AGENTS.md:110-114`). Hapus rujukan OQ. |
| 7 | `PRD.md:242,244,247` (§8 bullet 1, 3, 6) | "Belum didefinisikan — Open Question" / "Open Question" | Sudah settle: OQ-6 (fallback HOD), OQ-3 (batas extend), OQ-5 (TL = role Spatie). Ganti label jadi "SETTLED — lihat §10". (Bullet 4, 5, 8 di §8 **masih benar** sebagai hal terbuka — tidak masuk daftar settled.) |
| 8 | `PRD.md:234` (§7 catatan) | Entitas Notification "sudah terbukti… sebagai tabel `app_notifications`" | Tabel aktual = **`notifications`** (migrasi `2026_10_03_000000`). Tambahkan anotasi nama. |
| 9 | `PRD.md:131` (FR-1.1) vs §6.1 judul "Create/**Update**/Review/Assign" | FR hanya memuat create, judul fitur menyebut Update | Update memang belum diimplementasikan (route ada, method tidak) — koreksi menunggu implementasi, atau catat sebagai bagian yang tertunda. |
| 10 | `AGENTS.md:57,108,180` (**bukan PRD, tapi wajib diperbaiki**) | Non-Goals masih "WA/email gateway → Nanti"; Notifikasi "WA/email gateway → fase berikutnya"; OQ-7 tanpa pengecualian | `TODO.md:52-55` mengklaim "AGENTS.md 4 titik amendmen (Non-Goals email-only, Modul MVP + row WA, bagian Notifikasi, OQ settled #7 DIKECUALIKAN)" tetapi **file di disk tidak memuat amendmen itu** (dibaca 2026-10-21; `AGENTS.md` di-gitignore `.gitignore:44`, jadi tidak bisa dicek via git). Sumber kebenaran de-facto = ADR-0009 + TODO. **Tindakan: amend ulang AGENTS.md atau koreksi klaim TODO.** |
| 11 | `PRD.md:5`/§10/AGENTS kriteria selesai (bukan kontradiksi, catatan) | OQ-8: UAT 13 skenario + suite + pilot | Artefak UAT tidak ada di repo; suite hijau ✓; pilot belum — jangan ditandai selesai penuh. |

---

## 5. Temuan teknis tambahan (di luar PRD, untuk tindak lanjut)

1. **Route tanpa method (latent 500):** `work-orders.edit/update/destroy` terdaftar (`route:list`) tapi `WorkOrderController` tidak mengimplementasikannya (`WorkOrderController.php` method terakhir `attachment()` :183-197). Perbaiki dengan menambah method atau membatasi `Route::resource` (`->only([...])`).
2. **Revisi assign pasc-assign 409 vs UI:** `show.tsx:49` (`ASSIGNABLE` termasuk `'assigned'`) vs `WorkOrderService.php:122-124`. Perlu keputusan: izinkan re-assign dari status `assigned` (menjaga `assigned_at`/deadline — logika SLA sudah diuji `AssignWorkOrderTest:158`) atau sembunyikan panel.
3. **Penerima H+6:** samakan dengan aturan bisnis (resolve role `general_manager`/`deputy_general_manager` dulu sebelum fallback HOD) di `CheckDeadlineEscalation::resolveRecipients`.
4. **Listener realtime frontend:** `window.Echo` tersambung tetapi tidak ada `.notification()` handler — badge/count hanya segar setiap kunjungan Inertia.
5. **Status `.scratch/wms-mvp/issues` basi:** issue 02 (WO create) berstatus `ready-for-agent` padahal create sudah shipped (WorkOrderTest); issue 03 `in-progress` padahal slice 1 selesai. Sinkronkan saat memakai issue tracker.
6. **FR-6.3 "akses ditolak tercatat di log"** belum ada implementasinya — putuskan bentuknya (log Laravel event `Gate::denied`? middleware?) sebelum menandai FR-6.3 selesai.

---

## 6. Sumber

**Dokumen spesifikasi/keputusan:**
- `PRD.md` (dibaca penuh, 292 baris)
- `TODO.md` (dibaca penuh, 422 baris)
- `AGENTS.md` (Goals/Non-Goals :42-58, Aturan Bisnis Kritis :62-114, Modul MVP :118-128, Success Criteria :156-160, Daftar OQ settled :172-184; seluruh file dicek untuk amendmen WA)
- `docs/adr/0001-tenant-scoping.md` (superseded), `0002-tenancy-stancl-single-database.md`, `0003-light-theme-only-for-mvp.md`, `0004-hak-akses-surface.md` (accepted), `0005-user-role-assignment.md` (accepted), `0006-guest-role-and-platform-account.md` (accepted), `0007-department-division-direction.md` (accepted), `0008-superadmin-only-role-assignment.md` (accepted), `0009-whatsapp-gateway.md` (**accepted**, slice 1–2), `0010-satu-home-tenant-per-akun.md` (accepted)
- `docs/opti-work2-feature-map.md` (Lampiran B — ada)
- `.scratch/wms-mvp/issues/*.md` (status 14 issue), `.scratch/wo-slice-1/spec.md`, `.scratch/wa-gateway/spec.md`

**Kode (utama):**
- Routes: `routes/web.php`, `routes/hak-akses.php`, `routes/settings.php`, `routes/channels.php`, `bootstrap/app.php` (scheduler)
- Controllers: `WorkOrderController.php`, `NotificationController.php`, `RoleController.php`, `Settings/WhatsAppController.php`, `MasterData/{Employee,Department,Division,Position}Controller.php`, `TenantController.php`
- Requests: `WorkOrder/ReviewWorkOrderRequest.php`, `WorkOrder/AssignWorkOrderRequest.php`, `Employee/*`, `Role/*`
- Services/Commands: `WorkOrderService.php`, `DeadlineCalculator.php`, `HodNotificationResolver.php`, `AuditLogger.php`, `Console/Commands/CheckDeadlineEscalation.php`, `WahaSyncSessions.php`, `WahaPing.php`, sync commands
- Models/Enums: `WorkOrder.php`, `WorkOrderEvent.php`, `Department.php`, `WorkOrderStatus.php`, `WorkOrderCategory.php`, `AuditLog.php`, `WaMessage.php`, `TenantWaSession.php`
- Notifications: `WorkOrderCreated.php`, `WorkOrderReviewed.php`, `WorkOrderAssigned.php`, `WorkOrderDeadlineEscalated.php`
- Middleware/Providers: `HandleInertiaRequests.php`, `EnsureEmployeeNotBanned.php`, `AppServiceProvider.php` (Gate::before)
- Migrasi: `2026_10_02_000000_create_organization_tables.php`, `2026_10_03_000000_create_notifications_table.php`, `2026_10_03_000100_create_work_orders_table.php`, `2026_10_17_024806/030526/035140`, `2026_10_21_000001–000004`, `0001_01_01_000100/000200`
- Seeder: `RoleAndPermissionSeeder.php`, `PermissionsSeeder.php`, `RolesSeeder.php`
- Frontend: `resources/js/pages/work-orders/{create,show,index}.tsx`, `pages/notifications/index.tsx`, `pages/dashboard.tsx`, `components/app-header.tsx`, `echo.js`, `app.tsx`, `hooks/use-flash-toast.ts`
- Tests: `Feature/WorkOrderTest.php`, `Feature/WorkOrder/{ReviewWorkOrderTest,AssignWorkOrderTest,DeadlineCalculatorTest}.php`, `Feature/EscalationTest.php`, `Feature/NotificationTest.php`, `Feature/AuditTrailTest.php`, `Feature/Rbac/{GateTest,PermissionConsistencyTest,RoleControllerTest,PermissionControllerTest,SyncAkunRolesTest}.php`, `Feature/RoleAndPermissionSeederTest.php`, `Feature/{WahaGatewayTest,WahaTenantSessionTest}.php`, `Unit/WahaClientTest.php`

**Perintah read-only:** `php artisan route:list --path=work-orders`, `php artisan migrate:status`.

**Tidak dijalankan:** suite test (sesuai batasan tugas); status "492 tests — 484 passed, 8 skipped" dikutip dari `TODO.md:5`.
