<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::livewire('services', 'pages::admin.services')->name('services');
    Route::livewire('doctors', 'pages::admin.doctors')->name('doctors');
    Route::livewire('service-prices', 'pages::admin.service-prices')->name('service-prices');
    Route::livewire('users', 'pages::admin.users')->name('users');
});
