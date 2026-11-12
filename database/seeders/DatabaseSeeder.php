<?php

namespace Database\Seeders;

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
     */
    public function run(): void
    {
        $branch = Branch::factory()->create([
            'code' => 'MAIN',
            'name' => 'Main Branch',
        ]);

        Terminal::factory()->for($branch)->create([
            'code' => 'POS-01',
            'name' => 'Counter 1',
        ]);

        User::factory()->for($branch)->withSupervisorPin()->create([
            'name' => 'Test User',
            'username' => 'test',
            'email' => 'test@example.com',
        ]);
    }
}
