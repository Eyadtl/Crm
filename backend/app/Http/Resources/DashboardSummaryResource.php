<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_projects' => $this['total_projects'],
            'projects_by_status' => $this['projects_by_status'],
            'top_contacts' => $this['top_contacts'],
            'latest_emails' => EmailResource::collection($this['latest_emails']),
        ];
    }
}
