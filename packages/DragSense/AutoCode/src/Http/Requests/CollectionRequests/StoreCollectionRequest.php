<?php

namespace DragSense\AutoCode\Http\Requests\CollectionRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string',
            'slug' => 'required|string',
            'layout' => 'required|string',
            'type' => 'nullable|string|in:collection',
            'scripts' => 'nullable|array',
            'scripts.head' => 'nullable|string',
            'scripts.footer' => 'nullable|string',
            'url' => 'nullable|string',
            'elements' => 'nullable|array',
            'elements.*' => 'nullable|array',
            'relationships' => 'nullable|array',
            'relationships.*' => 'nullable|string',
            'states' => 'nullable|array',
            'states.*' => 'nullable|array',
            'components' => 'nullable|array',
            'components.*' => 'nullable|string',
            'forms' => 'nullable|array',
            'forms.*' => 'nullable|string',
            'styles' => 'nullable|array',
            'styles.*' => 'nullable|array',
            'setting' => 'nullable|array',
            'setting.title' => 'nullable|string',
            'setting.desc' => 'nullable|string',
            'setting.image' => 'nullable|string',
            'setting.content' => 'nullable|string',
            'setting.excerpt' => 'nullable|string',
            'setting.status' => 'nullable|string|in:DRAFT,PUBLIC,PRIVATE',
            'updater' => 'nullable|array',
            'creator' => 'nullable|array',
        ];
    }

    public function messages()
    {
        return [
            // Custom error messages (if any)
        ];
    }
}
