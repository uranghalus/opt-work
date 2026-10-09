<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    public function index(): Response
    {
        $positions = Position::query()
            ->with(['department', 'division'])
            ->withCount('employees')
            ->orderBy('nama_position')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('positions/index', [
            'positions' => $positions,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('positions/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_position' => ['required', 'string', 'max:255'],
        ]);

        Position::create($validated);

        return redirect()
            ->route('positions.index', ['tenant' => tenant()?->code])
            ->with('success', 'Position berhasil dibuat.');
    }

    public function show(Position $position): Response
    {
        return Inertia::render('positions/show', [
            'position' => $position->load(['department', 'division'])->loadCount('employees'),
        ]);
    }

    public function edit(Position $position): Response
    {
        return Inertia::render('positions/edit', [
            'position' => $position,
        ]);
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $validated = $request->validate([
            'nama_position' => ['required', 'string', 'max:255'],
        ]);

        $position->update($validated);

        return redirect()
            ->route('positions.show', ['tenant' => $position->tenant->code, 'position' => $position])
            ->with('success', 'Position berhasil diperbarui.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        $position->delete();

        return redirect()
            ->route('positions.index', ['tenant' => tenant()?->code])
            ->with('success', 'Position berhasil dihapus.');
    }
}
