<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\WorkPlanning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkPlanning>
 */
class WorkPlanningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `tgl_jadwal` memakai hari ini supaya baris masuk ke minggu berjalan
     * (Gantt default menampilkan minggu ini). Kalau sebuah test butuh WO di
     * luar minggu, ia mengubah tanggalnya sendiri secara eksplisit.
     *
     * `lama_pekerjaan_hari` null berarti "HOD belum mengisi durasi" — Gantt
     * menyubstitusinya 1 hari supaya bar tetap terlihat, dan test menutupi
     * perilaku itu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'tgl_jadwal' => now(),
            'jam_mulai' => null,
            'jam_selesai' => null,
            'jenis_pekerjaan' => null,
            'lama_pekerjaan_hari' => null,
            'budget' => null,
            'status_jadwal' => WorkPlanning::STATUS_PLANNED,
            'catatan' => null,
            'extend_count' => 0,
        ];
    }

    /** Durasi (hari kerja) sudah diisi HOD. */
    public function withDuration(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'lama_pekerjaan_hari' => $days,
        ]);
    }

    /** Menunggu approval DGM/GM setelah HOD mengajukan extend jadwal. */
    public function pendingExtend(string $proposedDate): static
    {
        return $this->state(fn (array $attributes) => [
            'status_jadwal' => WorkPlanning::STATUS_PENDING,
            'proposed_tgl_jadwal' => $proposedDate,
            'extend_reason' => 'Material tiba terlambat dari vendor.',
            'extend_requested_at' => now(),
        ]);
    }

    /** Sudah disetujui DGM/GM: jadwal resmi bergeser. */
    public function rescheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_jadwal' => WorkPlanning::STATUS_RESCHEDULED,
            'original_tgl_jadwal' => now()->subDays(3),
            'extend_count' => 1,
        ]);
    }
}
