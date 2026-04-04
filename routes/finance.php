<?php

use App\Http\Controllers\FinanceExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'finance.manager'])->prefix('finance')->name('finance.')->group(function (): void {
    Route::livewire('dashboard', 'pages::finance.dashboard')->name('dashboard');
    Route::livewire('money-trail', 'pages::finance.money-trail')->name('money-trail');
    Route::livewire('expenses', 'pages::finance.expenses')->name('expenses');
    Route::livewire('ledger', 'pages::finance.ledger')->name('ledger');
    Route::livewire('ledger/{ledger}', 'pages::finance.ledger-show')->name('ledger.show');
    Route::livewire('audit', 'pages::finance.audit')->name('audit');
    Route::livewire('exports', 'pages::finance.exports')->name('exports');

    Route::get('export/{type}', FinanceExportController::class)
        ->whereIn('type', ['invoices', 'expenses', 'ledger', 'shifts'])
        ->name('export.download');
});
