<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function user_can_update_store_settings(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/settings/store', [
                'clientId' => 'test_client_id_123',
                'clientSecret' => 'test_client_secret_456',
                'grantType' => 'authorization_code',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'client_id',
                'client_secret',
                'grant_type',
                'has_client_id',
                'has_client_secret',
            ]);

        // Verify settings were saved
        $this->assertEquals('test_client_id_123', SystemSetting::get('nuvemshop.client_id'));
        $this->assertEquals('test_client_secret_456', SystemSetting::get('nuvemshop.client_secret'));
        $this->assertEquals('authorization_code', SystemSetting::get('nuvemshop.grant_type'));
    }

    /** @test */
    public function sensitive_values_are_encrypted(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/settings/store', [
                'clientId' => 'sensitive_client_id',
                'clientSecret' => 'sensitive_client_secret',
            ]);

        // Check that values are encrypted in database
        $clientId = SystemSetting::where('key', 'nuvemshop.client_id')->first();
        $clientSecret = SystemSetting::where('key', 'nuvemshop.client_secret')->first();

        $this->assertTrue($clientId->is_sensitive);
        $this->assertTrue($clientSecret->is_sensitive);

        // The raw value should be encrypted
        $this->assertNotEquals('sensitive_client_id', $clientId->value);
        $this->assertNotEquals('sensitive_client_secret', $clientSecret->value);

        // But decryption should work
        $this->assertEquals('sensitive_client_id', Crypt::decryptString($clientId->value));
        $this->assertEquals('sensitive_client_secret', Crypt::decryptString($clientSecret->value));
    }

    /** @test */
    public function sensitive_values_are_masked_in_response(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/settings/store', [
                'clientId' => 'very_long_client_id_12345678',
                'clientSecret' => 'very_long_secret_87654321',
            ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/settings/store');

        $response->assertStatus(200);
        $data = $response->json();

        // Sensitive values should be partially masked
        $this->assertStringContainsString('****', $data['client_id']);
        $this->assertStringContainsString('****', $data['client_secret']);
        $this->assertEquals('very', substr($data['client_id'], 0, 4));
        $this->assertTrue($data['has_client_id']);
        $this->assertTrue($data['has_client_secret']);
    }

    /** @test */
    public function grant_type_is_not_sensitive(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/settings/store', [
                'grantType' => 'authorization_code',
            ]);

        $grantType = SystemSetting::where('key', 'nuvemshop.grant_type')->first();

        $this->assertFalse($grantType->is_sensitive);
        $this->assertEquals('authorization_code', $grantType->value);
    }

    /** @test */
    public function validation_fails_for_invalid_grant_type(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/settings/store', [
                'grantType' => 'invalid_grant_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['grant_type']);
    }

    /** @test */
    public function validation_passes_for_valid_grant_types(): void
    {
        $validGrantTypes = ['authorization_code', 'refresh_token'];

        foreach ($validGrantTypes as $grantType) {
            $response = $this->actingAs($this->user)
                ->putJson('/api/settings/store', [
                    'grantType' => $grantType,
                ]);

            $response->assertStatus(200);
            $this->assertEquals($grantType, SystemSetting::get('nuvemshop.grant_type'));
        }
    }

    /** @test */
    public function user_can_update_partial_settings(): void
    {
        // Set initial values
        SystemSetting::set('nuvemshop.client_id', 'initial_client_id', [
            'type' => 'string',
            'group' => 'nuvemshop',
            'is_sensitive' => true,
        ]);

        SystemSetting::set('nuvemshop.client_secret', 'initial_secret', [
            'type' => 'string',
            'group' => 'nuvemshop',
            'is_sensitive' => true,
        ]);

        // Update only client_id
        $response = $this->actingAs($this->user)
            ->putJson('/api/settings/store', [
                'clientId' => 'updated_client_id',
            ]);

        $response->assertStatus(200);

        // client_id should be updated
        $this->assertEquals('updated_client_id', SystemSetting::get('nuvemshop.client_id'));

        // client_secret should remain unchanged
        $this->assertEquals('initial_secret', SystemSetting::get('nuvemshop.client_secret'));
    }

    /** @test */
    public function unauthenticated_user_cannot_update_settings(): void
    {
        $response = $this->putJson('/api/settings/store', [
            'clientId' => 'test_client_id',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function settings_are_grouped_correctly(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/settings/store', [
                'clientId' => 'test_client_id',
                'clientSecret' => 'test_client_secret',
                'grantType' => 'authorization_code',
            ]);

        $clientId = SystemSetting::where('key', 'nuvemshop.client_id')->first();
        $clientSecret = SystemSetting::where('key', 'nuvemshop.client_secret')->first();
        $grantType = SystemSetting::where('key', 'nuvemshop.grant_type')->first();

        $this->assertEquals('nuvemshop', $clientId->group);
        $this->assertEquals('nuvemshop', $clientSecret->group);
        $this->assertEquals('nuvemshop', $grantType->group);
    }

    /** @test */
    public function cache_is_cleared_after_update(): void
    {
        // Set initial value
        SystemSetting::set('nuvemshop.client_id', 'initial_value', [
            'type' => 'string',
            'group' => 'nuvemshop',
            'is_sensitive' => true,
        ]);

        // Cache the value
        $cachedValue = SystemSetting::get('nuvemshop.client_id');
        $this->assertEquals('initial_value', $cachedValue);

        // Update via API
        $this->actingAs($this->user)
            ->putJson('/api/settings/store', [
                'clientId' => 'updated_value',
            ]);

        // Value should be updated (cache should be cleared)
        $newValue = SystemSetting::get('nuvemshop.client_id');
        $this->assertEquals('updated_value', $newValue);
    }
}
