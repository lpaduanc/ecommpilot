<?php

namespace App\Services\ExternalData;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DecodoProxyService
{
    private string $logChannel = 'analysis';

    /**
     * Decodo Web Scraping API endpoint.
     */
    private const API_URL = 'https://scraper-api.decodo.com/v2/scrape';

    /**
     * Check if Decodo API is enabled and configured.
     */
    public function isEnabled(): bool
    {
        $enabled = SystemSetting::get('external_data.decodo.enabled', false);
        $username = $this->getUsername();
        $password = $this->getPassword();

        return $enabled && ! empty($username) && ! empty($password);
    }

    /**
     * Get Decodo username from settings.
     */
    private function getUsername(): ?string
    {
        return SystemSetting::get('external_data.decodo.username');
    }

    /**
     * Get Decodo password from settings.
     */
    private function getPassword(): ?string
    {
        return SystemSetting::get('external_data.decodo.password');
    }

    /**
     * Get headless mode setting.
     */
    private function getHeadlessMode(): string
    {
        return SystemSetting::get('external_data.decodo.headless', 'html');
    }

    /**
     * Get JS rendering setting.
     */
    private function getJsRendering(): bool
    {
        return (bool) SystemSetting::get('external_data.decodo.js_rendering', false);
    }

    /**
     * Get output format setting.
     * Options: 'raw' (HTML), 'markdown' (better for AI), 'cleaned_html'
     */
    private function getOutputFormat(): string
    {
        return SystemSetting::get('external_data.decodo.output_format', 'markdown');
    }

    /**
     * Get timeout for API requests.
     */
    private function getTimeout(): int
    {
        return (int) SystemSetting::get('external_data.decodo.timeout', 30);
    }

    /**
     * Build Basic Auth header value.
     */
    private function getBasicAuthHeader(): string
    {
        $credentials = $this->getUsername().':'.$this->getPassword();

        return 'Basic '.base64_encode($credentials);
    }

    /**
     * Make a scraping request through Decodo API.
     *
     * @param  string  $url  Target URL to scrape
     * @param  array  $options  Additional options (headless, js_rendering, etc.)
     * @return array{success: bool, body: string|null, status: int|null, error: string|null}
     */
    public function get(string $url, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'body' => null,
                'status' => null,
                'error' => 'Decodo API not enabled or configured',
            ];
        }

        $startTime = microtime(true);

        try {
            // Build request payload
            $payload = [
                'url' => $url,
                'headless' => $options['headless'] ?? $this->getHeadlessMode(),
                'geo' => $options['geo'] ?? 'Brazil',
                'locale' => $options['locale'] ?? 'pt-br',
            ];

            // Add markdown output format (better for AI analysis)
            $outputFormat = $options['output'] ?? $this->getOutputFormat();
            if ($outputFormat === 'markdown') {
                $payload['markdown'] = true;
            }

            // Add JS rendering if enabled (headless must be 'html' or 'png')
            if ($options['js_rendering'] ?? $this->getJsRendering()) {
                $payload['headless'] = 'html';
            }

            Log::channel($this->logChannel)->debug('DecodoProxyService: Starting scrape request', [
                'target_url' => $url,
                'headless' => $payload['headless'],
                'markdown' => $payload['markdown'] ?? false,
                'geo' => $payload['geo'],
            ]);

            $response = Http::timeout($this->getTimeout())
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => $this->getBasicAuthHeader(),
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, $payload);

            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            // Parse response
            $responseData = $response->json();

            // Check for API-level errors
            if (isset($responseData['error'])) {
                Log::channel($this->logChannel)->warning('DecodoProxyService: API returned error', [
                    'url' => $url,
                    'error' => $responseData['error'],
                    'time_ms' => $elapsedMs,
                ]);

                return [
                    'success' => false,
                    'body' => null,
                    'status' => $response->status(),
                    'error' => $responseData['error'],
                ];
            }

            // Extract HTML content from response
            // Decodo API v2 returns content inside results array: {"results":[{"content":"..."}]}
            $htmlContent = null;
            if (isset($responseData['results'][0]['content'])) {
                $htmlContent = $responseData['results'][0]['content'];
            } elseif (isset($responseData['content'])) {
                $htmlContent = $responseData['content'];
            } elseif (isset($responseData['body'])) {
                $htmlContent = $responseData['body'];
            } elseif (isset($responseData['html'])) {
                $htmlContent = $responseData['html'];
            }

            if ($response->successful() && $htmlContent) {
                Log::channel($this->logChannel)->info('DecodoProxyService: Request completed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'time_ms' => $elapsedMs,
                    'body_size' => strlen($htmlContent),
                ]);

                return [
                    'success' => true,
                    'body' => $htmlContent,
                    'status' => $response->status(),
                    'error' => null,
                ];
            }

            // Handle non-successful responses
            Log::channel($this->logChannel)->warning('DecodoProxyService: Request failed', [
                'url' => $url,
                'status' => $response->status(),
                'time_ms' => $elapsedMs,
                'response' => substr($response->body(), 0, 500),
            ]);

            return [
                'success' => false,
                'body' => null,
                'status' => $response->status(),
                'error' => 'HTTP '.$response->status().': '.($responseData['message'] ?? 'Unknown error'),
            ];

        } catch (\Exception $e) {
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel($this->logChannel)->warning('DecodoProxyService: Request exception', [
                'url' => $url,
                'error' => $e->getMessage(),
                'time_ms' => $elapsedMs,
            ]);

            return [
                'success' => false,
                'body' => null,
                'status' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test Decodo API connection.
     *
     * @return array{success: bool, ip: string|null, country: string|null, error: string|null}
     */
    public function testConnection(): array
    {
        // Use ip.decodo.com as test URL - it returns JSON with IP info
        $result = $this->get('https://ip.decodo.com/json');

        if (! $result['success']) {
            return [
                'success' => false,
                'ip' => null,
                'country' => null,
                'error' => $result['error'],
            ];
        }

        try {
            // The response body might be the JSON content or HTML containing JSON
            $body = $result['body'];

            // Try to extract JSON from the response
            $data = json_decode($body, true);

            // If not valid JSON, try to find JSON in HTML
            if (! is_array($data) && preg_match('/\{[^}]+\}/', $body, $matches)) {
                $data = json_decode($matches[0], true);
            }

            return [
                'success' => true,
                'ip' => $data['ip'] ?? 'Unknown',
                'country' => $data['country'] ?? $data['geo'] ?? 'Unknown',
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => true, // Scraping worked, just couldn't parse IP
                'ip' => 'Unknown',
                'country' => 'Unknown',
                'error' => null,
            ];
        }
    }

    /**
     * Get current configuration status for debugging.
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'has_credentials' => ! empty($this->getUsername()) && ! empty($this->getPassword()),
            'api_url' => self::API_URL,
            'headless' => $this->getHeadlessMode(),
            'js_rendering' => $this->getJsRendering(),
            'output_format' => $this->getOutputFormat(),
            'timeout' => $this->getTimeout(),
        ];
    }
}
