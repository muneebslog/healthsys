<?php

use App\Livewire\QueueControl;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('reception/shifts', 'pages::reception.shifts')->name('reception.shifts');
    Route::livewire('reception/walk-in', 'pages::reception.walk-in')->name('reception.walk-in');
    Route::livewire('reception/appointments', 'pages::reception.appointments')->name('reception.appointments');
    Route::livewire('reception/doctor-share-out', 'pages::reception.doctor-share-out')->name('reception.doctor-share-out');
    Route::livewire('queues', 'pages::reception.queues')->name('queues.index');
    Route::livewire('queues/control/{queue}', QueueControl::class)->name('queues.control');
});
