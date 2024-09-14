<?php

namespace DragSense\AutoCode\Http\Requests\FormRequests;

use DragSense\AutoCode\Rules\CustomMultipleEmails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;

class StoreFormRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string',
            'slug' => 'required|string',
            'type' => 'nullable|string|in:form',
            'record' => 'nullable|boolean',
            'recordOnly' => 'nullable|boolean',
            'emailbody' => 'nullable|array',
            'emailbody.plain' => 'nullable|string',
            'emailbody.head' => 'nullable|string',
            'emailbody.body' => 'nullable|string',
            'emailbody.footer' => 'nullable|string',
            'emailbody.html' => 'nullable|boolean',
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
            'setting' => 'nullable|array',
            'setting.to' => ['nullable', 'string', new CustomMultipleEmails],
            'setting.from' => ['nullable', 'string', 'email'],
            'setting.subject' => 'nullable|string',
            'setting.cc' => ['nullable', 'string', new CustomMultipleEmails],
            'setting.bcc' => ['nullable', 'string', new CustomMultipleEmails],
            'setting.replyTo' => ['nullable', 'string', 'email'],
            'setting.redirect' => 'nullable|url',
            'setting.successMessage' => 'nullable|string',
            'setting.errorMessage' => 'nullable|string',
            'setting.sendCopy' => 'nullable|boolean',
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
