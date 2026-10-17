# 02: Endpoint review HOD (keputusan eksekusi)

**What to build:** `POST {tenant}/work-orders/{workOrder}/review` — HOD memutuskan eksekusi
langsung vs dijadwalkan, sesuai aturan kategori.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] Route + controller action gated `work-order.review` via `Gate::authorize` (pola `akun.update`);
      entri allowlist `work-order.review` di `PermissionConsistencyTest` dikeluarkan di commit yang sama
- [ ] FormRequest: `action` required in:execute_immediately,schedule; `scheduled_date`
      required_if action=schedule + after_or_equal:today + diblokir saat kategori Accident;
      `hod_notes` nullable string
- [ ] Guard kategori: Accident + action=schedule → ditolak (server-side; `allowsScheduling()` sudah ada)
- [ ] Transisi status: `waiting_hod` → `hod_approved` (langsung) / `scheduled` (jadwal +
      `scheduled_date` tersimpan); idempoten terhadap review ulang (re-review diperbolehkan
      selama belum assigned)
- [ ] Entri histori `reviewed` (siapa, kapan, action, from/to status)
- [ ] Test: sukses langsung, sukses jadwal, Accident ditolak, tanpa permission ditolak (403),
      scheduled_date wajib untuk jadwal, entri histori tercatat
