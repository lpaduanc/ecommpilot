<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailConfiguration>
 */
class EmailConfigurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = fake()->randomElement(['smtp', 'mailgun', 'ses', 'postmark', 'resend']);

        $settings = [
            'from_address' => fake()->safeEmail(),
            'from_name' => fake()->company(),
        ];

        // Add provider-specific settings
        switch ($provider) {
            case 'smtp':
                $settings['host'] = fake()->domainName();
                $settings['port'] = fake()->randomElement([25, 465, 587]);
                $settings['username'] = fake()->email();
                $settings['password'] = fake()->password();
                $settings['encryption'] = fake()->randomElement(['tls', 'ssl']);
                break;
            case 'mailgun':
                $settings['domain'] = fake()->domainName();
                $settings['api_key'] = 'key-'.fake()->sha256();
                $settings['api_url'] = 'api.mailgun.net';
                break;
            case 'ses':
                $settings['key'] = fake()->sha256();
                $settings['secret'] = fake()->sha256();
                $settings['region'] = fake()->randomElement(['us-east-1', 'us-west-2', 'eu-west-1']);
                break;
            case 'postmark':
                $settings['token'] = fake()->sha256();
                break;
            case 'resend':
                $settings['api_key'] = 're_'.fake()->sha256();
                break;
        }

        return [
            'name' => fake()->words(3, true),
            'identifier' => fake()->unique()->slug(),
            'provider' => $provider,
            'is_active' => fake()->boolean(80), // 80% chance of being active
            'settings' => $settings,
        ];
    }
}
