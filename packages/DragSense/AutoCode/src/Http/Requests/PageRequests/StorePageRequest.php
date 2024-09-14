<?php

namespace DragSense\AutoCode\Http\Requests\PageRequests;

use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string',
            'slug' => 'required|string',
            'layout' => 'required|string',
            'scripts' => 'nullable|array',
            'scripts.head' => 'nullable|string',
            'scripts.footer' => 'nullable|string',
            'url' => 'nullable|string',
            'elements' => 'nullable|array',
            'elements.*' => 'nullable|array',
            'components' => 'nullable|array',
            'components.*' => 'nullable|string',
            'forms' => 'nullable|array',
            'forms.*' => 'nullable|string',
            'styles' => 'nullable|array',
            'setting' => 'nullable|array',
            'setting.title' => 'nullable|string',
            'setting.desc' => 'nullable|string',
            'setting.image' => 'nullable|string',
            'setting.content' => 'nullable|string',
            'setting.excerpt' => 'nullable|string',
            'setting.status' => 'nullable|in:DRAFT,PUBLIC,PRIVATE',
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