<?php

namespace Database\Seeders;

use App\ExtendRequestStatus;
use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\ExtendRequest;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkData;
use App\Models\WorkDataPekerja;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkPlanning;
use App\Notifications\DailyWorkAssigned;
use App\Notifications\ExtendRequestSubmitted;
use App\Notifications\WorkOrderAssigned;
use App\Notifications\WorkOrderCreated;
use App\Notifications\WorkOrderRejected;
use App\Notifications\WorkOrderReviewed;
use App\Notifications\WorkOrderSubmitted;
use App\Notifications\WorkScheduleExtendRequested;
use App\Services\DeadlineCalculator;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dataset demo untuk presentasi aplikasi (fresh-only): jalankan via
 * `php artisan migrate:fresh --seed` (ter-wire setelah
 * RoleAndPermissionSeeder) atau standalone
 * `php artisan db:seed --class=DemoSeeder` (role & permission wajib sudah
 * di-seed lebih dulu karena seeder ini hanya me-ASSIGN role, tidak membuat).
 *
 * Mengisi SELURUH data demo KECUALI role & permission (milik
 * RoleAndPermissionSeeder): divisi, department, position, karyawan + akun
 * login, Work Order (semua status/kategori beserta histori, planning,
 * extend, work data), Daily Work (template + instance), dan riwayat
 * notifikasi in-app.
 *
 * Idempoten ringan: firstOrCreate pada kunci natural (tenant code, email,
 * kode department/division, nomor WO) — skenario dependen (event, planning,
 * extend, work data) hanya dibuat saat WO-nya baru. Dirancang untuk
 * database segar; tidak digabungkan dengan data sync Optigate.
 *
 * Login demo: semua akun memakai password "password" (default UserFactory).
 * Nomor WhatsApp HOD = 081347233907 (Budi Santoso — Maintenance; ADR-0009:
 * `employees.number` otoritatif, `users.phone` fallback).
 */
class DemoSeeder extends Seeder
{
    /** Nomor WhatsApp HOD untuk demo (ADR-0009). */
    private const HOD_PHONE = '081347233907';

    private const TENANT_CODE = 'hq';

    private const DEMO_PASSWORD = 'password';

    private DeadlineCalculator $deadline;

    public function run(): void
    {
        $this->deadline = app(DeadlineCalculator::class);

        $tenant = Tenant::whereCode(self::TENANT_CODE)->first()
            ?? Tenant::create([
                'optigate_company_id' => 1,
                'code' => self::TENANT_CODE,
                'name' => 'Head Office',
                'is_active' => true,
            ]);

        $tenant->makeCurrent();

        try {
            $org = $this->seedOrganization($tenant);
            $this->seedWorkOrders($org);
            $sariAdhoc = $this->seedDailyWork($org);
            $this->seedNotifications($org, $sariAdhoc);
        } finally {
            Tenant::forgetCurrent();
        }
    }

    /**
     * Struktur organisasi demo: akun + karyawan, department (HOD/manager),
     * divisi, position (level OptiGate), lalu kaitkan semuanya.
     *
     * @return array{users: array<string, User>, employees: array<string, Employee>, departments: array<string, Department>}
     */
    private function seedOrganization(Tenant $tenant): array
    {
        $departmentDefs = [
            'MTC' => ['DEP-MTC', 'Maintenance'],
            'IT' => ['DEP-IT', 'Teknologi Informasi'],
            'HK' => ['DEP-HK', 'Housekeeping'],
            'SEC' => ['DEP-SEC', 'Security'],
        ];

        $divisionDefs = [
            'MEC' => ['DIV-MEC', 'Divisi Mekanik', 'MTC'],
            'ELC' => ['DIV-ELC', 'Divisi Elektrik', 'MTC'],
            'INF' => ['DIV-INF', 'Divisi Infrastruktur', 'IT'],
        ];

        $positionLevels = [
            'Head of Department' => [3, 'Manager'],
            'Team Leader' => [5, 'Supervisor / Lead'],
            'Teknisi Senior' => [7, 'Staff / Pelaksana'],
            'Teknisi' => [7, 'Staff / Pelaksana'],
            'Staf IT' => [7, 'Staff / Pelaksana'],
            'Petugas Kebersihan' => [7, 'Staff / Pelaksana'],
            'Staf Administrasi' => [7, 'Staff / Pelaksana'],
        ];

        $hodKeys = ['MTC' => 'budi', 'IT' => 'dewi', 'HK' => 'ratna', 'SEC' => 'agus'];

        $roster = [
            'budi' => ['email' => 'budi.hod@appdutamall.com', 'nama' => 'Budi Santoso', 'role' => 'hod', 'is_hod' => true, 'dept' => 'MTC', 'position' => 'Head of Department', 'division' => null, 'phone' => self::HOD_PHONE],
            'dewi' => ['email' => 'dewi.hod@appdutamall.com', 'nama' => 'Dewi Lestari', 'role' => 'hod', 'is_hod' => true, 'dept' => 'IT', 'position' => 'Head of Department', 'division' => null, 'phone' => null],
            'ratna' => ['email' => 'ratna.hod@appdutamall.com', 'nama' => 'Ratna Puspita', 'role' => 'hod', 'is_hod' => true, 'dept' => 'HK', 'position' => 'Head of Department', 'division' => null, 'phone' => null],
            'agus' => ['email' => 'agus.hod@appdutamall.com', 'nama' => 'Agus Pratama', 'role' => 'hod', 'is_hod' => true, 'dept' => 'SEC', 'position' => 'Head of Department', 'division' => null, 'phone' => null],
            'wawan' => ['email' => 'wawan.tl@appdutamall.com', 'nama' => 'Wawan Setiawan', 'role' => 'team_leader', 'is_hod' => false, 'dept' => 'MTC', 'position' => 'Team Leader', 'division' => 'MEC', 'phone' => null],
            'sari' => ['email' => 'sari.wulandari@appdutamall.com', 'nama' => 'Sari Wulandari', 'role' => 'karyawan', 'is_hod' => false, 'dept' => 'MTC', 'position' => 'Teknisi Senior', 'division' => 'ELC', 'phone' => null],
            'rudi' => ['email' => 'rudi.hartono@appdutamall.com', 'nama' => 'Rudi Hartono', 'role' => 'karyawan', 'is_hod' => false, 'dept' => 'MTC', 'position' => 'Teknisi', 'division' => 'MEC', 'phone' => null],
            'joko' => ['email' => 'joko.susilo@appdutamall.com', 'nama' => 'Joko Susilo', 'role' => 'karyawan', 'is_hod' => false, 'dept' => 'MTC', 'position' => 'Teknisi', 'division' => 'MEC', 'phone' => null],
            'andi' => ['email' => 'andi.wijaya@appdutamall.com', 'nama' => 'Andi Wijaya', 'role' => 'field_staff', 'is_hod' => false, 'dept' => 'MTC', 'position' => 'Teknisi', 'division' => 'ELC', 'phone' => null],
            'maya' => ['email' => 'maya.it@appdutamall.com', 'nama' => 'Maya Anggraini', 'role' => 'karyawan', 'is_hod' => false, 'dept' => 'IT', 'position' => 'Staf IT', 'division' => 'INF', 'phone' => null],
            'citra' => ['email' => 'citra.hk@appdutamall.com', 'nama' => 'Citra Ayu', 'role' => 'karyawan', 'is_hod' => false, 'dept' => 'HK', 'position' => 'Petugas Kebersihan', 'division' => null, 'phone' => null],
            'dedik' => ['email' => 'admin.tenant@appdutamall.com', 'nama' => 'Dedi Kurniawan', 'role' => 'admin_tenant', 'is_hod' => false, 'dept' => null, 'position' => 'Staf Administrasi', 'division' => null, 'phone' => null],
            'hendra' => ['email' => 'gm@appdutamall.com', 'nama' => 'Hendra Gunawan', 'role' => 'general_manager', 'is_hod' => false, 'dept' => null, 'position' => null, 'division' => null, 'phone' => null],
            'iwan' => ['email' => 'dgm@appdutamall.com', 'nama' => 'Iwan Pranowo', 'role' => 'deputy_general_manager', 'is_hod' => false, 'dept' => null, 'position' => null, 'division' => null, 'phone' => null],
            'titi' => ['email' => 'viewer@appdutamall.com', 'nama' => 'Titi Rahmawati', 'role' => 'viewer', 'is_hod' => false, 'dept' => null, 'position' => null, 'division' => null, 'phone' => null],
        ];

        $users = [];
        $employees = [];
        $nikSequence = 1;

        foreach ($roster as $key => $person) {
            $user = User::firstOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['nama'],
                    'password' => self::DEMO_PASSWORD,
                    'email_verified_at' => now(),
                    'tenant_id' => $tenant->id,
                    'phone' => $person['phone'],
                    'is_hod' => $person['is_hod'],
                ],
            );
            $user->assignRole($person['role']);

