<?php

namespace App\Providers;

use App\Mail\Transport\MailjetTransport;
use App\Notifications\Channels\DatabaseEmailChannel;
use App\Services\AI\AIManager;
use App\Services\ExternalData\CompetitorAnalysisService;
use App\Services\ExternalData\DecodoProxyService;
use App\Services\ExternalData\ExternalDataAggregator;
use App\Services\ExternalData\GoogleTrendsService;
use App\Services\ExternalData\MarketDataService;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Dangerous commands that should be logged and potentially blocked.
     */
    private const DANGEROUS_COMMANDS = [
        'migrate:fresh',
        'migrate:reset',
        'migrate:refresh',
        'db:wipe',
        'db:seed', // Only dangerous if run alone
    ];

    /**
     * Commands that are ALWAYS BLOCKED (even in local environment).
     * Use migrate:safe instead for controlled destructive operations.
     */
    private const ALWAYS_BLOCKED_COMMANDS = [
        'migrate:fresh',
        'migrate:reset',
        'db:wipe',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register AIManager as a singleton
        $this->app->singleton(AIManager::class, function ($app) {
            return new AIManager;
        });

        // Register External Data Services as singletons
        $this->app->singleton(DecodoProxyService::class);
        $this->app->singleton(GoogleTrendsService::class);
        $this->app->singleton(MarketDataService::class);
        $this->app->singleton(CompetitorAnalysisService::class, function ($app) {
            return new CompetitorAnalysisService($app->make(DecodoProxyService::class));
        });
        $this->app->singleton(ExternalDataAggregator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Mailjet transport
        Mail::extend('mailjet', function (array $config) {
            return new MailjetTransport(
                config('services.mailjet.key', ''),
                config('services.mailjet.secret', '')
            );
        });

        // Register custom notification channel for admin-configured email
        Notification::resolved(function (ChannelManager $service) {
            $service->extend('database-email', function ($app) {
                return new DatabaseEmailChannel;
            });
        });

        // SECURITY: Log all dangerous artisan commands
        $this->registerDangerousCommandLogger();
    }

    /**
     * Register a listener to log dangerous artisan commands.
     * This helps track down unexpected database resets.
     */
    private function registerDangerousCommandLogger(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            $command = $event->command;

            // Check if this is an ALWAYS BLOCKED command (blocked in ALL environments)
            // Exception: Allow if called from migrate:safe command (via internal flag)
            if (app()->bound('migrate_safe_bypass') && app('migrate_safe_bypass') === true) {
                return; // Allow - being called from migrate:safe
            }

            foreach (self::ALWAYS_BLOCKED_COMMANDS as $blocked) {
                if ($command === $blocked || str_starts_with($command, $blocked . ' ')) {
                    // Log the blocked attempt
                    Log::channel('daily')->emergency('🚨 BLOCKED DANGEROUS COMMAND - USE migrate:safe INSTEAD', [
                        'command' => $command,
                        'input' => $event->input->__toString(),
                        'environment' => app()->environment(),
                        'timestamp' => now()->toIso8601String(),
                        'user' => get_current_user(),
                        'cwd' => getcwd(),
                        'php_sapi' => PHP_SAPI,
                        'backtrace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15))
                            ->map(fn ($trace) => ($trace['file'] ?? 'unknown') . ':' . ($trace['line'] ?? '?'))
                            ->toArray(),
                    ]);

                    // ALWAYS block - no exceptions
                    throw new \RuntimeException(
                        "\n\n" .
                        "╔════════════════════════════════════════════════════════════════════╗\n" .
                        "║  🚨 BLOQUEADO: O comando '{$command}' está DESABILITADO!         ║\n" .
                        "╠════════════════════════════════════════════════════════════════════╣\n" .
                        "║  Este comando DESTRUIRIA todos os dados do banco de dados.        ║\n" .
                        "║                                                                    ║\n" .
                        "║  Se você REALMENTE precisa resetar o banco, use:                  ║\n" .
                        "║                                                                    ║\n" .
                        "║    php artisan migrate:safe --fresh --seed                        ║\n" .
                        "║                                                                    ║\n" .
                        "║  Este comando cria um backup antes e exige confirmação.           ║\n" .
                        "╚════════════════════════════════════════════════════════════════════╝\n\n"
                    );
                }
            }

            // Log other dangerous commands (but don't block in local)
            foreach (self::DANGEROUS_COMMANDS as $dangerous) {
                if ($command === $dangerous || str_starts_with($command, $dangerous . ' ')) {
                    // Log with CRITICAL level
                    Log::channel('daily')->critical('⚠️ DANGEROUS COMMAND EXECUTED', [
                        'command' => $command,
                        'input' => $event->input->__toString(),
                        'environment' => app()->environment(),
                        'timestamp' => now()->toIso8601String(),
                        'user' => get_current_user(),
                        'cwd' => getcwd(),
                        'php_sapi' => PHP_SAPI,
                        'backtrace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15))
                            ->map(fn ($trace) => ($trace['file'] ?? 'unknown') . ':' . ($trace['line'] ?? '?'))
                            ->toArray(),
                    ]);

                    break; // Already logged, no need to check more
                }
            }
        });
    }
}
