<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('owner')->name('owner.')->group(function (): void {
    Route::livewire('shifts', 'pages::owner.shifts')->name('shifts');
    Route::livewire('shifts/{shift}', 'pages::owner.shift-show')->name('shifts.show');
});
