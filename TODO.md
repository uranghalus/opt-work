# TODO - Spatie Multitenancy v4 Migration Progress

## Alur inti WO — submit/verify/riwayat + eskalasi + audit (2026-10-22) — DONE

- [x] **Resource route only()** (`routes/web.php`): `work-orders` dibatasi
      `index/create/store/show`; route baru submit/verify/reject + attachment
      `{type?}` (pilihan result vs submission); test rute di `WorkOrderTest`.
- [x] **Eskalasi H+6** (`CheckDeadlineEscalation`): penerima H+6 = semua user
      tenant ber-role `general_manager`/`deputy_general_manager` (inklusif),
      kosong → fallback rantai HOD; teks notifikasi Indonesia; **bugfix
      `whereHasRoles`** — method tidak ada di spatie, jatuh ke dynamic-where
      Eloquent dengan kolom fiktif `has_roles` (diam-diam 0 baris di sqlite,
      QueryException di MySQL) → diganti `whereHas('roles', ...)` di 3 titik
      (GM/DGM, team_leader, hod); test TL yang misnamed di-upgrade jadi benar.
- [x] **FR-1.7 Submit hasil** (`WorkOrderService::submit` — guard 409
      assigned/revision, event `submitted`, notify rantai HOD + WA
      `WahaWhatsAppChannel`): migration `result_notes` + `result_attachments`,
      panel Submit di show.tsx, gate `work-order.submit`.
- [x] **FR-1.8 Verify/Reject** (`approve()`/`reject()` +
      `verifyResultRecipients()` = pelaksana + requester): notifikasi
      Verified/Rejected (catatan revisi di `hod_notes` + meta event, deadline
      tidak di-reset), panel Verify, gate `work-order.verify`.
- [x] **FR-1.9 Riwayat** (`WorkOrder::events()`, show() load events
      newest-first + `user_name`, section Riwayat + Hasil Pekerjaan, export
      `statusLabel` di status-badge.tsx, attachment route `{type?}`).
- [x] **FR-7 realtime** (`realtime-notifications.tsx`): Echo private
      `App.Models.User.{id}`, toast + reload badge notifikasi, cleanup
      `channel.leave()`; dipasang di `app-layout.tsx`.
- [x] **FR-6.3 audit access.denied** (`AccessDeniedAuditor` + render hook di
      `bootstrap/app.php`): setiap 403 tercatat di `audit_events` dengan
      subject synthetic `access` + izin/route yang ditolak. Deviasi dari
      rencana (event `Gate::evaluate`): render hook — probe `can()` bendera
      nav tidak membanjiri audit. NB: `AuthorizationException` dikonversi
      `AccessDeniedHttpException` oleh `Handler::prepareException` SEBELUM
      render callbacks dijalankan — hook mengetik `HttpException` + filter
      status 403; exception asli via `getPrevious()`.
- [x] **FR-4.1 dropdown departemen aktif** (`WorkOrderController::create()`
      filter `where('is_active', true)`).
- [x] **Verifikasi**: suite 536 test (528 passed, 8 skipped, 0 failed);
      pint bersih; tsc bersih untuk seluruh file sesi ini (sisa error tsc =
      WIP sesi paralel di `employees/*`); commit per-topik `a776180..40714c8`.

## Redesign halaman Department — "Registry" (2026-10-22) — DONE

- [x] **Diagnosis + arah "Registry"** — critique `.scratch/department-page/research/`
      (light+dark+390px, ukur DOM): flatness bukan selera, tapi cacat token
      (canvas↔card 1.06:1, 0 elevasi, ink fraction tabel 45%, void 221px,
      detail 49% lebar). Arah: pita pengukuran gelap + tabel dua tingkat +
      meter headcount + chip exception + detail split penuh.
- [x] **Token layer** (`resources/css/app.css`): canvas `#EEF1F4`, accent
      `#E6EBEF`, `border-strong` `#8894A2` (3:1 di putih), `shadow-card` /
      `shadow-raised` dua lapis, `warning-ink` (6.4:1) + `warning-bright` untuk
      pita gelap; mirror dark theme.
- [x] **`RegistryBand`** (`resources/js/components/registry-band.tsx`):
      satu pita `panel` 3 sel, numeral tabular 2rem (1.5rem mobile), ikon mint,
      rail 2px `brand-strong`, tone amber untuk exception; dipakai index+show.
- [x] **Index** (`resources/js/pages/departments/index.tsx`): band; tabel
      4 kolom di `xl` (Department/HOD/Divisi/Karyawan) dengan `colgroup`
      `20rem/15rem/auto/8rem`; grid kartu 2 kolom di `lg`, 1 kolom di bawah;
      meter headcount (max halaman); chip "Tanpa HOD" `warning-ink`; timestamp
      server-side; paginasi hanya bila >1 halaman; focus ring di nama, angka,
      dan kartu.
- [x] **Detail** (`resources/js/pages/departments/show.tsx`): full width,
      split 5/7; kartu HOD + deputy/manager + link karyawan; Divisi di kiri,
      Posisi di kanan; kedua relation list link ke `employees` terfilter;
      deskripsi layout nyata (kode · N divisi · N karyawan · status HOD);
      focus ring di semua baris.
- [x] **Backend** (`app/Http/Controllers/MasterData/DepartmentController.php`):
      `stats` (departments/employees/without_hod/max_employees), eager-load
      `divisions` di index, `positions.division` di show, `per_page` 50,
      `last_synced_label` diformat server (hindari hydration mismatch).
- [x] **DESIGN.md**: elevation levels 0–3, Registry Band/Table, Metric type,
      master-data layout rules, Two-Strata exception, Registry/Meter rules,
      Do/Don't; section Timeline sesi paralel dipertahankan.
- [x] **Verifikasi**: `tests/Feature/DepartmentPageTest.php` 10 test /
      51 assertion; `npm run types:check`, `vendor/bin/pint`, `npm run build`
      hijau. Capture 1440/1280/1100/390 light+dark: band 99px (74px mobile),
      row 62px, tabel 1118px, grid fill 95%, tanpa error browser.
- [x] **Cleanup**: tenant fixture `ui-dept` (7 users, 126 employees,
      37 positions, 18 divisions, 9 departments) dihapus; script seed/probe
      sementara dihapus; dev server sesi ini dihentikan; tenant asli
      (`bm`/`dmb`/`dmp`) tidak disentuh.
