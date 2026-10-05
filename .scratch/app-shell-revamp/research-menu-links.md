# Research — Kebutuhan Link Menu untuk Shell OptiWorks

**Tanggal:** 2026-10-05
**Metode:** audit langsung ke sumber primer repo (routes, middleware, komponen nav) — bukan ringkasan sekunder.
**Catatan substitusi:** skill `/research` menyarankan background agent; harness ini tidak punya subagent tool, jadi audit dijalankan in-thread (disclosed).

## 1. Route yang benar-benar ada saat ini

| Sumber | Route | Catatan |
| --- | --- | --- |
| `routes/web.php` | `dashboard` (`/dashboard`) | Inertia page `dashboard` |
| `routes/web.php` | `notifications.index` (`/notifications`), `notifications.read`, `notifications.read-all` | NotificationController; dipakai bell + badge unread |
| `routes/web.php` | `saml.*` | Login/logout SSO-only (tidak ada form login) |
| `routes/settings.php` | `profile.edit`, `security.edit`, `appearance.edit` | `/settings/profile|security|appearance` |
| `routes/tenant.php` | `divisions.*` (`/{tenant}/divisions`) | middleware `permission:division.read` |
| `routes/tenant.php` | `departments.*` (`/{tenant}/departments`) | middleware `permission:department.read` |
| `routes/tenant.php` | `positions.*` (`/{tenant}/positions`) | middleware `permission:employee.read` |
| `routes/tenant.php` | `employees.*` (`/{tenant}/employees`) | middleware `permission:employee.read` |
| `routes/tenant.php` | `work-orders.index` (`/{tenant}/work-orders`), `work-orders.create`, `work-orders.store`, `work-orders.show` | middlewares `permission:work-order.read|create` |

**Belum ada route** (dari PRD/DESIGN_BRIEF, jangan dikarang di nav): Daily Work (D01), Work Data (WD01/WD02), Extend (W09–W11), Admin Users & Roles (A01).

## 2. Kontrak data yang tersedia untuk nav (shared props)

`app/Http/Middleware/HandleInertiaRequests.php`:

- `auth.user` — identitas (nama, email, avatar).
- `unreadNotificationsCount` — badge bell (closure, per request).
- `activeTenant` — cabang aktif dari path (`tenant()?->getTenantKey() ?? user->tenant_id`).
- `can` — **hanya** `division.read`, `department.read`, `employee.read`. Tidak ada `work-order.*` di shared props.
- `sidebarOpen` — cookie `sidebar_state` (primitive shadcn sudah menulis cookie ini).
- `flash.success`.

Temuan penting: page master-data memakai `can.create`/`can.update` (override per-page dari controller), sedangkan `can` shared hanya memuat 3 key `.read`. Artinya **gating grup Master Data hanya boleh memakai `*.read`**; aksi create/update tetap urusan page.

## 3. Konsumen nav yang ada di kode

- `resources/js/components/nav-items.tsx` — `mainNavItems(activeTenant)` + `utilityNavItems`; berisi placeholder `href: dashboard()` untuk Daily Work, Work Data, Admin (belum ada route).
- `resources/js/components/app-sidebar.tsx` — `masterDataNavItems(can, activeTenant)` (Department, Divisi, Karyawan) — versi lama rute sidebar.
- `resources/js/components/app-header.tsx`, `icon-rail.tsx`, `bottom-nav.tsx` — versi shell header (uncommitted).
- `resources/js/layouts/app-layout.tsx` — memilih `app-header-layout` sebagai template aktif.
- `resources/js/layouts/settings/layout.tsx` — nav internal Pengaturan (Profil/Keamanan/Tampilan) yang hidup di dalam konten.

## 4. Kebutuhan link menu (hasil sintesis)

**Utama — Operasional:** Beranda, Work Order, Daily Work*, Work Data*, Notifikasi (badge unread).
**Data Master (permission-gated, tenant-scoped):** Department, Divisi, Posisi, Karyawan.
**Sistem:** Admin / Pengguna & Role* (role `Super Admin`, belum ada route), Pengaturan (Profil, Keamanan, Tampilan).
**Utility:** Bantuan* (segera hadir), Keluar (POST logout).

`*` = belum ada route; tampil sebagai item terkait modul yang belum dibangun (placeholder ke dashboard) **atau** ditahan sampai route-nya ada. Keputusan: item tanpa route nyata tetap tampil hanya jika ada di `mainNavItems` existing (Daily Work, Work Data, Admin) agar IA tidak berubah diam-diam, diberi penanda "segera" bila perlu.

**Fakta konteks lain yang mengikat shell:**

- Multi-cabang via path `/{tenant}/...` → satu cabang aktif per sesi; `activeTenant` = path segment → cabang switcher (issue 09) bernilai tinggi tapi menjaga header tetap ringkas.
- Mobile: field worker (Sari) memakai telepon; `PRODUCT.md` butuh bottom-nav ≤5 + target 44px.
- Bell + badge unread sudah nyata (`unreadNotificationsCount`), jadi shell wajib mempertahankannya.
- Tema: `useAppearance()` menyediakan `light|dark|system`; `initializeTheme()` dipanggil di `app.tsx`. `app.css` saat ini **mengunci tema terang** (blok `.dark` menyalin nilai light) → redesign harus membuka dark mode sungguhan (permintaan user).
- `resources/js/layouts/app/app-sidebar-layout.tsx` (kandidat layout terpilih) memakai primitive shadcn `Sidebar` lengkap: `SidebarProvider` (cookie state + shortcut Ctrl/Cmd+B), `collapsible="icon"`, `variant` sidebar/floating/inset, sheet untuk mobile, `SidebarMenuBadge`, tooltip collapsed. Stack guideline ui-ux-pro-max (shadcn) eksplisit: pakai `Sidebar` untuk navigasi utama, jangan bikin sidebar custom.
