<?php

namespace DragSense\AutoCode\Rules;

use Illuminate\Contracts\Validation\Rule;

class CustomMultipleEmails implements Rule
{
    public function passes($attribute, $value)
    {
        $emails = explode(',', $value);
        foreach ($emails as $email) {
            if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }
        return true;
    }

    public function message()
    {
        return 'The :attribute field must be a valid comma-separated list of emails.';
    }
}