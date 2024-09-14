<?php

namespace DragSense\AutoCode\Http\Requests\ThemeRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIconRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|min:2',
            'svg' => 'nullable|string',
            'classes' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'svg.required_without' => 'The svg field is required when classes is not present.',
            'classes.required_without' => 'The classes field is required when svg is not present.',
        ];
    }
}
