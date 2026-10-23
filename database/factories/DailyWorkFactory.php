<?php

namespace Database\Factories;

use App\Models\DailyWork;
use App\Models\DailyWorkTemplate;
use App\Models\Employee;
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
}
