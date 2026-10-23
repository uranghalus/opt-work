<?php

namespace App\Http\Controllers;

use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\DailyWorkAssigned;
use App\Services\DailyWorkService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FR-5.3–5.4: daftar harian Daily Work milik karyawan yang login
 * (materialize-on-view dulu, lalu render), plus update status/lokasi
 * item miliknya sendiri (FR-5.4). Template CRUD + adhoc + team view
 * ada di controller ini juga (Task 3).
 */
class DailyWorkController extends Controller
{
    public function __construct(private readonly DailyWorkService $dailyWorkService) {}

    public function index(Request $request): Response
    {
        Gate::authorize('daily-work.read');

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,in_progress,done'],
        ]);

        // "Hari ini" mengikuti timezone app (Asia/Makassar — D2).
        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'])
            : CarbonImmutable::today();

        $user = $request->user();
        $employee = $user->employee_id ? Employee::find($user->employee_id) : null;

        if ($employee !== null) {
            $this->dailyWorkService->materializeFor($employee, $date);
        }

        $dailyWork = DailyWork::query()
            ->when($employee !== null, fn ($query) => $query->where('employee_id', $employee->getKey()))
            // whereDate: sqlite menyimpan kolom date 'Y-m-d H:i:s' —
            // perbandingan string Y-m-d kosong di sqlite (MySQL DATE cocok).
            ->whereDate('work_date', $date->toDateString())
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DailyWork $work) => $this->workEntry($work));

        return Inertia::render('daily-work/index', [
            'dailyWork' => $dailyWork,
            'date' => $date->toDateString(),
            'can_update' => $employee !== null && $user->can('daily-work.update'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('daily-work.update');

        // Resolusi manual (bukan implicit binding): binding model jalan
        // sebelum IdentifyTenant → scope BelongsToTenant gagal saat Tenant
        // belum current (pola resolveWorkOrderFromRoute).
        $dailyWork = DailyWork::query()
            ->where('id', (string) $request->route('dailyWork'))
            ->firstOrFail();

        // FR-5.4: karyawan hanya mengubah item miliknya sendiri.
        abort_unless($dailyWork->employee_id === $request->user()->employee_id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,done'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $dailyWork->update([
            'status' => $validated['status'],
            'location' => $validated['location'] ?? null,
            'done_at' => $validated['status'] === 'done' ? now() : null,
        ]);

        // Kembali ke daftar tanggal item itu; tanpa query `date` bila item
        // adalah hari ini (default index sudah hari ini) — URL tetap bersih.
        $parameters = ['tenant' => tenant()?->code];

        if (! $dailyWork->work_date->isSameDay(CarbonImmutable::today())) {
            $parameters['date'] = $dailyWork->work_date->toDateString();
        }

        return redirect()->route('daily-work.index', $parameters);
    }

    /**
     * Task 3 (FR-5.1) — daftar template rutin per karyawan untuk HOD.
     * Filter: employee_id (bawahan), is_active, search judul.
     */
    public function templatesIndex(Request $request): Response
    {
        Gate::authorize('daily-work.read');

        $validated = $request->validate([
            'employee_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        if (isset($validated['employee_id'])) {
            // Karyawan di luar scope manajerial HOD tidak boleh disorot.
            abort_unless($this->employeeIsManaged($request->user(), $validated['employee_id']), 403);
        }

        $templates = DailyWorkTemplate::query()
            ->with('employee')
            // Hanya karyawan dalam scope manajerial HOD (dept yang dipimpin).
            ->whereIn('employee_id', $this->managedEmployeeQuery($request->user())->select('id'))
            ->when($validated['employee_id'] ?? null, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when(isset($validated['is_active']), fn ($query) => $query->where('is_active', (bool) $validated['is_active']))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DailyWorkTemplate $template) => $this->templateEntry($template));

        // Opsi filter karyawan (dropdown) + target form adhoc — karyawan
        // dalam scope manajerial HOD saja, dibatasi agar payload wajar.
        $employees = $this->managedEmployeeQuery($request->user())
            ->with('department')
            ->orderBy('nama_employee')
            ->limit(200)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->getKey(),
                'nama_employee' => $employee->nama_employee,
                'department_name' => $employee->department?->nama_department,
            ]);

        return Inertia::render('daily-work/templates', [
            'templates' => $templates,
            'employees' => $employees,
        ]);
    }

    /**
     * Task 3 (FR-5.1) — simpan template rutin baru untuk satu karyawan
     * bawahan. HOD department tujuan (atau deputy) saja.
     */
    public function templateStore(Request $request): RedirectResponse
    {
        Gate::authorize('daily-work.create');

        $validated = $this->validateTemplate($request);

        abort_unless($this->employeeIsManaged($request->user(), $validated['employee_id']), 403);

        DailyWorkTemplate::query()->create([
            'employee_id' => $validated['employee_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'weekdays' => $validated['weekdays'],
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->getKey(),
        ]);

        return redirect()
            ->route('daily-work.templates', ['tenant' => tenant()?->code])
            ->with('flash.toast', ['type' => 'success', 'message' => 'Template Daily Work dibuat.']);
    }

    /**
     * Task 3 (FR-5.1) — perbarui template rutin milik bawahan. Karyawan
     * pemilik template tidak berubah (lineage materialize mengikuti).
     */
    public function templateUpdate(Request $request): RedirectResponse
    {
        Gate::authorize('daily-work.update');

        $template = $this->resolveTemplateFromRoute($request);
        abort_unless($this->employeeIsManaged($request->user(), $template->employee_id), 403);

        $validated = $this->validateTemplate($request);

        $template->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'weekdays' => $validated['weekdays'],
            'is_active' => $validated['is_active'] ?? $template->is_active,
        ]);

        return redirect()
            ->route('daily-work.templates', ['tenant' => tenant()?->code])
            ->with('flash.toast', ['type' => 'success', 'message' => 'Template Daily Work diperbarui.']);
    }

    /**
     * Task 3 (FR-5.1) — hapus template. Instance harian terlanjur
     * ter-materialize tetap ada (template_id nullOnDelete = riwayat).
     */
    public function templateDestroy(Request $request): RedirectResponse
    {
        Gate::authorize('daily-work.delete');

        $template = $this->resolveTemplateFromRoute($request);
        abort_unless($this->employeeIsManaged($request->user(), $template->employee_id), 403);

        $template->delete();

        return redirect()
            ->route('daily-work.templates', ['tenant' => tenant()?->code])
            ->with('flash.toast', ['type' => 'success', 'message' => 'Template Daily Work dihapus.']);
    }

    /**
     * Task 3 (FR-5.2) — tugas tambahan HOD untuk karyawan pada tanggal
     * tertentu → notifikasi (database + broadcast + WA outbox) ke akun
     * yang ter-link (pola WorkOrderAssigned; tanpa akun → log warning).
     */
    public function adhocStore(Request $request): RedirectResponse
    {
        Gate::authorize('daily-work.create');

        $validated = $request->validate([
            'employee_id' => ['required', 'uuid'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::query()->findOrFail($validated['employee_id']);
        abort_unless($this->employeeIsManaged($request->user(), $employee->getKey()), 403);

        $dailyWork = DailyWork::query()->create([
            'employee_id' => $employee->getKey(),
            'work_date' => $validated['work_date'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'source' => 'adhoc',
            'created_by' => $request->user()->getKey(),
        ]);

        $recipient = $employee->user;

        if ($recipient === null) {
            Log::warning('Daily Work adhoc assigned to an employee without a linked akun; no in-app notification possible.', [
                'daily_work_id' => $dailyWork->getKey(),
                'employee_id' => $employee->getKey(),
            ]);
        } else {
            $recipient->notify(new DailyWorkAssigned($dailyWork));
        }

        return redirect()
            ->route('daily-work.team', [
                'tenant' => tenant()?->code,
                'employee' => $employee->getKey(),
                'date' => $validated['work_date'],
            ])
            ->with('flash.toast', ['type' => 'success', 'message' => 'Tugas tambahan dikirim ke karyawan.']);
    }

    /**
     * Task 3 — pratinjau HOD: daftar harian satu karyawan bawahan pada
     * satu tanggal (sama seperti yang dilihat karyawan; materialize dulu).
     */
    public function teamIndex(Request $request): Response
    {
        Gate::authorize('daily-work.read');

        $validated = $request->validate([
            'employee' => ['required', 'uuid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,in_progress,done'],
        ]);

        $employee = Employee::query()->findOrFail($validated['employee']);
        abort_unless($this->employeeIsManaged($request->user(), $employee->getKey()), 403);

        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'])
            : CarbonImmutable::today();

        $this->dailyWorkService->materializeFor($employee, $date);

        $dailyWork = DailyWork::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('work_date', $date->toDateString())
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DailyWork $work) => $this->workEntry($work));

        return Inertia::render('daily-work/team', [
            'dailyWork' => $dailyWork,
            'employee' => [
                'id' => $employee->getKey(),
                'nama_employee' => $employee->nama_employee,
                'department_name' => $employee->department?->nama_department,
            ],
            'date' => $date->toDateString(),
        ]);
    }

    /**
     * Validasi payload template (store & update). `employee_id` wajib saat
     * store; saat update, pemilik template tidak berubah.
     *
     * @return array{employee_id?: string, title: string, description?: string|null, weekdays: list<int>, is_active?: bool}
     */
    private function validateTemplate(Request $request): array
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            ...$validated,
            'weekdays' => array_values(array_unique(array_map('intval', $validated['weekdays']))),
        ];
    }

    /**
     * Resolusi manual template (pola resolveWorkOrderFromRoute): implicit
     * binding berjalan sebelum IdentifyTenant, scope tenant gagal.
     */
    private function resolveTemplateFromRoute(Request $request): DailyWorkTemplate
    {
        return DailyWorkTemplate::query()
            ->where('id', (string) $request->route('template'))
            ->firstOrFail();
    }

    /**
     * Query karyawan dalam scope manajerial user: semua karyawan pada
     * department yang dipimpin (hod_user_id) atau dideputikan
     * (manager_user_id) — mirror scoping review WO. Dipakai sebagai
     * subquery whereIn agar tenant scope tetap aktif.
     */
    private function managedEmployeeQuery(User $user)
    {
        $departmentIds = Department::query()
            ->where('hod_user_id', $user->getKey())
            ->orWhere('manager_user_id', $user->getKey())
            ->pluck('id');

        return Employee::query()->whereIn('department_id', $departmentIds);
    }

    private function employeeIsManaged(User $user, ?string $employeeId): bool
    {
        if ($employeeId === null) {
            return false;
        }

        return $this->managedEmployeeQuery($user)->whereKey($employeeId)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function workEntry(DailyWork $work): array
    {
        return [
            'id' => $work->id,
            'work_date' => $work->work_date->toDateString(),
            'title' => $work->title,
            'description' => $work->description,
            'location' => $work->location,
            'status' => $work->status,
            'done_at' => $work->done_at?->toDateTimeString(),
            'source' => $work->source,
            'template_id' => $work->template_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateEntry(DailyWorkTemplate $template): array
    {
        return [
            'id' => $template->id,
            'employee_id' => $template->employee_id,
            'employee_name' => $template->employee?->nama_employee,
            'title' => $template->title,
            'description' => $template->description,
            'weekdays' => $template->weekdays,
            'is_active' => $template->is_active,
        ];
    }
}
