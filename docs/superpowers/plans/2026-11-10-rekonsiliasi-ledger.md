# Ledger — Rekonsiliasi Branch WO (2026-11-10)

## Baseline `main` @ b90863a (worktree `opti-works-reconcile`)
- `vendor` terpasang fresh di worktree (composer install). `.env` dari `.env.example` (baris `[TEMPLATE]` dibuang) + `key:generate`. `.env.testing` disalin; `public/build` disalin dari repo asli (hindari Vite manifest missing di test Inertia).
- **Suite `main`: 655 tests · 642 passed · 5 failed · 8 skipped · 0 error.**
- 5 kegagalan baseline (BUKAN regresi; jangan dikejar):
  1. `it renders the local login screen for guests` (auth/SSO flag — kemungkinan env)
  2. `it previews a subordinate daily list and materializes templates for that date`
  3. `it lists active assignments with the server computed timer for the assignee`
  4. `it keeps assignments invisible to users who are not assignees`
  5. `it surfaces pending extend requests only to the deciding approver (R21)`
- **Floor non-regresi:** hasil akhir ≤ 5 failed dari himpunan yang sama (idealnya 0). Jika muncul kegagalan LAIN → itu regresi merge, wajib diperbaiki.

## Keputusan (Rulings)
- R0.1: Integrasi berbasis `main` (bukan branch). Alasan: `main` lebih kaya (RequestService/WorkTimeLogService/DailyWork/eskalasi) + test.
- R0.2: Worktree terpisah + `composer install` sendiri (BUKAN junction) — junction menyebabkan autoload menunjuk tree lain ("Target class [config] tidak ada"), pelajaran dari audit.
- R0.3: Raise memory `php -d memory_limit=1536M vendor/bin/pest` — `php artisan test` meneruskan 128M ke child process dan OOM.

## Progress
- [x] Fase 0.1 worktree
- [x] Fase 0.2 baseline
- [x] Fase 0.3 backup branch `backup/features-department-syncronize-20261110`
