<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('doctor')->name('doctor.')->group(function (): void {
    Route::livewire('/', 'pages::doctor.dashboard')->name('dashboard');
    Route::livewire('profile', 'pages::doctor.profile')->name('profile');
    Route::livewire('payouts', 'pages::doctor.payouts')->name('payouts');
    Route::livewire('queue', 'pages::doctor.queue-today')->name('queue');
});
