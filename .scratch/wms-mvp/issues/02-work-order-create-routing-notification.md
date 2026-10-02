# 02: Buat WO + routing lintas department + notifikasi masuk HOD

**What to build:** Requester membuat Work Order (kategori Normal / Urgent by Accident / Urgent Request by Owner, department tujuan, lampiran gambar); WO masuk antrian HOD department tujuan; notifikasi in-app tersimpan DB + broadcast Reverb — dibangun benar sejak awal: satu tipe notifiable, channel privat, ownership check pada mark-as-read (perbaikan atas gap opti-work2).

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] Form create WO dengan kategori dan department tujuan (required)
- [ ] Urgent by Accident tidak menampilkan opsi jadwal (FR-1.2); Urgent Request by Owner boleh dijadwalkan (FR-1.3)
- [ ] Notifikasi realtime ke HOD department tujuan dengan rantai fallback (lihat ticket 05)
- [ ] Notifikasi tersimpan DB, unread count, riwayat; satu tipe notifiable
