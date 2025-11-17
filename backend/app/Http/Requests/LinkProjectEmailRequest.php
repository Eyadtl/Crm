<?php

namespace App\Http\Requests;

class LinkProjectEmailRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email_id' => ['required', 'exists:emails,id'],
        ];
    }
}
