<?php

namespace App\Http\Requests;

class ProjectUpdateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'deal_name' => ['sometimes', 'string', 'max:255'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'marketing_manager_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'deal_owner_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'deal_status_id' => ['sometimes', 'exists:deal_statuses,id'],
            'closed_lost_reason' => ['sometimes', 'nullable', 'string'],
            'estimated_value' => ['sometimes', 'nullable', 'numeric'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
