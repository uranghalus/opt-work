# Spec: Login Lokal Sementara via Fortify (SAML off via env flag)

Tanggal: 2026-10-26 · Status: **disetujui user** · Ukuran: kecil
Revisi 3 (final): keputusan user — **pakai controller Fortify bawaan apa adanya**
(rev 2 Breeze-style DIBATALKAN). Controller, throttle, dan landing tidak ditulis
ulang; yang ditambah hanya halaman UI, route `GET /login`, dan jalur guest.

## Intent & Kenapa

User perlu testing fitur aplikasi tanpa SAML. Backend login password (Fortify v1.40,
Laravel 13) **sudah ada dan teruji** (`POST /login` = controller vendor + throttle +
`LoginLanding` role-aware). Yang belum ada: halaman login UI, `GET /login`, dan
jalur guest ke sana. SAML diparkir sementara secara reversible lewat env flag.

## Goals

- Tester login via email + password di halaman `/login` (akun demo `DemoSeeder`, password `password`).
- Guest diarahkan ke halaman login (bukan SSO portal) saat flag off.
- Re-enable SAML = ubah 1 baris `.env`, tanpa edit kode.
- Fix bug `PositionTreeController` yang membuat `positions/tree` 500.

## Non-Goals

- Tidak menulis controller/Request salinan di `app/` (keputusan user rev 3).
- Tidak menghapus/memblokir route SAML (test SAML tetap hijau).
- Tidak menambah fitur register / reset password (Fortify features tetap `[]`).
- Tidak mengubah perilaku apa pun saat flag on (default).

## Anti-Goals

- Test suite jadi merah.
- "Nonaktif" yang sulit dibalikkan.

## Constraint

- Nama route `login.store` / `logout` tidak berubah → 20+ test existing jadi regresi.
- Default flag = `true` (perilaku saat ini) — test existing & CI tidak terpengaruh.
- Fortify: `POST /login` sudah terdaftar + throttle; `Fortify::loginView` sudah
  menunjuk ke `auth/login` (Inertia) di `FortifyServiceProvider`.

## Pendekatan

Dipilih (rev 3): **Fortify bawaan** — `fortify.views => true` mendaftarkan
`GET /login` yang merender `auth/login` via `Fortify::loginView`; flag env hanya
mengubah tujuan redirect guest. Ditolak: Breeze-style controller di app (rev 2,
dibatalkan user); blokir total route SAML; tanpa flag.

## Keputusan Detail

1. **Flag:** `config/auth.php` → `'sso' => ['enabled' => env('AUTH_SSO_ENABLED', true)]`.
   `.env` dev: `AUTH_SSO_ENABLED=false`; `.env.example` didokumentasikan.
2. **`GET /login`:** `config/fortify.php` `'views' => true` — satu-satunya route
   baru yang muncul (view lain ter-gate fitur yang nonaktif). Render via
   `Fortify::loginView` yang sudah ada (props: `canResetPassword`, `status`).
3. **Guest redirect:** `bootstrap/app.php` `redirectGuestsTo` → flag on:
   `saml.redirect` (perilaku sekarang) · flag off: `route('login')`.
4. **Root `/`:** guest → flag on: `saml.redirect` · flag off: `route('login')`.
5. **Halaman login baru:** `resources/js/pages/auth/login.tsx` — `useForm` POST
   string URL `/login` (tidak ada ziggy; wayfinder tidak untuk route vendor),
   error bag Inertia, prop `status`, styling konsisten halaman `saml/denied.tsx`.
6. **Fix bug:** `routes/web.php` tambah import `use App\Http\Controllers\MasterData\PositionTreeController;`
   (saat ini resolve ke global namespace → route `positions/tree` 500 & `route:list` crash).
7. **Polish kecil:** halaman `saml/denied` menerima prop `ssoEnabled` (dari flag);
   button "Coba masuk lagi" → `/login` saat flag off, `/saml/redirect` saat on.

## Amandemen rev 4 (2026-10-26, permintaan user)

8. **Flow login lokal = flow SAML, tanpa SSO:** user login (Fortify) → tenant
   dicari dari home-tenant akun (`users.tenant_id`, diisi dari employee saat link —
   `EmployeeController::linkAkun`, `DemoSeeder:157`) → redirect ke
   `/{kode-tenant}/dashboard` via `LoginLanding`. **Sudah berjalan — tanpa kode
   baru**; dipin oleh `RoleAwareLoginTest` yang ada.
9. **Seeder akun per role:** `DemoSeeder` sudah mencakup 9/11 role (hod, TL,
   karyawan, field_staff, admin_tenant, GM, DGM, viewer + superadmin dari
   RoleAndPermissionSeeder; semua password `password`). Yang kurang hanya
   **`staf_admin_dept`** → ditambahkan 1 entri person ke `DemoSeeder`
   (firstOrCreate, idempoten) + sesuaikan hitungan di `DemoSeederTest`.
10. **Desain halaman login via ui-ux-pro-max** (saya sebagai lead designer).
    Aturan yang di-pin dari hasil pencarian (ux + shadcn):
    - Error per field: `<p role="alert">` inline di bawah input invalid
      (bukan visual-only; error diumumkan screen reader).
    - Autocomplete: `autocomplete="email"` / `autocomplete="current-password"`;
      paste TIDAK diblokir (WCAG 2.2 Accessible Authentication).
    - Feedback submit: tombol `disabled` + label "Memproses…" saat `form.processing`.
    - Label selalu visible (`htmlFor`/`id`), bukan placeholder-only.
    - Layout: centered card `max-w-md` di `bg-canvas` — konsisten `saml/denied.tsx`.

## Test

- Baru: `GET /login` render Inertia `auth/login` (guest) · user ter-authentikasi
  di-redirect dari `/login` · root `/` guest flag on → SAML (regresi) / flag off →
  `/login` · `redirectGuestsTo` flag off → `/login` · halaman denied prop `ssoEnabled`
  per flag · smoke `positions/tree`.
- Existing (gratis): attempt/throttle/landing/ban lewat `RoleAwareLoginTest`,
  `AuthenticationTest`, `EmployeeBanLoginTest` — tidak berubah, tetap hijau.

## Tersisa untuk Implementer

- Styling persis halaman login (ikuti pola `saml/denied.tsx` + komponen ui existing).
- Penempatan file test sesuai konvensi suite.
