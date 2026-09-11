<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@taskflow.test'],
            ['name' => 'Usuario de práctica', 'password' => bin2hex(random_bytes(24))]
        );

        if (! $user->tasks()->exists()) {
            Task::factory()->count(2)->for($user)->create();
        }
    }
}
