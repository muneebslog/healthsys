<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('reception/shifts', 'pages::reception.shifts')->name('reception.shifts');
});