            $employee = Employee::firstOrCreate(
                ['email' => $person['email']],
                [
                    'nik_employee' => 'NIK-HQ-'.str_pad((string) $nikSequence, 3, '0', STR_PAD_LEFT),
                    'nama_employee' => $person['nama'],
                    'number' => $person['phone'],
                ],
            );

            if ($user->employee_id === null) {
                $user->forceFill(['employee_id' => $employee->getKey()])->save();
            }

            $users[$key] = $user;
            $employees[$key] = $employee;
            $nikSequence++;
        }

        $departments = [];

        foreach ($departmentDefs as $key => [$kode, $nama]) {
            $hodKey = $hodKeys[$key];
            $departments[$key] = Department::firstOrCreate(
                ['kode_department' => $kode],
                [
                    'nama_department' => $nama,
                    'hod_user_id' => $users[$hodKey]->getKey(),
                    'manager_user_id' => $key === 'MTC' ? $users['wawan']->getKey() : null,
                    'hod_employee_id' => $employees[$hodKey]->getKey(),
                    'is_active' => true,
                ],
            );
        }

        $divisions = [];

        foreach ($divisionDefs as $key => [$kode, $nama, $deptKey]) {
            $divisions[$key] = Division::firstOrCreate(
                ['kode_division' => $kode],
                [
                    'nama_division' => $nama,
                    'department_id' => $departments[$deptKey]->getKey(),
                    'is_direct' => false,
                ],
            );
        }

        $positions = [];

        foreach ($roster as $person) {
            if ($person['position'] === null) {
                continue;
            }

            $deptKey = $person['dept'];
            [$level, $label] = $positionLevels[$person['position']];

            $positions[$person['position'].'|'.($deptKey ?? '')] = Position::firstOrCreate(
                [
                    'nama_position' => $person['position'],
                    'department_id' => $deptKey !== null ? $departments[$deptKey]->getKey() : null,
                ],
                ['level' => $level, 'level_label' => $label, 'is_vacant' => false],
            );
        }

        foreach ($roster as $key => $person) {
            $employee = $employees[$key];
            $employee->department_id = $person['dept'] !== null ? $departments[$person['dept']]->getKey() : null;
            $employee->position_id = $person['position'] !== null
                ? $positions[$person['position'].'|'.($person['dept'] ?? '')]->getKey()
                : null;
            $employee->division_id = $person['division'] !== null ? $divisions[$person['division']]->getKey() : null;
            $employee->save();
        }

        return [
            'users' => $users,
            'employees' => $employees,
            'departments' => $departments,
        ];
    }

    /**
     * 12 Work Order mencakup seluruh status, kategori, eskalasi, extend,
     * planning, dan Work Data — histori (event) ikut di-backdate.
     *
     * @param  array{users: array<string, User>, employees: array<string, Employee>, departments: array<string, Department>}  $org
     */
    private function seedWorkOrders(array $org): void
    {
        $mtc = $org['departments']['MTC'];
        $budi = $org['users']['budi'];
        $sariUser = $org['users']['sari'];
        $sari = $org['employees']['sari'];
        $rudi = $org['employees']['rudi'];
        $joko = $org['employees']['joko'];
        $andi = $org['employees']['andi'];
        $today = Carbon::today();

        // ── 1 · waiting_hod · Normal — baru dibuat, menunggu review HOD ──
        $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261023-0001',
            'requester_user_id' => $org['users']['citra']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Normal,
            'title' => 'AC ruang meeting lantai 1 tidak dingin',
            'description' => 'AC sudah dinyalakan sejak pagi tetapi tidak mengeluarkan udara dingin. Ruang ini dipakai rapat rutin setiap hari kerja.',
            'status' => WorkOrderStatus::WaitingHod,
        ], $today->copy()->subDay()->setTime(8, 30));

        // ── 2 · waiting_hod · Urgent by Accident — wajib eksekusi langsung ──
        $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261023-0002',
            'requester_user_id' => $org['users']['maya']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Accident,
            'title' => 'Kebocoran pipa air utama di ruang server',
            'description' => 'Pipa distribusi air di plafon ruang server menyemburkan air ke arah rak utama. Beberapa perangkat sudah dipindahkan darurat.',
            'status' => WorkOrderStatus::WaitingHod,
        ], now()->copy()->subHours(3));

        // ── 3 · hod_approved — direview eksekusi langsung, belum ditugaskan ──
        [$wo3, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261022-0003',
            'requester_user_id' => $org['users']['dedik']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Normal,
            'title' => 'Lampu darurat koridor lantai 3 mati',
            'description' => 'Empat lampu darurat di koridor lantai 3 tidak menyala saat uji mati listrik pekan lalu.',
            'status' => WorkOrderStatus::HodApproved,
            'hod_action' => 'execute_immediately',
            'hod_notes' => 'Segera cek dan perbaiki — komponen cadangan tersedia di gudang.',
        ], $today->copy()->subDays(2)->setTime(9, 0));

        if ($fresh) {
            $this->event($wo3, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::HodApproved,
                ['action' => 'execute_immediately', 'scheduled_date' => null],
                $today->copy()->subDays(2)->setTime(10, 0));
        }

        // ── 4 · scheduled · Normal — dengan Work Planning (planned) ──
        $jadwal4 = $today->copy()->addDays(3);
        [$wo4, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261021-0004',
            'requester_user_id' => $org['users']['citra']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Normal,
            'title' => 'Servis AC ruang direktur (kompresor aus)',
            'description' => 'AC ruang direktur berbunyi kasar dan pendinginan melemah. Diduga kompresor aus dan perlu penggantian.',
            'status' => WorkOrderStatus::Scheduled,
            'hod_action' => 'schedule',
            'scheduled_date' => $jadwal4,
        ], $today->copy()->subDays(3)->setTime(9, 0));

        if ($fresh) {
            $reviewed4 = $today->copy()->subDays(2)->setTime(10, 0);
            $this->event($wo4, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::Scheduled,
                ['action' => 'schedule', 'scheduled_date' => $jadwal4->toDateString()], $reviewed4);
            $this->insertAt(WorkPlanning::class, [
                'work_order_id' => $wo4->getKey(),
                'tgl_jadwal' => $jadwal4->toDateString(),
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '11:00:00',
                'jenis_pekerjaan' => 'Pergantian kompresor AC',
                'lama_pekerjaan_hari' => 1,
                'budget' => 1750000,
                'status_jadwal' => WorkPlanning::STATUS_PLANNED,
                'catatan' => 'Koordinasi dengan sekretariat untuk akses ruangan.',
            ], $reviewed4);
        }

        // ── 5 · assigned · Urgent by Owner — on-time, dua pelaksana ──
        $assigned5 = $today->copy()->subDay()->setTime(9, 0);
        $deadline5 = $this->deadline->calculate(WorkOrderCategory::Owner, $assigned5);
        [$wo5, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261022-0005',
            'requester_user_id' => $org['users']['maya']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Owner,
            'title' => 'Lift lobi utara — panel indikator berkedip',
            'description' => 'Panel indikator lift lobi utara berkedip dan kadang tidak merespons tombol lantai. Permintaan langsung dari Owner.',
            'status' => WorkOrderStatus::Assigned,
            'hod_action' => 'execute_immediately',
            'assigned_employees' => [$sari->getKey(), $rudi->getKey()],
            'assignment_notes' => 'Prioritaskan keselamatan pengguna — pasang barrier sementara di lobi.',
            'assigned_at' => $assigned5,
            'deadline_date' => $deadline5,
        ], $today->copy()->subDays(2)->setTime(8, 0));

        if ($fresh) {
            $this->event($wo5, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::HodApproved,
                ['action' => 'execute_immediately', 'scheduled_date' => null],
                $today->copy()->subDays(2)->setTime(9, 0));
            $this->event($wo5, $budi, 'assigned', WorkOrderStatus::HodApproved, WorkOrderStatus::Assigned,
                [
                    'employee_ids' => [$sari->getKey(), $rudi->getKey()],
                    'removed_employee_ids' => [],
                    'notified_user_ids' => [],
                    'unlinked_employee_ids' => [],
                    'assignment_notes' => 'Prioritaskan keselamatan pengguna — pasang barrier sementara di lobi.',
                    'deadline_date' => $deadline5->toDateString(),
                ], $assigned5);
        }

        // ── 6 · in_progress · Normal — deadline hari ini (proksimitas SLA) ──
        $assigned6 = $today->copy()->subDays(6)->setTime(10, 0);
        $deadline6 = $this->deadline->calculate(WorkOrderCategory::Normal, $assigned6);
        [$wo6, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261017-0006',
            'requester_user_id' => $org['users']['citra']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Normal,
            'title' => 'Mesin pompa air lantai 2 berbunyi nyaring',
            'description' => 'Pompa air lantai 2 mengeluarkan suara kasar saat menyala. Dikhawatirkan bearing mulai rusak.',
            'status' => WorkOrderStatus::InProgress,
            'hod_action' => 'execute_immediately',
            'assigned_employees' => [$sari->getKey(), $joko->getKey()],
            'assignment_notes' => 'Cek bearing dan alignment — siapkan pompa cadangan bila perlu.',
            'assigned_at' => $assigned6,
            'deadline_date' => $deadline6,
        ], $today->copy()->subDays(6)->setTime(8, 0));

        if ($fresh) {
            $this->event($wo6, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::HodApproved,
                ['action' => 'execute_immediately', 'scheduled_date' => null],
                $today->copy()->subDays(6)->setTime(9, 0));
            $this->event($wo6, $budi, 'assigned', WorkOrderStatus::HodApproved, WorkOrderStatus::Assigned,
                [
                    'employee_ids' => [$sari->getKey(), $joko->getKey()],
                    'removed_employee_ids' => [],
                    'notified_user_ids' => [],
                    'unlinked_employee_ids' => [],
                    'assignment_notes' => 'Cek bearing dan alignment — siapkan pompa cadangan bila perlu.',
                    'deadline_date' => $deadline6->toDateString(),
                ], $assigned6);
        }

        // ── 7 · pending_verify · Normal — telat ringan, hasil menunggu verifikasi ──
        $assigned7 = $today->copy()->subDays(8)->setTime(10, 0);
        $deadline7 = $this->deadline->calculate(WorkOrderCategory::Normal, $assigned7);
        $submitted7 = $today->copy()->subDay()->setTime(15, 0);
        [$wo7, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261016-0007',
            'requester_user_id' => $org['users']['dedik']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Normal,
            'title' => 'Penerangan area parkir basement mati sebagian',
            'description' => 'Sekitar sepertiga titik lampu parkir basement B1 mati. Area gelap menimbulkan risiko bagi pengguna.',
            'status' => WorkOrderStatus::PendingVerify,
            'hod_action' => 'execute_immediately',
            'assigned_employees' => [$rudi->getKey()],
            'assignment_notes' => 'Fokus jalur pejalan kaki dari lift ke pintu masuk.',
            'assigned_at' => $assigned7,
            'deadline_date' => $deadline7,
            'result_notes' => 'Sudah mengganti 6 titik lampu LED yang mati. Dua titik sisanya menunggu kedatangan armatur dari gudang pusat.',
        ], $today->copy()->subDays(8)->setTime(8, 30));

        if ($fresh) {
            $this->event($wo7, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::HodApproved,
                ['action' => 'execute_immediately', 'scheduled_date' => null],
                $today->copy()->subDays(8)->setTime(9, 30));
            $this->event($wo7, $budi, 'assigned', WorkOrderStatus::HodApproved, WorkOrderStatus::Assigned,
                [
                    'employee_ids' => [$rudi->getKey()],
                    'removed_employee_ids' => [],
                    'notified_user_ids' => [],
                    'unlinked_employee_ids' => [],
                    'assignment_notes' => 'Fokus jalur pejalan kaki dari lift ke pintu masuk.',
                    'deadline_date' => $deadline7->toDateString(),
                ], $assigned7);
            $this->event($wo7, $sariUser, 'submitted', WorkOrderStatus::Assigned, WorkOrderStatus::PendingVerify,
                ['notes_preview' => str((string) $wo7->result_notes)->limit(120)->toString(), 'attachment_count' => 0],
                $submitted7);
        }

        // ── 8 · revision · Normal — hasil ditolak HOD, catatan revisi ──
        $assigned8 = $today->copy()->subDays(9)->setTime(11, 0);
        $deadline8 = $this->deadline->calculate(WorkOrderCategory::Normal, $assigned8);
        $submitted8 = $today->copy()->subDays(3)->setTime(10, 0);
        $rejected8 = $today->copy()->subDays(2)->setTime(11, 0);
        $notes8 = 'Tutupan kayu belum rapat — masih rembes saat hujan. Ulangi penutupan dengan sealant waterproof.';
        [$wo8, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261015-0008',
            'requester_user_id' => $org['users']['maya']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Normal,
            'title' => 'Plafon pantry lantai 2 bocor',
            'description' => 'Ada bercak rembesan dan tetesan air di plafon pantry lantai 2 saat hujan.',
            'status' => WorkOrderStatus::Revision,
            'hod_action' => 'execute_immediately',
            'assigned_employees' => [$sari->getKey()],
            'assignment_notes' => 'Telusuri sumber bocor dari plafon atas sebelum menutup.',
            'assigned_at' => $assigned8,
            'deadline_date' => $deadline8,
            'result_notes' => 'Sudah dipasangi tutupan kayu di area bocor.',
            'hod_notes' => $notes8,
        ], $today->copy()->subDays(9)->setTime(9, 0));

        if ($fresh) {
            $this->event($wo8, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::HodApproved,
                ['action' => 'execute_immediately', 'scheduled_date' => null],
                $today->copy()->subDays(9)->setTime(10, 0));
            $this->event($wo8, $budi, 'assigned', WorkOrderStatus::HodApproved, WorkOrderStatus::Assigned,
                [
                    'employee_ids' => [$sari->getKey()],
                    'removed_employee_ids' => [],
                    'notified_user_ids' => [],
                    'unlinked_employee_ids' => [],
                    'assignment_notes' => 'Telusuri sumber bocor dari plafon atas sebelum menutup.',
                    'deadline_date' => $deadline8->toDateString(),
                ], $assigned8);
            $this->event($wo8, $sariUser, 'submitted', WorkOrderStatus::Assigned, WorkOrderStatus::PendingVerify,
                ['notes_preview' => str('Sudah dipasangi tutupan kayu di area bocor.')->limit(120)->toString(), 'attachment_count' => 0],
                $submitted8);
            $this->event($wo8, $budi, 'rejected', WorkOrderStatus::PendingVerify, WorkOrderStatus::Revision,
                ['notes' => $notes8], $rejected8);
        }

        // ── 9 · closed · Normal — lifecycle lengkap + Work Data terfinalisasi ──
        $assigned9 = $today->copy()->subDays(19)->setTime(11, 0);
        $deadline9 = $this->deadline->calculate(WorkOrderCategory::Normal, $assigned9);
        $submitted9 = $today->copy()->subDays(18)->setTime(9, 0);
        $verified9 = $today->copy()->subDays(18)->setTime(11, 30);
        [$wo9, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261005-0009',
            'requester_user_id' => $org['users']['dedik']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Normal,
            'title' => 'Keran air wudhu musholla bocor',
            'description' => 'Keran air wudhu tidak menutup sempurna sehingga air mengalir terus. Pemborosan air cukup terlihat pada tagihan.',
            'status' => WorkOrderStatus::Closed,
            'hod_action' => 'execute_immediately',
            'assigned_employees' => [$sari->getKey(), $rudi->getKey()],
            'assignment_notes' => 'Siapkan keran cadangan dari gudang sebelum membongkar.',
            'assigned_at' => $assigned9,
            'deadline_date' => $deadline9,
            'result_notes' => 'Keran dan seal sudah diganti. Tes tekanan 30 menit — tidak ada rembesan.',
        ], $today->copy()->subDays(19)->setTime(9, 0), $verified9);

        if ($fresh) {
            $this->event($wo9, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::HodApproved,
                ['action' => 'execute_immediately', 'scheduled_date' => null],
                $today->copy()->subDays(19)->setTime(10, 0));
            $this->event($wo9, $budi, 'assigned', WorkOrderStatus::HodApproved, WorkOrderStatus::Assigned,
                [
                    'employee_ids' => [$sari->getKey(), $rudi->getKey()],
                    'removed_employee_ids' => [],
                    'notified_user_ids' => [],
                    'unlinked_employee_ids' => [],
                    'assignment_notes' => 'Siapkan keran cadangan dari gudang sebelum membongkar.',
                    'deadline_date' => $deadline9->toDateString(),
                ], $assigned9);
            $this->event($wo9, $sariUser, 'submitted', WorkOrderStatus::Assigned, WorkOrderStatus::PendingVerify,
                ['notes_preview' => str('Keran dan seal sudah diganti. Tes tekanan 30 menit — tidak ada rembesan.')->limit(120)->toString(), 'attachment_count' => 0],
                $submitted9);
            $this->event($wo9, $budi, 'verified', WorkOrderStatus::PendingVerify, WorkOrderStatus::Closed, [], $verified9);

            $workData9 = $this->insertAt(WorkData::class, [
                'work_order_id' => $wo9->getKey(),
                'no_kerja' => $wo9->nomor_wo,
                'department_id' => $mtc->getKey(),
                'status_pekerjaan' => 'selesai',
                'tgl_mulai_aktual' => $assigned9->toDateString(),
                'tgl_selesai_aktual' => $verified9->toDateString(),
                'jam_pengerjaan' => 24.50,
                'gambar_sebelum' => [],
                'gambar_sesudah' => [],
                'prediksi_penyebab' => 'Seal keran aus karena usia pakai lebih dari 3 tahun.',
                'tindakan' => 'Penggantian keran dan seal baru, dilanjutkan tes tekanan 30 menit.',
                'hasil_kesimpulan' => 'Keran berfungsi normal, tidak ada rembesan setelah penggantian.',
                'saran_solusi' => 'Tambahkan inspeksi keran wudhu ke patroli teknik bulanan.',
                'pic_lead_user_id' => $budi->getKey(),
                'finalized_at' => $today->copy()->subDays(17)->setTime(10, 0),
            ], $verified9);

            foreach ([$sari, $rudi] as $pekerja) {
                WorkDataPekerja::create([
                    'work_data_id' => $workData9->getKey(),
                    'employee_id' => $pekerja->getKey(),
                    'nama_employee' => $pekerja->nama_employee,
                    'role_pekerja' => 'pelaksana',
                ]);
            }
        }

        // ── 10 · closed · Urgent by Accident — Work Data belum difinalisasi ──
        $assigned10 = $today->copy()->subDays(23)->setTime(13, 0);
        $deadline10 = $this->deadline->calculate(WorkOrderCategory::Accident, $assigned10);
        $submitted10 = $today->copy()->subDays(22)->setTime(9, 0);
        $verified10 = $today->copy()->subDays(22)->setTime(11, 0);
        [$wo10, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261001-0010',
            'requester_user_id' => $org['users']['maya']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Accident,
            'title' => 'Panel utama MDP trip — lantai 3 gelap total',
            'description' => 'Panel MDP lantai 3 trip mendadak sehingga seluruh lantai kehilangan listrik. Terjadi saat jam operasional.',
            'status' => WorkOrderStatus::Closed,
            'hod_action' => 'execute_immediately',
            'assigned_employees' => [$andi->getKey(), $rudi->getKey()],
            'assignment_notes' => 'Gunakan APD lengkap — panel masih berpotensi tersisa daya.',
            'assigned_at' => $assigned10,
            'deadline_date' => $deadline10,
            'result_notes' => 'Trip disebabkan terminal loosen pada breaker utama. Terminal dikencangkan, beban normal kembali.',
        ], $today->copy()->subDays(23)->setTime(12, 0), $verified10);

        if ($fresh) {
            $this->event($wo10, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::HodApproved,
                ['action' => 'execute_immediately', 'scheduled_date' => null],
                $today->copy()->subDays(23)->setTime(12, 30));
            $this->event($wo10, $budi, 'assigned', WorkOrderStatus::HodApproved, WorkOrderStatus::Assigned,
                [
                    'employee_ids' => [$andi->getKey(), $rudi->getKey()],
                    'removed_employee_ids' => [],
                    'notified_user_ids' => [],
                    'unlinked_employee_ids' => [],
                    'assignment_notes' => 'Gunakan APD lengkap — panel masih berpotensi tersisa daya.',
                    'deadline_date' => $deadline10->toDateString(),
                ], $assigned10);
            $this->event($wo10, $sariUser, 'submitted', WorkOrderStatus::Assigned, WorkOrderStatus::PendingVerify,
                ['notes_preview' => str('Trip disebabkan terminal loosen pada breaker utama. Terminal dikencangkan, beban normal kembali.')->limit(120)->toString(), 'attachment_count' => 0],
                $submitted10);
            $this->event($wo10, $budi, 'verified', WorkOrderStatus::PendingVerify, WorkOrderStatus::Closed, [], $verified10);

            $workData10 = $this->insertAt(WorkData::class, [
                'work_order_id' => $wo10->getKey(),
                'no_kerja' => $wo10->nomor_wo,
                'department_id' => $mtc->getKey(),
                'status_pekerjaan' => 'selesai',
                'tgl_mulai_aktual' => $assigned10->toDateString(),
                'tgl_selesai_aktual' => $verified10->toDateString(),
                'jam_pengerjaan' => 22.00,
                'gambar_sebelum' => [],
                'gambar_sesudah' => [],
                'prediksi_penyebab' => null,
                'tindakan' => null,
                'hasil_kesimpulan' => null,
                'saran_solusi' => null,
                'pic_lead_user_id' => $budi->getKey(),
                'finalized_at' => null,
            ], $verified10);

            foreach ([$andi, $rudi] as $pekerja) {
                WorkDataPekerja::create([
                    'work_data_id' => $workData10->getKey(),
                    'employee_id' => $pekerja->getKey(),
                    'nama_employee' => $pekerja->nama_employee,
                    'role_pekerja' => 'pelaksana',
                ]);
            }
        }

        // ── 11 · assigned · Normal — telat + eskalasi berjenjang + extend ──
        $assigned11 = $today->copy()->subDays(15)->setTime(11, 0);
        $originalDeadline11 = $this->deadline->calculate(WorkOrderCategory::Normal, $assigned11);
        $extendedDeadline11 = $this->deadline->addBusinessDays($originalDeadline11, 2);
        $telat11 = (int) $originalDeadline11->copy()->startOfDay()->diffInDays($today);
        [$wo11, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261011-0011',
            'requester_user_id' => $org['users']['citra']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Normal,
            'title' => 'Chiller gedung B tidak dingin',
            'description' => 'Suhu area gedung B naik terus meski chiller berjalan. Perbaikan menunggu spare part kompresor dari vendor.',
            'status' => WorkOrderStatus::Assigned,
            'hod_action' => 'execute_immediately',
            'assigned_employees' => [$sari->getKey(), $andi->getKey()],
            'assignment_notes' => 'Cek tekanan freon dan kondensor; vendor disiapkan bila butuh part.',
            'assigned_at' => $assigned11,
            'deadline_date' => $extendedDeadline11,
            'escalation_h3_sent_at' => $telat11 >= 3 ? $originalDeadline11->copy()->addDays(3) : null,
            'escalation_h5_sent_at' => $telat11 >= 5 ? $originalDeadline11->copy()->addDays(5) : null,
            'escalation_h6_sent_at' => $telat11 >= 6 ? $originalDeadline11->copy()->addDays(6) : null,
            'is_escalated' => true,
            'extend_count' => 1,
            'extend_reason' => 'Menunggu spare part kompresor dari vendor Jakarta.',
            'extended_at' => $today->copy()->subDays(3)->setTime(9, 0),
        ], $today->copy()->subDays(15)->setTime(9, 0));

        if ($fresh) {
            $this->event($wo11, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::HodApproved,
                ['action' => 'execute_immediately', 'scheduled_date' => null],
                $today->copy()->subDays(15)->setTime(10, 0));
            $this->event($wo11, $budi, 'assigned', WorkOrderStatus::HodApproved, WorkOrderStatus::Assigned,
                [
                    'employee_ids' => [$sari->getKey(), $andi->getKey()],
                    'removed_employee_ids' => [],
                    'notified_user_ids' => [],
                    'unlinked_employee_ids' => [],
                    'assignment_notes' => 'Cek tekanan freon dan kondensor; vendor disiapkan bila butuh part.',
                    'deadline_date' => $extendedDeadline11->toDateString(),
                ], $assigned11);

            $approvedExtend = $this->insertAt(ExtendRequest::class, [
                'work_order_id' => $wo11->getKey(),
                'requested_by_user_id' => $sariUser->getKey(),
                'days' => 2,
                'reason' => 'Menunggu spare part kompresor dari vendor Jakarta.',
                'status' => ExtendRequestStatus::Approved,
                'team_leader_user_id' => $org['users']['wawan']->getKey(),
                'team_leader_approved_at' => $today->copy()->subDays(4)->setTime(13, 0),
                'approved_by_user_id' => $budi->getKey(),
                'approved_at' => $today->copy()->subDays(3)->setTime(9, 0),
            ], $today->copy()->subDays(4)->setTime(10, 0));

            $rejectedExtend = $this->insertAt(ExtendRequest::class, [
                'work_order_id' => $wo11->getKey(),
                'requested_by_user_id' => $sariUser->getKey(),
                'days' => 1,
                'reason' => 'Perkiraan selesai hari Jumat, mohon toleransi satu hari.',
                'status' => ExtendRequestStatus::Rejected,
                'rejected_by_user_id' => $budi->getKey(),
                'rejected_at' => $today->copy()->subDays(2)->setTime(13, 0),
                'rejected_reason' => 'Vendor bisa kirim hari ini — pekerjaan harus tetap jalan.',
            ], $today->copy()->subDays(2)->setTime(10, 0));

            $pendingExtend = $this->insertAt(ExtendRequest::class, [
                'work_order_id' => $wo11->getKey(),
                'requested_by_user_id' => $sariUser->getKey(),
                'days' => 1,
                'reason' => 'Progres 80% — sisa pekerjaan flushing sistem.',
                'status' => ExtendRequestStatus::PendingHod,
            ], now()->copy()->subHour());

            $this->event($wo11, $sariUser, 'extend_requested', null, null,
                ['extend_request_id' => $approvedExtend->getKey(), 'days' => 2],
                $today->copy()->subDays(4)->setTime(10, 0));
            $this->event($wo11, $org['users']['wawan'], 'extend_tl_approved', null, null,
                ['extend_request_id' => $approvedExtend->getKey(), 'days' => 2],
                $today->copy()->subDays(4)->setTime(13, 0));
            $this->event($wo11, $budi, 'extend_approved', null, null,
                ['extend_request_id' => $approvedExtend->getKey(), 'days' => 2],
                $today->copy()->subDays(3)->setTime(9, 0));
            $this->event($wo11, $sariUser, 'extend_requested', null, null,
                ['extend_request_id' => $rejectedExtend->getKey(), 'days' => 1],
                $today->copy()->subDays(2)->setTime(10, 0));
            $this->event($wo11, $budi, 'extend_rejected', null, null,
                ['extend_request_id' => $rejectedExtend->getKey(), 'days' => 1],
                $today->copy()->subDays(2)->setTime(13, 0));
            $this->event($wo11, $sariUser, 'extend_requested', null, null,
                ['extend_request_id' => $pendingExtend->getKey(), 'days' => 1],
                now()->copy()->subHour());
        }

        // ── 12 · scheduled · Owner — Extend Work Schedule menunggu DGM/GM ──
        $jadwal12 = $today->copy()->addDays(2);
        $proposed12 = $this->deadline->addBusinessDays($jadwal12, 3);
        [$wo12, $fresh] = $this->workOrder([
            'nomor_wo' => 'WO-HQ-20261022-0012',
            'requester_user_id' => $org['users']['maya']->getKey(),
            'target_department_id' => $mtc->getKey(),
            'category' => WorkOrderCategory::Owner,
            'title' => 'Renovasi & relokasi rak server ruang IT',
            'description' => 'Pemindahan rak server ke ruang baru sesuai arahan Owner — memerlukan koordinasi downtime dengan IT.',
            'status' => WorkOrderStatus::Scheduled,
            'hod_action' => 'schedule',
            'scheduled_date' => $jadwal12,
        ], $today->copy()->subDays(2)->setTime(9, 0));

        if ($fresh) {
            $reviewed12 = $today->copy()->subDay()->setTime(10, 0);
            $extendRequested12 = now()->copy()->subHours(2);
            $this->event($wo12, $budi, 'reviewed', WorkOrderStatus::WaitingHod, WorkOrderStatus::Scheduled,
                ['action' => 'schedule', 'scheduled_date' => $jadwal12->toDateString()], $reviewed12);

            $this->insertAt(WorkPlanning::class, [
                'work_order_id' => $wo12->getKey(),
                'tgl_jadwal' => $jadwal12->toDateString(),
                'jam_mulai' => '09:00:00',
                'jam_selesai' => '15:00:00',
                'jenis_pekerjaan' => 'Relokasi rak server',
                'lama_pekerjaan_hari' => 2,
                'budget' => 4250000,
                'status_jadwal' => WorkPlanning::STATUS_PENDING,
                'catatan' => 'Downtime perlu disepakati dengan tim IT.',
                'original_tgl_jadwal' => $jadwal12->toDateString(),
                'proposed_tgl_jadwal' => $proposed12->toDateString(),
                'extend_reason' => 'Rak baru tiba dari vendor terlambat — perlu penundaan 3 hari kerja.',
                'extend_requested_by' => $budi->getKey(),
                'extend_requested_at' => $extendRequested12,
            ], $reviewed12);

            $this->event($wo12, $budi, 'schedule_extend_requested', null, null,
                [
                    'reason' => 'Rak baru tiba dari vendor terlambat — perlu penundaan 3 hari kerja.',
                    'proposed_tgl_jadwal' => $proposed12->toDateString(),
                ], $extendRequested12);
        }
    }

    /**
     * Daily Work: 4 template per karyawan (dikelola HOD), materialize
     * tanggal hari ini (hanya weekday yang cocok — meniru
     * DailyWorkService::materializeFor), tugas adhoc HOD, dan riwayat.
     *
     * @param  array{users: array<string, User>, employees: array<string, Employee>}  $org
     * @return DailyWork adhoc hari ini milik Sari (dipakai notifikasi demo)
     */
    private function seedDailyWork(array $org): DailyWork
    {
        $today = Carbon::today();
        $budiId = $org['users']['budi']->getKey();

        $templateDefs = [
            [$org['employees']['sari'], 'Patroli & inspeksi area teknik', 'Ronde pagi: cek AHU, panel distribusi, dan pump room.', [1, 2, 3, 4, 5]],
            [$org['employees']['sari'], 'Cek level bahan bakar genset', null, [1, 4]],
            [$org['employees']['rudi'], 'Inspeksi pompa air & panel listrik', 'Catat tekanan pompa dan suhu panel MDP.', [1, 2, 3, 4, 5]],
            [$org['employees']['andi'], 'Ronde keamanan & kebersihan area luar', null, [2, 4, 6]],
        ];

        $templates = [];

        foreach ($templateDefs as [$employee, $title, $description, $weekdays]) {
            $templates[] = DailyWorkTemplate::firstOrCreate(
                ['employee_id' => $employee->getKey(), 'title' => $title],
                ['description' => $description, 'weekdays' => $weekdays, 'is_active' => true, 'created_by' => $budiId],
            );
        }

        $cycle = ['done', 'in_progress', 'pending'];
        $index = 0;

        foreach ($templates as $template) {
            if (! in_array($today->dayOfWeek, $template->weekdays, true)) {
                continue;
            }

            $status = $cycle[$index % 3];
            $this->firstOrCreateDailyWork(
                [
                    'employee_id' => $template->employee_id,
                    'work_date' => $today,
                    'title' => $template->title,
                    'source' => 'template',
                    'template_id' => $template->getKey(),
                ],
                [
                    'description' => $template->description,
                    'status' => $status,
                    'done_at' => $status === 'done' ? $today->copy()->setTime(9, 15) : null,
                    'created_by' => null,
                ],
            );
            $index++;
        }

        // Adhoc pertama milik Sari — dikembalikan untuk notifikasi demo.
        $sariAdhoc = $this->firstOrCreateDailyWork(
            [
                'employee_id' => $org['employees']['sari']->getKey(),
                'work_date' => $today,
                'title' => 'Bantu penataan kabel di aula utama',
                'source' => 'adhoc',
                'template_id' => null,
            ],
            [
                'description' => 'Persiapan townhall — koordinasi dengan IT.',
                'location' => 'Aula Utama',
                'status' => 'pending',
                'done_at' => null,
                'created_by' => $budiId,
            ],
        );

        $adhocToday = [
            [$org['employees']['rudi'], 'Ganti lampu taman depan', null, 'Taman Depan', 'in_progress', null],
        ];

        foreach ($adhocToday as [$employee, $title, $description, $location, $status, $doneAt]) {
            $this->firstOrCreateDailyWork(
                [
                    'employee_id' => $employee->getKey(),
                    'work_date' => $today,
                    'title' => $title,
                    'source' => 'adhoc',
                    'template_id' => null,
                ],
                [
                    'description' => $description,
                    'location' => $location,
                    'status' => $status,
                    'done_at' => $doneAt,
                    'created_by' => $budiId,
                ],
            );
        }

        $history = [
            [$org['employees']['sari'], $today->copy()->subDays(3), 'Pembersihan filter AC ruang server', 'Ruang Server'],
            [$org['employees']['rudi'], $today->copy()->subDays(2), 'Pelumasan bearing pompa sirkulasi', 'Pump Room'],
        ];

        foreach ($history as [$employee, $date, $title, $location]) {
            $this->firstOrCreateDailyWork(
                [
                    'employee_id' => $employee->getKey(),
                    'work_date' => $date,
                    'title' => $title,
                    'source' => 'adhoc',
                    'template_id' => null,
                ],
                [
                    'description' => null,
                    'location' => $location,
                    'status' => 'done',
                    'done_at' => $date->copy()->setTime(10, 30),
                    'created_by' => $budiId,
                ],
            );
        }

        return $sariAdhoc;
    }

    /**
     * Riwayat notifikasi in-app (bel + history) — baris mentah bertipe FQCN
     * dengan payload persis bentuk toArray() notification aslinya.
     *
     * @param  array{users: array<string, User>, employees: array<string, Employee>}  $org
     */
    private function seedNotifications(array $org, DailyWork $sariAdhoc): void
    {
        $budi = $org['users']['budi'];
        $today = Carbon::today();

        $wo1 = WorkOrder::where('nomor_wo', 'WO-HQ-20261023-0001')->firstOrFail();
        $wo2 = WorkOrder::where('nomor_wo', 'WO-HQ-20261023-0002')->firstOrFail();
        $wo3 = WorkOrder::where('nomor_wo', 'WO-HQ-20261022-0003')->firstOrFail();
        $wo4 = WorkOrder::where('nomor_wo', 'WO-HQ-20261021-0004')->firstOrFail();
        $wo5 = WorkOrder::where('nomor_wo', 'WO-HQ-20261022-0005')->firstOrFail();
        $wo6 = WorkOrder::where('nomor_wo', 'WO-HQ-20261017-0006')->firstOrFail();
        $wo7 = WorkOrder::where('nomor_wo', 'WO-HQ-20261016-0007')->firstOrFail();
        $wo8 = WorkOrder::where('nomor_wo', 'WO-HQ-20261015-0008')->firstOrFail();
        $wo12 = WorkOrder::where('nomor_wo', 'WO-HQ-20261022-0012')->firstOrFail();
        $pendingExtend = ExtendRequest::where('status', ExtendRequestStatus::PendingHod->value)->firstOrFail();
        $pendingPlanning = WorkPlanning::where('status_jadwal', WorkPlanning::STATUS_PENDING)->firstOrFail();

        $workOrderData = fn (string $type, WorkOrder $wo): array => [
            'type' => $type,
            'work_order_id' => $wo->getKey(),
            'nomor_wo' => $wo->nomor_wo,
            'title' => $wo->title,
            'category' => $wo->category->value,
            'target_department_id' => $wo->target_department_id,
        ];

        // Budi (HOD Maintenance) — 2 WO baru belum dibaca, hasil submit, 1 lama dibaca.
        $this->notify($budi, WorkOrderCreated::class,
            [...$workOrderData('work_order.created', $wo1),
                'message' => sprintf('WO baru "%s" (%s) menunggu review Anda.', $wo1->title, $wo1->nomor_wo),
            ], $wo1->created_at->copy());
        $this->notify($budi, WorkOrderCreated::class,
            [...$workOrderData('work_order.created', $wo2),
                'message' => sprintf('WO baru "%s" (%s) menunggu review Anda.', $wo2->title, $wo2->nomor_wo),
            ], $wo2->created_at->copy());
        $this->notify($budi, WorkOrderCreated::class,
            [...$workOrderData('work_order.created', $wo3),
                'message' => sprintf('WO baru "%s" (%s) menunggu review Anda.', $wo3->title, $wo3->nomor_wo),
            ], $wo3->created_at->copy(), $wo3->created_at->copy()->addHour());
        $this->notify($budi, WorkOrderSubmitted::class,
            [...$workOrderData('work_order.submitted', $wo7),
                'message' => sprintf('Pelaksana menyerahkan hasil WO %s ("%s") — menunggu verifikasi HOD.', $wo7->nomor_wo, $wo7->title),
            ], $today->copy()->subDay()->setTime(15, 0));
        $this->notify($budi, ExtendRequestSubmitted::class,
            [
                'type' => 'work_order.extend_requested',
                'extend_request_id' => $pendingExtend->getKey(),
                'work_order_id' => $pendingExtend->work_order_id,
                'nomor_wo' => $pendingExtend->workOrder->nomor_wo,
                'title' => $pendingExtend->workOrder->title,
                'days' => $pendingExtend->days,
                'message' => sprintf('Pengajuan extend %d hari untuk WO %s ("%s") menunggu persetujuan.', $pendingExtend->days, $pendingExtend->workOrder->nomor_wo, $pendingExtend->workOrder->title),
            ], now()->copy()->subHour());

        // Sari — penugasan WO + tugas tambahan HOD.
        $this->notify($org['users']['sari'], WorkOrderAssigned::class,
            [...$workOrderData('work_order.assigned', $wo5),
                'message' => sprintf('Anda ditugaskan pada WO %s ("%s") — deadline %s.', $wo5->nomor_wo, $wo5->title, $wo5->deadline_date?->isoFormat('D MMMM Y')),
            ], $wo5->assigned_at?->copy() ?? now());
        $this->notify($org['users']['sari'], WorkOrderAssigned::class,
            [...$workOrderData('work_order.assigned', $wo6),
                'message' => sprintf('Anda ditugaskan pada WO %s ("%s") — deadline %s.', $wo6->nomor_wo, $wo6->title, $wo6->deadline_date?->isoFormat('D MMMM Y')),
            ], $wo6->assigned_at?->copy() ?? now(), $wo6->assigned_at?->copy()->addHours(2));
        $this->notify($org['users']['sari'], DailyWorkAssigned::class,
            [
                'type' => 'daily_work.assigned',
                'daily_work_id' => $sariAdhoc->getKey(),
                'work_date' => $sariAdhoc->work_date->toDateString(),
                'title' => $sariAdhoc->title,
                'location' => $sariAdhoc->location,
                'message' => sprintf('Tugas tambahan "%s" untuk %s ditambahkan HOD.', $sariAdhoc->title, $sariAdhoc->work_date->format('d M Y')),
            ], $today->copy()->setTime(8, 5));

        // Rudi — penugasan WO lift.
        $this->notify($org['users']['rudi'], WorkOrderAssigned::class,
            [...$workOrderData('work_order.assigned', $wo5),
                'message' => sprintf('Anda ditugaskan pada WO %s ("%s") — deadline %s.', $wo5->nomor_wo, $wo5->title, $wo5->deadline_date?->isoFormat('D MMMM Y')),
            ], $wo5->assigned_at?->copy() ?? now());

        // Maya (requester) — keputusan review + hasil ditolak.
        $this->notify($org['users']['maya'], WorkOrderReviewed::class,
            [...$workOrderData('work_order.reviewed', $wo4),
                'decision' => $wo4->hod_action,
                'message' => sprintf('WO %s ("%s") dijadwalkan pada %s.', $wo4->nomor_wo, $wo4->title, $wo4->scheduled_date?->isoFormat('D MMMM Y')),
            ], $today->copy()->subDays(2)->setTime(10, 0));
        $this->notify($org['users']['maya'], WorkOrderRejected::class,
            [...$workOrderData('work_order.rejected', $wo8),
                'message' => sprintf('Hasil WO %s ("%s") dikembalikan untuk revisi. Catatan HOD: %s', $wo8->nomor_wo, $wo8->title, $wo8->hod_notes),
            ], $today->copy()->subDays(2)->setTime(11, 0));

        // Citra (requester) — hasil review lama (dibaca).
        $this->notify($org['users']['citra'], WorkOrderReviewed::class,
            [...$workOrderData('work_order.reviewed', $wo3),
                'decision' => $wo3->hod_action,
                'message' => sprintf('WO %s ("%s") disetujui untuk dieksekusi langsung.', $wo3->nomor_wo, $wo3->title),
            ], $today->copy()->subDays(2)->setTime(10, 0), $today->copy()->subDays(2)->setTime(11, 0));

        // Wawan (TL) — pengajuan extend tahap TL (sudah diputuskan, dibaca).
        $approvedExtend = ExtendRequest::where('status', ExtendRequestStatus::Approved->value)->firstOrFail();
        $this->notify($org['users']['wawan'], ExtendRequestSubmitted::class,
            [
                'type' => 'work_order.extend_requested',
                'extend_request_id' => $approvedExtend->getKey(),
                'work_order_id' => $approvedExtend->work_order_id,
                'nomor_wo' => $approvedExtend->workOrder->nomor_wo,
                'title' => $approvedExtend->workOrder->title,
                'days' => $approvedExtend->days,
                'message' => sprintf('Pengajuan extend %d hari untuk WO %s ("%s") menunggu persetujuan.', $approvedExtend->days, $approvedExtend->workOrder->nomor_wo, $approvedExtend->workOrder->title),
            ], $today->copy()->subDays(4)->setTime(10, 0), $today->copy()->subDays(4)->setTime(13, 0));

        // GM & DGM — Extend Work Schedule menunggu approval.
        foreach (['hendra', 'iwan'] as $key) {
            $this->notify($org['users'][$key], WorkScheduleExtendRequested::class,
                [
                    'type' => 'work_planning.extend_requested',
                    'work_planning_id' => $pendingPlanning->getKey(),
                    'work_order_id' => $wo12->getKey(),
                    'nomor_wo' => $wo12->nomor_wo,
                    'title' => $wo12->title,
                    'tgl_jadwal' => $pendingPlanning->tgl_jadwal?->toDateString(),
                    'proposed_tgl_jadwal' => $pendingPlanning->proposed_tgl_jadwal?->toDateString(),
                    'message' => sprintf('Extend jadwal WO %s ("%s"): %s → %s menunggu approval DGM/GM.', $wo12->nomor_wo, $wo12->title, $pendingPlanning->tgl_jadwal?->toDateString() ?? '-', $pendingPlanning->proposed_tgl_jadwal?->toDateString() ?? '-'),
                ], now()->copy()->subHours(2));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: WorkOrder, 1: bool} [workOrder, freshlyCreated]
     */
    private function workOrder(array $attributes, CarbonInterface $createdAt, ?CarbonInterface $updatedAt = null): array
    {
        $existing = WorkOrder::query()->where('nomor_wo', $attributes['nomor_wo'])->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        $workOrder = new WorkOrder($attributes);
        $workOrder->created_at = $createdAt;
        $workOrder->updated_at = $updatedAt ?? $createdAt;
        $workOrder->save();

        return [$workOrder, true];
    }

    /**
     * Baris histori WO (append-only) dengan timestamp demo.
     *
     * @param  array<string, mixed>  $meta
     */
    private function event(WorkOrder $workOrder, ?User $actor, string $action, ?WorkOrderStatus $from, ?WorkOrderStatus $to, array $meta, CarbonInterface $at): void
    {
        $event = new WorkOrderEvent([
            'work_order_id' => $workOrder->getKey(),
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'meta' => $meta === [] ? null : $meta,
        ]);
        $event->created_at = $at;
        $event->updated_at = $at;
        $event->save();
    }

    /**
     * Insert dengan created_at/updated_at eksplisit (demo histori) —
     * timestamp bukan fillable, jadi diset sebagai atribut sebelum save.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $attributes
     */
    private function insertAt(string $model, array $attributes, CarbonInterface $at, ?CarbonInterface $updatedAt = null): object
    {
        $row = new $model($attributes);
        $row->created_at = $at;
        $row->updated_at = $updatedAt ?? $at;
        $row->save();

        return $row;
    }

    /**
     * firstOrCreate Daily Work dengan lookup aman lintas-DB: kolom DATE di
     * sqlite tersimpan sebagai 'Y-m-d H:i:s' (pola whereDate dari
     * DailyWorkService::materializeFor) — pencocokan string 'Y-m-d' polos
     * meleset lalu menabrak unique (template_id, work_date) saat re-run.
     *
     * @param  array<string, mixed>  $lookup  employee_id, work_date, title, source, template_id?
     * @param  array<string, mixed>  $values  atribut pelengkap saat membuat baris baru
     */
    private function firstOrCreateDailyWork(array $lookup, array $values): DailyWork
    {
        $workDate = $lookup['work_date'] instanceof CarbonInterface
            ? $lookup['work_date']->toDateString()
            : (string) $lookup['work_date'];

        $found = DailyWork::query()
            ->whereDate('work_date', $workDate)
            ->where('employee_id', $lookup['employee_id'])
            ->where('source', $lookup['source'])
            ->where('title', $lookup['title'])
            ->when(
                ($lookup['template_id'] ?? null) !== null,
                fn (Builder $query) => $query->where('template_id', $lookup['template_id']),
                fn (Builder $query) => $query->whereNull('template_id'),
            )
            ->first();

        if ($found !== null) {
            return $found;
        }

        return DailyWork::create(array_merge($lookup, $values));
    }

    /**
     * Satu baris notifikasi database (bel in-app) dengan timestamp demo.
     *
     * @param  array<string, mixed>  $data
     */
    private function notify(User $user, string $type, array $data, CarbonInterface $at, ?CarbonInterface $readAt = null): void
    {
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $data,
        ]);
        $notification->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
            'read_at' => $readAt,
        ])->save();
    }
}
