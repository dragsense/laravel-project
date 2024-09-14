<?php

namespace DragSense\AutoCode\Http\Requests\ThemeRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnimationRequest extends FormRequest
{
    public function rules()
    {
        return [
            'styles' => 'nullable|array',
        ];
    }

    public function messages()
    {
        return [
            // Custom error messages (if any)
        ];
    }
}
