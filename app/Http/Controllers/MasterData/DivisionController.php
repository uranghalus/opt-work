<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    public function index(Request $request): Response
    {
        $divisions = Division::query()
            ->withCount('departments')
            ->orderBy('nama_division')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('divisions/index', [
            'divisions' => $divisions,
            'can' => [
                'create' => $request->user()?->can('division.create') ?? false,
                'update' => $request->user()?->can('division.update') ?? false,
                'delete' => $request->user()?->can('division.delete') ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('divisions/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_division' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('divisions', 'kode_division')->where('tenant_id', tenant()?->getTenantKey()),
            ],
            'nama_division' => ['required', 'string', 'max:255'],
        ]);

        Division::create($validated);

        return redirect()
            ->route('divisions.index', ['tenant' => tenant()?->getTenantKey()])
            ->with('success', 'Divisi berhasil dibuat.');
    }

    public function show(Division $division): Response
    {
        return Inertia::render('divisions/show', [
            'division' => $division->loadCount('departments'),
        ]);
    }

    public function edit(Division $division): Response
    {
        return Inertia::render('divisions/edit', [
            'division' => $division,
        ]);
    }

    public function update(Request $request, Division $division): RedirectResponse
    {
        $validated = $request->validate([
            'kode_division' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('divisions', 'kode_division')
                    ->where('tenant_id', $division->tenant_id)
                    ->ignore($division),
            ],
            'nama_division' => ['required', 'string', 'max:255'],
        ]);

        $division->update($validated);

        return redirect()
            ->route('divisions.show', ['tenant' => $division->tenant_id, 'division' => $division])
            ->with('success', 'Divisi berhasil diperbarui.');
    }

    public function destroy(Division $division): RedirectResponse
    {
        $division->delete();

        return redirect()
            ->route('divisions.index', ['tenant' => tenant()?->getTenantKey()])
            ->with('success', 'Divisi berhasil dihapus.');
    }
}
