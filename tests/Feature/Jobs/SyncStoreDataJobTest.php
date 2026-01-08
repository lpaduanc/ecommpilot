<?php

namespace Tests\Feature\Jobs;

use App\Enums\SyncStatus;
use App\Jobs\SyncStoreDataJob;
use App\Models\Store;
use App\Models\User;
use App\Services\Integration\NuvemshopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SyncStoreDataJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        SyncStoreDataJob::dispatch($store);

        Queue::assertPushed(SyncStoreDataJob::class, function ($job) use ($store) {
            return $job->store->id === $store->id;
        });
    }

    public function test_job_is_dispatched_to_sync_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        SyncStoreDataJob::dispatch($store);

        Queue::assertPushedOn('sync', SyncStoreDataJob::class);
    }

    public function test_job_has_correct_configuration(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $job = new SyncStoreDataJob($store);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(600, $job->timeout);
        $this->assertEquals(2, $job->maxExceptions);
    }

    public function test_job_unique_id_is_based_on_store(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $job = new SyncStoreDataJob($store);

        $this->assertEquals('sync_store_'.$store->id, $job->uniqueId());
    }

    public function test_job_successfully_syncs_store_data(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $mockService = Mockery::mock(NuvemshopService::class);
        $mockService->shouldReceive('syncProducts')->once()->with($store);
        $mockService->shouldReceive('syncOrders')->once()->with($store);
        $mockService->shouldReceive('syncCustomers')->once()->with($store);

        $job = new SyncStoreDataJob($store);
        $job->handle($mockService);

        $store->refresh();
        $this->assertEquals(SyncStatus::Completed, $store->sync_status);
        $this->assertNotNull($store->last_sync_at);
    }

    public function test_job_marks_store_as_syncing_during_execution(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $syncingStatusCaptured = false;

        $mockService = Mockery::mock(NuvemshopService::class);
        $mockService->shouldReceive('syncProducts')->once()->andReturnUsing(function () use ($store, &$syncingStatusCaptured) {
            $store->refresh();
            $syncingStatusCaptured = $store->sync_status === SyncStatus::Syncing;
        });
        $mockService->shouldReceive('syncOrders')->once();
        $mockService->shouldReceive('syncCustomers')->once();

        $job = new SyncStoreDataJob($store);
        $job->handle($mockService);

        $this->assertTrue($syncingStatusCaptured);
    }

    public function test_job_uses_checkpoints_for_idempotency(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        // Simulate that products were already synced
        Cache::put("sync_checkpoint:{$store->id}", ['products'], now()->addHours(2));

        $mockService = Mockery::mock(NuvemshopService::class);
        // Products should NOT be synced again
        $mockService->shouldNotReceive('syncProducts');
        $mockService->shouldReceive('syncOrders')->once();
        $mockService->shouldReceive('syncCustomers')->once();

        $job = new SyncStoreDataJob($store);
        $job->handle($mockService);

        $store->refresh();
        $this->assertEquals(SyncStatus::Completed, $store->sync_status);
    }

    public function test_job_clears_checkpoint_on_success(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $mockService = Mockery::mock(NuvemshopService::class);
        $mockService->shouldReceive('syncProducts')->once();
        $mockService->shouldReceive('syncOrders')->once();
        $mockService->shouldReceive('syncCustomers')->once();

        $job = new SyncStoreDataJob($store);
        $job->handle($mockService);

        $this->assertNull(Cache::get("sync_checkpoint:{$store->id}"));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
