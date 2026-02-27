<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoundController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\IssController;

Route::get('/rounds/current', [RoundController::class, 'current']);
Route::get('/rounds/{round}/board', [RoundController::class, 'board']);
Route::post('/rounds/{round}/bets', [BetController::class, 'store']);
Route::get('/iss/now', [IssController::class, 'now']);

