<?php

namespace Database\Factories;

use App\DailyWorkStatus;
use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyWork>
 */
class DailyWorkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'work_date' => $this->todayString(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'location' => fake()->optional()->city(),
            'status' => 'pending',
            'done_at' => null,
            'source' => 'adhoc',
            'template_id' => null,
            'created_by' => null,
        ];
    }

    /**
     * Instance materialize dari template — mewarisi title/description
     * template dan menunjuk lineage-nya.
     */
    public function forTemplate(DailyWorkTemplate $template, string $workDate): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $template->employee_id,
            'work_date' => $workDate,
            'title' => $template->title,
            'description' => $template->description,
            'source' => 'template',
            'template_id' => $template->getKey(),
        ]);
    }

    /**
     * Tanggal hari ini (timezone app — WITA) sebagai string Y-m-d, aman
     * dipakai dalam kolom date.
     */
    private function todayString(): string
    {
        return now()->toDateString();
    }

    /**
     * Bukti sudah diserahkan karyawan — menunggu verifikasi HOD/TL
     * (plan .scratch/daily-work-verification/plan.md Task 2).
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DailyWorkStatus::PendingVerify->value,
            'proof_notes' => fake()->paragraph(),
            'proof_attachments' => [
                'daily-work/fake-1.jpg',
                'daily-work/fake-2.jpg',
            ],
            'submitted_at' => now(),
            'done_at' => now(),
        ]);
    }

    /** Terverifikasi HOD/TL — "Tandai telah selesai". */
    public function verified(): static
    {
        return $this->submitted()->state(fn (array $attributes) => [
            'status' => DailyWorkStatus::Done->value,
            'verified_by' => User::factory(),
            'verified_at' => now(),
        ]);
    }

    /** Dikembalikan HOD/TL (rejection_note wajib) — karyawan mengisi ulang. */
    public function returnedByHod(): static
    {
        return $this->submitted()->state(fn (array $attributes) => [
            'status' => DailyWorkStatus::InProgress->value,
            'rejected_by' => User::factory(),
            'rejected_at' => now(),
            'rejection_note' => fake()->sentence(),
        ]);
    }
}
