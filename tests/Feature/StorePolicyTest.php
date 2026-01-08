<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Policies\StorePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected StorePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new StorePolicy;
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    public function test_user_can_view_own_store(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->view($user, $store));
    }

    public function test_user_cannot_view_other_users_store(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->view($user, $store));
    }

    public function test_admin_can_view_any_store(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->view($admin, $store));
    }

    public function test_user_can_update_own_store(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $store));
    }

    public function test_user_cannot_update_other_users_store(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->update($user, $store));
    }

    public function test_user_can_delete_own_store(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $store));
    }

    public function test_user_with_credits_can_request_analysis(): void
    {
        $user = User::factory()->withCredits(10)->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->requestAnalysis($user, $store));
    }

    public function test_user_without_credits_cannot_request_analysis(): void
    {
        $user = User::factory()->withoutCredits()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($this->policy->requestAnalysis($user, $store));
    }

    public function test_only_admin_can_restore_store(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($this->policy->restore($user, $store));
        $this->assertTrue($this->policy->restore($admin, $store));
    }

    public function test_only_admin_can_force_delete_store(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($this->policy->forceDelete($user, $store));
        $this->assertTrue($this->policy->forceDelete($admin, $store));
    }
}
