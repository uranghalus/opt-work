<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkOrder;
use App\WorkOrderCategory;
use App\WorkOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nomor_wo' => fake()->unique()->numerify('WO-##########'),
            'requester_user_id' => User::factory(),
            'target_department_id' => Department::factory(),
            'category' => WorkOrderCategory::Normal,
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => WorkOrderStatus::WaitingHod,
        ];
    }
}
