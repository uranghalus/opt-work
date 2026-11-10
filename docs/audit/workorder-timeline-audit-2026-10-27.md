# Audit frontend–backend halaman Timeline — 2026-10-27

Audit menyeluruh `work-orders/timeline` (metode di bawah permintaan pengguna)
setelah migrasi ke CalendarKit Pro. Loop pengujian yang dipakai sebagai
kebenaran (diagnosing-bugs: feedback loop dulu, teori kemudian):

1. `php artisan test --filter=WorkOrderVisibilityTest` — pengaman kontrak
   payload (`events.0.id`) + skala visibilitas D1 → **4 passed, 54 assertions**.
2. `npx tsc --noEmit` → **0 error** (setelah perbaikan di bawah).
3. `npm run build` → **hijau** (graph modul calendarkit+tema benar).

## Kesesuaian kontrak frontend ↔ backend

| Poin | Sisi backend (`WorkOrderController::timeline`) | Sisi frontend (`timeline.tsx`) | Status |
|---|---|---|---|
| Props halaman | `events`, `totalScheduled`, `today`, `range{start,end,view}` | `PageProps` identik | ✅ |
| `extendedProps` | `nomor_wo`, `title`, `status`, `category`, `deadline_state`, `deadline_label`, `department_name` | `TimelineEventProps` identik | ✅ |
| Nilai `deadline_state` | `on_track` / `due_soon` / `overdue` / `escalated` (deadlineState() baris ~265–292) | union `DeadlineState` identik; peta warna lengkap keempatnya | ✅ |
| Semantik `end` | end-exclusive (`whereBetween(..., $end->subDay())`) | all-day CalendarKit end-exclusive (D2) | ✅ |
| Rentang server | cap 62 hari, fallback minggu berjalan, `?week=` legacy tetap dilayani; view lama `dayGridWeek/dayGridMonth` tetap echo | window maksimal: month = 1 bulan (≤31 hari) — selalu di bawah cap | ✅ |
| "Hari ini" | server (Asia/Makassar, D2) dikirim sebagai `today` | seed fokus tanggal dari `range.start`, `timezone="Asia/Makassar"` | ✅ |
| Visibilitas | `visibleFor` (requester / hod+manager / pelaksana) | tidak ada logika visibilitas di client | ✅ |

## Bug yang ditemukan & diperbaiki

1. **Tipe `id` mengkhianati tipe (mismatch number vs string)** — server mengirim
   `$workOrder->getKey()` (int), frontend mengetik `id: string` dan CalendarKit
   `CalendarEvent.id: string`. Nilai dipakai mentah. **Perbaikan**: `String(event.id)`
   di `toCalendarEvents`; test kontrak di sisi server tidak tersentuh.
2. (Sudah diperbaiki putaran sebelumnya, dicatat untuk konteks: window kosong
   tidak lagi me-unmount scheduler; baris departemen bertahan via ref.)

## Temuan minor

- ~~Label deadline dari server campur bahasa (`'On track'` vs `'Telat H+x'` /
  `'Eskalasi H+x'`).~~ **Diperbaiki 2026-10-27**: `'On track'` → `'Sesuai jadwal'`
  di `deadlineState()` (dua kemunculan), mengikuti konvensi label Indonesia
  yang sudah dipakai `DeadlineRail` (preseden F2 di TODO.md). Verifikasi:
  `WorkOrderVisibilityTest` 4 passed / 54 assertions (test mengunci label
  `'Deadline besok', tidak menyentuh label on_track).
- `resolveTimelineRange` memakai fallback `view = 'dayGridWeek'` (legacy) —
  frontend tidak mengonsumsi `range.view`, jadi echo legacy aman untuk link lama.
- Route `url` per event dihitung server-side via `route('work-orders.show')` —
  frontend hanya meng-link — tidak ada URL hard-coded di client. ✅

## Putaran audit UI kedua (ui-ux-pro-max) — 2026-10-27

Loop yang dipakai kembali sama: test kontrak/tsc/build (semua hijau) + tool
search skill (`ux` dan `color` domain) sebagai peta penyakit umum.

### Kontrak backend ↔ frontend (re-konfirmasi, tuntas)
- Label `'Sesuai jadwal'` hasil perbaikan tampil lewat jalur yang sama
  (`deadlineState()` → `deadline_label` → chip meta) tanpa transformasi client.
- Cast `id: String(event.id)` aktif; tsc menegaskan tidak ada penyimpangan tipe.
- Kombinasi window frontend (day=1, week=7, month≤31) tetap di bawah cap 62 hari.

### Temuan UI yang diperbaiki putaran ini
1. **Kontras chip gagal AA** — teks putih di atas warna deadline: `on_track`
   3,46:1 dan `due_soon` 3,18:1 (butuh ≥4,5:1 untuk teks 11–12px). Palette
   digelapkan dan **diukur ulang dengan kalkulasi luminans**: 5,02/5,02/4,83/8,24 — semuanya lolos.
2. **Teks chip 10px** di bawah lantai keterbacaan (anti-pattern "text <12px
   body") — `wos-chip-no` dan `wos-chip-meta` dinaikkan ke 0,6875rem (11px).

### Fitur UI yang sudah sesuai (checklist pre-delivery)
- Nama aksesibel chip memuat info penuh (nomor, judul, status, deadline,
  departemen) → truncation bisa disingkap lewat keyboard/screen reader.
- Chip = `<a>` sungguhan → navigasi keyboard, middle-click, dan new-tab jalan.
- Fokus terlihat (`outline` ring token); hover 160ms, `prefers-reduced-motion`
  mematikan animasi; target sentuh 44px di pointer coarse.
- `aria-live` hitungan WO, `aria-busy` saat reload parsial, empty state `role="status"
yang tidak membongkar scheduler (baris departemen bertahan).
- Tidak ada scroll horizontal di level halaman — grid menggulir di dalam region.
- Mode gelap mengikuti `.dark` ancestor lewat tema token CalendarKit.

## Kesimpulan

Frontend dan backend halaman Timeline **sudah sesuai** pada seluruh kontrak yang
dipakai runtime; satu bug tipis (tipe `id`) diperbaiki, satu catatan minor
(label campur bahasa) turut dirapikan. Skala visibilitas, semantik tanggal
WITA, dan cap rentang server tidak berubah dari standar 2026-10-24.
