<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Demo users all have the password "password". The owner and pharmacist
     * are asked to turn on two-factor authentication at first sign-in.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $branch = Branch::factory()->create([
            'code' => 'MAIN',
            'name' => 'Main Branch',
        ]);

        Terminal::factory()->for($branch)->create([
            'code' => 'POS-01',
            'name' => 'Counter 1',
        ]);

        $users = [
            ['owner', 'Store Owner', Role::Owner, true],
            ['pharmacist', 'Head Pharmacist', Role::Pharmacist, true],
            ['cashier', 'Counter Cashier', Role::Cashier, false],
            ['clerk', 'Inventory Clerk', Role::InventoryClerk, false],
        ];

        foreach ($users as [$username, $name, $role, $isSupervisor]) {
            $factory = User::factory()->for($branch);

            if ($isSupervisor) {
                $factory = $factory->withSupervisorPin('1234');
            }

            $factory->create([
                'name' => $name,
                'username' => $username,
                'email' => "{$username}@example.com",
            ])->assignRole($role);
        }

        if (app()->environment('local')) {
            $this->call(DemoCatalogSeeder::class);
        }
    }
}
