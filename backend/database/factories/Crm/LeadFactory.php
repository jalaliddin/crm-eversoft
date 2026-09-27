<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->boolean() ? fake()->company() : null,
            'email' => fake()->safeEmail(),
            'phone' => '+998'.fake()->numerify('9########'),
            'source' => fake()->randomElement(['website', 'referral', 'instagram', 'telegram', 'cold_call']),
            'status' => fake()->randomElement(['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'lost']),
            'expected_value' => fake()->randomElement([5, 10, 25, 50, 120]) * 1000000,
        ];
    }
}
