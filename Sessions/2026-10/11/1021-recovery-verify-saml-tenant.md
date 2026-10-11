# 10:21 — Recovery verification: SAML/tenant + Employee index

## Konteks
User melaporkan "semua kode SAML/active-tenant ter-revert setelah refactor Employee page". Diagnosis (`diagnosing-bugs`): **tidak ada yang hilang** — semua sudah di-commit `0b32e88` (11/10 01:24) dan pushed. Persepsi "revert" dipetakan ke window rusak setelah migrasi Spatie (commit `dd($samlUser)` di `acs()` membunuh semua login SAML, 09/10 23:53 → dibersihkan 10/10). Reflog bersih, tanpa reset/checkout/stash; mtimes membuktikan Employee refactor (00:44–01:05) tidak pernah menyentuh file SAML (23:05–23:13).

## Yang dikerjakan
- Feedback loop: `php artisan test tests/Feature/SamlTenantResolutionTest.php` — green 14/14 di HEAD; ditambah 1 test (log warning saat assertion tanpa company data), diverifikasi red→green via mutasi resolver.
- Full suite menemukan **1 failure nyata di Employee refactor**: `employees.data.0.department` tidak ada — `->through(EmployeeResource::make)` membungkus tiap row dengan key `data` ekstra (double-wrap), UI yang disetujui (flat rows) tidak bisa jalan.
- Code review 2-axis (`code-review` skill, fixed point `7d7ff47`) → temuan di-fix:
  - EmployeeController: `->resolve()` per row (flat rows + meta pagination top-level).
  - EmployeeResource: precedence `(bool) ($this->user->is_active ?? true)`.
  - Frozen columns: Name `left-32`, Department `left-[328px]` (sebelumnya 80/200, overlap saat scroll horizontal).
  - SamlTenantResolver: `LOWER(TRIM(?))` dua-duanya di SQL (konsistensi case-folding).
  - routes `/`: redirect pakai `tenant->code`, bukan ULID.
  - RoleAndPermissionSeeder: email superadmin dari `config('auth.super_admin_email')`.
  - EmployeeIndexRequest: `Rule::in(EmployeeQuery::SORTABLE_COLUMNS)` (satu sumber kebenaran).
- Verifikasi: full suite 172/164 passed/8 skipped/0 failures; `npm run build:ssr` sukses; pint green. phpstan masih 53 error pre-existing repo-wide (bukan dari diff ini) → follow-up terpisah.

## Commits
- `aeaa2c5` fix(tenant): normalize resolver matching, home redirect by code, seeder email from config
- `f705d71` fix(employees): flatten index rows and align frozen column offsets

## Terkait
- [[Sessions/2026-10/changelog]]
- Keputusan user: matching tenant **name-only** (assertion Optigate hanya membawa atribut `company` — lihat `result-assertion.md`).
