<?php

namespace DragSense\AutoCode\Http\Requests\BackupRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBackupRequest extends FormRequest
{
    public function rules()
    {
        return [
            '_id' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            // Custom error messages (if any)
        ];
    }
}
