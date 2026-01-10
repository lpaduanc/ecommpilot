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

// Sync Brazil locations from IBGE API every Sunday at 3:00 AM
Schedule::job(new SyncBrazilLocationsJob)->weeklyOn(0, '3:00');
