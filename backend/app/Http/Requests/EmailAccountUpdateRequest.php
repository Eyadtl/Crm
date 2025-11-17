<?php

namespace App\Http\Requests;

class EmailAccountUpdateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,disabled'],
            'sync_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:60'],
            'credentials.username' => ['sometimes', 'string'],
            'credentials.password' => ['sometimes', 'string'],
            'disabled_reason' => ['nullable', 'string'],
        ];
    }
}
