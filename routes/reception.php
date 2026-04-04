<?php

use App\Http\Controllers\DoctorSharePayoutReceiptPrintController;
use App\Http\Controllers\ShiftCloseSummaryPrintController;
use App\Livewire\QueueControl;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('reception/shifts', 'pages::reception.shifts')->name('reception.shifts');
    Route::get('reception/shifts/{shift}/close-summary', ShiftCloseSummaryPrintController::class)
        ->name('reception.shift-close-summary');
    Route::livewire('reception/walk-in', 'pages::reception.walk-in')->name('reception.walk-in');
    Route::livewire('reception/lab', 'pages::reception.lab')->name('reception.lab');
    Route::livewire('reception/procedures', 'pages::reception.procedures')->name('reception.procedures');
    Route::livewire('reception/procedures/{procedure}', 'pages::reception.procedure-show')->name('reception.procedures.show');
    Route::livewire('reception/appointments', 'pages::reception.appointments')->name('reception.appointments');
    Route::livewire('reception/doctor-share-out', 'pages::reception.doctor-share-out')->name('reception.doctor-share-out');
    Route::get('reception/doctor-share-ledger/{ledger}/payout-receipt', DoctorSharePayoutReceiptPrintController::class)
        ->name('reception.doctor-share-payout-receipt');
    Route::livewire('queues', 'pages::reception.queues')->name('queues.index');
    Route::livewire('queues/control/{queue}', QueueControl::class)->name('queues.control');
});
