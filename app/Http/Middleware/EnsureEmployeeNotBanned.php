<?php

namespace App\Http\Middleware;

use App\Models\Employee;
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
        }

        return $next($request);
    }
}
