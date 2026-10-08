# 11: Layout consistency for master data show pages

**What to build:** Padanan konsisten untuk halaman `show` master data. `work-orders/show.tsx` dan `divisions/show.tsx` saat ini merender `<Heading>` sendiri tanpa `.layout`. Agar konsisten dengan `index.tsx` dan `settings/tenants/show.tsx`, pindahkan metadata halaman ke `.layout` shell: breadcrumbs, title, description, dan actions jika relevan. Halaman tetap read-only.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] `work-orders/show.tsx` memiliki `WorkOrderShow.layout` dengan breadcrumbs, title = `nomor_wo`, description singkat
- [ ] `divisions/show.tsx` memiliki `DivisionShow.layout` dengan breadcrumbs ke `Data Master > Divisi`, title = nama divisi
- [ ] `<Heading>` internal di kedua file dihapus / diganti dengan konten body saja; heading kini berasal dari shell
- [ ] Visual regresi dicek desktop & mobile, tidak ada shift layout yang merusak
