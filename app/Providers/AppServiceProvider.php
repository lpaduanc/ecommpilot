<?php

namespace App\Providers;

use App\Mail\Transport\MailjetTransport;
use App\Services\AI\AIManager;
use App\Services\ExternalData\CompetitorAnalysisService;
use App\Services\ExternalData\DecodoProxyService;
use App\Services\ExternalData\ExternalDataAggregator;
use App\Services\ExternalData\GoogleTrendsService;
use App\Services\ExternalData\MarketDataService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
    }
}
