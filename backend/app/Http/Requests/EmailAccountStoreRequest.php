<?php

namespace App\Http\Requests;

class EmailAccountStoreRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'unique:email_accounts,email'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'imap_host' => ['required', 'string'],
            'imap_port' => ['required', 'integer'],
            'smtp_host' => ['required', 'string'],
            'smtp_port' => ['required', 'integer'],
            'security_type' => ['required', 'in:ssl,tls,starttls'],
            'auth_type' => ['required', 'string'],
            'credentials.username' => ['required', 'string'],
            'credentials.password' => ['required', 'string'],
            'sync_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:60'],
        ];
    }
}
