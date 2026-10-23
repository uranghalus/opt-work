<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\AuditEvent;
use App\Models\ExtendRequest;
use App\Models\WorkOrder;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Pengaman integritas data: work_orders.requester_user_id adalah
        // cascadeOnDelete — menghapus akun yang pernah membuat WO akan
        // menghapus riwayat WO/Work Data/extend cabang ini, dan akun yang
        // pernah mengajukan extend kena FK restrict (QueryException 500).
        // Akun internal WMS di-provision dan di-link ke karyawan; self-delete
        // hanya untuk akun tanpa jejak kerja sama sekali.
        $hasWorkHistory = WorkOrder::query()->where('requester_user_id', $user->getKey())->exists()
            || ExtendRequest::query()->where('requested_by_user_id', $user->getKey())->exists()
            || AuditEvent::query()->where('actor_user_id', $user->getKey())->exists();

        if ($hasWorkHistory) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Akun memiliki riwayat Work Order/audit dan tidak dapat dihapus. Hubungi superadmin.',
            ]);

            return to_route('profile.edit');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
