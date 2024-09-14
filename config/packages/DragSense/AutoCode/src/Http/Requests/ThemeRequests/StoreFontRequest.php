<?php

namespace DragSense\AutoCode\Http\Requests\ThemeRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFontRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|min:2',
            'fontFamily' => 'required|string',
            'isGoogleFont' => 'nullable|boolean',
            'fontSrc' => 'nullable|array',
            'fontSrc.*' => 'nullable|array',
            'src' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            // Custom error messages (if any)
        ];
    }
}
