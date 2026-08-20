<?php

use Anima\Http\Controllers\AssetController;
use Anima\Http\Controllers\WorkbenchController;
use Illuminate\Support\Facades\Route;

Route::get('/assets/{path}', [AssetController::class, 'show'])
    ->where('path', '.*')
    ->name('anima.assets');

Route::get('/{view?}', [WorkbenchController::class, 'index'])
    ->where('view', '(.*)')
    ->name('anima.workbench');
