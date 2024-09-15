<?php

namespace DragSense\AutoCode\Services;

use DragSense\AutoCode\Exports\DocumentsExport;
use DragSense\AutoCode\Models\Collection;
use DragSense\AutoCode\Models\Form;
use DragSense\AutoCode\Models\Document;
use DragSense\AutoCode\Http\Exceptions\ApiError;
use DragSense\AutoCode\Models\Schema\DocumentSchema;
use DragSense\AutoCode\Utils\Utils;
use Exception;
use File;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Excel;
use Schema;
use Str;

class DocumentServices
{


    protected $excel;

    public function __construct(Excel $excel)
    {
        $this->excel = $excel; // Inject Excel service
    }

    /**
     * Create a document
     *
     * @param array $documentData
     * @return  Document
     */
    public function createDocument(string $collectionId, bool $isForm, array $documentBody)
    {
        try {
            // Find the collection or form by ID
            $collection = $isForm ? Form::find($collectionId) : Collection::find($collectionId);
            if (!$collection) {
                throw new ApiError(($isForm ? 'Form' : 'Collection') . ' not found', 404);
            }

            $this->createTableIfNotExists($collection->slug);

            // Decode content if 'setting' exists
            if (isset($documentBody['setting']) && isset($documentBody['setting']['content'])) {
                $documentBody['setting']['content'] = html_entity_decode($documentBody['setting']['content']);
            }

            // Update states with the specific content type
            if (isset($documentBody['states'])) {
                Utils::updateContentWithType($documentBody['states'], 'content');
                $documentBody['_states'] = $documentBody['states'];
            }

            $documentBody['updater'] = [];
            // Prepare the document data
            $documentBody['coll'] = [
                '_id' => $collection->_id,
                'slug' => $collection->slug,
            ];

            $this->createTableIfNotExists($collection->slug);

            // Assuming you have a model named after the collection slug
            try {
                $documentModel = $this->getDocumentModel($collection->slug);
            } catch (Exception $e) {
                Log::warning($e->getMessage());
                throw new ApiError($e->getMessage(), 404);

            }
            // Create and save the document
            return $documentModel::create($documentBody);

        } catch (Exception $e) {
            // Handle exceptions and log the error if needed
            error_log('An unexpected error occurred: ' . $e->getMessage());
            throw new ApiError('An unexpected error occurred', 500);
        }
    }



    /**
     * Update a document
     *
     * @param string $id
     * @param array $documentData
     * @return  Document
     * @throws ApiError
     */
    public function updateDocument(string $collectionId, bool $isForm, string $id, array $documentData): Document
    {
        $collection = $isForm ? Form::find($collectionId) : Collection::find($collectionId);

        if (!$collection) {
            throw new ApiError(($isForm ? 'Form' : 'Collection') . ' not found', 404);
        }

        $this->createTableIfNotExists($collection->slug);

        try {
            $documentModel = $this->getDocumentModel($collection->slug);
        } catch (Exception $e) {
            Log::warning($e->getMessage());
            throw new ApiError($e->getMessage(), 404);

        }

        $document = $documentModel::find($id);
        if (!$document) {
            throw new ApiError('Document not found', 404);
        }

        if (isset($documentData['setting']['content'])) {
            $documentData['setting']['content'] = html_entity_decode($documentData['setting']['content']);
        }

        if (isset($documentBody['states'])) {
            Utils::updateContentWithType($documentBody['states'], 'content');
            $documentBody['_states'] = $documentBody['states'];
        }



        $document->update($documentData);
        return $document;
    }



    /**
     * Get documents with pagination
     *
     * @param array $filter
     * @param array $options
     * @return  mixed
     */
    public function getDocuments(string $collectionId, bool $isForm, array $filter = [], array $options = [])
    {

        $collection = $isForm ? Form::find($collectionId) : Collection::find($collectionId);

        if (!$collection) {
            throw new ApiError(($isForm ? 'Form' : 'Collection') . ' not found', 404);
        }

        $this->createTableIfNotExists($collection->slug);

        try {
            $documentModel = $this->getDocumentModel($collection->slug);
        } catch (Exception $e) {
            Log::warning($e->getMessage());
            throw new ApiError($e->getMessage(), 404);

        }

        $projection = [
            'name',
            'slug',
            'setting',
            'creator',
            'created_at',
            'updated_at',
            'updater',
            '_id',
        ];

        return $documentModel::paginate($filter, $options, $projection);
    }

