<?php

use Anima\Http\Controllers\WorkbenchController;
use Illuminate\Support\Facades\Route;

Route::get('/{view?}', [WorkbenchController::class, 'index'])
    ->where('view', '(.*)')
    ->name('anima.workbench');
