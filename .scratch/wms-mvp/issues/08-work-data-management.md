# 08: Work Data Management (histori + search)

**What to build:** Work Data dapat dicari/difilter (department, rentang tanggal, status); append-only setelah WO closed; detail view read-only.

**Blocked by:** 04

**Status:** done (slice 1, 2026-10-23) — auto-generate saat WO closed + snapshot pekerja + finalisasi analitik sekali + UI index/show. Ruling: alur revisi post-closing (PRD OQ) = WO closed terminal di MVP, ditandai `// OQ` di kode.

- [x] List + filter (department, rentang tanggal, status)
- [x] Detail read-only — tidak bisa diedit setelah WO closed
- [x] Append-only integrity teruji (finalisasi analitik sekali; kedua kali 409; tanpa route edit/delete)
