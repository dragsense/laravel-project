<?php

namespace DragSense\AutoCode\Http\Requests\DocumentRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string',
            'slug' => 'required|string',
            'url' => 'nullable|string',
            'relationships' => 'nullable|array',
            'relationships.*' => 'nullable|array',
            'relationships.*.*' => 'nullable|string',
            'states' => 'nullable|array',
            'states.*' => 'nullable|array',
            'setting' => 'nullable|array',
            'setting.title' => 'nullable|string',
            'setting.desc' => 'nullable|string',
            'setting.image' => 'nullable|string',
            'setting.content' => 'nullable|string',
            'setting.excerpt' => 'nullable|string',
            'setting.status' => 'nullable|string|in:DRAFT,PUBLIC,PRIVATE',
            'duplicated' => 'nullable|boolean',
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
