<?php

namespace DragSense\AutoCode\Http\Requests\ThemeRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCodeRequest extends FormRequest
{
    public function rules()
    {
        return [
            'code' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            // Custom error messages (if any)
        ];
    }
}
