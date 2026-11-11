<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkPlanning;
use App\Services\WorkPlanningService;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data dev untuk Gantt Jadwal (`work-orders/gantt`).
 *
 * Gantt membaca `work_plannings`, dan seeder ini mengisi SEMUA keadaan yang
 * harus terlihat di layar supaya halamannya bisa dinilai tanpa mengetik data
 * manual:
 *
 *  - beberapa departemen → baris induk (parentID) lebih dari satu
 *  - durasi berbeda-beda → panjang bar bervariasi
 *  - jam_mulai/jam_selesai → kolom "Mulai" tidak semua kosong
 *  - status_jadwal planned / pending / rescheduled → ketiga label muncul
 *  - deadline on_track / due_soon / overdue / escalated → keempat pewarnaan
 *    taskbar muncul berpasangan dengan label teksnya
 *
 * Sengaja memakai WorkPlanningService::createForWorkOrder() (sama seperti alur
 * produksi saat HOD review "schedule") daripada insert langsung, supaya seeder
 * tidak jadi sumber kebenaran kedua soal bagaimana planning lahir.
 *
 * Idempoten: WO dicari ulang lewat nomor_wo, jadi aman dijalankan berulang.
 */
class WorkPlanningSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(WorkPlanningService::class);

        // Tiap tenant aktif punya jadwal sendiri; sekalikan di context-nya.
        $tenants = Tenant::query()->where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $tenant->makeCurrent();

            $today = Carbon::today();
            $monday = $today->copy()->startOfWeek(Carbon::MONDAY);

            foreach ($this->departments() as $code => $spec) {
                $department = Department::updateOrCreate(
                    ['kode_department' => $code],
                    ['nama_department' => $spec['name']],
                );

                foreach ($spec['workOrders'] as $index => $woSpec) {
                    $this->seedWorkOrder(
                        $department,
                        $index,
                        $woSpec,
                        $monday,
                        $service,
                    );
                }
            }
        }

        Tenant::forgetCurrent();
    }

    /**
     * Departemen + WO terjadwal. Offset hari dihitung dari Senin minggu ini
     * (0 = Senin … 6 = Minggu) supaya datanya selalu jatuh di minggu yang
     * sedang ditampilkan Gantt, kapan pun seeder dijalankan.
     *
     * @return array<string, array<string, mixed>>
     */
    private function departments(): array
    {
        return [
            'SEED-MEP' => [
                'name' => 'MEP',
                'workOrders' => [
                    ['dayOffset' => 0, 'title' => 'Ganti bearing motor AHU-03', 'duration' => 3, 'start' => '08:00', 'end' => '16:00', 'deadline' => 'on_track', 'status' => WorkOrderStatus::InProgress],
                    ['dayOffset' => 1, 'title' => 'Perbaikan pompa boiler B1', 'duration' => 1, 'start' => '09:00', 'end' => '12:00', 'deadline' => 'due_soon', 'status' => WorkOrderStatus::Assigned],
                    ['dayOffset' => 3, 'title' => 'Bersihkan filter air pendingin', 'duration' => 2, 'start' => '13:00', 'end' => '17:00', 'deadline' => 'overdue', 'status' => WorkOrderStatus::InProgress],                ],
            ],
            'SEED-HVAC' => [
                'name' => 'HVAC & BMS',
                'workOrders' => [
                    ['dayOffset' => 0, 'title' => 'Kalibrasi sensor suhu lantai 5', 'duration' => 1, 'start' => '08:30', 'end' => '11:30', 'deadline' => 'on_track', 'status' => WorkOrderStatus::Closed],
                    ['dayOffset' => 2, 'title' => 'Overhaul chiller unit 01', 'duration' => 4, 'start' => '07:00', 'end' => '18:00', 'deadline' => 'escalated', 'status' => WorkOrderStatus::InProgress],
                    ['dayOffset' => 4, 'title' => 'Update setpoint BMS pusat', 'duration' => 1, 'start' => '10:00', 'end' => '15:00', 'deadline' => 'on_track', 'status' => WorkOrderStatus::Scheduled],
                ],
            ],
            'SEED-ITSEC' => [
                'name' => 'IT & Security',
                'workOrders' => [
                    ['dayOffset' => 1, 'title' => 'Ganti kamera CCTV koridor barat', 'duration' => 2, 'start' => '09:00', 'end' => '14:00', 'deadline' => 'on_track', 'status' => WorkOrderStatus::Assigned],
                    ['dayOffset' => 2, 'title' => 'Patch switch akses data center', 'duration' => 1, 'start' => '20:00', 'end' => '23:00', 'deadline' => 'due_soon', 'status' => WorkOrderStatus::PendingVerify],
                ],
            ],
            'SEED-CIVIL' => [
                'name' => 'Sipil & Interior',
                'workOrders' => [
                    ['dayOffset' => 0, 'title' => 'Tambal retak dinding parkir B2', 'duration' => 5, 'start' => '08:00', 'end' => '17:00', 'deadline' => 'overdue', 'status' => WorkOrderStatus::InProgress],
                    ['dayOffset' => 3, 'title' => 'Perbaikan plafon ruang rapat', 'duration' => 2, 'start' => '09:00', 'end' => '13:00', 'deadline' => 'on_track', 'status' => WorkOrderStatus::Scheduled, 'scheduleStatus' => WorkPlanning::STATUS_PENDING],
                ],
            ],
            'SEED-HK' => [
                'name' => 'Housekeeping',
                'workOrders' => [
                    ['dayOffset' => 1, 'title' => 'Cuci fasad gedung wing timur', 'duration' => 1, 'start' => '07:00', 'end' => '12:00', 'deadline' => 'on_track', 'status' => WorkOrderStatus::Closed],
                    ['dayOffset' => 2, 'title' => 'Servis tangga berjalan lado utara', 'duration' => 3, 'start' => '22:00', 'end' => '05:00', 'deadline' => 'on_track', 'status' => WorkOrderStatus::Assigned],
                    // Jadwal yang sudah disetujui DGM/GM dan resmi bergeser,
                    // supaya label "Dijadwalkan ulang" juga terlihat.
                    ['dayOffset' => 4, 'title' => 'Pengecatan ulang marka parkir', 'duration' => 1, 'start' => '08:00', 'end' => '14:00', 'deadline' => 'on_track', 'status' => WorkOrderStatus::Scheduled, 'scheduleStatus' => 'rescheduled'],
                ],
            ],
        ];
    }

    private function seedWorkOrder(
        Department $department,
        int $index,
        array $spec,
        Carbon $monday,
        WorkPlanningService $service,
    ): void {
        $tenant = Tenant::current();
        $workOrder = WorkOrder::query()->updateOrCreate(
            ['nomor_wo' => 'WO-SEED-'.$department->kode_department.'-'.($index + 1)],
            [
                'requester_user_id' => $this->requester($tenant)->getKey(),
                'target_department_id' => $department->getKey(),
                'category' => WorkOrderCategory::Normal,
                'title' => $spec['title'],
                'description' => 'Data dev untuk menilai tampilan Gantt Jadwal.',
                'status' => $spec['status'],
                'hod_action' => 'schedule',
                'scheduled_date' => $monday->copy()->addDays($spec['dayOffset']),
                'deadline_date' => $this->deadlineDate($spec['deadline']),
                'is_escalated' => $spec['deadline'] === 'escalated',
                'extend_count' => $spec['deadline'] === 'escalated' ? 1 : 0,
            ],
        );

        $planning = WorkPlanning::query()
            ->where('work_order_id', $workOrder->getKey())
            ->first()
            ?? $service->createForWorkOrder($workOrder);

        $planning->update([
            'jam_mulai' => $spec['start'],
            'jam_selesai' => $spec['end'],
            'jenis_pekerjaan' => $spec['title'],
            'lama_pekerjaan_hari' => $spec['duration'],
            'catatan' => 'Data dev; bukan jadwal nyata.',
            'status_jadwal' => ($spec['scheduleStatus'] ?? WorkPlanning::STATUS_PLANNED),
        ]);
    }

    /**
     * Deadline yang menghasilkan keempat status pewarnaan:
     * on_track (+10 hari), due_soon (besok), overdue (terlewat 2 hari),
     * escalated (terlewat 5 hari + is_escalated).
     */
    private function deadlineDate(string $state): Carbon
    {
        return match ($state) {
            'due_soon' => Carbon::today()->addDay(),
            'overdue' => Carbon::today()->subDays(2),
            'escalated' => Carbon::today()->subDays(5),
            default => Carbon::today()->addDays(10),
        };
    }

    /**
     * Requester dibuat lewat UserFactory, bukan insert langsung, supaya kolom
     * wajib (password, remember_token, dsb) terisi seperti di produksi.
     *
     * Izin baca diberikan eksplisit: tanpa itu data dev ini tidak akan
     * terlihat oleh siapa pun di halaman Gantt (atinya "unauthorized"), dan
     * seeder dev menjadi sia-sia.
     */
    private function requester(Tenant $tenant): User
    {
        $user = User::query()->where('email', 'requester@example.com')->first()
            ?? User::factory()->create([
                'name' => 'Requester Dev',
                'email' => 'requester@example.com',
                'tenant_id' => $tenant->getKey(),
            ]);

        if (! $user->hasPermissionTo('work-order.read')) {
            $user->givePermissionTo('work-order.read');
        }

        return $user;
    }
}
