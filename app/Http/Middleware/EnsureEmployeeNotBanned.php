<?php

namespace App\Http\Middleware;

use App\Models\Employee;
<<<<<<< HEAD
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Q7 (grill 2026-10-21): blokir memblokir login. Satu choke point untuk semua
 * jalur — login baru (SAML ACS maupun password) dan sesi yang sudah berjalan —
 * karena aturan ditekankan pada SETIAP request, bukan hanya saat login.
 *
 * Aturan: akun yang tertaut ke karyawan ber-`banned_at` di-logout dan
 * dialihkan ke halaman penolakan (alasan `banned`). Tanpa tautan = tanpa
 * blokir (karyawan tanpa akun tidak punya sesi untuk diblokir).
 *
 * Super admin dikecualikan, konsisten dengan setiap gate lain (ADR-0004:
 * platform account bypasses every gate) — akun platform tidak boleh bisa
 * dikunci oleh blokir pada record karyawan, agar administrasi platform tidak
 * pernah terkunci total.
 *
 * Employee di-fetch tanpa scope tenant: middleware ini juga berjalan di rute
 * non-tenant (admin dashboard, settings) di mana Tenant::current() kosong.
 */
class EnsureEmployeeNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->employee_id !== null && ! $user->isSuperAdmin()) {
            $employee = Employee::query()
                ->withoutGlobalScope('tenant')
                ->whereKey($user->employee_id)
                ->first(['id', 'banned_at']);

            if ($employee !== null && $employee->banned_at !== null) {
                Auth::guard('web')->logout();

                return redirect()
                    ->route('saml.denied')
                    ->with('saml.denial_reason', 'banned');
            }
=======
use App\Models\User;
use App\Services\LoginLanding;
use Closure;
use Illuminate\Http\Request;

/**
 * Q7 ban enforcement (grill 2026-10-22): a banned karyawan gets no usable
 * session anywhere in the portal. Sits on the web stack as the single choke
 * point covering both ends of the rule:
 *
 * - an active session — every authenticated web request is checked;
 * - a fresh login — both the password and SAML paths land on a web route
 *   the moment they authenticate, so the bounce happens one hop in.
 *
 * The link (users.employee_id) is global identity and is checked without
 * the tenant scope, so a ban follows the account on every cabang route.
 * An unlinked account (SAML never auto-links) inherits the block from a
 * same-email karyawan in the current cabang; IdentifyTenant has run before
 * us, so the tenant global scope enforces the same-cabang half.
 *
 * Platform accounts are exempt via User::isSuperAdmin() — the same source
 * Gate::before delegates to: an operator must always be able to fix a ban,
 * never be caught by one.
 */
class EnsureEmployeeNotBanned
{
    public function __construct(protected LoginLanding $landing) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user === null || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($this->isBanned($user)) {
            return $this->landing->deny('banned');
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
        }

        return $next($request);
    }
<<<<<<< HEAD
=======

    /**
     * Linked: the link is the identity — one lookup, done. Unlinked (SAML)
     * or dangling link: the same-email karyawan of the current cabang stands
     * in, matching the Q7 rule "ter-link atau email sama-tenant".
     */
    protected function isBanned(User $user): bool
    {
        if ($user->employee_id !== null) {
            $linked = Employee::withoutGlobalScope('tenant')
                ->whereKey($user->employee_id)
                ->first();

            if ($linked !== null) {
                return $linked->isBanned();
            }
        }

        return Employee::query()
            ->whereRaw('LOWER(email) = LOWER(?)', [$user->email])
            ->whereNotNull('banned_at')
            ->exists();
    }
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
}
