<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleUserSeeder extends Seeder
{
    /**
     * Create one user per role with known credentials for testing.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Asset Control Officer',
                'email' => 'asset.control@aco.com',
                'role' => UserRole::AssetControlOfficer,
            ],
            [
                'name' => 'Purchaser',
                'email' => 'purchaser@aco.com',
                'role' => UserRole::Purchaser,
            ],
            [
                'name' => 'Finance Officer',
                'email' => 'finance@aco.com',
                'role' => UserRole::FinanceOfficer,
            ],
            [
                'name' => 'General Manager',
                'email' => 'gm@aco.com',
                'role' => UserRole::GeneralManager,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => 'password',
                    'role' => $userData['role'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
