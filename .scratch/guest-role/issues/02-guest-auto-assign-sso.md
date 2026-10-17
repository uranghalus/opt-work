# 02: Auto-assign `guest` saat login (jalur SSO)

**What to build:** Service `LoginProvisioningService::provision(User, email)`
dipanggil di `SamlController::loginUser()` setelah user dibuat/ditemukan,
sebelum `Auth::login`. Dalam `DB::transaction`.

**Status:** done 2026-10-17

- [x] `LoginProvisioningService` (platform branch: role+flag+link by email via
      `withoutGlobalScope('tenant')`, `tenant_id` tak disentuh, konflik link
      tak ditimpa; guest branch: guard `! isSuperAdmin() && employee_id ===
      null && ! roles()->exists()` → `assignRole('guest')` dalam transaksi;
      role `guest` hilang → log critical + skip)
- [x] `SamlController::loginUser()` memanggil service sebelum `Auth::login`
- [x] `GuestProvisioningTest` (7): first login unlinked → guest; second login
      idempoten; existing ber-role tak tersentuh; linked user no guest;
      platform self-heal (role+flag+link) tanpa tenant current; guest
      403 di `/hak-akses`; seluruh flag `can` false
- [x] `HandleInertiaRequests` tak berubah — flag Guest otomatis false
- [x] `SamlTest`: kasus first-login sekarang ekspektasi provisioned (guest +
      redirect dashboard); kasus `no_role` lama dipertahankan sebagai test
      katalog-unseeded