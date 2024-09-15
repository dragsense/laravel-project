<?php

namespace DragSense\AutoCode\Services;

use DragSense\AutoCode\Http\Exceptions\ApiError;
use DragSense\AutoCode\Models\Collection;
use DragSense\AutoCode\Models\Form;
use DragSense\AutoCode\Models\Schema\CollectionSchema;
use DragSense\AutoCode\Models\Schema\ComponentSchema;
use DragSense\AutoCode\Models\Schema\DocumentSchema;
use DragSense\AutoCode\Models\Schema\FormSchema;
use DragSense\AutoCode\Models\Schema\LayoutSchema;
use DragSense\AutoCode\Models\Schema\MediaSchema;
use DragSense\AutoCode\Models\Schema\PageSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Schema;
use Str;
use ZipArchive;
use Illuminate\Support\Facades\Log;

class BackupServices
{
    protected $dataDir;
    protected $backupDir;
    protected $publicDir;

    public function __construct()
    {
        $this->dataDir = storage_path('app/private/data');
        $this->backupDir = storage_path('app/private/backups');
        $this->publicDir = storage_path('app/public');

        // Ensure the backup directory exists
        if (!File::exists($this->backupDir)) {
            try {
                File::makeDirectory($this->backupDir, 0755, true);
            } catch (\Exception $e) {
                Log::error('Failed to create backup directory: ' . $e->getMessage());
                throw new \Exception('Failed to create backup directory.', 500);
            }
        }
    }

