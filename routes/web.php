<?php

use TelescopeAI\AutoDebug\Http\Controllers\AutoDebugDashboardController;
use TelescopeAI\AutoDebug\Http\Middleware\Authorize;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AutoDebug Dashboard Routes
|--------------------------------------------------------------------------
|
| These routes are automatically registered by the AutoDebugServiceProvider.
| They include the Authorize middleware which gates access based on
| environment and user roles.
|
*/

Route::prefix('auto-debug')
    ->middleware(['web', Authorize::class])
    ->name('autodebug.')
    ->group(function () {

        Route::get('/', [AutoDebugDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/entry/{entry}', [AutoDebugDashboardController::class, 'show'])
            ->name('show');

        Route::post('/entry/{entry}/reanalyze', [AutoDebugDashboardController::class, 'reanalyze'])
            ->name('reanalyze');

        Route::post('/entry/{entry}/ignore', [AutoDebugDashboardController::class, 'ignore'])
            ->name('ignore');

        Route::get('/stats', [AutoDebugDashboardController::class, 'stats'])
            ->name('stats');
    });
