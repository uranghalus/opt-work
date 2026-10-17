# 03: Endpoint assign + notifikasi + deadline

**What to build:** `POST {tenant}/work-orders/{workOrder}/assign` — assign 1+ karyawan pelaksana,
set `assigned_at`, hitung deadline, notifikasi assignee, entri histori.

**Blocked by:** 02

**Status:** done 2026-10-17 (commit `937a474`)

- [x] Route + controller action gated `work-order.assign` via `Gate::authorize`; entri allowlist
      `work-order.assign` dikeluarkan di commit yang sama
- [x] FormRequest: `employees` required array min 1 (exists + distinct; pengecekan department target
      di service — butuh WO), `assignment_notes` nullable
- [x] Efek: status → `assigned`; `assigned_at` + `deadline_date` hanya sekali (assign pertama);
      revisi penugasan mengganti orang tanpa menggeser SLA; 409 bila assign sebelum review
- [x] Notifikasi `WorkOrderAssigned` (database + broadcast) hanya ke akun link; tanpa akun →
      tetap ter-assign, tercatat di meta event + log warning (gap opti-work2 ditutup)
- [x] Entri histori `assigned` (employee_ids/notified/unlinked meta)
- [x] Test: AssignWorkOrderTest 7/7 (urgent vs normal deadline, revisi, luar department, 403,
      409 pre-review, notif terkirim/skip)
