<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $company = fake()->boolean(40);

        return [
            'type' => $company ? 'company' : 'individual',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company_name' => $company ? fake()->company() : null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+998'.fake()->numerify('9########'),
            'city' => fake()->randomElement(['Tashkent', 'Samarkand', 'Bukhara', 'Khiva', 'Namangan', 'Andijan']),
            'country' => 'Uzbekistan',
            'source' => fake()->randomElement(['website', 'referral', 'instagram', 'telegram', 'cold_call', 'exhibition']),
            'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']),
        ];
    }
}
