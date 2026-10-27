# 11:00 — Prioritas Fitur MVP (putusan dari #3)

## Konteks
Setelah user decisions dari §8 PRD, memilih urutan fitur MVP selanjutnya berdasarkan TODO.md section "4. Fitur MVP sisa — pilih urutan hasil #3".

## Yang dikerjakan
- [x] **FR-1.1 field WO kurang** (S-M): priorititas + jumlah personel + jenis kerusakan → dicatat untuk implementasi selanjutnya
- [x] **FR-6.2 gating master data + work-order verbs** (M): keluarkan dari allowlist honesty test `PermissionConsistencyTest`; sekaligus menutup OPEN guest (opsi a)
- [x] **UI reader audit** (M): halaman riwayat `audit_logs`/`audit_events` ter-gate (verb baru `audit.read`?) — FR-6.3 sisa
- [x] **Akun CRUD** (S-M): verifikasi dulu: link/unlink sudah ada UI di `employees/show.tsx`; yang kurang mungkin hanya verbs

## Verifikasi
- Prioritas fitur MVP tercatat dan akan diikuti di sesi selanjutnya
- Gap yang terbuka akan dipantau di PRD.md header status

## Commits
- (tidak ada commit git baru, hanya dokumentasi prioritas)

## Terkait
- [[Sessions/2026-10/changelog]]
- TODO.md: section "4. Fitur MVP sisa"
- PRD.md: header peta status