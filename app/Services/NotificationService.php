<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Analysis;
use App\Models\Notification;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Notifica início de sincronização
     */
    public function notifySyncStarted(Store $store, string $syncType): void
    {
        try {
            $typeLabel = $this->getSyncTypeLabel($syncType);

            $this->createNotification(
                user: $store->user,
                store: $store,
                type: NotificationType::Sync,
                title: 'Sincronização Iniciada',
                message: "A sincronização de {$typeLabel} da loja {$store->name} foi iniciada.",
                data: [
                    'sync_type' => $syncType,
                    'store_name' => $store->name,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar notificação de sync iniciado', [
                'store_id' => $store->id,
                'sync_type' => $syncType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifica conclusão de sincronização
     */
    public function notifySyncCompleted(Store $store, string $syncType, array $stats): void
    {
        try {
            $typeLabel = $this->getSyncTypeLabel($syncType);
            $statsText = $this->formatSyncStats($stats);

            $this->createNotification(
                user: $store->user,
                store: $store,
                type: NotificationType::Sync,
                title: 'Sincronização Concluída',
                message: "A sincronização de {$typeLabel} da loja {$store->name} foi concluída com sucesso. {$statsText}",
                data: [
                    'sync_type' => $syncType,
                    'store_name' => $store->name,
                    'stats' => $stats,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar notificação de sync concluído', [
                'store_id' => $store->id,
                'sync_type' => $syncType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifica falha na sincronização
     */
    public function notifySyncFailed(Store $store, string $syncType, string $error): void
    {
        try {
            $typeLabel = $this->getSyncTypeLabel($syncType);

            // Use custom error message if it contains specific keywords (like reconnection request)
            // Otherwise use the generic message
            $isCustomMessage = str_contains($error, 'reconect') || str_contains($error, 'reconex');
            $message = $isCustomMessage
                ? $error
                : "A sincronização de {$typeLabel} da loja {$store->name} falhou. Por favor, tente novamente.";

            $this->createNotification(
                user: $store->user,
                store: $store,
                type: NotificationType::Sync,
                title: 'Erro na Sincronização',
                message: $message,
                data: [
                    'sync_type' => $syncType,
                    'store_name' => $store->name,
                    'error' => $error,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar notificação de sync falhou', [
                'store_id' => $store->id,
                'sync_type' => $syncType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifica início de análise
     */
    public function notifyAnalysisStarted(Analysis $analysis): void
    {
        try {
            $store = $analysis->store;

            $this->createNotification(
                user: $analysis->user,
                store: $store,
                type: NotificationType::Analysis,
                title: 'Análise de IA Iniciada',
                message: "A análise de IA da loja {$store->name} foi iniciada. Você será notificado quando estiver pronta.",
                data: [
                    'analysis_id' => $analysis->id,
                    'store_name' => $store->name,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar notificação de análise iniciada', [
                'analysis_id' => $analysis->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifica conclusão de análise
     */
    public function notifyAnalysisCompleted(Analysis $analysis): void
    {
        try {
            $store = $analysis->store;
            $suggestionsCount = $analysis->persistentSuggestions()->count();

            $this->createNotification(
                user: $analysis->user,
                store: $store,
                type: NotificationType::Analysis,
                title: 'Análise de IA Concluída',
                message: "A análise de IA da loja {$store->name} foi concluída com {$suggestionsCount} recomendações personalizadas.",
                data: [
                    'analysis_id' => $analysis->id,
                    'store_name' => $store->name,
                    'suggestions_count' => $suggestionsCount,
                    'health_score' => $analysis->healthScore(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar notificação de análise concluída', [
                'analysis_id' => $analysis->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifica falha na análise
     */
    public function notifyAnalysisFailed(Analysis $analysis, string $error): void
    {
        try {
            $store = $analysis->store;

            $this->createNotification(
                user: $analysis->user,
                store: $store,
                type: NotificationType::Analysis,
                title: 'Erro na Análise de IA',
                message: "A análise de IA da loja {$store->name} falhou. Seus créditos foram reembolsados. Por favor, tente novamente.",
                data: [
                    'analysis_id' => $analysis->id,
                    'store_name' => $store->name,
                    'error' => $error,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar notificação de análise falhou', [
                'analysis_id' => $analysis->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifica envio de e-mail
     */
    public function notifyEmailSent(User $user, string $emailType, string $recipient): void
    {
        try {
            $typeLabel = $this->getEmailTypeLabel($emailType);

            $this->createNotification(
                user: $user,
                store: null,
                type: NotificationType::Email,
                title: 'E-mail Enviado',
                message: "{$typeLabel} foi enviado para {$recipient}.",
                data: [
                    'email_type' => $emailType,
                    'recipient' => $recipient,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar notificação de email enviado', [
                'user_id' => $user->id,
                'email_type' => $emailType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifica falha no envio de e-mail
     */
    public function notifyEmailFailed(User $user, string $emailType, string $recipient, string $error): void
    {
        try {
            $typeLabel = $this->getEmailTypeLabel($emailType);

            $this->createNotification(
                user: $user,
                store: null,
                type: NotificationType::Email,
                title: 'Erro no Envio de E-mail',
                message: "Falha ao enviar {$typeLabel} para {$recipient}. Por favor, verifique suas configurações de e-mail.",
                data: [
                    'email_type' => $emailType,
                    'recipient' => $recipient,
                    'error' => $error,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar notificação de email falhou', [
                'user_id' => $user->id,
                'email_type' => $emailType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cria uma notificação
     */
    private function createNotification(
        User $user,
        ?Store $store,
        NotificationType $type,
        string $title,
        string $message,
        array $data = []
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'store_id' => $store?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Obtém label do tipo de sincronização
     */
    private function getSyncTypeLabel(string $syncType): string
    {
        return match ($syncType) {
            'products' => 'produtos',
            'orders' => 'pedidos',
            'customers' => 'clientes',
            'coupons' => 'cupons',
            'all' => 'todos os dados',
            default => $syncType,
        };
    }

    /**
     * Obtém label do tipo de e-mail
     */
    private function getEmailTypeLabel(string $emailType): string
    {
        return match ($emailType) {
            'analysis_completed' => 'E-mail de análise concluída',
            'welcome' => 'E-mail de boas-vindas',
            'password_reset' => 'E-mail de redefinição de senha',
            default => 'E-mail',
        };
    }

    /**
     * Formata estatísticas da sincronização
     */
    private function formatSyncStats(array $stats): string
    {
        $parts = [];

        if (isset($stats['products_count'])) {
            $parts[] = "{$stats['products_count']} produtos";
        }

        if (isset($stats['orders_count'])) {
            $parts[] = "{$stats['orders_count']} pedidos";
        }

        if (isset($stats['customers_count'])) {
            $parts[] = "{$stats['customers_count']} clientes";
        }

        if (isset($stats['coupons_count'])) {
            $parts[] = "{$stats['coupons_count']} cupons";
        }

        if (empty($parts)) {
            return '';
        }

        return 'Sincronizados: '.implode(', ', $parts).'.';
    }
}
