# TODO — opti-works

**Sumber kebenaran status kerja yang sedang berjalan.**
Aturan: item checkbox pertama yang belum `[x]` = pekerjaan sesi berikutnya.
Diperbarui di akhir setiap sesi kerja. Lihat `AGENTS.md` § "Di Awal Sesi Baru".

**Status overall:** rekonfigurasi tenancy (`.scratch/tenancy-reconfig/`) — **6 dari 7 tiket selesai**
(ticket 01 sudah fungsional, tinggal satu pertanyaan konvensi —lihat bawah).
App jalan di **MariaDB 10.4 (XAMPP)**.

---

## Sedang berjalan

- [ ] **Tidak ada item aktif.** Langkah berikutnya: mulai `.scratch/wms-mvp/issues/01-foundation-rbac-tenant-master-data.md`,
      atau bereskan dulu utang teknis di bawah.

## Utang teknis / blocker

- [ ] **Belum ada test runner JS** (tidak ada Playwright/vitest). `branchDestination()` dan
      perilaku rail-tertekan **belum teruji visual/browser** — ticket 05. Menambah test runner
      = perubahan dependency, perlu persetujuan.
- [ ] **`SamlTest.php:220` gagal** — pre-existing, gagal identik di SQLite & MariaDB. Belum diinvestigasi.
- [ ] **Verifikasi MySQL 8 (bukan MariaDB)** — ticket 07 hijau di MariaDB 10.4. Kalau target deploy MySQL 8, jalankan sekali di sana.
- [ ] **`WorkOrderCreated` tidak implement `ShouldQueue`** — notifikasi masih sinkron, jadi
      `QueueTenancyBootstrapper` belum punya traffic nyata. Kalau mau lewat worker, tambahkan
      `ShouldQueue` (perhatikan `SerializesModels` akan re-fetch `WorkOrder` di dalam worker).
- [ ] **Formatting repo belum bersih** — `npm run check` menandai 237 file (terutama urutan
      Tailwind class). Pre-existing, sengaja tidak disentuh: `vp check --fix` akan menyentuh
      237 file di luar scope. Kalau mau bersih, itu PR tersendiri.
- [ ] **`tenants.id` = `varchar(255)`** — composite unique `work_orders(tenant_id, nomor_wo)`
      = 1020 bytes untuk key column. Aman di 3072 byte; perhatikan kalau composite nambah kolom.
- [ ] **`.env` masih credensial XAMPP** (`root`, tanpa password). Belum ada konfigurasi produksi.
- [ ] Test suite masih pin sqlite `:memory:` (`phpunit.xml`). MySQL sudah pernah diuji manual
      (hasil identik) tapi belum jadi driver test permanen.

## Selesai

- [x] Ticket 01 — restore work order show page
- [x] Ticket 02 — RBAC gates mati + identity flags (`is_super_admin`, `is_hod`), seam `TenantAccess`
- [x] Ticket 03 — kolom dedikasi tenant (`name`/`code`/`is_active`), fix `TenantSeeder`
- [x] Ticket 04 — CRUD tenant di `/settings/tenants`
- [x] Ticket 05 — tenant switcher (sidebar + header, `branchDestination()`, collapsed-safe)
- [x] Ticket 06 — `QueueTenancyBootstrapper` aktif + 3 test (mutation-checked)
- [x] Ticket 07 — alignment FK slug↔`tenants.id`, verified live di MariaDB; `.env` sekarang MySQL

### Ticket 01 — perlu keputusan, bukan implementasi

Sudah **fungsional**: `work-orders/show.tsx` ada, 8 field lengkap, lampiran render dari
storage tenant-scoped, feature test lulus (15 assertion). Crash 500 yang jadi alasan tiket
sudah beres.

Tinggal 1 kriteria: `work-orders/show.tsx` tidak set `.layout`. Halaman itu render
`<Heading>` sendiri — sama seperti `create.tsx`/`edit.tsx`. Tapi **`divisions/show.tsx` yang
ditiket bilang "mirror" juga tidak set `.layout`**, jadi ini bukan defect, cuma pertanyaan
konvensi: mau semua `show.tsx` master-data ikut set `.layout` (supaya konsisten dengan
`index.tsx` dan `settings/tenants/show.tsx`), atau dibiarkan render heading sendiri?

### Temuan penting ticket 07 (jangan diulang)

`string('tenant_id')->constrained('tenants')` **tidak menghasilkan foreign key sama sekali** —
`constrained()` hanya ada di `ForeignIdColumnDefinition`, jadi di `ColumnDefinition` biasa itu
no-op senyap. Ticket 07 sebelumnya "hijau" di SQLite hanya karena `SQLiteGrammar::compileDropForeign`
juga no-op, jadi tidak ada yang gagal. FK harus ditulis eksplisit:

```php
$table->string('tenant_id');
$table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
```

Pola yang benar sudah ada di `2019_09_15_000020_create_domains_table.php`.

## Keputusan tertunda

- [ ] Kapan RBAC Spatie diaktifkan kembali? `TenantAccess` sudah disiapkan supaya re-entry additive.
- [ ] Per-branch permission set (butuh Spatie `teams => true`) — deferred, lihat `issues/00-rejected-and-deferred.md`.
- [ ] **Mulai WMS MVP** — `.scratch/wms-mvp/issues/01-foundation-rbac-tenant-master-data.md` adalah
      langkah berikutnya yang wajar sekarang tenancy-reconfig sudah bersih.