    /**
     * Get a single document
     *
     * @param string $id
     * @return  Document
     * @throws ApiError
     */
    public function getDocument(string $collectionId, bool $isForm, string $id): Document
    {

        $collection = $isForm ? Form::find($collectionId) : Collection::find($collectionId);

        if (!$collection) {
            throw new ApiError(($isForm ? 'Form' : 'Collection') . ' not found', 404);
        }

        $this->createTableIfNotExists($collection->slug);

        try {
            $documentModel = $this->getDocumentModel($collection->slug);
        } catch (Exception $e) {
            Log::warning($e->getMessage());
            throw new ApiError($e->getMessage(), 404);

        }


        $document = $documentModel::find($id);
        if (!$document) {
            throw new ApiError('Document not found', 404);
        }




        if (!$isForm) {
            $relatedCollectionIds = $collection->relationships;
            $relatedDocuments = [];
            foreach ($relatedCollectionIds as $relatedCollectionId) {
                // Fetch the related collection
                $relatedCollection = Collection::find($relatedCollectionId);

                if ($relatedCollection) {
                    $relatedDocIds = $document->relationships[$relatedCollectionId] ?? [];

                    // Check the content of $relatedDocIds
                    if (empty($relatedDocIds)) {
                        Log::warning("No related document IDs found for collection ID: $relatedCollectionId");
                        continue; // Skip to the next iteration if no IDs are found
                    }

                    try {
                        $documentModel = $this->getDocumentModel($relatedCollection->slug);
                    } catch (Exception $e) {
                        Log::warning($e->getMessage());
                        continue;
                    }
                    // Fetch documents from the related collection
                    $relatedDocs = $documentModel::whereIn('_id', $relatedDocIds)
                        ->select('_id', 'name', 'slug')
                        ->get();

                    $relatedDocuments[$relatedCollectionId] = $relatedDocs;
                }
            }
            $document->populatedRelationships = $relatedDocuments;

        }

        $document->populatedImage = $document->populatedImages;
      
        return $document;
    }


    /**
     * Duplicate a document
     *
     * @param string $id
     * @return  Document
     */
    public function duplicateDocument(string $collectionId, bool $isForm, string $id): Document
    {
        $collection = $isForm ? Form::find($collectionId) : Collection::find($collectionId);

        if (!$collection) {
            throw new ApiError(($isForm ? 'Form' : 'Collection') . ' not found', 404);
        }

        $this->createTableIfNotExists($collection->slug);

        try {
            $documentModel = $this->getDocumentModel($collection->slug);
        } catch (Exception $e) {
            Log::warning($e->getMessage());
            throw new ApiError($e->getMessage(), 404);

        }
        return $documentModel::duplicate($id, "Document");
    }

    /**
     * Delete a document
     *
     * @param string $id
     * @return  bool
     * @throws ApiError
     */
    public function deleteDocument(string $collectionId, bool $isForm, string $id): bool
    {

        $collection = $isForm ? Form::find($collectionId) : Collection::find($collectionId);

        if (!$collection) {
            throw new ApiError(($isForm ? 'Form' : 'Collection') . ' not found', 404);
        }

        $this->createTableIfNotExists($collection->slug);

        try {
            $documentModel = $this->getDocumentModel($collection->slug);
        } catch (Exception $e) {
            Log::warning($e->getMessage());
            throw new ApiError($e->getMessage(), 404);

        }
        $document = $documentModel::find($id);

        if (!$document) {
            throw new ApiError('Document not found', 404);
        }

        $document->delete();
        return true;
    }


