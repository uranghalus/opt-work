# 03: Review HOD + assign karyawan + deadline

**What to build:** HOD memutuskan eksekusi langsung vs dijadwalkan (by_accident dipaksa langsung, by_owner bebas pilihan), assign karyawan pelaksana, deadline dihitung dari **tanggal assign** (urgent 3 / normal 6 hari kerja, libur configurable via config/holidays.php), notifikasi ke karyawan yang di-assign.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Keputusan HOD: eksekusi langsung / jadwalkan sesuai aturan kategori
- [ ] Assign 1+ karyawan + notifikasi ke assignee
- [ ] Deadline otomatis: urgent 3 / normal 6 hari kerja, dihitung dari tanggal assign, libur configurable
- [ ] Semua perubahan status tercatat di histori (siapa, kapan, aksi)
