<?php

namespace Database\Factories;

use App\Enums\LabTestSourcing;
use App\Models\LabTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabTest>
 */
class LabTestFactory extends Factory
{
    protected $model = LabTest::class;

    public function definition(): array
    {
        $code = 'LT-'.fake()->unique()->numerify('####');

        return [
            'name' => fake()->words(3, true).' panel',
            'test_code' => $code,
            'sourcing' => fake()->randomElement(LabTestSourcing::cases()),
            'days_required' => fake()->numberBetween(0, 7),
            'price' => fake()->numberBetween(500, 50000),
            'hospital_share' => 70,
            'lab_share' => 30,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
