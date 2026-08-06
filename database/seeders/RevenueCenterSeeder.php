<?php

namespace Database\Seeders;

use App\Models\RevenueCenter;
use Illuminate\Database\Seeder;

class RevenueCenterSeeder extends Seeder
{
    public function run(): void
    {
        $centers = [
            ['code' => 'RC-ADMIN', 'name' => 'Administrative Office', 'description' => 'General administrative functions'],
            ['code' => 'RC-IT', 'name' => 'Information Technology', 'description' => 'IT and computing resources'],
            ['code' => 'RC-FIN', 'name' => 'Finance Department', 'description' => 'Financial operations'],
            ['code' => 'RC-HR', 'name' => 'Human Resources', 'description' => 'HR and personnel functions'],
            ['code' => 'RC-OPS', 'name' => 'Operations', 'description' => 'Day-to-day operations'],
        ];

        foreach ($centers as $center) {
            RevenueCenter::firstOrCreate(['code' => $center['code']], $center);
        }
    }
}
