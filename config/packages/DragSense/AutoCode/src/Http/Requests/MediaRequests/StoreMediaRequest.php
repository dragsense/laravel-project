<?php

namespace DragSense\AutoCode\Http\Requests\MediaRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function rules()
    {
        return [
            'src' => 'nullable|string',
            'fileName' => 'nullable|string',
            'name' => 'required|string',
            'type' => 'nullable|string',
            'alt' => 'required|string',
            'size' => 'nullable|numeric',
            'dimensions' => 'nullable|array',
            'dimensions.*' => 'nullable|numeric',
            'mimetype' => 'nullable|string',
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
