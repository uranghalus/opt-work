# 14: WorkOrderCreated ShouldQueue

**What to build:** Implement `ShouldQueue` pada event `WorkOrderCreated` untuk broadcast notifikasi async.

**Blocked by:** wms-mvp 02 work-order-create-routing-notification

**Status:** ready-for-agent

- [ ] Tambah `ShouldQueue` interface ke `WorkOrderCreated`
- [ ] Pastikan queue connection `database` tersedia di `.env.example`
- [ ] Tambah test: event dispatched, job queued saat create work order
- [ ] Dokumentasi di Decisions/
