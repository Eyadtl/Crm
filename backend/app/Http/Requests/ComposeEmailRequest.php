<?php

namespace App\Http\Requests;

class ComposeEmailRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'from_account_id' => ['required', 'exists:email_accounts,id'],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['email'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['email'],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ];
    }
}
