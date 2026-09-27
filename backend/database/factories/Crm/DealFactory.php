<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Deal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Deal> */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(3, true)),
            'value' => fake()->randomElement([8, 15, 30, 60, 150]) * 1000000,
            'currency' => 'UZS',
            'status' => 'open',
            'probability' => 25,
            'expected_close_date' => now()->addDays(fake()->numberBetween(5, 90))->format('Y-m-d'),
        ];
    }
}
