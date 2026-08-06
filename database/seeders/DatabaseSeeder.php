<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Database\Seeders\RoleUserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Post::factory(10)->create([
            'user_id' => $user->id,
        ]);

        $this->call(RoleUserSeeder::class);
    }
}
