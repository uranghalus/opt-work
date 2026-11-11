# Login Lokal via Fortify (SAML off sementara) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tester dapat login email+password di halaman `/login` (Fortify bawaan) sementara SAML diparkir lewat flag `AUTH_SSO_ENABLED` — reversible tanpa edit kode — plus fix route `positions/tree` yang 500 dan pelengkapan akun demo `staf_admin_dept`.

**Architecture:** Fortify headless yang sudah terpasang tetap satu-satunya mesin login (controller vendor + `LoginLanding` role-aware: login → home tenant akun → `/{kode}/dashboard`, sama dengan flow SAML tanpa SSO). Kita hanya: aktifkan route view `GET /login`, buat halaman Inertia baru, kondisikan redirect guest/root pada flag env, lengkapi seeder, fix bug route.

**Tech Stack:** Laravel 13.34 + Fortify 1.40 · Inertia React 3 (useForm, tanpa ziggy) · Pest (sqlite `:memory:`) · shadcn ui (`@/components/ui/*`) · desain via ui-ux-pro-max (ux + shadcn searches 2026-10-26).

**Spec:** `.scratch/local-login/spec.md` (rev 4, disetujui user) — argumen dari spec, baca keduanya.

## Global Constraints

- Nama route `login.store` / `logout` **tidak boleh berubah** — 20+ test existing (`RoleAwareLoginTest`, `AuthenticationTest`, `EmployeeBanLoginTest`, `EmployeeAccountLinkTest`) POST ke sana.
- Default flag: `config('auth.sso.enabled')` = `true` (env `AUTH_SSO_ENABLED` tidak diset) — perilaku existing semua test/CI tidak berubah.
- Route SAML (`saml.redirect/acs/denied/sls/slo/metadata`) tidak dihapus, tidak diblokir.
- `config('fortify.features')` tetap `[]` — tidak ada fitur Fortify baru (register/reset/2FA).
- Frontend: POST ke **string URL** `/login` (wayfinder tidak generate route vendor; ziggy tidak terpasang).
- PHP: curly braces + type hints (aturan Boost); `vendor/bin/pint --dirty --format agent` sebelum commit PHP.
- Test Pest pola suite: helper global `createTestTenant()`, `createUser()`, `Tenant::forgetCurrent()` di `afterEach`.
- `npm run build` = wayfinder + tsc + vp build (satu pintu verifikasi frontend).
- Seeder: idempoten (`firstOrCreate` pada kunci natural), ikuti pola `DemoSeeder`.

## Review Focus

Kondisi yang tidak di-exercise test task mana pun, yang paling mungkin menggigit:

1. **Flag on (default) harus mempertahankan SEMUA redirect existing** — regrese paling mahal bila root closure/`redirectGuestsTo` ditulis salah. → Test pin: Task 2 ("sends guests to the sso portal while sso is enabled") + full suite Task 7.
2. **`GET /login` untuk user yang sudah login** — harus redirect (middleware `guest:web`), bukan error/halaman dobel. → Test pin: Task 1 ("bounces authenticated users away from the login screen").
3. **Flag dibaca di dua titik berbeda waktu** (closure `redirectGuestsTo` — lazy saat exception; closure root `/` — saat request) — keduanya harus resolve `config('auth.sso.enabled')` tanpa crash. → Test pin: Task 2 (dua path: `route('home')` dan `/tenants`).
4. **Halaman login POST ke `/login` persis** — URL salah = 404 diam saat submit (tidak ketangkap test render). → Pin: Task 7 full suite (test POST existing) + review manual `form.post('/login')` di Task 3.
5. **Nama route `login` baru tidak menabrak nama route existing** — tabrakan diam (route() mengembalikan match pertama). → Pin: Task 1 step `route:list --path=login` (harus persis 2 route: GET `login`, POST `login.store`).
6. **DemoSeeder idempoten setelah penambahan person** — re-run `db:seed` tidak boleh duplikat/mengubah dataset lain. → Pin: Task 5 (DemoSeederTest re-run tetap hijau).

---