- [ ] **Sisa (opsional):** search/sort/filter di index (audit #8); baris
      tabel 56px; ekspos Divisi di kartu tablet bila datanya panjang.

## Audit UI halaman Department + slice #1–#5 (2026-10-22) — DONE

- [x] **Audit UI halaman Department** (index + show), penilaian bebas tanpa
      `DESIGN.md`/`DESIGN_BRIEF.md` sebagai standar. Output:
      **`.scratch/department-page/research/department-page-ui-audit.md`**
      (audit + bukti ukur + tangkapan layar). Metode: render nyata di Chrome
      headless dengan fixture org realistis (9 department, 126 karyawan,
      HOD sebagian terisi), ukur DOM, hitung rasio kontras WCAG.
      **2 critical + 3 high + 7 medium/low**, antara lain: department ke-11
      tidak terjangkau (DMB punya 11 department, `paginate(10)` tanpa UI
      paginasi), 3 dari 6 kolom off-screen di 390px, hydration mismatch di
      setiap load, halaman detail tanpa `<h1>`/breadcrumb/tombol kembali,
      "Belum di-set" 3.10:1 (gagal AA), link nama tanpa focus ring.
- [x] **Slice #1–#5** (dipilih user) — semua hijau:
      - **A1** tanggal sinkron diformat di server (`last_synced_label`,
        timezone app, label "Sinkron terakhir:") → hydration mismatch hilang.
      - **C1** `per_page` 50 + footer paginasi → semua department terjangkau.
      - **M1** `<768px` ganti ke **card list** (kode, nama, HOD, karyawan,
        divisi); sel HOD diberi `truncate` + `max-w-[20rem]`.
      - **A2+A3** `show.tsx` pakai `.layout` **callback** → `<h1>` + breadcrumb
        `Data Master › Department › <nama>` + link balik ke daftar.
      - **A5** link nama dapat ring fokus 2px + offset 2px (teal).
      Verifikasi: `tests/Feature/DepartmentPageTest.php` 7 test (format
      tanggal + anti-regresi "Okt" dibaca sebagai specifier format, paginasi
      51 department → 2 halaman, detail & divisi render). **Full suite 503:
      495 passed, 8 skipped, 0 gagal.** `pint --dirty` bersih, `tsc` 0,
      `npm run build` hijau. Bukti: `.impeccable/review/dept-*.png`
      (light, dark, mobile, detail, fokus) + `capture-departments.mjs` +
      `measure-departments.mjs`.
- [x] **Bonus — `divisions/show.tsx` crash** (ditemukan saat audit): pola
      `layout: { title: (division) => division.nama_division }` **rusak** —
      Inertia v3 menyalin nilai layout apa adanya, jadi `title` jadi function
      dan React melempar *"Functions are not valid as a React child"*; `<h1>`
      kosong di setiap load halaman detail Divisi. Diganti ke
      `layout: (props) => ({...})`. Lihat
      `Mistakes/inertia-layout-title-fungsi-render-crash.md` di vault.
- [ ] **Sisa audit (belum dikerjakan):** search/sort/filter di index.
      Item #6–#8 versi visual (chip "Tanpa HOD", headcount link, detail dua
      kolom, divisi/posisi link, bobot kolom) diselesaikan di section
      "Redesign halaman Department — Registry" di atas.

## Timeline view (2026-10-22) — DONE

- [x] **Timeline mingguan Work Order** —surface jadwal baru di
      `/{tenant}/work-orders/timeline` (`work-orders.timeline`), grid 7 kolom
      hari (Senin–Minggu) berisi WO yang punya `scheduled_date` di minggu itu.
      **Tanpa tabel/kolom baru** — memakai `work_orders.scheduled_date` yang
      sudah ada. Trigger: user minta "bisa diimplementasikan?" untuk referensi
      visual dashboard "Jiron" (research: `.scratch/design/research/jiron-dashboard-style-reference.md`).
- [x] **Backend:** `WorkOrderController@timeline()` — scope visibilitas sama
      dengan `index()` (requester + department yang di-HOD/di-deputi user),
      filter `scheduled_date` BETWEEN awal–akhir minggu, group per hari.
      Minggu travels sebagai query param `?week=YYYY-MM-DD` (deep-linkable,
      back button jalan); param tak bisa diparse atau >1 tahun dari minggu
      ini → fallback ke minggu berjalan. Deadline state dihitung server-side
      dari `deadline_date` (on_track / due_soon / overdue / escalated ≥H+3)
      supaya label rail tidak dihitung ulang di client.
- [x] **Frontend:** `resources/js/pages/work-orders/timeline.tsx` — heading
      band shell (breadcrumb Work Order › Timeline, aksi "Buat Work Order"),
      navigator minggu (‹ / › / "Minggu ini"), grid `md:2` → `xl:7` dengan
      `overflow-x` di dalam region sendiri (bukan scroll halaman — DESIGN.md
      "board scrolls horizontally inside its own region"), min-width 1388px
      supaya trio status + kategori + deadline (P1) tidak terpotong.
      Kolom "hari ini" ditandai **teks + rule** (`aria-current="date"`, badge
      "Hari ini", garis brand) — bukan warna saja (WCAG 1.4.1/1.4.11).
      Region scroll bisa fokus keyboard + `role="region"` berlabel (WCAG 2.1.1).
- [x] **Nav:** item "Terjadwal" (Operasional) diarahkan ke route timeline —
      sebelumnya salah nunjuk ke `work-orders.index` (daftar, bukan jadwal).
      Item "Work Order" tetap ke daftar.
- [x] **Test:** `tests/Feature/WorkOrderTimelineTest.php` 12 test — 7 kolom per
      minggu, group per tanggal, WO di luar minggu tidak muncul, WO tanpa
      scheduled_date tidak muncul, WO department milik user terlihat, WO
      department lain tidak terlihat, isolasi tenant, fallback param tak valid
      & jauh, penanda hari ini, deadline state (overdue/escalated/due_soon),
      dan butuh auth. **Full suite 481: 473 passed, 8 skipped, 0 gagal.**
      `pint --dirty` bersih, `tsc --noEmit` 0, `npm run build` hijau.
- [x] **Bukti visual:** `.impeccable/review/timeline-*.png` (desktop light,
      desktop dark, minggu kosong, mobile 390px, fokus keyboard) +
      `.impeccable/review/capture-timeline.mjs`. Fixture sementara
      (tenant/user/department/WO) sudah dibersihkan dari DB dev.
- [ ] **Catatan Architect (tab Timeline di ViewSwitcher):** DESIGN.md masih
      menyebut `Timeline · Kalender` sebagai tab ViewSwitcher di home HOD.
      Yang dibangun hari ini = route timeline terpisah (nav "Terjadwal"),
      bukan tab di dashboard (dashboard masih mock data). Kalau mau tab
      ViewSwitcher sungguhan, itu pekerjaan terpisah.

## Rencana 2026-10-22 (besok) - NEXT

- [x] **Q7 - semantik ban/login:** putuskan `banned_at` hanya penanda record atau benar-benar memblokir login. Jika blokir: gate di SAML/middleware, cabut sesi aktif, dan test regresi.
      **DONE 2026-10-22 (keputusan: blokir penuh):** middleware `EnsureEmployeeNotBanned`
      di web stack setelah `IdentifyTenant` (link FK global / email same-cabang untuk akun
      SAML unlinked; super admin eksempti via `isSuperAdmin()` konsisten `Gate::before`;
      deny → `LoginLanding::deny('banned')`); `ban()` mencabut baris `sessions` akun
      terkait dalam transaksi; guard `banned_at` di `WorkOrderService::assign`
      (ValidationException `employees`); copy `banned` di peta `saml.denied`.
      Test `EmployeeBanLoginTest` 7/7 (SAML + password ditolak, sesi mati di request
      berikutnya, unban pulih, revoke sesi + audit, assign ditolak, superadmin lolos);
      regresi 64 test hijau.
