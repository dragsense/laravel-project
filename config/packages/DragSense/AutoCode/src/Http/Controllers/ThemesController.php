<?php

namespace DragSense\AutoCode\Http\Controllers;

use DragSense\AutoCode\Http\Exceptions\ApiError;
use DragSense\AutoCode\Http\Requests\ThemesRequests\StoreThemesRequest;
use DragSense\AutoCode\Services\BackupServices;
use DragSense\AutoCode\Services\ThemesServices;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;

class ThemesController extends Controller
{
    // Dependency injection for the BackupServices
    protected $themesServices;
    protected $backupServices;
    public function __construct(ThemesServices $themesServices, BackupServices $backupServices)
    {
        $this->themesServices = $themesServices;
        $this->backupServices = $backupServices;
    }


    /**
     * Install a backup.
     *
     * @param string $id The ID of the backup to install.
     * @return JsonResponse
     */
    public function installTheme(StoreThemesRequest $request): JsonResponse
    {

        $destinationDir = $this->themesServices->installTheme($request->all());
        // Install the backup and return the response
        $theme = $this->backupServices->installBackup('', $destinationDir);

        if (File::exists($destinationDir + ".zip")) {
            File::delete($destinationDir + ".zip");
        }


        return response()->json(['theme' => $theme]);
    }
}
