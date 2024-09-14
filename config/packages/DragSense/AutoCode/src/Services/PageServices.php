<?php

namespace DragSense\AutoCode\Services;

use DragSense\AutoCode\Models\Media;
use DragSense\AutoCode\Models\Page;
use DragSense\AutoCode\Http\Exceptions\ApiError;
use Illuminate\Support\Facades\Log;

class PageServices
{
    /**
     * Create a page
     *
     * @param array $pageData
     * @return  Page
     */
    public function createPage(array $pageData): Page
    {
        if (isset($pageData['setting']['content'])) {
            $pageData['setting']['content'] = html_entity_decode($pageData['setting']['content']);
        }

        if (isset($pageData['scripts'])) {
            $pageData['scripts']['head'] = html_entity_decode($pageData['scripts']['head'] ?? '');
            $pageData['scripts']['footer'] = html_entity_decode($pageData['scripts']['footer'] ?? '');
        }

        if (!isset($pageData['elements'])) {
            $pageData['elements'] = [
                "0" => [
                    "_uid" => "0",
                    "tagName" => "div",
                    "name" => 'Page Root Element',
                    "type" => "layout",
                    "layout" => "root",
                    "nodeValue" => "",
                    "childNodes" => []
                ]
            ];
        }

        return Page::create($pageData);
    }

    /**
     * Update a page
     *
     * @param string $id
     * @param array $pageData
     * @return  Page
     * @throws ApiError
     */
    public function updatePage(string $id, array $pageData): Page
    {
        $page = Page::find($id);
        if (!$page) {
            throw new ApiError('Page not found', 404);
        }

        if (isset($pageData['setting']['content'])) {
            $pageData['setting']['content'] = html_entity_decode($pageData['setting']['content']);
        }

        if (isset($pageData['scripts'])) {
            $pageData['scripts']['head'] = html_entity_decode($pageData['scripts']['head'] ?? '');
            $pageData['scripts']['footer'] = html_entity_decode($pageData['scripts']['footer'] ?? '');
        }

        $page->update($pageData);
        return $page;
    }

    /**
     * Update page elements
     *
     * @param string $id
     * @param array $pageData
     * @return  Page
     * @throws ApiError
     */
    public function updateElements(string $id, array $pageData): Page
    {
        $page = Page::find($id);
        if (!$page) {
            throw new ApiError('Page not found', 404);
        }

        $page->published = false;
        $page->_components = $pageData['_components'];
        $page->_elements = $pageData['_elements'];
        $page->_forms = $pageData['_forms'];

        $page->update();
        return $page;
    }

    /**
     * Publish a page
     *
     * @param string $id
     * @param array $pageData
     * @return  Page
     * @throws ApiError
     */
    public function publishPage(string $id, array $pageData): Page
    {
        $page = Page::find($id);
        if (!$page) {
            throw new ApiError('Page not found', 404);
        }

        if ($page->published) {
            throw new ApiError('Page is already published', 400);
        }

        $page->elements = $page->_elements;
        $page->components = $page->_components;
        $page->forms = $page->_forms;
        $page->styles = $page->_styles;
        $page->published = true;
        $page->updater = $pageData['updater'] ?? null;

        $page->update($pageData);
        return $page;
    }

    /**
     * Restore a page
     *
     * @param string $id
     * @param array $pageData
     * @return  Page
     * @throws ApiError
     */
    public function restorePage(string $id, array $pageData): Page
    {
        $page = Page::find($id);

        if (!$page) {
            throw new ApiError('Page not found', 404);
        }

        if ($page->published) {
            throw new ApiError('There are no new draft changes to restore', 400);
        }

        $page->_elements = $page->elements;
        $page->_components = $page->components;
        $page->_forms = $page->forms;
        $page->_styles = $page->styles;
        $page->updater = $pageData['updater'] ?? null;
        $page->published = true;

        $page->update($pageData);
        return $page;
    }

    /**
     * Update a page style
     *
     * @param string $id
     * @param array $pageData
     * @return  Page
     * @throws ApiError
     */
    public function updateStyle(string $id, array $pageData): Page
    {
        $page = Page::find($id);
        if (!$page) {
            throw new ApiError('Page not found', 404);
        }

      

        $page->published = false;
        $page->_styles = $pageData['_styles'];

        $page->update();
        return $page;
    }

    /**
     * Get pages with pagination
     *
     * @param array $filter
     * @param array $options
     * @return  mixed
     */
    public function getPages(array $filter = [], array $options = [])
    {
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

        return Page::paginate($filter, $options, $projection);
    }

    /**
     * Get a single page
     *
     * @param string $id
     * @return  Page
     * @throws ApiError
     */
    public function getPage(string $id): Page
    {

        $projection = [
            'name',
            'scripts',
            'slug',
            'type',
            '_id',
            'layout',
            'setting',
        ];

        $page = Page::select($projection)->find($id);
        if (!$page) {
            throw new ApiError('Page not found', 404);
        }


        $page->populatedLayout = $page->populatedLayouts;
        $page->populatedImage = $page->populatedImages;

        return $page;
    }


    /**
     * Duplicate a page
     *
     * @param string $id
     * @return  Page
     */
    public function duplicatePage(string $id): Page
    {
        return Page::duplicate($id, "Page");
    }

    /**
     * Delete a page
     *
     * @param string $id
     * @return  bool
     * @throws ApiError
     */
    public function deletePage(string $id): bool
    {
        $page = Page::find($id);
        if (!$page) {
            throw new ApiError('Page not found', 404);
        }

        $page->delete();
        return true;
    }
}
