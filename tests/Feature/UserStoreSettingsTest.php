<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that camelCase fields from frontend are properly converted and saved.
     */
    public function test_user_can_update_store_settings_with_camel_case(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/settings/store', [
                'clientId' => '24713',
                'clientSecret' => 'c85726f31bf4f4b304488d6f802fe8f8a4a1df307f6ef258',
                'grantType' => 'authorization_code',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Configurações da loja atualizadas com sucesso.',
            ]);

        // Verify data was saved to database
        $this->assertDatabaseHas('system_settings', [
            'key' => 'nuvemshop.client_id',
            'group' => 'nuvemshop',
            'is_sensitive' => true,
        ]);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'nuvemshop.client_secret',
            'group' => 'nuvemshop',
            'is_sensitive' => true,
        ]);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'nuvemshop.grant_type',
            'value' => 'authorization_code',
            'group' => 'nuvemshop',
            'is_sensitive' => false,
        ]);

        // Verify the values can be retrieved correctly
        $this->assertEquals('24713', SystemSetting::get('nuvemshop.client_id'));
        $this->assertEquals('c85726f31bf4f4b304488d6f802fe8f8a4a1df307f6ef258', SystemSetting::get('nuvemshop.client_secret'));
        $this->assertEquals('authorization_code', SystemSetting::get('nuvemshop.grant_type'));
    }

    /**
     * Test that snake_case fields still work (backward compatibility).
     */
    public function test_user_can_update_store_settings_with_snake_case(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/settings/store', [
                'client_id' => '24713',
                'client_secret' => 'test_secret_key',
                'grant_type' => 'refresh_token',
            ]);

        $response->assertOk();

        $this->assertEquals('24713', SystemSetting::get('nuvemshop.client_id'));
        $this->assertEquals('test_secret_key', SystemSetting::get('nuvemshop.client_secret'));
        $this->assertEquals('refresh_token', SystemSetting::get('nuvemshop.grant_type'));
    }

    /**
     * Test that settings are properly retrieved with masked sensitive values.
     */
    public function test_user_can_get_store_settings_with_masked_values(): void
    {
        $user = User::factory()->create();

        // Set some settings
        SystemSetting::set('nuvemshop.client_id', '24713', [
            'type' => 'string',
            'group' => 'nuvemshop',
            'label' => 'Client ID',
            'is_sensitive' => true,
        ]);

        SystemSetting::set('nuvemshop.client_secret', 'c85726f31bf4f4b304488d6f802fe8f8a4a1df307f6ef258', [
            'type' => 'string',
            'group' => 'nuvemshop',
            'label' => 'Client Secret',
            'is_sensitive' => true,
        ]);

        SystemSetting::set('nuvemshop.grant_type', 'authorization_code', [
            'type' => 'string',
            'group' => 'nuvemshop',
            'label' => 'Grant Type',
            'is_sensitive' => false,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/settings/store');

        $response->assertOk()
            ->assertJsonStructure([
                'client_id',
                'client_secret',
                'grant_type',
                'has_client_id',
                'has_client_secret',
            ])
            ->assertJson([
                'grant_type' => 'authorization_code',
                'has_client_id' => true,
                'has_client_secret' => true,
            ]);

        // Verify sensitive values are masked (not returning full values)
        $data = $response->json();
        $this->assertStringContainsString('****', $data['client_id']);
        $this->assertStringContainsString('****', $data['client_secret']);
    }

    /**
     * Test validation fails for invalid grant_type.
     */
    public function test_validation_fails_for_invalid_grant_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/settings/store', [
                'clientId' => '24713',
                'clientSecret' => 'test_secret',
                'grantType' => 'invalid_type',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['grant_type']);
    }

    /**
     * Test that unauthenticated users cannot access settings.
     */
    public function test_unauthenticated_user_cannot_access_settings(): void
    {
        $response = $this->getJson('/api/settings/store');
        $response->assertUnauthorized();

        $response = $this->putJson('/api/settings/store', [
            'clientId' => '24713',
        ]);
        $response->assertUnauthorized();
    }
}
