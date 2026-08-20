<?php

use Anima\Http\Controllers\EntryController;
use Anima\Http\Controllers\ReplayController;
use Illuminate\Support\Facades\Route;

Route::get('/entries', [EntryController::class, 'index'])->name('anima.api.entries.index');
Route::get('/entries/{id}', [EntryController::class, 'show'])->name('anima.api.entries.show');
Route::delete('/entries/{id}', [EntryController::class, 'destroy'])->name('anima.api.entries.destroy');
Route::delete('/entries', [EntryController::class, 'clear'])->name('anima.api.entries.clear');

Route::post('/replay', [ReplayController::class, 'store'])->name('anima.api.replay');
