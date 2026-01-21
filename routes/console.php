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

// Sync Brazil locations from IBGE API every Sunday at 3:00 AM
Schedule::job(new SyncBrazilLocationsJob)->weeklyOn(0, '3:00');
