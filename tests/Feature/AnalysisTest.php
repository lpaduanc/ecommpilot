<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    public function test_user_can_view_current_analysis(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('analysis.view');
        $store = Store::factory()->synced()->create(['user_id' => $user->id]);
        $user->update(['active_store_id' => $store->id]);

        Analysis::factory()->completed()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/analysis/current');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'analysis' => [
                    'id',
                    'status',
                    'summary',
                ],
            ]);
    }

    public function test_user_can_view_analysis_history(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('analysis.view');
        $store = Store::factory()->synced()->create(['user_id' => $user->id]);
        $user->update(['active_store_id' => $store->id]);

        Analysis::factory()->completed()->count(3)->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/analysis/history');

        $response->assertStatus(200);
    }

    public function test_guest_cannot_access_analysis(): void
    {
        $response = $this->getJson('/api/analysis/current');

        $response->assertStatus(401);
    }

    public function test_user_can_view_specific_analysis(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('analysis.view');
        $store = Store::factory()->synced()->create(['user_id' => $user->id]);
        $user->update(['active_store_id' => $store->id]);

        $analysis = Analysis::factory()->completed()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/analysis/{$analysis->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $analysis->id);
    }

    public function test_user_cannot_view_other_users_analysis(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('analysis.view');
        $userStore = Store::factory()->synced()->create(['user_id' => $user->id]);
        $user->update(['active_store_id' => $userStore->id]);

        $otherUser = User::factory()->create();
        $otherStore = Store::factory()->synced()->create(['user_id' => $otherUser->id]);

        $analysis = Analysis::factory()->completed()->create([
            'user_id' => $otherUser->id,
            'store_id' => $otherStore->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/analysis/{$analysis->id}");

        // Now returns 404 instead of 403 because we filter by store_id
        $response->assertStatus(404);
    }
}
