<?php

use Illuminate\Support\Facades\Route;
use DragSense\AutoCode\Http\Controllers\AutoCodeController;

Route::get('/', [AutoCodeController::class, 'handlePageRequest']);

Route::get('{any}', [AutoCodeController::class, 'handlePageRequest']);
