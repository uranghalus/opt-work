# Design — OptiWorks Pagination System (2026-10-23)

---
created: 2026-10-23
status: draft (menunggu approval)
---

## Intent & Why

Seluruh daftar berpaginasi di OptiWorks harus terasa sebagai satu sistem, bukan kumpulan footer yang dibuat berbeda-beda. Referensi visual utama adalah Shadcn Base UI Pagination: komposisi `nav → ul → li`, previous/next, nomor halaman, dan ellipsis. Referensi material dan token utama adalah `DESIGN.md` — calm, card-based, operational, bilingual-safe, dan tidak boleh mengorbankan state filter atau aksesibilitas.

Audit awal menemukan empat pola implementation yang berbeda, URL state yang hilang pada `work-orders`, target sentuh di bawah 44px pada beberapa footer, serta payload `divisions`/`notifications` yang belum bisa dijangkau dari UI. Solusi harus mengurangi duplikasi, menjaga URL server-side yang sudah benar, dan cocok dengan product density (desktop HOD serta mobile field worker).

## Approaches (dipertimbangkan → dipilih)

1. **A. Pagination system end-to-end — DIPILIH.** Primitive visual Shadcn di layer UI dan satu komponen aplikasi yang memahami metadata paginator Laravel, URL halaman, window/ellipsis, Inertia navigation, mobile summary, serta variant `surface`/`console`.
2. Ditolak **B. Skin per halaman** — perubahan visual cepat, tetapi logic URL, window halaman, label, state aktif, dan accessibility tetap berbeda-beda serta继续 diduplikasi.
3. Ditolak **C. Primitive Shadcn saja** — composition closer to upstream, tetapi setiap halaman masih harus membangun window, ellipsis, filter-state URL, mobile behavior, dan console styling sendiri.

## Goals

- Semua permukaan berpaginasi memakai satu visual grammar dan satu sumber behavior.
- Current page selalu jelas secara visual dan semantik (`aria-current="page"`).
- Nomor halaman dibatasi dengan window + ellipsis; tidak pernah merender seluruh daftar halaman.
- Previous/next, first/last, nomor halaman, dan summary memakai URL dari Laravel agar filter/search/sort tetap tersimpan.
- Inertia navigation mempertahankan scroll dan state; tidak memaksa full reload.
- Mobile tidak horizontal-scroll dan semua control punya target sentuh minimal 44×44px.
- Light, dark, dan console stratum mengikuti token yang sudah diotorisasi di `DESIGN.md`.
- Halaman yang server-side paginate tetapi sebelumnya tidak bisa dijangkau (`divisions`, `notifications`) ikut mendapatkan pagination UI.

## Non-Goals

- Tidak mengubah query, sorting, filtering, atau ukuran halaman di backend.
- Tidak menambah dependency Base UI baru; project memakai Radix Button, sehingga composition Shadcn diadaptasi ke primitive lokal.
- Tidak mengubah copy atau kapabilitas di luar pagination.
- Tidak membangun DataTable generic atau虚拟 infinite scroll.
- Tidak merapikan bagian halaman selain footer/area pagination.

## Component Architecture

### 1. Primitive UI — `resources/js/components/ui/pagination.tsx`

Adaptasi komposisi Shadcn Base UI/current New York:

- `Pagination` → semantic `nav` dengan `aria-label` yang dapat dioverride.
- `PaginationContent` → semantic `ul`.
- `PaginationItem` → semantic `li`.
- `PaginationLink` → visual anchor/button primitive dengan state `active`, `disabled`, dan focus ring.
- `PaginationPrevious`, `PaginationNext`, `PaginationEllipsis` → label lokal dan ikon Lucide.

Primitive ini hanya menangani composition/visual; tidak mengetahui Laravel atau Inertia.

### 2. Komponen aplikasi — `resources/js/components/pagination/DataPagination.tsx`

Satu komponen menerima metadata length-aware paginator dari server:

```ts
type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    first_page_url?: string | null;
    last_page_url?: string | null;
};
```

Contract:

- `meta`: metadata paginator server.
- `itemLabel`: noun singular/plural dalam Bahasa Indonesia, misalnya `karyawan`, `department`, `izin`.
- `variant`: `surface` (default) atau `console`.
- `className`: ekstensi layout footprint.
- `ariaLabel`: nama nav, default `Paginasi {itemLabel}`.
- `showSummary`: default `true`; summary tetap dapat dimatikan untuk layout sangat sempit.
- URL untuk nomor halaman dibentuk dari `path` + query server bila tersedia. Agar tidak bergantung pada parse URL client-side, backend tetap menjadi sumber URL canonical.

### Window halaman

Desktop menampilkan maksimal 7 slot:

- first page;
- current page及其 satu tetangga kiri/kanan;
- last page;
- satu ellipsis pada masing-masing sisi yang terpotong.

Contoh: `1 … 4 5 6 … 12`. Pada jumlah halaman kecil, semua nomor ditampilkan tanpa ellipsis.

### Mobile

- Previous dan Next tetap eksplisit dan berbahasa Indonesia.
- Nomor halaman penuh disembunyikan pada `< sm` untuk mencegah overflow.
- Current page ditampilkan sebagai compact state `5 / 12`.
- Semua interactive control minimal 44×44px.

