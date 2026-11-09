# Work Order Timeline — Vis Works Scheduler (2026-10-27)

> Supersede sebagian dari `workorder-timeline-design-decisions.md` (2026-10-24).
> Dokumen lama tetap arsip keputusan yang belum digantikan — bagian yang masih
> berlaku cukup, sehingga tidak disunting.

## Keputusan (dari sesi grilling + audit ui-ux-pro-max)

| Keputusan | Isi |
| --- | --- |
| Bentuk | Scheduler per-resource: **baris = departemen** (collapsible), kolom = hari; chip Work Order pada sel hari |
| Library | **vis-timeline 8 (MIT)** — tanpa lisensi bayar; sumbu waktu zoom aktif (Ctrl+scroll + tombol), skala **hari** sebagai default |
| Interaksi | **Read-only**: chip adalah anchor ke detail WO (middle-click/new-tab jalan). Drag-reschedule fase 2 — `scheduled_date` DATE tanpa jam dan perubahan jadwal lewat gerbong extend (R21), jadi drag dianggap tidak jujur terhadap gerbang approval |
| HP | Responsif: scroll horizontal terlokalisasi dalam region (bukan halaman); label departemen & sumbu waktu termasuk bagian panel yang tidak ikut bergeser |
| Daya tampung | >200 WO/minggu: baris kecil (hanya department), tumpukan chip diberi batas 5 per sel-hari dengan tautan "+N WO lagi" ke daftar WO |
| Dependensi | `@fullcalendar/react` + `fullcalendar-timeline.css` **dihapus** (halaman ini pemakai satu-satunya); diganti `workorder-timeline.css` konsumen token |

## Supersede vs dipertahankan dari 2026-10-24

**Supersede** (struktur tampilan):
- dayGrid FullCalendar → rangka panel vis-timeline (label panel kiri, sumbu hari).
- View `dayGridWeek|dayGridMonth` → bucket zoom `day|week|month` (`?view=`;
  nilai lama tetap diterima server untuk link tua).
- "Hari ini" highlight → band background diberi tanda brand + tint (bukan
  warna sendirian) mengikuti kriteria A2 audit.

**Dipertahankan** (semantik tanggal — masih benar):
- Server (WITA, D2) memantau "today"; browser time line dimatikan
  (`showCurrentTime: false`) karena zona browser bisa beda.
- Rentang tampil bepergian sebagai `?start=&end=` (end exclusive) —
  deep-linkable dan tombol kembali bekerja.
- Deadline state dihitung server (`deadline_state`, `deadline_label`),
  render tidak menghitung tanggal.
- Visibility tetap `WorkOrder::scopeVisibleFor` (D1 pada 2026-10-24).
- Cap rentang ±183 hari di client dengan batas server yang sama ±1 tahun.

## Kriteria audit yang mau dipenuhi implementasi

Dokumen lengkap: `docs/audit/workorder-timeline-uiux-audit.md`. Ringkas:

1. D1 chip min-tinggi 28px, 44px pada `pointer: coarse`; jarak tumpukan 8px.
2. D2 deadline selalu teks terlihat + aria-label lengkap (bukan `title` attr);
   rail warna hanya pemanis.
3. D3 kolom hari & label departemen tidak bergeser bersama isi kolom lain
   (panel vis-timeline inherent).
4. D4 collapse baris departemen lewat tombol `aria-expanded` yang fokus-tag.
5. D5 kontrol zoom/tanggal = tombol berlabel, min-tinggi 44px coarse.
6. D6 empty state eksplisit per rentang.
7. D7 `aria-busy` saat partial reload + `aria-live` hitungan dipertahankan.
8. D8 styling hanya lewat token var — vis stylesheet dasar di-import lalu
   dikustomisasi; tidak ada default biru vis yang lolos.
9. D9 `prefers-reduced-motion` mematikan animasi zoom/caret/backdrop.
10. D10 tarik chip di agregasi ke dalam cell-hari sebesar batas 5 + "+N lagi".
11. D11 URL/range/today semantik dipertahankan persis.
12. D12 navigasi keyboard: semua chip adalah `<a href>` yang fokus-tag;
    detail per chip tersedia lewat fokus + pembacaan aria-label penuh.

## Catatan delapan yang bisa dievaluasi di fase 2

- Baris pelaksana (`assigned_employees` JSON multi-nilai) di dalam baris
  departemen — struktur collapse sudah siap menampungnya.
- Drag-reschedule + guard gerbong extend (R21).
- Filter satu departemen / filter status di header grup.
