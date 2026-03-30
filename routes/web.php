<?php

use App\Http\Controllers\Api\QueueControlController;
use App\Http\Controllers\InvoicePrintController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('token-screen', 'token-screen.index')->name('token-screen');

Route::middleware(['web', 'token.screen.control', 'throttle:120,1'])
    ->prefix('api')
    ->group(function (): void {
        Route::post('queues/{queue}/call-next', [QueueControlController::class, 'callNext'])
            ->name('api.queues.call-next');
        Route::post('queues/{queue}/skip', [QueueControlController::class, 'skip'])
            ->name('api.queues.skip');
        Route::post('queues/{queue}/previous', [QueueControlController::class, 'previous'])
            ->name('api.queues.previous');
        Route::post('tokens/{token}/requeue', [QueueControlController::class, 'requeue'])
            ->name('api.tokens.requeue');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('invoices/{invoice}/print', InvoicePrintController::class)->name('invoices.print');
});

require __DIR__.'/reception.php';
require __DIR__.'/doctor.php';
require __DIR__.'/admin.php';
require __DIR__.'/owner.php';
require __DIR__.'/settings.php';
