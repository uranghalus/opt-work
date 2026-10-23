<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\WorkData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FR-3.2–3.3: Work Data — list terfilter (department, rentang tanggal,
 * status) + detail read-only + finalisasi analitik SEKALI (append-only;
 * setelah finalized_at terisi, record beku selamanya — tidak ada edit,
 * tidak ada delete).
 */
class WorkDataController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('work-data.read');

        $filters = $request->validate([
            'department_id' => ['nullable', 'string'],
            'status' => ['nullable', 'in:selesai'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = WorkData::query()
            ->with('department:id,nama_department')
            ->orderByDesc('tgl_selesai_aktual');

        if (filled($filters['department_id'] ?? null)) {
            $query->where('department_id', $filters['department_id']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status_pekerjaan', $filters['status']);
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('tgl_selesai_aktual', '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('tgl_selesai_aktual', '<=', $filters['to']);
        }

        return Inertia::render('work-data/index', [
            'workData' => $query->paginate(12)->withQueryString(),
            'filter_options' => [
                'departments' => Department::query()
                    ->orderBy('nama_department')
                    ->get(['id', 'nama_department']),
                'statuses' => [
                    ['value' => 'selesai', 'label' => 'Selesai'],
                ],
            ],
            'filters' => [
                'department_id' => $filters['department_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
        ]);
    }

    public function show(Request $request): Response
    {
        Gate::authorize('work-data.read');

        // Resolusi manual (pola anti-implicit binding pada grup web); scope
        // tenant membuat WO cabang lain 404 walau id-nya diketahui.
        $workData = WorkData::query()
            ->where('id', $request->route('workData'))
            ->firstOrFail();

        $workData->load([
            'department:id,nama_department',
            'pekerja.employee:id,nama_employee',
            'workOrder:id,nomor_wo,title,status',
            'picLead:id,name',
        ]);

        return Inertia::render('work-data/show', [
            'workData' => $workData,
        ]);
    }

    /**
     * FR-3.3: satu-satunya tulisan pasca-close — analitik post-mortem
     * (prediksi penyebab, tindakan, kesimpulan, saran solusi). Conditional
     * update whereNull(finalized_at) membuat dua finalisasi konkuren hanya
     * satu yang lolos; setelah itu record beku selamanya.
     */
    public function finalize(Request $request): RedirectResponse
    {
        Gate::authorize('work-data.update');

        $workData = WorkData::query()
            ->where('id', $request->route('workData'))
            ->firstOrFail();

        if ($workData->finalized_at !== null) {
            abort(409, 'Analitik Work Data sudah difinalisasi dan bersifat append-only.');
        }

        $validated = $request->validate([
            'prediksi_penyebab' => ['required', 'string', 'max:2000'],
            'tindakan' => ['required', 'string', 'max:2000'],
            'hasil_kesimpulan' => ['required', 'string', 'max:2000'],
            'saran_solusi' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($workData, $validated): void {
            $updated = WorkData::query()
                ->whereKey($workData->getKey())
                ->whereNull('finalized_at')
                ->update([
                    ...$validated,
                    'finalized_at' => now(),
                ]);

            if ($updated === 0) {
                abort(409, 'Analitik Work Data sudah difinalisasi dan bersifat append-only.');
            }
        });

        return redirect()
            ->route('work-data.show', ['tenant' => tenant()?->code, 'workData' => $workData])
            ->with('success', 'Analitik Work Data '.$workData->no_kerja.' tersimpan — record kini terkunci.');
    }
}
