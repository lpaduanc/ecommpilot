<?php

namespace App\Services\ExternalData;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleTrendsService
{
    private string $logChannel = 'analysis';

    /**
     * Check if the service is enabled.
     */
    private function isEnabled(): bool
    {
        $mainEnabled = SystemSetting::where('key', 'external_data.enabled')->first();
        $trendsEnabled = SystemSetting::where('key', 'external_data.trends.enabled')->first();

        return ($mainEnabled ? (bool) $mainEnabled->getCastedValue() : false)
            && ($trendsEnabled ? (bool) $trendsEnabled->getCastedValue() : false);
    }

    /**
     * Get the API key.
     */
    private function getApiKey(): ?string
    {
        return SystemSetting::get('external_data.serpapi_key');
    }

    /**
     * Check if the service is configured and enabled.
     */
    public function isConfigured(): bool
    {
        return $this->isEnabled() && ! empty($this->getApiKey());
    }

    /**
     * Get trends data for keywords.
     *
     * @param  array  $keywords  Keywords to search (max 5)
     * @param  string  $niche  Store niche for context
     * @return array Trends data
     */
    public function getTrends(array $keywords, string $niche): array
    {
        if (! $this->isConfigured()) {
            return $this->emptyResponse('Serviço não configurado');
        }

        // Limit keywords to 5
        $keywords = array_slice($keywords, 0, 5);

        if (empty($keywords)) {
            return $this->emptyResponse('Nenhuma palavra-chave fornecida');
        }

        try {
            $result = $this->fetchTrends($keywords);

            Log::channel($this->logChannel)->info('GoogleTrendsService: Fetched trends', [
                'keywords' => $keywords,
                'tendencia' => $result['tendencia'],
                'interesse_busca' => $result['interesse_busca'],
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::channel($this->logChannel)->error('GoogleTrendsService: Error fetching trends', [
                'keywords' => $keywords,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyResponse('Erro ao buscar dados: '.$e->getMessage());
        }
    }

    /**
     * Fetch trends from SerpAPI.
     */
    private function fetchTrends(array $keywords): array
    {
        $query = implode(',', $keywords);
        $apiKey = $this->getApiKey();

        // Log masked API key for debugging
        $maskedKey = $apiKey ? substr($apiKey, 0, 8).'...'.substr($apiKey, -4) : 'NOT_SET';
        Log::channel($this->logChannel)->debug('GoogleTrendsService: API Request', [
            'api_key_masked' => $maskedKey,
            'api_key_length' => strlen($apiKey ?? ''),
            'query' => $query,
        ]);

        $response = Http::timeout(30)->get('https://serpapi.com/search', [
            'api_key' => $apiKey,
            'engine' => 'google_trends',
            'q' => $query,
            'data_type' => 'TIMESERIES',
            'geo' => 'BR',
            'hl' => 'pt-BR',
        ]);

        if (! $response->successful()) {
            // Log full error response for debugging
            Log::channel($this->logChannel)->error('GoogleTrendsService: API Error Response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);
            throw new \Exception('API retornou status '.$response->status().': '.$response->body());
        }

        $data = $response->json();

        // Parse interest over time
        $interestData = $data['interest_over_time']['timeline_data'] ?? [];

        if (empty($interestData)) {
            return $this->emptyResponse('Sem dados de interesse disponíveis');
        }

        // Calculate average interest and trend
        $values = [];
        $monthlyData = [];

        foreach ($interestData as $point) {
            $value = $point['values'][0]['extracted_value'] ?? 0;
            $values[] = $value;

            // Extract month for seasonality
            $date = $point['date'] ?? '';
            if (preg_match('/(\w+)\s+\d+/', $date, $matches)) {
                $month = $this->translateMonth($matches[1]);
                if (! isset($monthlyData[$month])) {
                    $monthlyData[$month] = [];
                }
                $monthlyData[$month][] = $value;
            }
        }

        $averageInterest = count($values) > 0 ? array_sum($values) / count($values) : 0;
        $tendencia = $this->calculateTrend($values);
        $sazonalidade = $this->detectSeasonality($monthlyData);

        // Get related queries
        $relatedQueries = [];
        if (isset($data['related_queries']['rising'])) {
            foreach (array_slice($data['related_queries']['rising'], 0, 5) as $query) {
                $relatedQueries[] = $query['query'] ?? '';
            }
        }

        return [
            'interesse_busca' => (int) round($averageInterest),
            'tendencia' => $tendencia,
            'sazonalidade' => $sazonalidade,
            'termos_relacionados' => array_filter($relatedQueries),
            'sucesso' => true,
            'motivo_falha' => null,
            'data_coleta' => now()->toISOString(),
        ];
    }

    /**
     * Calculate trend based on values (alta, estavel, queda).
     */
    private function calculateTrend(array $values): string
    {
        if (count($values) < 8) {
            return 'estavel';
        }

        // Compare first 4 values with last 4 values
        $firstValues = array_slice($values, 0, 4);
        $lastValues = array_slice($values, -4);

        $firstAvg = array_sum($firstValues) / count($firstValues);
        $lastAvg = array_sum($lastValues) / count($lastValues);

        if ($firstAvg == 0) {
            return 'estavel';
        }

        $change = (($lastAvg - $firstAvg) / $firstAvg) * 100;

        if ($change > 15) {
            return 'alta';
        } elseif ($change < -15) {
            return 'queda';
        }

        return 'estavel';
    }

    /**
     * Detect seasonality peaks.
     */
    private function detectSeasonality(array $monthlyData): array
    {
        if (empty($monthlyData)) {
            return [];
        }

        // Calculate average for each month
        $monthAverages = [];
        foreach ($monthlyData as $month => $values) {
            $monthAverages[$month] = array_sum($values) / count($values);
        }

        // Find overall average
        $overallAvg = array_sum($monthAverages) / count($monthAverages);

        // Find months with above-average interest (> 20% above average)
        $peakMonths = [];
        foreach ($monthAverages as $month => $avg) {
            if ($avg > $overallAvg * 1.2) {
                $peakMonths[] = $month;
            }
        }

        return $peakMonths;
    }

    /**
     * Translate English month names to Portuguese.
     */
    private function translateMonth(string $month): string
    {
        $months = [
            'Jan' => 'janeiro',
            'Feb' => 'fevereiro',
            'Mar' => 'março',
            'Apr' => 'abril',
            'May' => 'maio',
            'Jun' => 'junho',
            'Jul' => 'julho',
            'Aug' => 'agosto',
            'Sep' => 'setembro',
            'Oct' => 'outubro',
            'Nov' => 'novembro',
            'Dec' => 'dezembro',
        ];

        return $months[$month] ?? strtolower($month);
    }

    /**
     * Return empty response with error message.
     */
    private function emptyResponse(?string $reason = null): array
    {
        return [
            'interesse_busca' => 0,
            'tendencia' => 'nao_disponivel',
            'sazonalidade' => [],
            'termos_relacionados' => [],
            'sucesso' => false,
            'motivo_falha' => $reason,
            'data_coleta' => now()->toISOString(),
        ];
    }
}
