<?php

namespace DragSense\AutoCode\Http\Requests\ThemeRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariableRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|min:2',
            'value' => 'required|string',
            'type' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            // Custom error messages (if any)
        ];
    }
}
