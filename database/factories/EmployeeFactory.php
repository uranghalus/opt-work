<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik_employee' => fake()->unique()->numerify('NIK-########'),
            'nama_employee' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'number' => fake()->optional()->e164PhoneNumber(),
            'photo_url' => null,
            'last_login_ip' => null,
        ];
    }
}
