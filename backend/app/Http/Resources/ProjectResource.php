<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deal_name' => $this->deal_name,
            'product_name' => $this->product_name,
            'deal_status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'is_terminal' => $this->status->is_terminal,
            ]),
            'deal_owner_id' => $this->deal_owner_id,
            'marketing_manager_id' => $this->marketing_manager_id,
            'estimated_value' => $this->estimated_value,
            'expected_close_date' => $this->expected_close_date,
            'notes' => $this->notes,
            'closed_lost_reason' => $this->closed_lost_reason,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'emails' => EmailResource::collection($this->whenLoaded('emails')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
        ];
    }
}
