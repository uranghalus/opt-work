# 03: Review HOD + assign karyawan + deadline

**What to build:** HOD memutuskan eksekusi langsung vs dijadwalkan (by_accident dipaksa langsung, by_owner bebas pilihan), assign karyawan pelaksana, deadline dihitung dari **tanggal assign** (urgent 3 / normal 6 hari kerja, libur configurable via config/holidays.php), notifikasi ke karyawan yang di-assign.

**Blocked by:** 02

**Status:** done 2026-10-23 — selesai sebagai slice 1 di `.scratch/wo-slice-1/` (spec + 4 tiket per seam, semua done; commits `6e47cf3`, `ea6779d`, `937a474`, `553c775`). Re-assign pasc-assign dibuka lewat commit `409a60c` (2026-10-23).

- [x] Keputusan HOD: eksekusi langsung / jadwalkan sesuai aturan kategori — review endpoint gated `work-order.review`, Accident dipaksa langsung, 409 pasca-review-stage (commit `ea6779d`)
- [x] Assign 1+ karyawan + notifikasi ke assignee — assign endpoint gated `work-order.assign`, `assigned_employees` + `WorkOrderAssigned` (commit `937a474`)
- [x] Deadline otomatis: urgent 3 / normal 6 hari kerja, dihitung dari tanggal assign, libur configurable — `DeadlineCalculator` + `config/holidays.php` (commit `6e47cf3`)
- [x] Semua perubahan status tercatat di histori (siapa, kapan, aksi) — `work_order_events` append-only + UI Riwayat FR-1.9 (commit `dd3f549`)
