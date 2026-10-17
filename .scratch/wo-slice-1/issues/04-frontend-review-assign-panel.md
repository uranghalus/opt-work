# 04: Frontend panel review/assign di work-orders/show

**What to build:** Panel aksi HOD di `resources/js/pages/work-orders/show.tsx` — keputusan
eksekusi + pemilih karyawan pelaksana, pola existing (self-contained heading-band action
dengan dialog sendiri, ala SyncButton/Ubah Peran).

**Blocked by:** 02, 03

**Status:** ready-for-agent

- [ ] Flag `can['work-order.review']` + `can['work-order.assign']` via Gate di
      `HandleInertiaRequests` + key di `Auth['can']` type (ADR-0004 rule 7)
- [ ] Panel review: pilihan eksekusi langsung / jadwal; kategori Accident → opsi jadwal
      disembunyikan (server `allowsScheduling()` tetap sumber kebenaran); error dialog
      satu key; close on success
- [ ] Panel assign: pemilih 1+ karyawan department target (data dari endpoint show),
      konfirmasi, feedback sukses/error; hidden tanpa verb; read-only seal saat WO belum
      waiting_hod / sudah assigned
- [ ] Wayfinder route functions (`@/actions/`), tanpa hardcoded URL
- [ ] Verifikasi: `npm run types:check`, `vp check`, `npm run build` hijau; screenshot
      headless light+dark (bukti `.impeccable/review/`)
