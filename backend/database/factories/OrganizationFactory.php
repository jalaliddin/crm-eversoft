<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Support\OrganizationSettings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'timezone' => 'Asia/Tashkent',
            'currency' => 'UZS',
            'locale' => 'en',
            'status' => 'active',
            'settings' => OrganizationSettings::defaults(),
        ];
    }
}
