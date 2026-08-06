<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\RevenueCenter;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $rc = RevenueCenter::where('code', 'RC-ADMIN')->first();

        $departments = [
            ['code' => 'DEPT-PUR', 'name' => 'Procurement', 'revenue_center_id' => $rc?->id, 'description' => 'Purchasing and procurement'],
            ['code' => 'DEPT-FIN', 'name' => 'Finance', 'revenue_center_id' => $rc?->id, 'description' => 'Financial management'],
            ['code' => 'DEPT-HR', 'name' => 'HR', 'revenue_center_id' => $rc?->id, 'description' => 'Human resources'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], $dept);
        }
    }
}
