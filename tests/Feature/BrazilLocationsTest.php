<?php

namespace Tests\Feature;

use App\Jobs\SyncBrazilLocationsJob;
use App\Models\User;
use App\Services\BrazilLocationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BrazilLocationsTest extends TestCase
{
    use RefreshDatabase;

    private string $statesPath;

    private string $citiesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statesPath = storage_path('app/data/brazil/states.json');
        $this->citiesPath = storage_path('app/data/brazil/cities.json');

        // Ensure directory exists for tests
        File::ensureDirectoryExists(dirname($this->statesPath));
    }

    public function test_can_get_states_from_api(): void
    {
        $response = $this->getJson('/api/locations/states');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'sigla', 'nome'],
                ],
            ]);
    }

    public function test_can_get_cities_by_state(): void
    {
        $response = $this->getJson('/api/locations/cities/SP');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'nome'],
                ],
            ]);
    }

    public function test_invalid_state_returns_validation_error(): void
    {
        $response = $this->getJson('/api/locations/cities/INVALID');

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'UF inválida. Use a sigla do estado (ex: SP, RJ).']);
    }

    public function test_admin_can_trigger_sync(): void
    {
        $admin = User::factory()->create([
            'role' => 'Admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/locations/sync');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Sincronização de localidades iniciada com sucesso.']);
    }

    public function test_admin_can_get_sync_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'Admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/locations/sync-status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'last_sync',
                'needs_sync',
                'states_count',
                'cities_count',
            ]);
    }

    public function test_service_returns_states(): void
    {
        $service = new BrazilLocationsService;

        $states = $service->getStates();

        $this->assertIsArray($states);
    }

    public function test_service_returns_cities_by_state(): void
    {
        $service = new BrazilLocationsService;

        $cities = $service->getCitiesByState('SP');

        $this->assertIsArray($cities);
    }

    public function test_sync_job_creates_files(): void
    {
        // Clean up first
        if (File::exists($this->statesPath)) {
            File::delete($this->statesPath);
        }
        if (File::exists($this->citiesPath)) {
            File::delete($this->citiesPath);
        }

        // Run job synchronously
        (new SyncBrazilLocationsJob)->handle();

        // Check files were created
        $this->assertTrue(File::exists($this->statesPath));
        $this->assertTrue(File::exists($this->citiesPath));

        // Verify JSON structure
        $states = json_decode(File::get($this->statesPath), true);
        $this->assertIsArray($states);
        $this->assertNotEmpty($states);
        $this->assertArrayHasKey('id', $states[0]);
        $this->assertArrayHasKey('sigla', $states[0]);
        $this->assertArrayHasKey('nome', $states[0]);

        $cities = json_decode(File::get($this->citiesPath), true);
        $this->assertIsArray($cities);
        $this->assertNotEmpty($cities);
    }
}
