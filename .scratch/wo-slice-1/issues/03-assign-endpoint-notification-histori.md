# 03: Endpoint assign + notifikasi + deadline

**What to build:** `POST {tenant}/work-orders/{workOrder}/assign` — assign 1+ karyawan pelaksana,
set `assigned_at`, hitung deadline, notifikasi assignee, entri histori.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Route + controller action gated `work-order.assign` via `Gate::authorize`; entri allowlist
      `work-order.assign` dikeluarkan di commit yang sama
- [ ] FormRequest: `employees` required array min 1 (id karyawan hasil sync, wajib di department
      target WO), `assignment_notes` nullable
- [ ] Efek: status → `assigned`, `assigned_at = now()` hanya sekali (pertama), deadline dihitung
      dari `assigned_at` (urgent 3 / normal 6 hari kerja, libur dari config) dan disimpan ke
      `deadline_date`; revisi penugasan (panggil ulang) diperbolehkan
- [ ] Notifikasi `WorkOrderAssigned` (database + broadcast, type `work_order.assigned`, pola
      `WorkOrderCreated`) ke `Employee::user`; karyawan tanpa akun link → assign tetap sah,
      tercatat di meta event + log
- [ ] Entri histori `assigned` (siapa, kapan, daftar assignee, from/to status)
- [ ] Test: sukses assign (status/assigned_at/deadline), deadline urgent vs normal + lintas libur,
      assign karyawan luar department ditolak, tanpa permission 403, notif terkirim ke akun link
      dan skip yang tanpa akun, histori tercatat