### Task 1: Aktifkan `GET /login` (Fortify views) + render test

**Files:**
- Modify: `config/fortify.php:130` (`'views' => false` → `'views' => true`)
- Create: `tests/Feature/Auth/LocalLoginTest.php`

**Interfaces:**
- Consumes: `Fortify::loginView` yang sudah terdaftar di `app/Providers/FortifyServiceProvider.php:49` (render Inertia `auth/login`, props `canResetPassword`, `status`).
- Produces: route `GET /login` bernama **`login`** (middleware `guest:web`) + komponen Inertia **`auth/login`** — dipakai Task 2 (redirect target) dan Task 3 (halaman).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Tenant;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '9001',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('renders the local login screen for guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

it('bounces authenticated users away from the login screen', function () {
    $user = createUser();

    $this->actingAs($user)->get('/login')->assertRedirect();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Auth/LocalLoginTest.php`
Expected: FAIL — test 1 `404` (route `login` belum terdaftar karena `views => false`).

- [ ] **Step 3: Set `'views' => true` di `config/fortify.php`**

Satu baris (line 130). Fortify lalu mendaftarkan `GET /login` → `AuthenticatedSessionController@create` → callback `Fortify::loginView`. View route lain tetap tak terdaftar (ter-gate `Features::enabled(...)`, semua fitur off).

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Auth/LocalLoginTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Verifikasi route & commit**

Run: `php artisan route:list --path=login`
Expected: tepat 2 route — `GET /login` (name `login`) dan `POST /login` (name `login.store`). Tidak ada nama `login` sebelumnya (Review Focus #5).

```bash
git add config/fortify.php tests/Feature/Auth/LocalLoginTest.php
git commit -m "feat(auth): enable Fortify GET /login for local login screen"
```

---

### Task 2: Flag `AUTH_SSO_ENABLED` + redirect guest/root kondisional

**Files:**
- Modify: `config/auth.php` (tambah key `sso` setelah `super_admin_email`, ~line 35)
- Modify: `bootstrap/app.php:52-53` (`redirectGuestsTo`)
- Modify: `routes/web.php:21-24` (closure root `/`)
- Modify: `.env.example` (section SAML, ~line 73)
- Test: `tests/Feature/Auth/LocalLoginTest.php` (tambah 2 test)

**Interfaces:**
- Consumes: route `login` (Task 1).
- Produces: **`config('auth.sso.enabled')`** (bool, default `true`) — dipakai Task 6 (prop halaman denied).

- [ ] **Step 1: Write the failing tests** (tambahkan ke `LocalLoginTest.php`)

```php
it('sends guests to the sso portal while sso is enabled', function () {
    $this->get(route('home'))->assertRedirect(route('saml.redirect'));
});

it('sends guests to the local login screen when sso is disabled', function () {
    config(['auth.sso.enabled' => false]);

    $this->get(route('home'))->assertRedirect(route('login'));

    // redirectGuestsTo (path middleware) mengikuti flag yang sama.
    $this->get('/tenants')->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Auth/LocalLoginTest.php --filter="sso"`
Expected: FAIL — test kedua: redirect masih ke `saml.redirect`.

- [ ] **Step 3: Implement**

`config/auth.php` — setelah `'super_admin_email' => ...`:

```php
    /*
    |--------------------------------------------------------------------------
    | Single Sign-On (SAML) Switch
    |--------------------------------------------------------------------------
    |
    | Set false to park the SSO flow temporarily: guests are sent to the
    | local Fortify login screen instead of the portal. Default true keeps
    | today's behavior. Re-enable by flipping the env value only.
    |
    */

    'sso' => [
        'enabled' => env('AUTH_SSO_ENABLED', true),
    ],
```

`bootstrap/app.php` — ganti baris 52-53:

```php
        // SSO on: guests go straight to the portal. Flag off (AUTH_SSO_ENABLED=false):
        // guests land on the local Fortify login screen instead.
        $middleware->redirectGuestsTo(fn () => route(
            config('auth.sso.enabled', true) ? 'saml.redirect' : 'login'
        ));
```

`routes/web.php` — ganti baris 22-24 (sisanya closure root tetap):

```php
    if (! auth()->check()) {
        return redirect()->route(config('auth.sso.enabled') ? 'saml.redirect' : 'login');
    }
```

`.env.example` — sebelum section SAML (line ~73):

```bash
# --- Local login switch -----------------------------------------------------
# Set false to park SAML SSO temporarily: guests land on the local login
# screen (Fortify /login) instead of the portal. Default true = SSO as usual.
AUTH_SSO_ENABLED=true
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Auth/LocalLoginTest.php`
Expected: PASS (4 tests) — termasuk regresi default (Review Focus #1).

- [ ] **Step 5: Pint + commit**

Run: `vendor/bin/pint --dirty --format agent` → `{"result":"passed"}`

```bash
git add config/auth.php bootstrap/app.php routes/web.php .env.example tests/Feature/Auth/LocalLoginTest.php
git commit -m "feat(auth): park SAML behind AUTH_SSO_ENABLED flag, guests fall back to local login"
```

---

### Task 3: Halaman login `auth/login.tsx` (lead designer: ui-ux-pro-max)

**Files:**
- Create: `resources/js/pages/auth/login.tsx`

**Interfaces:**
- Consumes: `POST /login` (`login.store`, Fortify vendor — throttle 5/menit, errors Inertia bag `email`/`password`) · komponen ui `button.tsx`, `input.tsx`, `label.tsx`, `checkbox.tsx` · pola token halaman standalone `saml/denied.tsx` (`bg-canvas`, `bg-card`, `text-ink`, `text-ink-muted`).
- Produces: komponen Inertia **`auth/login`** (nama harus persis = assertion Task 1). Props dari `Fortify::loginView`: `canResetPassword: boolean`, `status?: string`.

**Keputusan desain (dari ui-ux-pro-max: `login form error validation` + `accessible authentication` (ux) + `login form input label` (shadcn)):**
1. Layout: centered card `max-w-md` di `bg-canvas` — satu-satunya pola halaman guest existing (`saml/denied.tsx`); brand icon + judul "Masuk ke OptiWorks" untuk orientasi.
2. A11y auth (WCAG 2.2): `autocomplete="email"` / `autocomplete="current-password"`; paste tidak diblokir (tanpa `onPaste` guard).
3. Label visible dengan pasangan `htmlFor`/`id` (`Email`, `Password`) — bukan placeholder-only.
4. Error inline per field: `<p role="alert">` di bawah input invalid (`form.errors.email` / `form.errors.password`) — diumumkan screen reader, bukan visual-only.
5. Feedback submit: `disabled={form.processing}` + label "Memproses…" saat processing.
6. Banner `status` (session flash) bila ada, di atas form.
7. Checkbox "Ingat saya" + Label (menitip `remember` — Fortify mendukung).
8. Tanpa animasi dekoratif (konsisten denied.tsx; `prefers-reduced-motion` aman by default).

- [ ] **Step 1: Create `resources/js/pages/auth/login.tsx`** sesuai keputusan desain di atas — `useForm({ email, password, remember })`, `form.post('/login')`.

- [ ] **Step 2: Verifikasi build**

Run: `npm run build`
Expected: exit 0 (wayfinder + tsc + vp build).

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/auth/login.tsx
git commit -m "feat(auth): local login screen (Inertia) posting to Fortify /login"
```

---

### Task 4: Fix import `PositionTreeController` + smoke test

**Files:**
- Modify: `routes/web.php` (tambah import di blok `use` atas, ~line 3-17)
- Create: `tests/Feature/PositionTreeTest.php`

**Interfaces:**
- Consumes: helper `createTestTenant`/`createUser` (Pest.php) · route `positions/tree` di grup `{tenant}` (`auth`, `tenant.access`) tanpa gate internal (`PositionTreeController::index` hanya butuh konteks tenant).
- Produces: route `positions/tree` sehat (`route:list` tidak crash lagi).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Tenant;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->hq = createTestTenant([
        'optigate_company_id' => '9001',
        'code' => 'hq',
        'name' => 'Head Office',
    ]);
    (new RoleAndPermissionSeeder)->run();
});

afterEach(function () {
    Tenant::forgetCurrent();
});

it('renders the position tree page', function () {
    $user = createUser(['tenant_id' => $this->hq->id]);
    $user->assignRole('karyawan');

    $this->actingAs($user)
        ->get('/hq/positions/tree')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('positions/tree'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/PositionTreeTest.php`
Expected: FAIL — 500 (`Class "PositionTreeController" not found` saat route di-resolve).

- [ ] **Step 3: Tambahkan import di `routes/web.php`**

```php
use App\Http\Controllers\MasterData\PositionTreeController;
```

- [ ] **Step 4: Run test to verify it passes + route:list sehat**

Run: `vendor/bin/pest tests/Feature/PositionTreeTest.php` → PASS (1 test)
Run: `php artisan route:list --path=positions` → exit 0, tanpa ReflectionException.

- [ ] **Step 5: Pint + commit**

Run: `vendor/bin/pint --dirty --format agent` → `{"result":"passed"}`

```bash
git add routes/web.php tests/Feature/PositionTreeTest.php
git commit -m "fix(routes): import MasterData PositionTreeController (positions/tree 500)"
```

---

### Task 5: Lengkapi seeder demo — akun `staf_admin_dept`

**Files:**
- Modify: `database/seeders/DemoSeeder.php` (tambah 1 entri di daftar person, ~line 129-143)
- Test: `tests/Feature/DemoSeederTest.php` (sesuaikan hitungan bila assert angka pasti)

**Interfaces:**
- Consumes: `RoleAndPermissionSeeder::SYSTEM_ROLES` (role `staf_admin_dept` sudah ada di katalog + `roleDefaults`) · pola person DemoSeeder (`email`/`nama`/`role`/`is_hod`/`dept`/`position`/`division`/`phone`, password `password`, firstOrCreate idempoten).
- Produces: akun demo per role untuk testing lokal login — matrix akun (semua password `password`):

| Role | Email | Nama |
|---|---|---|
| super_admin | superadmin@appdutamall.com | Super Admin (flag, tanpa tenant → admin panel) |
| admin_tenant | admin.tenant@appdutamall.com | Dedi Kurniawan |
| general_manager | gm@appdutamall.com | Hendra Gunawan |
| deputy_general_manager | dgm@appdutamall.com | Iwan Pranowo |
| hod | budi.hod@appdutamall.com (+3 HOD lain) | Budi Santoso (MTC) |
| staf_admin_dept | **fajar.staf@appdutamall.com (BARU)** | Fajar Nugroho (MTC) |
| team_leader | wawan.tl@appdutamall.com | Wawan Setiawan (MTC) |
| karyawan | sari.wulandari@appdutamall.com (+4 lain) | Sari Wulandari |
| field_staff | andi.wijaya@appdutamall.com | Andi Wijaya |
| viewer | viewer@appdutamall.com | Titi Rahmawati |

- [ ] **Step 1: Run DemoSeederTest sebagai baseline**

Run: `vendor/bin/pest tests/Feature/DemoSeederTest.php`
Expected: hijau (6 tests) — catat hitungan yang di-assert (jumlah karyawan/akun) untuk Step 3.

- [ ] **Step 2: Tambahkan entri person `staf_admin_dept` di `DemoSeeder.php`**

```php
'fajar' => ['email' => 'fajar.staf@appdutamall.com', 'nama' => 'Fajar Nugroho', 'role' => 'staf_admin_dept', 'is_hod' => false, 'dept' => 'MTC', 'position' => 'Staf Admin', 'division' => null, 'phone' => null],
```

- [ ] **Step 3: Sesuaikan hitungan di `DemoSeederTest.php`** bila test meng-assert jumlah pasti (karyawan 15 → 16, dst.), lalu:

Run: `vendor/bin/pest tests/Feature/DemoSeederTest.php`
Expected: hijau — termasuk test idempoten re-run (Review Focus #6).

- [ ] **Step 4: Pint + commit**

Run: `vendor/bin/pint --dirty --format agent` → `{"result":"passed"}`

```bash
git add database/seeders/DemoSeeder.php tests/Feature/DemoSeederTest.php
git commit -m "feat(demo): staf_admin_dept demo account for local-login testing"
```

---

### Task 6: Halaman denied — pintu balik ke login saat flag off

**Files:**
- Modify: `routes/web.php` (closure `saml.denied`, ~line 99-115 — tambah prop)
- Modify: `resources/js/pages/saml/denied.tsx` (prop `ssoEnabled` + href kondisional)
- Test: `tests/Feature/Auth/LocalLoginTest.php` (tambah 2 test)

**Interfaces:**
- Consumes: `config('auth.sso.enabled')` (Task 2).
- Produces: prop Inertia **`ssoEnabled: boolean`** di komponen `saml/denied`.

- [ ] **Step 1: Write the failing tests** (tambahkan ke `LocalLoginTest.php`)

```php
it('points the denied screen back to the local login when sso is disabled', function () {
    config(['auth.sso.enabled' => false]);

    $this->withSession(['saml.denial_reason' => 'no_role'])
        ->get('/saml/denied')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('saml/denied')
            ->where('ssoEnabled', false));
});

it('keeps the denied screen pointing at the sso portal by default', function () {
    $this->withSession(['saml.denial_reason' => 'no_role'])
        ->get('/saml/denied')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('ssoEnabled', true));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Auth/LocalLoginTest.php --filter="denied"`
Expected: FAIL — prop `ssoEnabled` belum ada.

- [ ] **Step 3: Implement**

`routes/web.php` closure denied — props jadi:

```php
        return inertia('saml/denied', [
            'message' => $messages[$reason] ?? 'Anda tidak dapat mengakses sistem saat ini. Hubungi administrator.',
            'ssoEnabled' => (bool) config('auth.sso.enabled', true),
        ]);
```

`resources/js/pages/saml/denied.tsx` — props + button:

```tsx
type PageProps = {
    message: string;
    ssoEnabled: boolean;
};

export default function SamlDenied({ message, ssoEnabled }: PageProps) {
```

Button "Coba masuk lagi": `href={ssoEnabled ? '/saml/redirect' : '/login'}`.

- [ ] **Step 4: Run test to verify it passes + build**

Run: `vendor/bin/pest tests/Feature/Auth/LocalLoginTest.php` → PASS (semua)
Run: `npm run build` → exit 0.

- [ ] **Step 5: Pint + commit**

Run: `vendor/bin/pint --dirty --format agent` → `{"result":"passed"}`

```bash
git add routes/web.php resources/js/pages/saml/denied.tsx tests/Feature/Auth/LocalLoginTest.php
git commit -m "feat(auth): denied screen offers local login path while sso is parked"
```

---

### Task 7: Verifikasi akhir + TODO.md

**Files:**
- Modify: `TODO.md` (section baru untuk kerja ini, centang selesai)

- [ ] **Step 1: Full suite**

Run: `php artisan test --compact`
Expected: 0 failed, 8 skipped (2FA, pre-existing), semua test baru hijau.

- [ ] **Step 2: Build + lint**

Run: `vendor/bin/pint --dirty --format agent` → passed
Run: `npm run build` → exit 0

- [ ] **Step 3: Update TODO.md + commit**

Tambahkan section "Login lokal via Fortify (SAML parkir)" — DONE, catat file yang disentuh + matrix akun demo + langkah manual user:

```
Sisa manual (user): set AUTH_SSO_ENABLED=false di .env dev, lalu login via /login
dengan akun DemoSeeder (password: password) untuk testing fitur.
```

```bash
git add TODO.md
git commit -m "docs: log local-login-via-fortify session in TODO"
```

- [ ] **Step 4: Laporkan langkah manual ke user**

User set `AUTH_SSO_ENABLED=false` di `.env`, jalankan `php artisan config:clear`, buka `/login`, login dengan akun demo (`password`). Re-enable SAML: hapus/kembalikan `true` di `.env`.
