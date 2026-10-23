<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PositionTreeController extends Controller
{
    /**
     * Display the position hierarchy tree.
     */
    public function index(Request $request): Response
    {
        $tenant = tenant();

        // Get root positions (those without parent, or with is_direct=true)
        $rootPositions = Position::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $q->whereNull('parent_position_id')
                    ->orWhere('is_direct', true);
            })
            ->with([
                'department',
                'division',
                'children' => function ($q) {
                    $q->with(['department', 'division', 'children' => function ($q2) {
                        $q2->with(['department', 'division', 'children' => function ($q3) {
                            $q3->with(['department', 'division', 'children' => function ($q4) {
                                $q4->with(['department', 'division']);
                            }]);
                        }]);
                    }]);
                },
            ])
            ->orderBy('level')
            ->orderBy('nama_position')
            ->get();

        return Inertia::render('positions/tree', [
            'rootPositions' => $rootPositions,
        ]);
    }

    /**
     * Get tree data for a specific root position (for AJAX loading).
     */
    public function show(Request $request, Position $position): Response
    {
        $tenant = tenant();

        // Verify position belongs to tenant
        abort_unless($position->tenant_id === $tenant->id, 404);

        $position->load([
            'department',
            'division',
            'parentPosition',
            'children' => function ($q) {
                $q->with(['department', 'division', 'children' => function ($q2) {
                    $q2->with(['department', 'division', 'children' => function ($q3) {
                        $q3->with(['department', 'division', 'children' => function ($q4) {
                            $q4->with(['department', 'division']);
                        }]);
                    }]);
                }]);
            }]);

        return Inertia::render('positions/tree', [
            'rootPositions' => collect([$position]),
            'initialRoot' => $position->id,
        ]);
    }
}