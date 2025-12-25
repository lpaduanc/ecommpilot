<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA Routes
|--------------------------------------------------------------------------
|
| All routes are handled by the Vue.js SPA frontend.
| API routes are excluded from this catch-all.
|
*/
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum|storage).*$');
