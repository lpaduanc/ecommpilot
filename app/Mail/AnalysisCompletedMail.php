<?php

namespace App\Mail;

use App\Models\Analysis;
use App\Services\EmailConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnalysisCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Analysis $analysis;

    public array $suggestions;

    public array $summary;

    public string $storeName;

    public string $userName;

    public string $periodStart;

    public string $periodEnd;

    public int $healthScore;

    public string $healthStatus;

    public string $mainInsight;

    public string $analysisType;

    public string $analysisTypeLabel;

    public array $premiumSummary;

    public array $alerts;

    /**
     * Create a new message instance.
     */
    public function __construct(Analysis $analysis)
    {
        $this->analysis = $analysis;
        $this->storeName = $analysis->store->name ?? 'Sua Loja';
        $this->userName = $analysis->user->name ?? 'Cliente';

        // Format dates
        $this->periodStart = $analysis->period_start?->format('d/m/Y') ?? '';
        $this->periodEnd = $analysis->period_end?->format('d/m/Y') ?? '';

        // Extract summary data
        $summary = $analysis->summary ?? [];
        $this->summary = $summary;
        $this->healthScore = $summary['health_score'] ?? 0;
        $this->healthStatus = $this->translateHealthStatus($summary['health_status'] ?? 'unknown');
        $mainInsight = $summary['main_insight'] ?? 'Análise concluída com sucesso.';
        $this->mainInsight = is_array($mainInsight) ? implode(' ', $mainInsight) : $mainInsight;

        // Analysis type
        $analysisTypeEnum = $analysis->analysis_type;
        $this->analysisType = $analysisTypeEnum instanceof \App\Enums\AnalysisType
            ? $analysisTypeEnum->value
            : (is_string($analysisTypeEnum) ? $analysisTypeEnum : 'general');
        $this->analysisTypeLabel = $analysisTypeEnum instanceof \App\Enums\AnalysisType
            ? $analysisTypeEnum->label()
            : 'Análise Geral';

        // Premium summary
        $this->premiumSummary = $summary['premium_summary'] ?? [];

        // Alerts
        $this->alerts = $analysis->alerts ?? [];

        // Get suggestions grouped by priority
        $this->suggestions = $analysis->persistentSuggestions()
            ->orderBy('priority')
            ->get()
            ->map(function ($suggestion) {
                // recommended_action can be array or string - convert to readable string
                $recommendedAction = $suggestion->recommended_action;
                if (is_array($recommendedAction)) {
                    $recommendedAction = implode("\n", array_map(function ($item, $index) {
                        if (is_array($item)) {
                            return ($index + 1).'. '.($item['step'] ?? $item['action'] ?? json_encode($item));
                        }

                        return ($index + 1).'. '.$item;
                    }, $recommendedAction, array_keys($recommendedAction)));
                }

                return [
                    'title' => $suggestion->title ?? '',
                    'description' => is_array($suggestion->description) ? implode(' ', $suggestion->description) : ($suggestion->description ?? ''),
                    'recommended_action' => $recommendedAction ?? '',
                    'category' => $this->translateCategory($suggestion->category ?? ''),
                    'expected_impact' => $suggestion->expected_impact ?? 'medium',
                    'priority' => $suggestion->priority ?? 0,
                ];
            })
            ->toArray();

        // Configure email sender based on email configuration
        $this->configureEmailSender();
    }

    /**
     * Configure email sender from EmailConfiguration.
     */
    private function configureEmailSender(): void
    {
        try {
            $emailService = app(EmailConfigurationService::class);
            $config = $emailService->getByIdentifier('ai-analysis');

            if ($config && $config->is_active) {
                $emailService->useConfiguration('ai-analysis');
                $settings = $config->settings;
                $this->from($settings['from_address'] ?? 'noreply@ecommpilot.com.br', $settings['from_name'] ?? 'EcommPilot IA');
            }
        } catch (\Exception $e) {
            // Use default if configuration fails
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Análise de IA Concluída - {$this->storeName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.analysis-completed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Translate health status to Portuguese.
     */
    private function translateHealthStatus(string $status): string
    {
        return match (strtolower($status)) {
            'excellent' => 'Excelente',
            'good' => 'Bom',
            'fair', 'regular' => 'Regular',
            'poor', 'needs_attention' => 'Precisa de Atenção',
            'critical' => 'Crítico',
            default => 'Não Disponível',
        };
    }

    /**
     * Format a value as Brazilian Real currency.
     * Handles strings like "R$ 15000", "15000.50", "R$15.000,00" etc.
     */
    public static function formatBRL(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        $raw = (string) $value;

        // Already well-formatted (e.g. "R$ 15.000,00")
        if (preg_match('/^R\$\s?[\d.,]+$/', $raw) && str_contains($raw, '.') && str_contains($raw, ',')) {
            return $raw;
        }

        // Strip currency symbol and whitespace
        $clean = preg_replace('/[R$\s]/', '', $raw);

        // Detect BR format (dots as thousands, comma as decimal): "15.000,50"
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $clean)) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',') && ! str_contains($clean, '.')) {
            // Simple comma decimal: "15000,50"
            $clean = str_replace(',', '.', $clean);
        }

        if (! is_numeric($clean)) {
            return $raw; // Return as-is if we can't parse
        }

        return 'R$ '.number_format((float) $clean, 2, ',', '.');
    }

    /**
     * Translate category to Portuguese.
     */
    private function translateCategory(string $category): string
    {
        return match (strtolower($category)) {
            'inventory' => 'Estoque',
            'coupon' => 'Cupons',
            'product' => 'Produtos',
            'marketing' => 'Marketing',
            'operational' => 'Operacional',
            'customer' => 'Clientes',
            'conversion' => 'Conversão',
            'pricing' => 'Precificação',
            default => ucfirst($category),
        };
    }
}
