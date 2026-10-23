# 04: Submit hasil + verifikasi HOD + Work Data saat close

**What to build:** Karyawan submit foto sebelum/sesudah + catatan (mobile, camera-first, touch target ≥44px, upload failure state jelas); HOD approve atau reject-revisi (loop kembali ke karyawan, deadline tidak reset); WO closed menghasilkan record Work Data append-only.

**Blocked by:** 03

**Status:** done 2026-10-23 — submit hasil + verifikasi HOD (commit `5a03ae0`…`dd3f549`, 2026-10-22: approve → Closed, reject → Revision, deadline tidak reset) + Work Data auto-generate saat close (FR-3.x slice 1, commit `6e4f3d4`: snapshot pekerja, finalisasi sekali, append-only). Sisa: **camera-first capture mobile belum** (input foto di `show.tsx` validasi accept image tapi tanpa atribut `capture`) — tetap di Backlog.

- [x] Submit hasil: catatan + foto (validasi ukuran/format)
- [x] Verifikasi HOD: approve (closed) / reject dengan catatan revisi — status kembali ke karyawan, deadline tidak reset
- [x] WO closed menghasilkan Work Data (append-only setelah closing)
- [ ] Mobile: camera-first capture, before/after slots, touch target ≥44px