- [x] **Q4 - linked-but-roleless:** putuskan auto-assign `karyawan` saat link atau tetap warning + role manual. Konfirmasi bahwa auto-assign sistem tidak melanggar kebijakan Q6 (grant/revoke role hanya superadmin).
      **DONE 2026-10-22 (keputusan: auto-`guest`, bukan `karyawan`):** `linkAkun`
      me-provision role `guest` (izin nol, role sistem #10) dalam transaksi yang sama
      untuk akun tanpa role; akun ber-role tak disentuh; unlink tak mencabut role.
      Bukan proxy-grant: provisioning sistem di luar cakupan "manual grant/revoke
      superadmin-only" → amendmen 1 paragraf di `docs/adr/0008` (bagian Amendment).
      Audit `akun.link` membawa `after.auto_role = guest`. Test `EmployeeAccountLinkTest`
      14/14 (3 baru: roleless→guest + izin nol + e2e login landing, ber-role utuh,
      guest bertahan lewat unlink).
- [x] **Q14 - staf lintas tenant:** putuskan satu home tenant per akun atau pivot `employee_user`; jika pivot, siapkan migrasi data + penyesuaian unique index/login tenant.
      **DONE 2026-10-22 (keputusan: status quo + drift guard, pivot = v2):** ADR-0010
      `docs/adr/0010-satu-home-tenant-per-akun.md` (accepted) — satu home tenant final
      MVP, last-assertion-wins dipertahankan; drift login (home tenant ≠ cabang karyawan
      tertaut) dicatat `Log::warning` via `SamlController::warnHomeTenantDrift` tanpa
      memutus link; pivot dibuka lagi di v2 (blocker: skalar `users.tenant_id`, SAML
      single-valued, role global, `BelongsToTenant`). Test `Auth/TenantDriftGuardTest`
      3/3 (drift memicu warning + link utuh, normal senyap, akun tanpa link diabaikan);
      regresi SAML 44 test hijau.
- [x] **Audit trail durable:** ganti `Log::info`/`Log::warning` untuk link/unlink, perubahan role, dan ban dengan tabel audit (actor, target, tenant, before/after, alasan). Jangan tambah dependency tanpa approval.
      **DONE 2026-10-22:** tabel `audit_events` (UUID PK, `tenant_id` = data tanpa
      `BelongsToTenant`, morph string, before/after/context json, `actor_email` snapshot)
      + model `AuditEvent` append-only; `record()` tanpa transaksi sendiri (pemanggil
      wajib dalam `DB::transaction` — pola `WorkOrderEvent`); 5 aksi `EmployeeController`
      ter-wire (link/unlink ganti `Log::info`; ban/unban/roles.sync dari nol logging).
      Tanpa dependency baru. Test `AuditTrailTest` 7/7 (atomisitas via `DB::listen`,
      tenant-as-data, 5 aksi lengkap, 403 = nol baris).
- [x] **Employee page filters/search:** filter organisasi/akun/status; pencarian akun server-side + pagination (kandidat saat ini dibatasi 100).
      **DONE 2026-10-22 (langkah 6 rencana kerja):**
      (a) `EmployeeIndexRequest` bertambah `department_id`/`division_id`/`position_id`
      (UUID), `has_account` (boolean), `status` (`all/active/banned`, preseden tri-state
      `TenantController`); `EmployeeQuery::build` menerima `$filters` + rantai `when()`;
      prop `filter_options` (departemen/divisi/posisi server-driven per tenant).
      (b) Prop statis `available_accounts` + `limit(100)` di `show` DIHAPUS → endpoint
      baru `GET {tenant}/employees/{employee}/akun/search` (gate `akun.update`, `q` +
      paginasi, kandidat lewat helper bersama `linkCandidates` yang kini juga dipakai
      `linkAkun`). Frontend: filter bar `employees/index.tsx` (5 Select + clear,
      URL-sync mengikuti debounce yang ada) + combobox async `employees/show.tsx`
      (debounce 300ms + AbortController, fetch wayfinder `search`).
      Test `EmployeeIndexFilterTest` 6/6 (filter gabungan + opsi server, has_account
      dua arah, persist lintas halaman, scoping tenant, empty state, validasi status);
      `EmployeeAccountLinkTest` 17/17 (3 baru: search by nama/email, paginasi 105
      kandidat → total 105/last_page 11/page 2, gate 403; test lama diadaptasi ke
      endpoint); `EmployeeIndexTest`/`EmployeeShowTest` regresi hijau; `tsc --noEmit`
      bersih (wayfinder di-generate dengan `--with-form`).
- [x] **WhatsApp Gateway integration:** selesai hari ini (slice 1 outbound + slice 2
      sesi-per-tenant) — lihat section "WhatsApp Gateway slice 1" dan "slice 2" di
      bawah. Item ini tadinya "discovery dulu + di luar Non-Goals"; keputusan grill
      G1–G7 + rencana slice 2 (Q1–Q5) + implementasi + test sudah masuk.

## WhatsApp Gateway slice 1 — outbound (2026-10-21) — DONE

- [x] **Grill G1–G7 dijawab user (semua mengikuti rekomendasi):** WAHA session global
      (`WAHA_SESSION=optiworks`), secret di env (bukan tabel settings), slice = tepat 4
      event (created/reviewed/assigned/escalated), nomor = `employees.number` fallback
      `users.phone`, outbox `wa_messages`, command-bot inbound ditunda (route webhook
      tidak dibuat), AGENTS.md di-amend + ADR-0009. Spec/issue:
      `.scratch/wa-gateway/spec.md` + `issues.md`. Item Q1-exemption, Q6 min-1-role,
      Q7 `resolveEmail()` = parkir ke sesi berikutnya (sesuai grill).
- [x] **Implementasi:** `config/waha.php` + `.env.example` (WAHA_URL/WAHA_API_KEY/
      WAHA_SESSION); `app/Services/WahaClient.php` (sendText `->throw()` tanpa auto-retry,
      `normalizeChatId` 08xx→628xx@c.us + hormati chatId mentah, `sessionStatus`);
      migrasi + model `app/Models/WaMessage.php` (outbox: pending/sent/failed/skipped,
      attempts, last_error, sent_at; **tanpa `BelongsToTenant`** — dibaca job tanpa
      konteks tenant, `tenant_id` = data); `app/Channels/WahaWhatsAppChannel.php`
      (selalu di `via()`, no-op mutlak tanpa `WAHA_URL`, tulis outbox **dalam transaksi
      bisnis** + dispatch after-commit, skipped bila nomor kosong);
      `app/Jobs/SendWaMessageJob.php` (3x backoff [30,120], guard status pending =
      idempoten, `failed()` → status failed, **implements `NotTenantAware`** — spatie v4
      menghapus job tenant-aware yang tidak membawa tenantId, berarti dispatch dari
      command eskalasi konsol tanpa tenant harus non-tenant-aware);
      `toWhatsApp()` + `waEvent()` pada 4 kelas notifikasi (template per event, Bahasa
      Indonesia, link `route('work-orders.show')`); `User::routeNotificationForWahaWhatsApp()`
      (employee.number via `withoutGlobalScope('tenant')` → phone);
      `app:waha-ping {phone?}` (config + status session + test send).
- [x] **Test:** `tests/Feature/WahaGatewayTest.php` 14 + `tests/Unit/WahaClientTest.php` 4 —
      unconfigured → nol baris/nol HTTP; sent (payload, chatId, X-Api-Key, session);
      skipped tanpa nomor; preferensi employee number; channel tanpa tenant current; job
      dispatch tanpa tenant current; template reviewed/assigned; gagal kirim → last_error
      + rethrow (retry), `failed()` → failed; eskalasi `app:check-deadline-escalation` →
      outbox per penerima (payload `H+3`); ping exit code 0/1 + status WORKING/UNREACHABLE.
      **Full suite 438: 430 passed, 8 skipped, 0 gagal, 0 warning** (warning awal dari
      `use RuntimeException` di file test tanpa namespace — dihapus). `pint --dirty` bersih.
      Tanpa perubahan frontend/JS (tsc/build tidak terpengaruh; tidak diubah).
- [x] **Dokumen:** `AGENTS.md` 4 titik amendmen (Non-Goals email-only, Modul MVP + row
      WA, bagian Notifikasi, OQ settled #7 DIKECUALIKAN) + `docs/adr/0009-whatsapp-gateway.md`
      (**status: `draft` — menunggu review user; flip ke `accepted` setelah OK**).
- [x] **DEFECT PRE-EXISTING (baru ketahuan, di luar scope slice — butuh keputusan user):**
      `notify()` yang berjalan **tanpa tenant current** membuat job broadcast
      (tenant-aware by default) dilempar `CurrentTenantCouldNotBeDeterminedInTenantAwareJob`
      dan **di-queue-delete** oleh spatie saat diproses — jalur command eskalasi di
      produksi membuang saluran broadcast hari ini (test eskalasi lama selalu jalan dengan
      tenant current sehingga tidak pernah ketahuan). Test slice ini memanggil channel
      langsung untuk skenario tanpa tenant. Opsi perbaikan: masukkan event broadcast ke
      `multitenancy.not_tenant_aware_jobs` (payload broadcast tidak butuh tenant) —
      keputusan terpisah, jangan dikerjakan diam-diam.
      **DONE 2026-10-22 (keputusan: masuk rencana langkah 5):** entry
      `BroadcastNotificationCreated::class` (kelas **unwrapped** — probe spatie
      membuka `BroadcastEvent` via `queueable_to_job` SEBELUM membaca daftar, jadi
      nama wrapper = no-op sunyi) di `config/multitenancy.php` `not_tenant_aware_jobs`.
      `SendWaMessageJob` sudah `NotTenantAware` dari slice 1 (WA tidak terdampak).
      Test red-green di `EscalationTest` (12/12): tanpa entry → exception +
      job di-delete; dengan entry → notify() lengkap 3 kanal, baris `notifications` tercatat.
- [ ] Setelah user puas dengan gateway (dan ADR-0009 di-flip ke accepted): kerjakan
      item Rencana yang di-parkir (Q7 ban, Q4 roleless, Q14 cross-tenant, audit trail,
      employee filters).

## WhatsApp Gateway slice 2 — sesi per tenant (2026-10-21) — DONE

- [x] **Rencana + grill Q1–Q5 disetujui user (semua rekomendasi diterima):**
      (Q1) halaman `/settings/whatsapp`; (Q2) izin `whatsapp.read`/`whatsapp.update`
      khusus `admin_tenant` + bypass super admin; (Q3) rate limit rethrow → backoff
      antrean, default 20/menit + jeda 2 dtk via `WAHA_RATE_PER_MINUTE`/
      `WAHA_MIN_INTERVAL`; (Q4) putuskan = stop (fallback logout, perlu pairing
      ulang); (Q5) ping tanpa `--tenant` = cek instance saja tanpa kirim. Skema:
      `tenant_wa_sessions` (tenant_id unique, session_name `tw-{kode}` unique
      global) + `wa_messages.session_name` snapshot + index `(tenant_id, status)`.
- [x] **Migrasi + model/enum:** `2026_10_21_000002_create_tenant_wa_sessions_table`,
      `2026_10_21_000003_add_session_name_to_wa_messages_table` (belum dijalankan ke
      dev DB — jalankan `php artisan migrate` saat deploy); `TenantWaSession`
      (`activeFor()`/`forTenant()`, trait `BelongsToTenant`+`HasUuids`),
      `TenantWaSessionStatus` (stopped/starting/scan_qr/working/stopping/error/
      missing + `isActive()`/`label()`/`fromWahaStatus()`).
- [x] **Guard channel + job:** channel berurutan — URL kosong no-op → nomor kosong
      skipped → tanpa tenant failed → tanpa sesi aktif failed (alasan menyebut
      status baris sesi) → sesi aktif pending + snapshot `session_name` + dispatch.
      Job re-resolve sesi saat run (sesi mati setelah dispatch → failed tanpa HTTP);
      rate limit `RateLimiter` per `waha:send:minute:{session}` +
      `waha:send:interval:{session}` — kena batas → tulis `last_error` → rethrow
      (backoff antrean, attempts HTTP tidak terpakai), kunci per sesi menjamin
      antrean tenant A tidak menahan tenant B.
- [x] **Transport + sinkron:** `WahaClient` ditulis ulang (send wajib sesi eksplisit,
      `listSessions`/`getSession` 404→null/`startOrCreateSession`/`qrImage`/
      `stopSession` fallback logout; `sessionStatus` lama dihapus);
      `app:waha-sync-sessions` cron `everyMinute` (`bootstrap/app.php`) — daftar sesi
      → status lokal; instance tak terjangkau (Connection/Stray) → **tanpa flip
      massal** + exit FAILURE; sesi hilang → Missing; `last_connected_at` hanya saat
      transisi masuk working; `phone_number` dari `me.tel`. `app:waha-ping --tenant=`.
- [x] **Izin + Gate + UI:** katalog + `roleDefaults` `whatsapp.read`/`whatsapp.update`
      (grant via `php artisan app:grant-default-permissions`); flags `auth.can` di
      `HandleInertiaRequests` + tipe TS `auth.ts`; `Settings\WhatsAppController`
      (index/store/start/stop/qr, `Gate::authorize`, QR data URI on-demand) +
      `routes/settings.php` (prefix `settings/whatsapp`); halaman
      `resources/js/pages/settings/whatsapp.tsx` (status badge + `usePoll(15s)` +
      QR auto-refresh 5dtk selama waiting + tombol gated), nav "WhatsApp" di
      `layouts/settings/layout.tsx` gated `auth.can['whatsapp.read']`.
- [x] **Test:** `WahaTenantSessionTest` 23 baru (isolasi dua tenant → nomor masing-
      masing; tanpa sesi → failed + nol HTTP; sesi non-aktif → alasan status; sesi
      mati setelah dispatch → failed tanpa HTTP; rate limit per sesi + interval;
      sync happy/missing/unreachable/no-config; ping unknown/tanpa sesi; Gate 403
      read/update + render + store/start/stop/qr); `WahaGatewayTest` rombak (sesi
      aktif prasyarat, session `tw-hq`, ping `--tenant`, helper rate limit longgar);
      `GateTest` +2 flag. **Full suite 462: 454 passed, 8 skipped, 0 gagal**;
      `pint --dirty` bersih; `tsc --noEmit` 0; `npm run build` hijau
      (wayfinder `--with-form` — tanpa flag itu helper `.form` hilang dan tsc
      meledak di 10+ file lama).
- [x] **Dokumen:** ADR-0009 di-amend (judul slice 1–2, keputusan 1/2/6/7/8/9 revisi,
      references file slice 2) — masih `draft`, menunggu review user.
- [ ] **Sisa manual (user):** `php artisan migrate` + `app:grant-default-permissions`
      di dev; isi `WAHA_URL`/`WAHA_API_KEY` di `.env`; daftar nomor per tenant di
      `/settings/whatsapp` → Mulai Sesi → pindai QR; flip ADR-0009 ke `accepted`
      setelah review; sinkron catatan vault (Sessions/Decisions/changelog/Index).

## Frontend HOD department (2026-10-20) — DONE

- [x] **Tampilan HOD di frontend + fix binding latent:** kolom HOD di
      `departments/index.tsx` dan baris HOD di `show.tsx` kini avatar (foto
      karyawan, fallback inisial `useInitials`) + nama + email; kosong = "Belum
      di-set". Payload: `DepartmentController` index/show eager-load
      `hod.employee` (+ `hodEmployee`, `manager` di show).
      **Bug latent diperbaiki**: implicit binding `{department}` jalan sebelum
      `IdentifyTenant` (SubstituteBindings lebih awal di grup web) →
      `Tenant::current()` null → scope `BelongsToTenant` = `whereRaw(1=0)` →
      show/edit/update/destroy department gagal via URL. Dikonversi ke
      `resolveDepartmentFromRoute()` manual (pola terdokumentasi di
      `EmployeeController::resolveEmployeeFromRoute` + `WorkOrderController`).
      Verifikasi: DepartmentHodDisplayTest 3/3 (payload index/show/null),
      DepartmentTenantResolutionTest 2/2 (edit/update/destroy + 404 lintas
      cabang), MasterData+BranchSwitcher+SyncDepartments 35/35, WorkOrder+
      Notification 20/20, Employee 20/20; pint + tsc + `npm run build` hijau.
      Session: `Sessions/2026-10/20/1320-frontend-hod-display.md`.

## Paritas sync Department+HOD vs opti-work2 (2026-10-20) — DONE

- [x] **Riset + implementasi gap tunggal `manager_user_id`:** background agent
      mendokumentasikan fitur opti-work2 dengan sitasi file:line di
      `.scratch/optigate-sync/research/opti-work2-department-hod-sync.md`.
      Fitur ternyata sudah ada di opti-works (`b0be63f`, lebih baik dari referensi:
      pagination + retry + transaction + deactivate + per-tenant); satu-satunya
      perilaku referensi yang hilang = `manager_user_id` (opti-work2 men-sync-nya,
      konsumen lokal sudah ada: fallback eskalasi, scoping WO, halaman show).
      `SyncDepartments` kini kirim `with=hod,manager`, tulis `manager_user_id`
      dari `dept['manager']['id']` via `portalUserMap()` (diekstrak, dipakai kedua
      pass), semantik persis HOD (unlinked → null, hilang dari portal → dikosongkan).
      Tanpa migrasi. Verifikasi: SyncDepartmentsTest 16/16 (2 test manager baru),
      SyncEmployees+SyncDivisions 17/17, pint --dirty passed, tsc exit 0, full suite
      381 → 363 passed + 8 skipped (10 error = `EscalationTest.php` WIP untracked
      milik sesi paralel, bukan surface ini). Session log:
      `Sessions/2026-10/20/1028-paritas-department-hod-sync-manager.md`.

## Unit Bisnis page revamp + URL move (2026-10-19) — DONE

- [x] **Move `/settings/tenants` → `/tenants`:** routes/web.php (prefix `tenants`, names
      unchanged), controller moved `app/Http/Controllers/TenantController.php` (namespace
      `App\Http\Controllers`), Inertia components moved `resources/js/pages/tenants/`,
      tests moved `tests/Feature/TenantManagementTest.php` (URLs updated), Wayfinder
      regenerated. Sidebar/settings-nav links auto-updated via route functions.
      Verifikasi: `php artisan route:list --path=tenants`, tenant tests 7/7, full suite
      350 (342 passed, 8 skipped), pint + tsc + `npm run build` hijau.
- [x] **White/light redesign Unit Bisnis (index + show):** rebuilt both pages on the
      light Stitch tokens (bg-card, brand tints, ink scale) — no slate-950 dark theme.
      Index: filter bar (debounced search + status segmented control), card grid,
      loading overlay, empty states, pagination. Show: identity card, detail rows,
      usage bars, Optigate info note. Both wrapped by `app-sidebar-layout` via
      `.layout` metadata (breadcrumbs, title, description, actions).
      Verifikasi: impeccable detect clean, tsc + build hijau.
- [ ] Visual check in browser by user (light + dark mode toggle) — pages are token-based
      so dark mode stays readable by design.

## Sprint A — commit slicing + review fixes (2026-10-19) — DONE, commits `47014af`…`8b80440`

- [x] **Sync endpoint tests** (`tests/Feature/TenantManagementTest.php`): super admin
      `POST /tenants/sync` → success flash "Sinkronisasi selesai", branch admin → 403,
      500 portal → error flash + data untouched (suite 10/10).
- [x] **Review fixes**: `optigate_company_id: string | null` (model cast string),
      show.tsx link ke work-orders via Wayfinder `workOrderIndex({ tenant: code })`.
- [x] **Commit slicing** (sebagian besar sudah di-commit sesi paralel: `3d8c9c1`
      divisions read-only, `abed8f9`, `402bbc4`, `5c33d0d`; sisa):
      `47014af` tenants move+redesign, `22f743e` orphaned division create/edit pages,
      `bb2cf6a` deps (log-viewer, shadcn, public/vendor assets), `8b80440` stale skill file.
      `pnpm-lock.yaml` dihapus (project pakai npm). Working tree bersih.
- [x] Verifikasi akhir: full suite 369 (361 passed, 8 skipped, 0 failed), pint, tsc,
      `npm run build` hijau, push ke origin/main.



## Department/HOD sync relations (2026-10-17) — DONE, commit `b0be63f`

- [x] **Relasi department ↔ karyawan ↔ HOD lewat sync Optigate:** `SyncDepartments` fetch
      `with=hod` → portal hod UUID dipetakan ke `hod_user_id` lokal via jembatan
      `employees.optigate_user_id` → `users.employee_id` (peta dibangun sekali per tenant;
      hanya employee dengan optigate_user_id non-null + akun tertaut; HOD portal yang belum
      ter-sync dibiarkan null, HOD lama dikosongkan bila portal tak lagi mencantumkan).
      `SyncEmployees` kini mengisi `employees.department_id` dari `department_id` portal via
      `optigate_department_id` (null-safe; dept tak dikenal dibiarkan null). Migrasi
      `optigate_department_id` + unique(tenant_id, optigate_department_id) + tombol
      "Sinkronkan" + flash alert di halaman departments. Verifikasi: sync tests 16/16,
      full suite 335 (327 passed, 8 skipped, 0 failed), pint + `npm run build` + tsc hijau.
- [ ] Follow-up (opsional): jalankan `app:sync-departments` lalu `app:sync-employees` di dev
      DB untuk mengisi relasi live; map HOD portal belum tertaut sampai karyawan+akun ikut
      disync (urutan: departments → employees).

## Guest role + akun platform (2026-10-17) — spec `.scratch/guest-role/`, commits `7e5fe00` + `0a34c48`

- [x] Root cause akun superadmin: role SUDAH ada; yang hilang link `users.employee_id` (0 dari 127
      employee tertaut) → kartu "Peran Akun" menampilkan "Belum ada akun yang terhubung"
- [x] `guest` = system role ke-10, nol permission (seeder idempoten; CONTEXT.md + ADR-0006)
- [x] Auto-assign `guest` di SSO (`LoginProvisioningService`, sebelum Auth::login, dalam transaksi,
      idempoten; guard state-based fail-closed: nol role + nol link)
- [x] Akun platform self-heal saat login: role `super_admin` + flag `is_super_admin` + tautan
      karyawan by email (config `auth.super_admin_email`, tanpa email literal; pola Spatie v8)
- [x] Perintah one-off `app:grant-super-admin-role` (idempoten, additive-only, `--dry-run`)
- [x] Test: GuestProvisioningTest 7 + SuperadminAccountTest 5 + SyncAkunRolesTest evict-guest;
      suite 328/320/0 (WIP SyncEmployees sesi paralel dikecualikan); pint + tsc + vp check hijau
- [ ] **OPEN (menunggu keputusan user):** guest tetap bisa membuka halaman tenant yang belum di-gate
      (daftar Karyawan, WO index miliknya, dashboard) — hanya hak-akses/akun/review/assign yang
      di-gate hari ini. Guest lebih dulu DENIED di login (Task 2), jadi secara NET akses guest
      bertambah. Opsi: (a) terima + alasankan, dengan issue gating master data terpisah;
      (b) guest tidak dapat tenant sama sekali → mendarat di halaman "menunggu provisioning"
      (permukaan baru). Perintah tulis `app:grant-super-admin-role` juga menunggu persetujuan —
      DB dev sudah compliant (dry-run all-green).
- [ ] Nanti: gate `work-order.create/read` + master data (employee/department/division/position)
      agar "guest tidak bisa sentuh tenant data" terpenuhi harfiah (allowlist honesty test)

## Next session — 2026-10-14 (RBAC v2: hak akses slices 6–8 frontend)

### Done today

- [x] **Ticket 03 (role-assignment) — hide Peran/Izin actions without rights:** 7 new shared `can` flags (`role.create/update/delete`, `permission.read/create/update/delete`) join `role.read` in `HandleInertiaRequests`; `Auth['can']` type extended. Peran page hides create/rename/delete/sync without the matching flag and renders banks read-only without `role.update`; Izin page hides batch-create/rename/delete the same way. Server still authorizes; UI is only a mirror. `GateTest` +3 (full set, read-only viewer, super admin zero-perm); `Feature/Rbac` 58 passed; `types:check`, `vp check`, `npm run build` green. Files: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/auth.ts`, `resources/js/pages/roles/index.tsx`, `resources/js/pages/settings/permissions/index.tsx`, `tests/Feature/Rbac/GateTest.php`.
- [x] **Role assignment ticket 01 (ADR-0005):** scope dippersempit via grill — assignment pada akun lewat halaman karyawan. Katalog `akun.*` + release grant ke admin_tenant; payload `akun` di `employees.show` ber-gate `akun.read` (resolusi manual mengikuti pola ban/WorkOrderController); section "Peran Akun" read-only. Ticket 02 (sync guarded) & 03 (can flags) siap di `.scratch/role-assignment/issues/`. Verifikasi: 277 tests (269 passed, 8 skipped), pint bersih, build exit 0. WIP sesi 11-10 (drop CRUD lokal karyawan) ikut di-commit.
- [x] **Per-release grant command `app:grant-default-permissions`:** menutup gap seeder (default hanya disync saat role pertama dibuat → izin katalog baru tidak pernah sampai ke role existing). Additive-only (tidak pernah mencabut, kustomisasi Hak Akses selamat), membuat izin katalog yang hilang (firstOrCreate), provisioning 9 role kanonik + invarian `is_system`, tidak pernah memberi apa-apa ke super_admin (bypass gate), `--dry-run`, idempoten. Seeder `catalogue()`/`roleDefaults()` dipromosikan public static sebagai sumber kebenaran bersama. Verifikasi: 18/18 test (7 baru) + full suite 272 tests (264 passed, 8 skipped, 0 failed), pint bersih, dry-run live di DB dev = 0 perubahan. Commit `5071fec`.
- [x] **Halaman Peran di-revamp total + keluar dari settings (Vault Console):** URL `/settings/roles` → `/hak-akses` (route names `roles.*` tetap), controller `Settings\RoleController` → `RoleController`, requests → `App\Http\Requests\Role\*`, routes dipindah ke `routes/hak-akses.php`, page `settings/roles/index.tsx` → `pages/roles/index.tsx` (AppLayout, bukan SettingsLayout), nav: grup baru "Hak Akses" (item "Peran") di antara Data Master dan Sistem. Desain baru: dark console stratum (panel tokens, gelap di kedua theme) — rail token peran (nama + mono id + mini coverage strip 12 segmen + count izin/pengguna, urutan hierarki PRD) + stage bank izin per resource (LED tri-state Penuh/Parsial/Kosong + count, master checkbox tri-state, verb chips ber-LED), super_admin = sealed plate tanpa switch, commit bar dirty-state + sweep animation, search debounce server-side + paginasi rail. Tabel dihapus. Verifikasi: `RoleControllerTest` 19/19, `npm run build` hijau, detector impeccable 0 temuan, screenshot Chrome headless (desktop/dirty/sealed/dark/mobile) via playwright-core --no-save (user sementara sudah dihapus). Bukti: `.impeccable/review/*.png`.
- [x] RBAC v2 slices 6–8 green: frontend Peran page (table + system badges + dropdown actions + create/rename/delete dialogs + grouped tri-state permission Sheet dengan super_admin locked notice + SpotlightCard empty state + Reveal entrance), Izin page (table + group filter Select + batch create dialog resource×CRUD + rename/delete + pagination), nav "Hak Akses" di grup Sistem gated `auth.can['role.read']` (Auth type + 6 nav call sites). Komponen reactbits di-vendor: `AnimatedContent.tsx` (GSAP) + `SpotlightCard.tsx` (zero-dep, diadaptasi rounded/padding) + wrapper `reveal.tsx` (respects prefers-reduced-motion). ← *desain Peran di atas sudah digantikan Vault Console; komponen vendor tetap dipakai di Izin.*
- [x] Full suite 265 tests: 257 passed, 8 skipped (2FA), 0 failed. `npm run build` hijau (wayfinder + tsc + vp build). Impeccable mechanical detector pada 6 file UI: 0 temuan.
- [x] Root-cause fix baseline merah: `types:check` gagal 44 error di HEAD (7 file pakai `usePage()` tanpa generic `InertiaConfig['sharedPageProps']`) → semua ditambahkan generic, tsc kini EXIT=0 di seluruh codebase.
- [x] Bugfix `settings/tenants/index.tsx`: 4 tombol paginasi mengandung artifact backtick-n (JSX text nyasar) yang membuat Radix Slot crash saat paginasi Unit Bisnis — diperbaiki.

### Work plan — next session (role assignment: tickets 02–03)

1. [x] **Ticket 02 — Assign & revoke roles on the linked Akun, guarded (M):** **Done 2026-10-16 (commit `74d2a6c`):** `POST {tenant}/employees/{employee}/akun/roles` (`employees.akun.roles.sync`), route ungated + `Gate::authorize('akun.update')` in controller; `SyncAkunRolesRequest` (single `roles` error key, super_admin tak dapat diberikan); sync replaces the set in `DB::transaction`; field_staff grant auto-adds karyawan, drop-karyawan-while-keeping-field_staff ditolak dengan pesan, revoke field_staff leaves karyawan; akun holding super_admin ditolak sebelum write; flags + department FKs byte-identical (asserted); fresh role effective next request tanpa re-login (tested). UI: dialog "Ubah Peran" self-contained di header kartu Peran Akun — hidden tanpa `akun.update`, absent tanpa akun, read-only seal untuk akun super_admin, destructive alert key `roles`, close on success. Verifikasi: `SyncAkunRolesTest` 12/12.
2. [x] **Ticket 03 — Hide Peran/Izin action buttons without rights (S):** shared `can` flags (`role.*`, `permission.*`) via Gate in `HandleInertiaRequests`, pages hide buttons. **Done 2026-10-16:** 8 flags shared (`role.read/create/update/delete`, `permission.read/create/update/delete`); `Auth['can']` type extended; Peran page hides create (role.create) / rename (role.update) / delete (role.delete) / permission-sync footer + banks read-only without role.update; Izin page hides batch-create (permission.create) / rename (permission.update) / delete (permission.delete); super_admin bypasses all. Verified: `GateTest` 3 new tests (full set, role.read-only, super admin zero-perm), `Feature/Rbac` 58 passed, pint clean, `types:check` + `vp check` green, `npm run build` exit 0. Decision: admin_tenant resolved to full set not asserted (follows `role.read` precedent).
3. [x] Ticket 02 selesai → hapus entri allowlist `akun.update` ("gated when role-sync lands") dari `PermissionConsistencyTest` di commit yang sama (honesty test) — **Done 2026-10-16, commit `74d2a6c`**; `akun.create`/`akun.delete` tetap di allowlist.
4. [x] Verifikasi visual sisa slice 8 `/settings/permissions` — **Done 2026-10-17, automated:** user temp (role `viewer` + `role.read`/`permission.read` langsung; role wajib karena LoginLanding menolak user tanpa role) + screenshot headless via `.impeccable/review/capture-permissions.mjs`. Bukti: `perm-light.png`, `perm-dark.png` (tombol aksi tersembunyi tanpa permission — ticket 03 tervalidasi visual), `perm-dialog-error.png` (dialog Tambah Izin, submit kosong → field merah + pesan error). User temp + script temp dihapus. Catatan minor: pesan validasi dialog masih English (pre-existing), teks "Head Office" di tenant switcher kontras rendah di dark (pre-existing).
5. [x] Vault sync — **Done 2026-10-17:** ADR-0005 → `Decisions/user-role-assignment-akun.md`, link dari `Index.md` (Last updated diubah). Catatan route `/hak-akses` ternyata sudah ada di `Decisions/peran-page-vault-console` — tidak diduplikasi.
6. [x] **WO slice 1 selesai 2026-10-17** (issue 03 wms-mvp = review → assign + deadline + histori) —
   tiket per seam di `.scratch/wo-slice-1/` (spec + 4 issues, semua done):
   - `6e47cf3` foundation: migration hod_action/scheduled_date/assigned_at/hod_notes,
     enum `HodApproved` ("Disetujui HOD"), `config/holidays.php` + `DeadlineCalculator`
     (urgent 3 / normal 6 hari kerja dari tanggal assign; fixed Y-m-d + recurring MM-DD),
     **fix cross-tenant read** di show()/attachment() (withoutGlobalScope dibuang; test
     regresi: WO tenant lain → 404, sebelumnya 200)
   - `ea6779d` review endpoint: POST .../review gated `work-order.review` (allowlist
     dikeluarkan), Accident dipaksa eksekusi langsung, 409 pasca-review-stage,
     `work_order_events` append-only + `WorkOrderReviewed` ke requester; **root-cause fix
     config/multitenancy.php queueable_to_job tertukar** (BroadcastEvent→'mailable', dst —
     setiap broadcast/mail queue crash di probe; kini event/mailable/notification)
   - `937a474` assign endpoint: POST .../assign gated `work-order.assign` (allowlist
     dikeluarkan), `assigned_employees` json + assignment_notes, assigned_at + deadline
     hanya sekali (revisi mengganti orang tanpa geser SLA), `WorkOrderAssigned` ke akun
     link saja (tanpa akun → meta event + log warning), 409 assign sebelum review
   - `553c775` panel frontend: flag `can` work-order.review/assign, panel Keputusan
     Review (Accident tanpa opsi jadwal), panel Assign (revisi mode), kartu Keputusan
     HOD, badge hod_approved; bukti headless `.impeccable/review/wo-panel-*.png`
   - Verifikasi: tsc + build hijau; suite 313/305/0 failed (6 failure = WIP merah sesi
     paralel SyncEmployees, bukan slice ini); pint bersih; slice-1 komit bersih dari WIP
     sesi paralel
   - Sisa allowlist honesty test: work-order.create/read/update/delete/submit/verify
     (gating menyusul saat permukaannya disentuh — issue 04+)

### Bonus 2026-10-16 (commit `74d2a6c`) — role-aware login + superadmin

- [x] **Role-aware login (LoginLanding):** satu rule landing untuk semua jalur login — no-role → session diakhiri + denied `no_role`; no-tenant → `no_tenant`; platform account → admin panel; home tenant → dashboard. Fortify `LoginResponse` binding menggantikan `config('fortify.home')` yang mati (menutup open question lama di Known-issues); SAML ACS menolak login tanpa peran sebelum tenant resolution. Authorization tetap per-request (pivot tidak di-cache — tested).
- [x] **Superadmin (Task 3):** seeder memberi akun `superadmin@appdutamall.com` peran `super_admin` + flag `is_super_admin`; bypass berjalan lewat `Gate::before` + role — **perbandingan email dihapus** dari `SamlTenantResolver::canAccessAnyTenant` (email hanya memilih akun yang di-seed). Flag-only super admin tetap lolos login tanpa peran Spatie (diurutkan sebelum cek no-role).
- [x] Verifikasi: full suite 301 tests (293 passed, 8 skipped 2FA, 0 failed); pint bersih; `npm run build` hijau. Commit + push `74d2a6c`.

### References for tomorrow (read before starting)

- `docs/adr/0005-user-role-assignment.md` — keputusan slice ini (JANGAN di-re-litigate)
- `docs/adr/0004-hak-akses-surface.md` — baseline RBAC: verb map, allowlist, guards, bypass super admin
- `.scratch/role-assignment/issues/02-assign-revoke-roles-guarded.md` + `03-hide-peran-izin-action-buttons.md` — acceptance criteria per ticket
- `.claude/plan/2026-10-14-work-plan.md` — plan hasil grill (settled decisions Q1–Q10)
- `CONTEXT.md` — glossary: Akun, Role Assignment, System Role, Hak Akses
- `tests/Feature/Rbac/RoleControllerTest.php` — pola test (acting user, tenant, gate) yang ditiru; `tests/Feature/Rbac/EmployeeAkunRolesTest.php` = ticket 01
- `Sessions/2026-10/16/0033-akun-role-read-surface.md` — log sesi pengerjaan ticket 01

### Deferred

- [x] Forward-looking allowlist upkeep — remove work-order.review/assign/submit/verify entries as WO gating lands; a stale entry fails the honesty test (S) — **selesai 2026-10-22**: review/assign digate + dihapus di slice sebelumnya, submit/verify digate + dihapus di sesi ini; allowlist kini kosong untuk semua verb work-order.
- [ ] Start the core WO flow: creation, category decision (Normal / Urgent by Accident / Urgent Request by Owner), HOD review → assign — the hak-akses permissions are in place to gate it (L)
- [ ] Akun CRUD surface (link/unlink login) — owns `akun.create`/`akun.delete`, currently allowlisted "gated when the Akun surface lands"

Catatan: item "Resolve PRD Open Questions" dihapus — sudah SETTLED di AGENTS.md (Team Leader = role Spatie; deadline urgent 3/normal 6; extend 3 hari/3 approved; Direksi ditunda).

### Catatan keputusan hari ini

- Reactbits: CountUp/Counter dilewati (butuh dep baru `motion` — butuh approval); dipakai AnimatedContent (GSAP, sudah ada) + SpotlightCard (zero-dep). SplitText.jsx hasil vendor sesi sebelumnya tidak disentuh.
- Heading-band actions = komponen self-contained yang memiliki dialognya sendiri (mengikuti pola SyncButton/Employees) — tidak perlu jembatan state page.
- `form.post(store.url())` / `form.patch(update.url(args))`: useForm Inertia v3 hanya menerima string URL; `router.*` menerima RouteDefinition langsung.

### Deferred (do not start yet)

- [ ] Page-access configuration (agreed: later phase, after CRUD)
- [ ] Sidebar metadata permission-filtering (agreed: out of scope; single shared flag only)
- [ ] Tenant (penyewa) CRUD (v2)
- [ ] External notifications (WA/email gateway)
- [ ] Advanced analytics/reporting

### Known tradeoffs

- [ ] Seeder re-creates permissions deleted via the UI (safe: delete is blocked while attached, so nothing re-attaches silently)
- [ ] Seeder does not push new default permissions to existing roles — handled by the explicit per-release grant step above

---

## Optigate sync — DONE 2026-10-10 (Phase 4 completion)

**Full suite: 143 tests, 135 passed, 8 skipped, 0 failures.** Sync tests: 9/9 with `Http::fake`.

`app:sync-tenants` rewritten from pre-migration legacy to spec:
- Matches on **`optigate_company_id`** (was: API id written as tenant id — would have failed on NOT NULL `code`)
- Sets `code` (lowercased slug) + `name` + `is_active`; landlord-side (no `Tenant::current()` requirement — the old version could never run from the scheduler)
- **Pagination** loop (`paginate=true&per_page=100`, `meta.total` driven, MAX_PAGES guard)
- **Deactivate on disappearance**: `is_active=false` + `deactivated_at=now()` (never delete); **reactivates** when the company reappears; tenants without `optigate_company_id` are untouched
- **Retries**: 3 attempts, growing backoff, on connection errors + 5xx only; 401/403 fail fast with logging
- **Scheduled**: daily 01:00 (`bootstrap/app.php` `withSchedule`)
- **Feature test**: `tests/Feature/SyncTenantsTest.php` — create, update, deactivate, reactivate, idempotency, pagination, 401 fail-fast, 5xx retry (all `Http::fake`)
- Removed dead `config/services_portal.php` (duplicated `services.optigate_portal`, nothing read it)
- Documented `WEB_PORTAL_URL` / `WEB_PORTAL_TOKEN` / `WEB_PORTAL_VERIFY_SSL` in `.env.example`

Follow-up (not started): `.scratch/optigate-sync/issues/01-align-department-employee-syncs.md`

## Completed
- ✅ Replaced manual tenant CRUD with Optigate API sync approach
- ✅ Tenant model uses ULID primary key, `optigate_company_id` (unique), `code` (URL slug)
- ✅ Migration: Restructured tenants table with ULID id, optigate_company_id, code
- ✅ Updated TenantFinder to find by `code` from route parameter
- ✅ Updated TenantAccess service for ULID compatibility
- ✅ Updated TenantSwitchController to use code for routing
- ✅ Updated EnsureTenantAccess middleware for code-based tenant access
- ✅ Fixed all models using BelongsToTenant trait for ULID compatibility
- ✅ Updated controller route redirects to use `tenant()->code` instead of UUID
- ✅ Fixed WorkOrderService to use tenant code for nomor_wo generation
- ✅ Added explicit model binding for workOrder parameter in routes
- ✅ Fixed WorkOrder show/attachment endpoints (removed implicit model binding conflicts)
- ✅ Updated TenantFactory to match new structure (optigate_company_id, code as slug)
- ✅ Updated tests to use new tenant structure (DashboardTest, MultitenancyTest)
- ✅ Fixed QueueTenancyBootstrapper tests for Spatie v4 behavior
- ✅ 2026-10-10: Rewrote `app:sync-tenants` per spec (see "Optigate sync" below)

## DONE — 2026-10-10: Queue Tenant Awareness Fix + Full Suite Green

**Full suite: 134 tests, 126 passed, 8 skipped (intentional 2FA), 0 failures.**

### Root cause of the "queue bug"
The bug was a phantom: `ProbeTenantContext` crashed mid-`handle()` on two v3-era leftovers,
so every query capture below the crash point never ran and `visibleWorkOrders` stayed at its
reset default `[]`:
1. `tenancy()` helper — removed in Spatie v4 (v3-only) → "Call to undefined function"
2. Undeclared static property `$visibleWorkOrdersWithoutScope` → fatal in PHP 8.2+

Tenancy itself worked end to end the whole time: container tenant restored at `JobProcessing`,
scoped query returned exactly `['WO-HQ-1']`. No app-code change, no migration.

### Also fixed (surfaced by the full-suite run, all pre-existing)
- `SamlController::acs()` had a committed `dd($samlUser)` — killed every SAML login AND
  segfaulted the test process (PHP crash dumping the LightSaml object graph). Removed.
- `SamlTest` asserted `route('dashboard')` — stale: dashboard now requires `{tenant}`.
  Fresh SAML users have no tenant → committed behavior sends them to `admin.dashboard`
  (tenant picker). Assertion updated to match.
- `AuthenticationTest` / `DashboardTest` / `NotificationTest`: same stale-route and stale
  tenant-creation fallout from the Spatie migration. Repaired to committed behavior.

### Follow-ups (decisions needed, NOT started)
- `config('fortify.home')` = `/dashboard` points at a dead route — where should password-login
  users land? Tied to the tenant-less-user landing gap below.
- Tenant-less non-admin users land on `admin.dashboard` → 403 (EnsurePlatformTenantAccess).
  Open: `.scratch/superadmin-login/issues/04b-handle-users-without-tenant.md`
- `config/multitenancy.php` lists `\Tests\Support\ProbeTenantContext` in `tenant_aware_jobs` —
  harmless (interface + default-true already cover it) but smells; consider removing.
- Eloquent quirk learned while diagnosing: calling `applyScopes()` then `toSql()` double-applies
  closure global scopes (toSql is a passthru → toBase() → applyScopes again). Production queries
  are unaffected; avoid that chain when debugging SQL.

### Reference
- Scratch issue: `.scratch/tenancy-reconfig/issues/06-enable-queue-tenancy-bootstrapper.md`
- Vault plan: `Planning/2026-10-10-queue-tenant-awareness-fix` (status: done)

## Files Modified
- app/Models/Tenant.php
- database/migrations/2026_10_09_121502_restructure_tenants_table.php
- app/TenantFinder.php
- app/Services/TenantAccess.php
- app/Http/Controllers/TenantSwitchController.php
- app/Http/Controllers/EnsureTenantAccess.php
- app/Http/Controllers/WorkOrderController.php
- app/Services/WorkOrderService.php
- app/Http/Controllers/SamlController.php  ← 2026-10-10: removed committed dd() in acs()
- routes/web.php
- database/factories/TenantFactory.php
- config/multitenancy.php
- tests/Feature/DashboardTest.php  ← 2026-10-10: stale dashboard route + tenant-less user fixes
- tests/Feature/MultitenancyTest.php
- tests/Feature/QueueTenancyBootstrapperTest.php  ← 2026-10-10: rewritten (see DONE section)
- tests/Feature/AuthenticationTest.php  ← 2026-10-10: stale dashboard route fix
- tests/Feature/Auth/SamlTest.php  ← 2026-10-10: stale redirect assertion fix
- tests/Feature/NotificationTest.php  ← 2026-10-10: tenants table required-field fix
- tests/Support/ProbeTenantContext.php  ← 2026-10-10: removed v3-era helpers, declared statics
- tests/Pest.php (createTestTenant, initTenant helpers)
