<?php

use Anima\Http\Controllers\EntryController;
use Anima\Http\Controllers\ReplayController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::get('/entries', [EntryController::class, 'index'])->name('anima.api.entries.index');
Route::get('/entries/{id}', [EntryController::class, 'show'])->name('anima.api.entries.show');
Route::delete('/entries/{id}', [EntryController::class, 'destroy'])->name('anima.api.entries.destroy');

Route::delete('/entries', [EntryController::class, 'clear'])
    ->middleware(ThrottleRequests::class . ':' . config('anima.rate_limits.purge', '10,1'))
    ->name('anima.api.entries.clear');

Route::post('/replay', [ReplayController::class, 'store'])
    ->middleware(ThrottleRequests::class . ':' . config('anima.rate_limits.replay', '30,1'))
    ->name('anima.api.replay');
