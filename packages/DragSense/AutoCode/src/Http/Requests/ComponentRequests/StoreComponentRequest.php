<?php

namespace DragSense\AutoCode\Http\Requests\ComponentRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComponentRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string',
            'parent' => 'nullable|string',
            'type' => 'nullable|string|in:component',
            'attached' => 'nullable|string',
            'elements' => 'nullable|array',
            'elements.*' => 'nullable|array',
            'states' => 'nullable|array',
            'states.*' => 'nullable|array',
            'components' => 'nullable|array',
            'components.*' => 'nullable|string',
            'forms' => 'nullable|array',
            'forms.*' => 'nullable|string',
            'styles' => 'nullable|array',
            'styles.*' => 'nullable|array',
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
