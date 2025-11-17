<?php

namespace App\Http\Requests;

class ProjectStoreRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'deal_name' => ['required', 'string', 'max:255'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'marketing_manager_id' => ['nullable', 'exists:users,id'],
            'deal_owner_id' => ['nullable', 'exists:users,id'],
            'deal_status_id' => ['required', 'exists:deal_statuses,id'],
            'estimated_value' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'expected_close_date' => ['nullable', 'date'],
        ];
    }
}
