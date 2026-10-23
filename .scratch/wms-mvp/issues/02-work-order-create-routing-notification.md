# 02: Buat WO + routing lintas department + notifikasi masuk HOD

**What to build:** Requester membuat Work Order (kategori Normal / Urgent by Accident / Urgent Request by Owner, department tujuan, lampiran gambar); WO masuk antrian HOD department tujuan; notifikasi in-app tersimpan DB + broadcast Reverb — dibangun benar sejak awal: satu tipe notifiable, channel privat, ownership check pada mark-as-read (perbaikan atas gap opti-work2).

**Blocked by:** 01

**Status:** done 2026-10-23 — create WO + routing lintas department + notifikasi sudah shipped (bukti: `WorkOrderController::store/review/assign`, `app/Notifications/WorkOrderCreated.php`); field tambahan FR-1.1 (prioritas, jumlah personel, jenis kerusakan) **ditunda** — tetap di Backlog TODO.md.

- [x] Form create WO dengan kategori dan department tujuan (required) — dropdown department difilter `is_active` (FR-4.1, commit `40714c8`)
- [x] Urgent by Accident tidak menampilkan opsi jadwal (FR-1.2); Urgent Request by Owner boleh dijadwalkan (FR-1.3) — review endpoint memaksa Accident eksekusi langsung (commit `ea6779d`) + panel frontend tanpa opsi jadwal (commit `553c775`)
- [x] Notifikasi realtime ke HOD department tujuan dengan rantai fallback (lihat ticket 05) — `WorkOrderCreated` + rantai fallback hod_user_id → manager → semua role hod
- [x] Notifikasi tersimpan DB, unread count, riwayat; satu tipe notifiable — tabel `app_notifications`, bell + toast realtime (commit `e81f041`), test `NotificationTest`
