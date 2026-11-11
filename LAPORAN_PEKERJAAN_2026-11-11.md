# Laporan Pekerjaan — 11 November 2026

## Ringkasan

Penerapan **Syncfusion React Gantt Chart** sebagai view jadwal kedua di modul Work Order.
Gantt sengaja menjadi halaman terpisah dari Timeline, karena keduanya membaca
sumber data yang berbeda dan keduanya masih hidup berdampingan.

## Keputusan Utama

### 1. Sumber data: `work_plannings`, bukan `work_orders.scheduled_date`

| View | Sumber | Bisa Gantt? |
|---|---|---|
| Timeline (lama) | `work_orders.scheduled_date` (satu tanggal) | ❌ tanpa durasi |
| Gantt (baru) | `work_plannings.tgl_jadwal` + `lama_pekerjaan_hari` | ✅ |

`work_plannings` sudah punya kolom yang dibutuhkan Gantt sejak FR-2.4:
`tgl_jadwal`, `jam_mulai`, `jam_selesai`, `lama_pekerjaan_hari` — hanya belum
pernah dirender sebagai timeline. Tidak ada migrasi baru.

### 2. Row axis = departemen (via `parentID`)

Satu baris induk per departemen (`TaskID` berawalan `dept-`), anak-anaknya WO.
Syncfusion mengenali baris induk dari anak yang menunjuk `parentID`, jadi baris
departemen tidak perlu flag khusus.

**Catatan penting:** Syncfusion Gantt **tidak punya row grouping**
(tidak ada `allowGrouping`/`groupedColumns`, dan modul `Group` tidak ada di daftar
injectable). Karena itu alternatif axis "per teknisi" dan "per status" tidak
mungkin dilakukan native — kalau nanti itu jadi kebutuhan, harus evaluasi ulang
komponen, bukan konfigurasi.

### 3. Task sengaja tidak bisa dipindah dari UI

`tgl_jadwal` hanya boleh berubah lewat Extend Work Schedule + approval DGM/GM
(FR-2.5). Karena itu:
- Modul `Edit` TIDAK di-Inject
- `allowTaskbarDragAndDrop` = false
- `editSettings.allowTaskbarEditing` = false
- Toolbar eksplisit tanpa item "Add"

Ini aturan bisnis, bukan kekurangan fitur. Menghidupkan drag akan menghilangkan
jalur audit.

### 4. Token adapter, bukan token system baru

Syncfusion tidak punya prop `theme`; themability murni CSS. Theme resminya memakai
namespace `--color-sf-*`, aplikasi ini memakai namespace sendiri. Jembatan tunggal
ada di `resources/css/syncfusion-gantt.css`.

Selector `:root, .dark` sengaja dipakai: dark mode aplikasi memakai kelas `.dark`
di `<html>`, bukan `.e-dark-mode` milik Syncfusion. Karena adapter hanya berupa
pemetaan `var(--token-app)`, satu selector membuat dark mode mengikuti otomatis
tanpa menyalin ~30 variabel gelap milik Syncfusion.

## File yang Dibuat / Diubah

| File | Status | Keterangan |
|---|---|
| `app/Http/Controllers/WorkPlanningGanttController.php` | baru | Endpoint data: grouping, mapping taskFields, derivasi deadline |
| `resources/js/pages/work-orders/gantt.tsx` | baru | Halaman Gantt, client-only mount, tooltip berlabel, navigasi detail |
| `resources/css/syncfusion-gantt.css` | baru | Import tema + adapter token |
| `tests/Feature/WorkPlanningGanttTest.php` | baru | 10 test kontrak data |
| `routes/web.php` | diubah | Route `work-orders.gantt` + import controller |
| `resources/js/components/nav-items.tsx` | diubah | Entri sidebar "Gantt Jadwal" + import route/icon |
| `resources/css/app.css` | diubah | Import adapter |
| `package.json` / `package-lock.json` | diubah | `@syncfusion/ej2-react-gantt`, `@syncfusion/ej2-tailwind3-theme` |
| `.env.example` | diubah | Placeholder license key + peringatan jangan commit key nyata |

## Pemetaan Data → Syncfusion `taskFields`

| `taskFields` | Sumber |
|---|---|
| `id` / `name` | `work_order_id` / `nomor_wo — title` |
| `parentID` | `dept-<hash>` (baris departemen) |
| `startDate` | `tgl_jadwal` |
| `endDate` | `tgl_jadwal + lama_pekerjaan_hari` |
| `duration` | `lama_pekerjaan_hari` (min 1 supaya bar tetap terlihat) |
| `milestone` | tidak dipetakan — tidak ada data milestone |
| `dependency` | tidak dipetakan — memang tidak ada data dependency |

Durasi null (HOD belum mengisi) disubstitusi 1 hari; durasi 0 akan jadi milestone
dan menyembunyikan durasi pekerjaan, jadi tidak dipakai.

## Aksesibilitas

- **Warna tidak pernah jadi satu-satunya sinyal**: `DeadlineLabel` dikirim
  bersama `DeadlineState`, muncul di kolom grid, di dalam taskbar, dan di tooltip.
  `queryTaskbarInfo` hanya mewarnai; tidak pernah membuat klaim visual tanpa teks.
- Event marker "Hari ini" pakai label teks, bukan pewarnaan sel.
- Keyboard: toolbar Zoom/Expand/Collapse bisa diakses; taskbar click membuka
  detail WO.
- Fallback placeholder dipertahankan pada tinggi sebelum hydration supaya
  layout tidak melompat (CLS).

## Verifikasi

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test` (Gantt + Planning) | **21 passed, 169 assertions** |
| `npx tsc --noEmit` | 4 error pre-existing di `work-orders/index.tsx` & `show.tsx` — dibuktikan lewat `git stash` bahwa muncul tanpa file baru |
| `vendor/bin/pint --dirty` | fixed (controller + routes) |
| `npx vp build` | sukses; chunk Gantt 2.45 MB / 551 KB gzip, **terisolasi di route sendiri** |
| Bundle CSS | Token `--color-sf-*` terkonfirmasi ter-bundle |

## Hal yang Belum Dikerjakan / Catatan Lanjutan

1. **Lisensi belum aktif** — Gantt berfungsi penuh tapi Syncfusion menampilkan
   banner trial. Aktivasi: `npx syncfusion-license activate` (key masuk
   `syncfusion-license.txt`, JANGAN `registerLicense()` di source karena ikut
   terbundel ke JS publik).
2. **Progress bar nol** — `progress` tidak dipetakan karena aplikasi belum punya
   data kemajuan WO per-step. Kalau nanti ada, tambahkan ke `taskFields`.
3. **Dua sumber jadwal masih hidup** — Timeline (`scheduled_date`) dan Gantt
   (`work_plannings.tgl_jadwal`). Migrasi Timeline ke `work_plannings` sebaiknya
   menyusul, supaya tidak ada dua kebenaran jadwal.
4. **Bundle besar** — `ej2-gantt` menarik Grid, TreeGrid, RichTextEditor, Calendar.
   Sudah code-split per route, tapi tetap biaya yang perlu disadari.
5. **React 19 aman, StrictMode tidak dijamin** — `GanttComponent` adalah class
   component dengan `render()` impure; Syncfusion tidak mendokumentasikan
   StrictMode. Sudah diberi disclaimer di komentar file.
6. **Data dev kosong** — `work_plannings` masih 0 record, jadi tampilan Gantt
   verifikasinya baru melalui test; perlu data nyata untuk tinjauan visual.
