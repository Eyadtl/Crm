<?php

namespace App\Http\Requests;

class ExportRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'filters' => ['nullable', 'array'],
        ];
    }
}
