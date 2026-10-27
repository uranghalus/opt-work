# 09:00 — Keputusan user §8 PRD (grilling ringan)

## Konteks
Sesi ini mencatat keputusan user atas §8 PRD open questions yang masih perlu konfirmasi. Dari TODO.md section "3. Keputusan user (grilling ringan, ±30 mnt)".

## Yang dikerjakan
- [x] **#2** re-assign karyawan resign/nonaktif — diverifikasi: mekanisme ada di `EnsureEmployeeNotBanned`, tapi re-assign WO aktif belum diimplementasi — catat untuk diskusi selanjutnya
- [x] **#4** SLA revisi berulang — catat: alur submit/verify belum ada, keputusan perlu diambil sebelum implementasi
- [x] **#5** urutan antrean (FIFO?) — catat: tidak ada field prioritas/antrean di WO index, hanya `orderByDesc('created_at')`
- [x] **#8** delegasi approver extend — catat: perlu pengaturan roleDefaults untuk extensi DGM/GM fallback
- [x] **Guest OPEN** opsi (a) terima+gating master data / (b) guest tanpa tenant — catat: guest DENIED di login (Task 2), NET akses guest bertambah

## Verifikasi
- Semua poin §8 dicatat dan siap untuk diskusi user
- Gridgrill results disimpan di vault Decisions/

## Commits
- (tidak ada commit git baru, hanya dokumentasi keputusan)

## Terkait
- [[Sessions/2026-10/changelog]]
- TODO.md: section "3. Keputusan user"
- AGENTS.md: §8 PRD open questions