    /**
     * Download documents as an Excel file with filtering.
     *
     * @param string $collectionId
     * @param bool $isForm
     * @param array $filter
     * @param array $options
     * @param string $host
     * @return string Path to the exported Excel file
     * @throws ApiError
     */
    public function downloadDocuments($collectionId, $isForm, $filter, $options, $host)
    {
        // Fetch collection or form
        $collection = $isForm ? Form::find($collectionId) : Collection::find($collectionId);
        if (!$collection) {
            throw new ApiError(404, ($isForm ? 'Form' : 'Collection') . ' not found');
        }

        // Temporary directory
        $tempDir = storage_path('app/private/temp');
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        // Build model name dynamically based on the collection slug
        $modelName = $collection->slug;

        // Retrieve documents based on filter and options
        $this->createTableIfNotExists($collection->slug);

        try {
            $documentModel = $this->getDocumentModel($modelName);

        } catch (Exception $e) {
            Log::warning($e->getMessage());
            throw new ApiError($e->getMessage(), 404);
        }

        $results = $documentModel::filterAllDocuments([], [], $filter, $options, function ($slug) {
            return $this->getDocumentModel($slug);
        });

        $stateKeys = $collection->states ? array_map(fn($key) => "states.$key", array_keys($collection->states)) : [];

        $documents = is_array($results) && isset($results['documents'])
            ? collect($results['documents'])
            : collect([]);

            Log::info('before', ["docs" => $documents]);

            // Transform data to include custom fields
            $documents = $documents->map(function ($doc) use ($host) {
                // Handle states if they exist
                $statesObject = isset($doc['states']) ? collect($doc['states'])->mapWithKeys(function ($value, $key) {
                    return ["states.$key" => $value['defaultValue'] ?? ''];
                })->toArray() : [];
            
                return array_merge([
                    'name' => $doc['name'] ?? '',
                  
                ], $statesObject, [
                    'setting.title' => isset($doc['setting']['title']) ? $doc['setting']['title'] : '', // Ensure setting exists
                    'setting.desc' => isset($doc['setting']['desc'] ) ? $doc['setting']['desc'] : '',
                    'setting.image' => isset($doc['setting']['populatedImage']) ? $host . $doc['setting']['populatedImage']['src'] : '',
                    'setting.content' => isset($doc['setting']['content']) ? $doc['setting']['content'] : '',
                    'setting.excerpt' => isset($doc['setting']['excerpt']) ? $doc['setting']['excerpt'] : '',
                    'setting.status' => isset($doc['setting']['status']) ? $doc['setting']['status'] : '',
                    'creator' => isset($doc['creator']['name']) ? $doc['creator']['name'] : null, // Use array access
                    'createdAt' => $doc['created_at'] ?? null, // Use snake_case if that's how it's returned in the log
                ],
               
            );
            })->toArray();
            
            Log::info('after', ["docs" => $documents]);

        // Define the fields
        $fields = array_merge(['name'], $stateKeys, [
            'setting.title',
            'setting.desc',
            'setting.image',
            'setting.content',
            'setting.excerpt',
            'setting.status',
            'creator',
            'createdAt'
        ]);

        $export = new DocumentsExport(collect($documents), $fields, $collection->name . ' Entries', $results['total']);

        $filename = uniqid() . '.xlsx';
        // Define output path
        // Define output path relative to storage/app
        $outputPath = $tempDir . '/' . $filename;

        // Store the export directly to the file system
        try {
            Log::info(''. $outputPath);
            $this->excel->store($export, 'private/temp/'.$filename ); // Use injected instance
        } catch (Exception $e) {
            Log::error('Error exporting documents: ' . $e->getMessage());
            throw new ApiError(500, 'Error exporting documents');
        }

        // Return the path to the exported file
        return $outputPath;

    }


