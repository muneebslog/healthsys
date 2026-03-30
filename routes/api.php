<?php

use App\Http\Controllers\Api\TokenScreenController;
use Illuminate\Support\Facades\Route;

Route::get('/token-screen/queues', [TokenScreenController::class, 'queues']);
Route::get('/token-screen/data', [TokenScreenController::class, 'data']);
