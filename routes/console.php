<?php

use App\Jobs\SyncBrazilLocationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
*/

// Sync all connected stores every day at midnight (Brazil timezone)
// Jobs are distributed across 4 hours (00:00 - 04:00) to avoid peak load
Schedule::command('stores:sync')
    ->dailyAt('00:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Sincronização automática de lojas concluída com sucesso.');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Sincronização automática de lojas falhou.');
    });

// Auto-analysis for eligible stores at 04:30 (after 4-hour sync window completes)
// Jobs are distributed across 2 hours (04:30 - 06:30) to avoid AI API overload
Schedule::command('analyses:auto')
    ->dailyAt('04:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::channel('analysis')->info('Análises automáticas agendadas com sucesso.');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::channel('analysis')->error('Comando de análises automáticas falhou.');
    });

// Sync Brazil locations from IBGE API every Sunday at 3:00 AM
Schedule::job(new SyncBrazilLocationsJob)->weeklyOn(0, '3:00');