## Visual Direction

### Surface variant (default)

- Container: border top + `surface-raised/60`, mengikuti card registry; summary di kiri dan controls di kanan pada desktop, stack pada mobile.
- Inactive number: ghost-style, `surface-raised` hover, `ink-muted` label, hairline border.
- Current page: solid Execution Teal (`brand`), white text, subtle raised shadow; tidakpill-shaped.
- Previous/Next: outline/secondary surface, radius `rounded-lg`/`rounded-xl`, focus ring teal.
- Ellipsis: non-interactive, 32–36px optical width tetapi bukan hit target.
- Summary: caption, tabular numerals, live-polite text yang pendek.

### Console variant

- Digunakan pada Hak Akses/Peran tanpaGly violating the Vault Console stratum.
- Memakai `console-*` token; current page memakai `console-accent`, bukan primary gradient.
- Border memakai `console-border`, hover memakai `console-hover`, copy memakai `console-ink-muted`.

### Dark mode

Semua warna datang dari semantic token existing; tidak ada hex baru di komponen. Dark mode mengikuti authored palette yang sudah ada.

## Inertia & URL Contract

- Semua navigasi memakai `router.get(url, {}, { preserveScroll: true, preserveState: true })`.
- Tidak lagi memakai `window.location.href`.
- Backend tetap memakai `withQueryString()` agar search/filter/sort tetap ikut pada prev/next/number URL.
- Komponen tidak mengubah atau menyetel query filter; ia hanyaasl navigated ke URL server.
- Jika canonical metadata `path` tidak tersedia pada prop某 surface, backend dapat diharapkan sudah mengirim `prev_page_url`/`next_page_url`; nomor halaman diturunkan dari URL tersebut secara aman, dengan fallback ke path/query canonical.

## Surface Coverage

| Surface | Perubahan |
|---|---|
| `work-orders/index` | Ganti custom pagination/full reload dengan sistem bersama; summary ditambahkan. |
| `work-data/index` | Hapus footer lokal; pakai komponen bersama. |
| `departments/index` | Hapus footer lokal; pakai komponen bersama. |
| `positions/index` | Refactor `registry/PaginationFooter` menjadi shared app component; hapus label hard-coded `position`. |
| `employees/index` | Ganti previous/current/next lokal dengan sistem bersama; summary dipertahankan. |
| `roles/index` | Pakai variant console agar sesuai Vault Console stratum. |
| `settings/permissions/index` | Ganti footer lokal dengan sistem bersama. |
| `tenants/index` | Ganti footer lokal dengan sistem bersama. |
| `divisions/index` | Perbaiki akses metadata paginator dan tampilkan sistem bersama. |
| `notifications/index` | Tambahkan pagination UI agar seluruh riwayat dapat dijangkau. |

## Accessibility Acceptance Criteria

- `<nav>` memiliki nama aksesibel yang spesifik.
- Current page memakai `aria-current="page"` dan style teal/console accent.
- Previous/next yang disabled memakai native disabled semantics, bukan hanya opacity.
- Ellipsis dekoratif diberi accessible text yang tidak tergantung warna.
- Focus-visible ring 2px teal dengan offset 2px pada surface; console memakai accent yang kontras.
- Tab order mengikuti urutan visual; tidak ada keyboard trap.
- Target sentuh mobile minimal 44×44px.
- Tidak ada horizontal page scroll pada 375px.
- Summary diumumkan dengan `aria-live="polite"`, tetapi tidak memaksa focus pindah saat halaman berganti.

## Responsive Acceptance Criteria

- 375px: summary stack, previous/current/next tetap dalam satu baris compact tanpa overflow.
- 768px+: desktop window/ellipsis aktif.
- Text scale 200% tidak memotong label atau membocorkan layout.
- Dark dan light theme memakai token yang sama secara semantik.

## Verification

- TypeScript: `npm run types:check`.
- Frontend lint/build: `npm run check` dan `npm run build` bila build environment tersedia.
- PHP: `vendor/bin/pint --dirty --format agent` bila ada file PHP yang tersentuh; test LaravelAffected bila backend contract berubah.
- Visual QA: desktop, 375px, light/dark, surface/console; keyboard previous/current/next; verify URL filter state setelah pindah halaman.
- Regression: pastikan Work Order, Work Data, Department, Position, Employee, Role, Permission, Tenant, Division, dan Notification semuanya dapat mencapai halaman berikutnya dan halaman terakhir.

## Open / Builder Decisions

- **Sudah diputuskan oleh user:** gunakan Pagination system end-to-end.
- **Call builder:**_refresh_: gunakan satu shared app component dengan dua variant (`surface`, `console`); tidak membuat package/dependency baru.
- **Call builder:** tetap sediakan primitive `ui/pagination.tsx` hanya bila它 memberikan composition value; jika tidak, shared app component boleh encapsulate semantic nav/ul/li langsung. Rekomendasi: dua layer, primitive + app adapter, karena ini mengikuti Shadcn dan memudahkan usage berikutnya.
