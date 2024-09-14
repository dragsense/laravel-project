<?php

namespace DragSense\AutoCode\Http\Requests\ThemesRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreThemesRequest extends FormRequest
{
    public function rules()
    {
        return [
            'themeId' => 'required|string',
            'projectId' => 'required|string',
            'url' => 'required|url',
        ];
    }

    public function messages()
    {
        return [
            // Custom error messages (if any)
        ];
    }
}
