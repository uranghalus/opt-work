# 04: Frontend panel review/assign di work-orders/show

**What to build:** Panel aksi HOD di `resources/js/pages/work-orders/show.tsx` — keputusan
eksekusi + pemilih karyawan pelaksana, pola existing (self-contained heading-band action
dengan dialog sendiri, ala SyncButton/Ubah Peran).

**Blocked by:** 02, 03

**Status:** done 2026-10-17 (commit `553c775`)

- [x] Flag `can['work-order.review']` + `can['work-order.assign']` via Gate di
      `HandleInertiaRequests` + key di `Auth['can']` type (ADR-0004 rule 7); GateTest
      expectation flag diperluas (3 test)
- [x] Panel review: radio eksekusi langsung / jadwal; kategori Accident → opsi jadwal
      disembunyikan (server `allowsScheduling()` tetap sumber kebenaran); error per-key
      (hod_action/scheduled_date) inline; reset on success
- [x] Panel assign: checkbox karyawan department target (data dari show(), hanya saat
      boleh assign), konfirmasi, feedback error; mode revisi saat assigned; disabled saat
      belum ada pilihan
- [x] Kartu info "Keputusan HOD": keputusan, tanggal jadwal, tanggal assign, deadline,
      catatan penugasan, nama pelaksana — read-only untuk semua user
- [x] Wayfinder route functions (`review`/`assign` dari `@/routes/work-orders`), tanpa URL hardcoded
- [x] Verifikasi: `tsc` hijau, `vp check` hijau di file tersentuh (vp check full-tree berisik
      karena dir agent .codex/.hermes + WIP sesi paralel — pre-existing), `npm run build`
      hijau; bukti headless light+dark + alur review penuh via endpoint nyata:
      `.impeccable/review/wo-panel-*.png` (fixture HOD dihapus setelah run)
