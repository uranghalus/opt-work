# 05: Test suite untuk department sync

**Status:** pending
**Blocked by:** 02 (harus implement dulu baru test)
**Depends on:** 02

## Why

Test yang ada masih minimal. Perlu coverage untuk semua scenario audit.

## Spec

Tambahkan test-case berikut di `tests/Feature/SyncDepartmentsTest.php`:

1. **Creates new departments** — payload dengan dept baru → create
2. **Re-run does not duplicate** — run dua kali → count sama
3. **Updates changed upstream data** — change name/code → update
4. **Deactivates removed upstream** — remove dept from payload → is_active=false (bukan delete/hard remove)
5. **API failure leaves data unchanged** — mock exception → data tidak berubah (transaction rollback)
6. **Pagination boundary** — mock last_page=2, verify all pages fetched
7. **Idempotent with existing optigate_department_id** — re-run → same records

## Acceptance

- [ ] Semua test-case di atas passing
- [ ] Full test suite hijau setelah perubahan
