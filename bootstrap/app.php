<?php

use App\Http\Middleware\EnsureFinanceManager;
use App\Http\Middleware\TokenScreenControl;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function (): void {
            Route::middleware('web')
                ->group(base_path('routes/finance.php'));
        },
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'finance.manager' => EnsureFinanceManager::class,
            'token.screen.control' => TokenScreenControl::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
