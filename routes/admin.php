<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::livewire('services', 'pages::admin.services')->name('services');
    Route::livewire('lab-tests', 'pages::admin.lab-tests')->name('lab-tests');
    Route::livewire('lab-api-logs', 'pages::admin.lab-api-logs')->name('lab-api-logs');
    Route::livewire('application-logs', 'pages::admin.application-logs')->name('application-logs');
    Route::livewire('queue-insights', 'pages::admin.queue-insights')->name('queue-insights');
    Route::livewire('queue-insights/{queue}', 'pages::admin.queue-insight-show')->name('queue-insights.show');
    Route::livewire('appointment-contacts', 'pages::admin.appointment-contacts')->name('appointment-contacts');
    Route::livewire('doctors', 'pages::admin.doctors')->name('doctors');
    Route::livewire('service-prices', 'pages::admin.service-prices')->name('service-prices');
    Route::livewire('users', 'pages::admin.users')->name('users');
    Route::livewire('settings', 'pages::admin.settings')->name('settings');
});
