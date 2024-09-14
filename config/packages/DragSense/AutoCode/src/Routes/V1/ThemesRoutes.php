<?php

namespace DragSense\AutoCode\Routes\V1;


use DragSense\AutoCode\Http\Controllers\ThemesController;
use Illuminate\Support\Facades\Route;

class ThemesRoutes
{
    public static function register()
    {
        Route::post('/', [ThemesController::class, 'installTheme']);
    }
}
