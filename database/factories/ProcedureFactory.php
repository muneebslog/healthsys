<?php

namespace Database\Factories;

use App\Enums\ProcedureStatus;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Procedure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procedure>
 */
class ProcedureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_number' => fake()->unique()->bothify('OT-####'),
            'patient_id' => Patient::factory()->head(),
            'doctor_id' => Doctor::factory(),
            'operation_name' => fake()->randomElement(['D&C', 'Appendectomy', 'C-section']),
            'package_price' => fake()->numberBetween(50_000, 500_000),
            'room_number' => fake()->optional()->bothify('OT-#'),
            'procedure_date' => fake()->optional()->date(),
            'notes' => fake()->optional()->sentence(),
            'status' => ProcedureStatus::Scheduled,
            'admission_at' => null,
            'discharge_at' => null,
        ];
    }
}
