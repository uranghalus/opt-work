<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FR-6.3: mencatat setiap akses HTTP yang ditolak (403) ke audit_events.
 *
 * Dipanggil dari render hook di bootstrap/app.php, BUKAN dari event
 * Gate::evaluate: probe `can()` (bendera navigasi Inertia dsb.) tidak
 * boleh membanjiri audit — yang dicatat hanya penolakan yang
 * benar-benar menggagalkan sebuah request.
 *
 * Hook menyapainya sebagai HttpException(403): Handler::prepareException
 * mengonversi AuthorizationException (gate/FormRequest) menjadi
 * AccessDeniedHttpException sebelum render callbacks dijalankan; exception
 * asli tetap tersedia via getPrevious() untuk ekstraksi izin spatie.
 *
 * Baris di sini memakai subject synthetic 'access' + nama izin/route
 * yang ditolak, karena AuditEvent::record() membutuhkan Model subject
 * dan penolakan akses tidak memiliki subject model.
 */
class AccessDeniedAuditor
{
    public static function record(HttpException $exception, Request $request): void
    {
        try {
            $denial = $exception->getPrevious() ?? $exception;

            $required = match (true) {
                $denial instanceof UnauthorizedException => array_values(array_merge(
                    $denial->getRequiredPermissions(),
                    $denial->getRequiredRoles(),
                )),
                default => [],
            };

            $user = $request->user();

            DB::transaction(function () use ($exception, $denial, $request, $required, $user): void {
                AuditEvent::create([
                    'tenant_id' => Tenant::current()?->id ?? $user?->tenant_id,
                    'actor_user_id' => $user?->getKey(),
                    'actor_email' => $user?->email,
                    'subject_type' => 'access',
                    'subject_id' => $required[0] ?? (string) ($request->route()?->getName() ?? 'unknown'),
                    'action' => 'access.denied',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'context' => [
                        'required' => $required,
                        'route' => $request->route()?->getName(),
                        'method' => $request->method(),
                        'url' => $request->fullUrl(),
                        'exception' => class_basename($exception),
                        'denial' => class_basename($denial),
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            // Audit tidak boleh menjatuhkan response 403 yang sudah benar.
            Log::warning('access.denied audit gagal ditulis.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
