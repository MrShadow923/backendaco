<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(string $role): User
    {
        $email = strtolower(str_replace(' ', '-', $role)) . '-' . uniqid() . '@test.com';

        return User::forceCreate([
            'name' => $role . ' User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    public function test_departments_can_be_listed(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        Department::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/departments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'description', 'is_active', 'created_at', 'updated_at'],
                ],
            ]);
    }

    public function test_department_can_be_created(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/departments', [
            'code' => 'DEPT-TEST',
            'name' => 'Test Department',
            'description' => 'A test department',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'code', 'name', 'description', 'is_active'],
            ]);

        $this->assertDatabaseHas('departments', [
            'code' => 'DEPT-TEST',
            'name' => 'Test Department',
        ]);
    }

    public function test_department_can_be_updated(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $department = Department::factory()->create();

        $response = $this->patchJson("/api/v1/departments/{$department->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_department_can_be_viewed(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $department = Department::factory()->create();

        $response = $this->getJson("/api/v1/departments/{$department->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'code', 'name', 'description', 'is_active'],
            ]);
    }

    public function test_unauthorized_user_cannot_create_department(): void
    {
        $purchaser = $this->createUser('purchaser');
        $this->actingAs($purchaser, 'sanctum');

        $response = $this->postJson('/api/v1/departments', [
            'code' => 'DEPT-TEST2',
            'name' => 'Unauthorized Dept',
        ]);

        $response->assertForbidden();
    }

    public function test_non_officer_can_view_departments(): void
    {
        $finance = $this->createUser('finance_officer');
        $this->actingAs($finance, 'sanctum');

        $response = $this->getJson('/api/v1/departments');

        $response->assertOk();
    }

    public function test_department_stock_releases_can_be_listed(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $department = Department::factory()->create();

        $response = $this->getJson("/api/v1/departments/{$department->id}/stock-releases");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
