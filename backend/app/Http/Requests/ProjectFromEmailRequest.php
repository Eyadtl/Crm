<?php

namespace App\Http\Requests;

class ProjectFromEmailRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'deal_name' => ['required', 'string', 'max:255'],
            'deal_status_id' => ['required', 'exists:deal_statuses,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'estimated_value' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