    /**
     * Creates a directory and copies files from source to destination.
     *
     * @param string $destinationDir The destination directory path.
     * @param string $sourceDir The source directory path.
     * @param string $dirName The name of the directory to be created.
     * @return void
     * @throws ApiError If the source directory does not exist.
     */
    private function createDirectoryAndCopyFiles($destinationDir, $sourceDir, $dirName)
    {
        $dirPath = $destinationDir . '/' . $dirName;

        // Create destination directory if it doesn't exist
        if (!File::exists($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        // Check if source directory exists
        if (!File::exists($sourceDir)) {
            // Remove created directory if source does not exist
            File::makeDirectory($sourceDir, 0755, true);
        }

        // If the directory name is 'data', apply filter logic
        if ($dirName === 'data') {
            $files = File::allFiles($sourceDir);

            foreach ($files as $file) {
                $filePath = (string) $file;

                // Skip copying 'setting.json' for 'data' directory
                if (strpos($filePath, 'setting.json') !== false) {
                    continue; // Skip this file
                }

                // Copy the file to the destination directory
                $relativePath = str_replace($sourceDir, '', $filePath);
                File::copy($filePath, $dirPath . '/' . $relativePath);
            }
        } else {
            // For other directories, copy everything without filtering
            File::copyDirectory($sourceDir, $dirPath);
        }
    }

    /**
     * Takes a snapshot of the database and saves it to the destination directory.
     *
     * @param string $destinationDir The directory to save the snapshot.
     * @return void
     * @throws ApiError If the database backup fails.
     */
    private function takeSnapshot($destinationDir, $isCollectionsEntries, $isFormsEntries)
    {
        try {
            $baseTables = [
                'ac_layouts' => 'layout',
                'ac_pages' => 'page',
                'ac_collections' => 'collection',
                'ac_forms' => 'form',
                'ac_components' => 'component',
                'ac_media' => 'media',
            ];

            // Initialize tables array with base tables


            // Add dynamic collection entries if flag is set
            if ($isCollectionsEntries && Schema::hasTable('ac_collections')) {

                $collectionSlugs = Collection::pluck('slug')->toArray();
                foreach ($collectionSlugs as $slug) {

                    $pluralSlug = Str::plural(strtolower($slug));

                    $tableName = 'ac_' . $pluralSlug;
                    $baseTables[$tableName] = 'document';
                }

            }

            // Add dynamic form entries if flag is set
            if ($isFormsEntries && Schema::hasTable('ac_forms')) {
                $formSlugs = Form::pluck('slug')->toArray();

                foreach ($formSlugs as $slug) {

                    $pluralSlug = Str::plural(strtolower($slug));

                    $tableName = 'ac_' . $pluralSlug;
                    $baseTables[$tableName] = 'document'; // Mapping form slugs to 'documents'
                }
            }

            // Initialize data array
            $exportData = [];
            \Log::info('Processing table: ', $baseTables);

            // Fetch data from each table
            foreach ($baseTables as $table => $schemaName) {

                \Log::info('Processing table: ' . $table);
                // Fetch data
                if (Schema::hasTable($table)) {
                    $data = DB::table($table)->get(); // Fetch data from the table
                    $exportData[$table] = [
                        'schema' => $schemaName,
                        'data' => $data->toArray()
                    ];
                }
            }

            // Encode the data to JSON format
            $jsonData = json_encode($exportData, JSON_PRETTY_PRINT);

            // Define file name and path
            $fileName = 'database' . '.json';
            $filePath = $destinationDir . '/' . $fileName;

            // Save the JSON to a file
            file_put_contents($filePath, $jsonData);


        } catch (\Exception $e) {
            // Clean up any created files or directories in case of an error
            if (File::exists($destinationDir)) {
                File::deleteDirectory($destinationDir);
            }
            throw new ApiError($e->getMessage() ?: 'Database backup failed.', 500);
        }
    }


    /**
     * Creates a ZIP archive of the backup directory and cleans up the directory.
     *
     * @param string $destinationDir The directory to be zipped.
     * @return void
     * @throws ApiError If ZIP creation fails.
     */
    private function createZipAndCleanUp($destinationDir)
    {
        $zipPath = $destinationDir . '.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($destinationDir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($destinationDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }

            $zip->close();
            File::deleteDirectory($destinationDir);
        } else {
            throw new ApiError('Failed to create zip file.', 500);
        }
    }

    /**
     * Extracts a ZIP archive to the specified directory.
     *
     * @param string $destinationDir The directory to extract the ZIP file.
     * @return void
     * @throws ApiError If ZIP extraction fails.
     */
    private function extractZip($destinationDir)
    {
        $zipPath = $destinationDir . '.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath) === true) {
            $zip->extractTo($destinationDir);
            $zip->close();
        } else {
            throw new ApiError('Failed to extract zip file.', 500);
        }
    }
    /**
     * Restores a database snapshot from the specified directory.
     *
     * @param string $destinationDir The directory containing the snapshot.
     * @return void
     * @throws ApiError If the database restore fails.
     */
    private function restoreSnapshot($destinationDir)
    {
        try {
            $fileName = 'database' . '.json';
            $filePath = $destinationDir . '/' . $fileName;

            // Log the file path for debugging
            \Log::info('JSON file path: ' . $filePath);

            // Check if JSON file exists
            if (!file_exists($filePath)) {
                throw new \Exception('Database restore failed: JSON file not found at ' . $filePath);
            }

            // Load the JSON file content
            $jsonData = json_decode(file_get_contents($filePath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Database restore failed: Invalid JSON format');
            }

            foreach ($jsonData as $table => $content) {
                try {
                    // Drop the table if it doesn't exist and create it based on the schema
                    if (!Schema::hasTable($table)) {
                        switch ($content['schema']) {
                            case 'page':
                                PageSchema::up($table);
                                break;
                            case 'collection':
                                CollectionSchema::up($table);
                                break;
                            case 'layout':
                                LayoutSchema::up($table);
                                break;
                            case 'component':
                                ComponentSchema::up($table);
                                break;
                            case 'form':
                                FormSchema::up($table);
                                break;
                            case 'media':
                                MediaSchema::up($table);
                                break;
                            case 'document':
                                DocumentSchema::up($table);
                                break;
                            default:
                                \Log::warning("Schema type not recognized for table: $table");
                                continue 2;
                        }
                    }

                    // Insert or update rows
                    foreach ($content['data'] as $row) {
                        $rowData = (array) $row;

                        // Check if the row already exists (Assuming '_id' is the unique field)
                        $existingRow = DB::table($table)->where('_id', $rowData['_id'])->first();

                        if ($existingRow) {
                            // Handle potential errors while updating rows
                            try {
                                DB::table($table)->where('_id', $rowData['_id'])->update($rowData);
                            } catch (\Illuminate\Database\QueryException $e) {
                                // Log full error message for debugging purposes
                                \Log::error("Error updating row in table $table: " . $e->getMessage());

                                // Limit the error message to 100 characters
                                $shortMessage = substr($e->getMessage(), 0, 200);
                                throw new \Exception($shortMessage ."...");
                            }
                        } else {
                            // Insert the row if it doesn't exist
                            DB::table($table)->insert($rowData);
                        }
                    }

                } catch (\Exception $tableException) {
                    // Log the specific error related to the table for debugging
                    \Log::error("Failed to restore table: $table. Error: " . $tableException->getMessage());
                    throw new \Exception("Failed to restore table: $table. Error: " . $tableException->getMessage());
                }
            }

        } catch (\Exception $e) {
            // Clean up any created files or directories in case of an error
            if (File::exists($destinationDir)) {
                File::deleteDirectory($destinationDir);
            }

            // Log the main error and throw ApiError with a shortened message
            \Log::error('Database restore failed: ' . $e->getMessage());

            // Return a shortened version of the message (first 100 characters)
            throw new ApiError('Database restore failed. Error: ' . $e->getMessage(), 500);
        }
    }





    /**
     * Installs a backup by extracting it, restoring the database, and copying files.
     *
     * @param string $id The backup identifier.
     * @return array Status of the operation.
     * @throws ApiError If the backup does not exist or restoration fails.
     */
    public function installBackup($id, $themePath)
    {
        $destinationDir = '';

        if ($themePath) {
            $destinationDir = $themePath;


        } else {
            $destinationDir = $this->backupDir . '/' . $id;

            // Check if backup ZIP file exists

        }

        if (!File::exists($destinationDir . '.zip')) {
            throw new \Exception('Backup folder not found.', 409); // HTTP 409 Conflict
        }

        // Extract the ZIP file
        $this->extractZip($destinationDir);

        // Restore the database
        $this->restoreSnapshot($destinationDir);

        // Copy data directory
        $this->copyDirectory($destinationDir, 'data', $this->dataDir);

        // Copy static directory
        $this->copyDirectory($destinationDir, 'static', $this->publicDir . '/static');

        // Clean up the extracted files
        File::deleteDirectory($destinationDir);

        return ['status' => true];
    }

    /**
     * Copies a directory from the backup to the target directory.
     *
     * @param string $destinationDir The backup directory path.
     * @param string $dirName The name of the directory to copy.
     * @param string $targetDir The target directory path.
     * @return void
     */
    private function copyDirectory($destinationDir, $dirName, $targetDir)
    {
        $dirPath = $destinationDir . '/' . $dirName;
        if (File::exists($dirPath)) {
            File::copyDirectory($dirPath, $targetDir);
        }
    }

    /**
     * Creates a backup by copying files and taking a database snapshot.
     *
     * @param string $id The backup identifier.
     * @return array Status of the operation.
     * @throws ApiError If the backup already exists.
     */
    public function createBackup($id, $isCollectionsEntries, $isFormsEntries)
    {
        $destinationDir = $this->backupDir . '/' . $id;

        if (File::exists($destinationDir . '.zip')) {
            throw new ApiError('Backup already exists.', 409);
        }

        File::makeDirectory($destinationDir, 0755, true);

        $this->create($destinationDir, $isCollectionsEntries, $isFormsEntries);

        return ['status' => true];
    }

    /**
     * Creates a backup by copying files and taking a database snapshot.
     *
     * @param string $destinationDir The backup destinationDir.
     * @throws ApiError If the backup already exists.
     */
    public function create($destinationDir, $isCollectionsEntries = fase, $isFormsEntries = false)
    {
        // Copy data directory
        $this->createDirectoryAndCopyFiles($destinationDir, $this->dataDir, 'data');

        // Copy static directory
        $this->createDirectoryAndCopyFiles($destinationDir, $this->publicDir . '/static', 'static');
        // Take a snapshot of the database
        $this->takeSnapshot($destinationDir, $isCollectionsEntries, $isFormsEntries);
        // Create a ZIP archive and clean up the directory
        $this->createZipAndCleanUp($destinationDir);

    }

    /**
     * Updates an existing backup by recreating it.
     *
     * @param string $id The backup identifier.
     * @return array Status of the operation.
     * @throws ApiError If the backup does not exist.
     */
    public function updateBackup($id, $isCollectionsEntries, $isFormsEntries)
    {
        $destinationDir = $this->backupDir . '/' . $id;

        if (!File::exists($destinationDir . '.zip')) {
            throw new ApiError('Backup not found.', 404);
        }
        Log::info($destinationDir);
        $this->create($destinationDir, $isCollectionsEntries, $isFormsEntries);
        // Re-create the backup
        return ['status' => true];
    }

    /**
     * Retrieves the path to a backup ZIP file.
     *
     * @param string $id The backup identifier.
     * @return string The path to the backup ZIP file.
     * @throws ApiError If the backup does not exist.
     */
    public function getBackup($id)
    {
        $zipPath = $this->backupDir . '/' . $id . '.zip';

        if (!File::exists($zipPath)) {
            throw new ApiError('Backup not found.', 404);
        }

        return $zipPath;
    }

    /**
     * Deletes a backup ZIP file.
     *
     * @param string $id The backup identifier.
     * @return array Status of the operation.
     * @throws ApiError If the backup does not exist.
     */
    public function deleteBackup($id)
    {
        $zipPath = $this->backupDir . '/' . $id . '.zip';

        if (!File::exists($zipPath)) {
            throw new ApiError('Backup not found.', 404);
        }

        File::delete($zipPath);

        return ['status' => true];
    }
}
