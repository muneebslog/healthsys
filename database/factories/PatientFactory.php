<?php

namespace Database\Factories;

use App\Enums\PatientType;
use App\Models\Family;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'type' => PatientType::Member,
            'relation_to_head' => fake()->randomElement(['Son', 'Daughter', 'Spouse']),
            'family_id' => Family::factory(),
        ];
    }

    public function head(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PatientType::Head,
            'relation_to_head' => null,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Patient $patient): void {
            if ($patient->type === PatientType::Head) {
                $patient->family->update(['head_id' => $patient->id]);
            }
        });
    }
}
