<?php

namespace Database\Factories\Hr;

use App\Models\Hr\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'employee_number' => fake()->unique()->numerify('E#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+998'.fake()->numerify('9########'),
            'email' => fake()->safeEmail(),
            'gender' => fake()->randomElement(['male', 'female']),
            'employment_type' => 'full_time',
            'hire_date' => now()->subYear()->format('Y-m-d'),
            'salary_type' => 'monthly',
            'salary_amount' => 6000000,
            'status' => 'active',
        ];
    }
}
