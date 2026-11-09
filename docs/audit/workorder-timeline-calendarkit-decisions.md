# Timeline Work Order — keputusan migrasi ke CalendarKit Pro (2026-10-27)

Dokumen ini **supersede** sebagian keputusan di
`workorder-timeline-vis-decisions.md` (vis-timeline, 2026-10-27) dan
menyatakan mana yang dipertahankan dari audit UI 2026-10-24
(`workorder-timeline-design-decisions.md` D1–D12).

getUserRequest: atas permintaan langsung pemilik produk, halaman
`work-orders/timeline` kini memakai **CalendarKit Pro** (`calendarkit-pro`
^2.0.0, MIT, React 18/19) dengan view **resource** sebagai engine scheduler.

## Yang berpindah ke CalendarKit

| Aspek | Keputusan |
|---|---|
| Engine | `<Scheduler>` dari `calendarkit-pro`; stylesheet bawaannya di-import langsung (`calendarkit-pro/styles.css`) |
| View default | `month` — grid murni tanggal (supersede per 2026-10-27: sumbu jam dihilangkan dari tampilan default atas permintaan produk; faktanya resource view di plugin ini selalu memakai sumbu jam per kolom — `ResourceView` → `gridHours`, tidak ada mode date-only). Switcher tetap aktif bila perlu view lain. |
| Chip WO | `components.EventContent` merender `<a>` sungguhan ke detail WO (middle-click & new-tab tetap jalan, D3) |
| Warna chip | mengikuti `deadline_state` yang sudah dihitung server (peta `DEADLINE_COLORS`) |
| Read-only | prop `readOnly` — drag-reschedule tetap fase 2 |
| Window server | `?start=&end=&view=` tetap dioperasikan lewat `onDateChange`/`onViewChange` (D2: end-exclusive; koordinat WITA via `timezone="Asia/Makassar"`) |
| "Today" | milik server (seed `date` dari `range.start`), bukan jam browser |
| Zoom | granularitas day/week/month via view switcher bawaan |

## Yang supersede dari standar vis-timeline

- **D5–D7 (zoom Ctrl+scroll, tombol −/+, hint pan)** — CalendarKit tidak punya
  zoom pixel-level; ganti granularitas view day/week/month.
- **D8 (pan horizontal memicu window baru)** — sekarang dipicu perpindahan
  fokus tanggal/view, bukan geseran bebas.
- **D10/D11 (`+N WO lagi`, collapse baris departemen)** — CalendarKit menangani
  penumpukan chip sendiri di dalam sel dan tidak menyediakan collapse baris;
  keduanya dilepas dan diserahkan ke plugin.
- **Struktur template string vis** (`woItemTemplate`, `deptGroupTemplate`,
  item background band) — dihapus; chip kini adalah komponen React `WoChip`.

## Yang dipertahankan

- Payload controller `WorkOrderController::timeline` **tidak berubah**
  (bentuk peristiwa FullCalendar-aware tetap; `WorkOrderVisibilityTest`
  lanjut menjadi pengaman).
- Skala visibilitas, scope kategori, semantics `?view=day|week|month` (bucket)
  pada link lama tetap valid; `agenda`/`resource` tidak mempunyai bucket di
  server (param `view` ditiadakan).
- Empty state, skeleton saat navigasi, dan teks hitungan `totalScheduled`.

## Catatan runtime (dari sesi debug 2026-10-27)

Optimizer dev server pernah gagal men-discover dependensi dinamis halaman ini
(dep `vis-timeline/standalone` saat itu) sehingga halaman tampil tanpa grid.
Karena itu `calendarkit-pro` didaftarkan eksplisit di `optimizeDeps.include`
(`vite.config.ts`) supaya ikut dioptimasi saat server start — jangan dihapus
selama pendekatan dynamic-import halaman masih dipakai.
