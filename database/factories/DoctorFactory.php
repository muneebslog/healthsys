<?php

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'specialization' => fake()->optional()->randomElement(['Gynecology', 'General surgery', 'Orthopedics']),
            'phone' => fake()->optional()->numerify('03#########'),
            'start_time' => null,
            'end_time' => null,
            'status' => 'active',
            'is_on_payroll' => false,
            'first_five_slips_full_share' => false,
            'user_id' => null,
        ];
    }
}
