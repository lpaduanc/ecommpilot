<?php

namespace App\Services;

use App\Jobs\SyncBrazilLocationsJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BrazilLocationsService
{
    private string $statesPath;

    private string $citiesPath;

    private string $timestampPath;

    public function __construct()
    {
        $this->statesPath = storage_path('app/data/brazil/states.json');
        $this->citiesPath = storage_path('app/data/brazil/cities.json');
        $this->timestampPath = storage_path('app/data/brazil/last_sync.txt');
    }

    /**
     * Get all Brazilian states
     */
    public function getStates(): array
    {
        if (! File::exists($this->statesPath)) {
            Log::warning('States file not found, returning empty array');

            return [];
        }

        try {
            $content = File::get($this->statesPath);
            $states = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON in states file', ['error' => json_last_error_msg()]);

                return [];
            }

            return $states ?? [];
        } catch (\Exception $e) {
            Log::error('Error reading states file', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Get cities by state UF
     */
    public function getCitiesByState(string $uf): array
    {
        if (! File::exists($this->citiesPath)) {
            Log::warning('Cities file not found, returning empty array');

            return [];
        }

        try {
            $content = File::get($this->citiesPath);
            $allCities = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON in cities file', ['error' => json_last_error_msg()]);

                return [];
            }

            $uf = strtoupper($uf);

            return $allCities[$uf] ?? [];
        } catch (\Exception $e) {
            Log::error('Error reading cities file', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Trigger sync job
     */
    public function sync(): bool
    {
        try {
            SyncBrazilLocationsJob::dispatch();
            Log::info('Brazil locations sync job dispatched');

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to dispatch Brazil locations sync job', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Get last sync date
     */
    public function getLastSyncDate(): ?Carbon
    {
        if (! File::exists($this->timestampPath)) {
            return null;
        }

        try {
            $timestamp = File::get($this->timestampPath);

            return Carbon::parse($timestamp);
        } catch (\Exception $e) {
            Log::error('Error reading sync timestamp', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Check if sync is needed (older than 7 days)
     */
    public function needsSync(): bool
    {
        $lastSync = $this->getLastSyncDate();

        if ($lastSync === null) {
            return true;
        }

        return $lastSync->addDays(7)->isPast();
    }

    /**
     * Get sync status information
     */
    public function getSyncStatus(): array
    {
        $lastSync = $this->getLastSyncDate();
        $statesCount = count($this->getStates());
        $citiesCount = $this->getTotalCitiesCount();

        return [
            'last_sync' => $lastSync?->toISOString(),
            'needs_sync' => $this->needsSync(),
            'states_count' => $statesCount,
            'cities_count' => $citiesCount,
        ];
    }

    /**
     * Get total cities count across all states
     */
    private function getTotalCitiesCount(): int
    {
        if (! File::exists($this->citiesPath)) {
            return 0;
        }

        try {
            $content = File::get($this->citiesPath);
            $allCities = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return 0;
            }

            return array_sum(array_map('count', $allCities));
        } catch (\Exception $e) {
            return 0;
        }
    }
}
