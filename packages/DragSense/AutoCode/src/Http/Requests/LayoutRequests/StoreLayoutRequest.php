<?php

namespace DragSense\AutoCode\Http\Requests\LayoutRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLayoutRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string',
            'elements' => 'nullable|array',
            'elements.*' => 'nullable|array',
            'components' => 'nullable|array',
            'components.*' => 'nullable|string',
            'forms' => 'nullable|array',
            'forms.*' => 'nullable|string',
            'styles' => 'nullable|array',
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