    /**
     * Get filtered documents
     *
     * @param string $collectionId
     * @param array $documentBody
     * @param array $options
     * @return array
     * @throws ApiError
     */
    public function getFilteredDocuments($collectionId, array $documentBody, array $options)
    {
        $documentIds = $documentBody['documentIds'] ?? [];
        $filters = $documentBody['filters'] ?? [];
        $withRelationship = $documentBody['withRelationship'] ?? false;
        $selectedRelationships = $documentBody['selectedRelationships'] ?? [];

        $projection = [
            'slug',
            '_id',
            'relationships',
        ];

        // Fetch the collection
        $collection = Collection::select($projection)
            ->find($collectionId);

        if (!$collection) {
            throw new ApiError('Collection not found', 404);
        }

        if (empty($documentIds)) {
            throw new ApiError('Not Found', 404);
        }

        $relatedCollections = $collection->populatedRelations;
        $docIds = is_array($documentIds) ? $documentIds : [$documentIds];
        $relationships = [];


        if ($withRelationship && !empty($relatedCollections)) {
            $relationships = collect($relatedCollections)->filter(function ($relation) use ($selectedRelationships) {
                return in_array($relation['_id'], $selectedRelationships);
            })->toArray();
        }

        $DocumentModel = $this->getDocumentModel($collection->slug);
        $results = $DocumentModel::filterDocuments($docIds, $filters, $relationships, $options, function ($slug) {
            return $this->getDocumentModel($slug);
        });

        return $results;
    }

    /**
     * Get all filtered documents
     *
     * @param string $collectionId
     * @param array $documentBody
     * @param array $filter
     * @param array $options
     * @return array
     * @throws ApiError
     */
    public function getAllFilteredDocuments($collectionId, array $documentBody, array $filter, array $options)
    {
        $filters = $documentBody['filters'] ?? [];
        $withRelationship = $documentBody['withRelationship'] ?? false;
        $selectedRelationships = $documentBody['selectedRelationships'] ?? [];

        $projection = [
            'slug',
            '_id',
            'relationships',
        ];

        // Fetch the collection
        $collection = Collection::select($projection)
            ->find($collectionId);

        if (!$collection) {
            throw new ApiError('Collection not found', 404);
        }

        $relatedCollections = $collection->populatedRelations;
        $relationships = [];

        if ($withRelationship && !empty($relatedCollections)) {
            $relationships = collect($relatedCollections)->filter(function ($relation) use ($selectedRelationships) {
                return in_array($relation['_id'], $selectedRelationships);
            })->toArray();
        }


        $DocumentModel = $this->getDocumentModel($collection->slug);
        $results = $DocumentModel::filterAllDocuments($filters, $relationships, $filter, $options, function ($slug) {
            return $this->getDocumentModel($slug);
        });


        return $results;
    }


    /**
     * Retrieve the model instance based on the collection slug.
     *
     * @param string $slug
     * @return "Model"
     */
    private function getDocumentModel(string $slug)
    {

        $camelCaseSlug = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));

        $pluralSlug = Str::plural(strtolower($slug));

        $tableName = 'ac_' . $pluralSlug;
        $modelClassName = 'DragSense\\AutoCode\\Models\\AC' . ucfirst($camelCaseSlug) . 'sDocument';
        $baseClassName = 'Document';

        // Check if the table exists
        if (!Schema::hasTable($tableName)) {
            throw new Exception('Table not found for the collection', 404);
        }

        // Dynamically create the model class if it doesn't exist
        if (!class_exists($modelClassName)) {
            eval ("
            namespace DragSense\\AutoCode\\Models;
    
            use Illuminate\Database\Eloquent\Model;
    
            class " . basename(str_replace('\\', '/', $modelClassName)) . " extends $baseClassName {
                protected \$table = '{$tableName}';
            }
            ");
        }

        // Return an instance of the dynamically created model
        return new $modelClassName();
    }


    /**
     * Create a dynamic table based on the collection slug name.
     *
     * @param string $slug
     * @return bool
     * @throws Exception
     */
    private function createTableIfNotExists(string $slug): bool
    {
        $pluralSlug = Str::plural(strtolower($slug));

        $tableName = 'ac_' . $pluralSlug;

        // Check if the table already exists
        if (!Schema::hasTable($tableName)) {
            // Create the table
            DocumentSchema::up($tableName);
            return true;
        }

        return false; // Table already exists
    }




}
