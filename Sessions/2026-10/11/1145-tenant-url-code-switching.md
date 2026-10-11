# 11:45 — Tenant URLs by code + same-page switching + .toString() root fix

## Konteks
Tiga masalah terkait URL tenant: (1) URL memakai ULID mentah, (2) pindah cabang selalu mendarat di dashboard, (3) crash runtime `Cannot read properties of undefined (reading 'toString')` di helper Wayfinder.

## Akar masalah (diagnosing-bugs)
- Crash: shared prop `activeTenant` nullable; halaman melewatkannya dengan non-null assertion (`activeTenant!`) → helper Wayfinder memanggil `parsedArgs.tenant.toString()` pada undefined. Build lolos karena `vp build` tidak menjalankan tsc.
- URL ULID: kedua switcher mengirim `t.id` (ULID) ke endpoint yang mengharapkan `code` → 404 → fallback `window.location.href = '/{id}'` → tenant tak ter-resolve → `activeTenant` null → crash. Satu akar untuk masalah 1 dan 3.

## Perubahan
- `resources/js/lib/tenant.ts`: `useRequiredTenant()` (satu seam, error jelas) + `switchTenant()`; semua halaman tenant memakainya; semua `activeTenant!` dihapus.
- Backend: `Tenant::scopeWhereCode()` (CI, exact) dipakai TenantFinder + route binding; unknown code → **404** (sebelumnya superadmin dapat 200 tanpa konteks tenant).
- POST `tenant/switching` + `TenantSwitchTarget`: pindah cabang tetap di halaman yang sama (route name + params → server rebuild; record → parent list; query string dibuang; fallback dashboard + pesan).
- Switcher (BranchSwitcher + TenantSelector) kirim `code` dan pakai endpoint POST.
- `npm run build` kini: `wayfinder:generate --with-form` + `tsc` + bundle; `dev` juga regenerate.
- Guard: `FrontendTenantGuardTest` (statis, larang `activeTenant!`) + tsc gate.

## Verifikasi
- Suite 186/178 passed/8 skipped/0 failures; tsc hijau; build + SSR hijau; pint hijau.
- Demo compile-time: helper tanpa tenant ditolak tsc pada file baru.
- Dev server restart (backend :8000, vite :5174 via public/hot).

## Commits
`1cf046d` fix(tenant-routes) · `7474184` fix(tenant) · `230429b` feat(tenant) · `c970dd8` test(tenant) · `955541d` fix(tenant) review follow-ups

## Terkait
- [[Sessions/2026-10/changelog]]
- Keputusan menunggu owner: redirect URL lama ber-ULID → ber-code (belum dibuat).
- Follow-up tercatat: phpstan ~53 error pre-existing; guard statis masih sempit (hanya `activeTenant!`).
