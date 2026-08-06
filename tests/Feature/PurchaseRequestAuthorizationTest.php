<?php

namespace Tests\Feature;

use App\Enums\PurchaseRequestStatus;
use App\Enums\UserRole;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Policies\PurchaseRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ───────────────────────────────────────────────

    private function createUserWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    // ─── Policy: create ────────────────────────────────────────

    public function test_policy_denies_purchaser_from_creating_pr(): void
    {
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $policy = new PurchaseRequestPolicy();

        $this->assertFalse($policy->create($purchaser));
    }

    public function test_policy_denies_finance_from_creating_pr(): void
    {
        $finance = $this->createUserWithRole(UserRole::FinanceOfficer);
        $policy = new PurchaseRequestPolicy();

        $this->assertFalse($policy->create($finance));
    }

    public function test_policy_denies_gm_from_creating_pr(): void
    {
        $gm = $this->createUserWithRole(UserRole::GeneralManager);
        $policy = new PurchaseRequestPolicy();

        $this->assertFalse($policy->create($gm));
    }

    // ─── Policy: update ────────────────────────────────────────

    public function test_policy_denies_aco_from_editing_submitted_pr(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $aco->id,
            'status' => PurchaseRequestStatus::Submitted,
        ]);

        $policy = new PurchaseRequestPolicy();

        $this->assertFalse($policy->update($aco, $pr));
    }

    public function test_policy_allows_aco_to_edit_draft_pr(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $aco->id,
            'status' => PurchaseRequestStatus::Draft,
        ]);

        $policy = new PurchaseRequestPolicy();

        $this->assertTrue($policy->update($aco, $pr));
    }
}
