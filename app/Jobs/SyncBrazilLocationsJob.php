<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncBrazilLocationsJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = 60;

    public $timeout = 300; // 5 minutes

    private string $statesPath;

    private string $citiesPath;

    private const API_BASE_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades';

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->statesPath = storage_path('app/data/brazil/states.json');
        $this->citiesPath = storage_path('app/data/brazil/cities.json');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting Brazil locations sync from IBGE API');

        try {
            // Ensure directory exists
            File::ensureDirectoryExists(dirname($this->statesPath));

            // Sync states
            $states = $this->syncStates();
            Log::info('Synced states', ['count' => count($states)]);

            // Sync cities for each state
            $allCities = $this->syncCities($states);
            Log::info('Synced cities', ['total_cities' => array_sum(array_map('count', $allCities))]);

            // Save sync timestamp
            $this->saveSyncTimestamp();

            Log::info('Brazil locations sync completed successfully');
        } catch (\Exception $e) {
            Log::error('Brazil locations sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync states from IBGE API
     */
    private function syncStates(): array
    {
        $response = Http::timeout(30)
            ->get(self::API_BASE_URL.'/estados', [
                'orderBy' => 'nome',
            ]);

        if (! $response->successful()) {
            throw new \Exception('Failed to fetch states from IBGE API: '.$response->status());
        }

        $states = collect($response->json())
            ->map(fn ($state) => [
                'id' => $state['id'],
                'sigla' => $state['sigla'],
                'nome' => $state['nome'],
            ])
            ->values()
            ->toArray();

        File::put($this->statesPath, json_encode($states, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $states;
    }

    /**
     * Sync cities for each state from IBGE API
     */
    private function syncCities(array $states): array
    {
        $allCities = [];

        foreach ($states as $state) {
            $uf = $state['sigla'];

            try {
                $response = Http::timeout(30)
                    ->get(self::API_BASE_URL."/estados/{$uf}/municipios", [
                        'orderBy' => 'nome',
                    ]);

                if (! $response->successful()) {
                    Log::warning("Failed to fetch cities for state {$uf}", ['status' => $response->status()]);

                    continue;
                }

                $cities = collect($response->json())
                    ->map(fn ($city) => [
                        'id' => $city['id'],
                        'nome' => $city['nome'],
                    ])
                    ->values()
                    ->toArray();

                $allCities[$uf] = $cities;

                Log::info("Synced cities for state {$uf}", ['count' => count($cities)]);

                // Small delay to avoid rate limiting
                usleep(100000); // 0.1 seconds
            } catch (\Exception $e) {
                Log::warning("Error syncing cities for state {$uf}", ['error' => $e->getMessage()]);
            }
        }

        File::put($this->citiesPath, json_encode($allCities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $allCities;
    }

    /**
     * Save sync timestamp
     */
    private function saveSyncTimestamp(): void
    {
        $timestampPath = storage_path('app/data/brazil/last_sync.txt');
        File::put($timestampPath, now()->toISOString());
    }
}
