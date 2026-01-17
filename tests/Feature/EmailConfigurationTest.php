<?php

namespace Tests\Feature;

use App\Models\EmailConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin role and user
        $adminRole = Role::create(['name' => 'admin']);
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);
    }

    public function test_admin_can_list_email_configurations(): void
    {
        // Create some configurations
        EmailConfiguration::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/settings/email');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'identifier',
                        'provider',
                        'is_active',
                        'settings',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_admin_can_create_smtp_email_configuration(): void
    {
        $data = [
            'name' => 'SMTP Configuration',
            'provider' => 'smtp',
            'is_active' => true,
            'settings' => [
                'from_address' => 'noreply@example.com',
                'from_name' => 'Test App',
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'user@example.com',
                'password' => 'secret',
                'encryption' => 'tls',
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/settings/email', $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'identifier',
                    'provider',
                    'is_active',
                    'settings',
                ],
            ]);

        $this->assertDatabaseHas('email_configurations', [
            'name' => 'SMTP Configuration',
            'provider' => 'smtp',
        ]);
    }

    public function test_admin_can_update_email_configuration(): void
    {
        $config = EmailConfiguration::factory()->create([
            'name' => 'Old Name',
            'provider' => 'smtp',
        ]);

        $data = [
            'name' => 'New Name',
            'is_active' => false,
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/admin/settings/email/{$config->id}", $data);

        $response->assertOk()
            ->assertJson([
                'message' => 'Configuração de e-mail atualizada com sucesso.',
            ]);

        $this->assertDatabaseHas('email_configurations', [
            'id' => $config->id,
            'name' => 'New Name',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_email_configuration(): void
    {
        $config = EmailConfiguration::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/settings/email/{$config->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Configuração de e-mail excluída com sucesso.',
            ]);

        $this->assertDatabaseMissing('email_configurations', [
            'id' => $config->id,
        ]);
    }

    public function test_non_admin_cannot_access_email_configurations(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/admin/settings/email');

        $response->assertForbidden();
    }

    public function test_settings_are_encrypted_in_database(): void
    {
        $config = EmailConfiguration::create([
            'name' => 'Test Config',
            'identifier' => 'test-config',
            'provider' => 'smtp',
            'is_active' => true,
            'settings' => [
                'from_address' => 'test@example.com',
                'from_name' => 'Test',
                'password' => 'secret123',
            ],
        ]);

        // Get raw database value
        $rawSettings = \DB::table('email_configurations')
            ->where('id', $config->id)
            ->value('settings');

        // Settings should be encrypted, not plain JSON
        $this->assertNotEquals(
            json_encode(['from_address' => 'test@example.com', 'from_name' => 'Test', 'password' => 'secret123']),
            $rawSettings
        );

        // But should decrypt correctly
        $this->assertEquals('secret123', $config->settings['password']);
    }

    public function test_sensitive_values_are_masked_in_display(): void
    {
        $config = EmailConfiguration::factory()->create([
            'settings' => [
                'from_address' => 'test@example.com',
                'from_name' => 'Test',
                'password' => 'verylongsecretpassword123',
                'api_key' => 'sk_test_1234567890abcdef',
            ],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/settings/email/{$config->id}");

        $response->assertOk();
        $data = $response->json('data');

        // Sensitive values should be masked
        $this->assertStringContainsString('****', $data['settings']['password']);
        $this->assertStringContainsString('****', $data['settings']['api_key']);

        // Non-sensitive values should not be masked
        $this->assertEquals('test@example.com', $data['settings']['from_address']);
        $this->assertEquals('Test', $data['settings']['from_name']);
    }
}
