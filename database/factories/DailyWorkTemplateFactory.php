<?php

namespace Database\Factories;

use App\Models\DailyWorkTemplate;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyWorkTemplate>
 */
class DailyWorkTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'weekdays' => [1, 2, 3, 4, 5],
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
