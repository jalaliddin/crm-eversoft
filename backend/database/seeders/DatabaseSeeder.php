<?php

namespace Database\Seeders;

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Development / demo data. Two tenants with clearly different data so isolation is easy to verify:
     * everything in "Alpha Trade" is prefixed "Alpha", everything in "Beta Services" is prefixed "Beta".
     */
    public function run(): void
    {
        $admin = User::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@eversoft.uz',
            'password' => 'password',
            'status' => 'active',
        ]);
        $admin->forceFill(['platform_role' => PlatformRole::PlatformSuperAdmin])->save();

        $support = User::query()->create([
            'name' => 'Platform Support',
            'email' => 'support@eversoft.uz',
            'password' => 'password',
            'status' => 'active',
        ]);
        $support->forceFill(['platform_role' => PlatformRole::PlatformSupport])->save();

        $this->call(DemoTenantSeeder::class, false, ['name' => 'Alpha Trade LLC', 'slug' => 'alpha', 'prefix' => 'Alpha', 'emailDomain' => 'alpha.test', 'size' => 24]);
        $this->call(DemoTenantSeeder::class, false, ['name' => 'Beta Services', 'slug' => 'beta', 'prefix' => 'Beta', 'emailDomain' => 'beta.test', 'size' => 12]);

        $this->command?->info('Seeded. All demo passwords: "password".');
        $this->command?->table(['Role', 'Email'], [
            ['Platform super admin', 'admin@eversoft.uz'],
            ['Alpha owner', 'owner@alpha.test'],
            ['Alpha HR manager', 'hr@alpha.test'],
            ['Alpha sales manager', 'sales@alpha.test'],
            ['Alpha sales agent', 'agent@alpha.test'],
            ['Alpha employee', 'employee@alpha.test'],
            ['Beta owner', 'owner@beta.test'],
        ]);
    }
}
