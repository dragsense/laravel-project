<?php

namespace DragSense\AutoCode\Services;

use DragSense\AutoCode\Http\Exceptions\ApiError;
use Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ThemesServices
{
    protected $themeDir;
    public function __construct()
    {
        $this->themeDir = storage_path('app/private/temp');

        // Ensure the theme directory exists
        if (!File::exists($this->themeDir)) {
            try {
                File::makeDirectory($this->themeDir, 0755, true);
            } catch (\Exception $e) {
                Log::error('Failed to create temp directory: ' . $e->getMessage());
                throw new \Exception('Failed to create temp directory.', 500);
            }
        }
    }

  

     /**
     * Install a theme
     *
     * @param array $themeData
     * @return string The path to the saved zip file
     * @throws \Exception
     */
    public function installTheme(array $themeData)
    {
        $themeId = $themeData['themeId'];
        $projectId = $themeData['projectId'];
        $url = $themeData['url'];

        // Ensure the theme directory exists
        if (!File::exists($this->themeDir)) {
            File::makeDirectory($this->themeDir, 0755, true);
        }

        $destinationDir = $this->themeDir . '/' . $themeId;

        // If the theme already exists, remove it
        if (File::exists($destinationDir)) {
            File::deleteDirectory($destinationDir); // Remove the existing theme folder
        }

        // Define the path for the downloaded zip file
        $zipFilePath = $this->themeDir . '/' . $themeId . '.zip';

        // If the zip file already exists, remove it
        if (File::exists($zipFilePath)) {
            File::delete($zipFilePath); // Remove the existing zip file
        }

        try {
            // Construct the download URL
            $themeUrl = "{$url}/api/projects/{$projectId}/themes/{$themeId}";

            // Download the theme using Laravel's HTTP client
            $response = Http::get($themeUrl);

            if ($response->failed()) {
                throw new \Exception("Failed to download theme, status code: " . $response->status());
            }

            // Ensure the response is read as binary and save it as a zip file
            File::put($zipFilePath, $response->body());

            // Log the success
            \Log::info("Theme {$themeId} downloaded and saved to {$zipFilePath}");

            // Return the path of the zip file
            return $destinationDir;

        } catch (\Exception $e) {
            throw new \Exception("Failed to install theme: " . $e->getMessage());
        }
    }

}
