<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EmailAccount */
class EmailAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'display_name' => $this->display_name,
            'imap_host' => $this->imap_host,
            'imap_port' => $this->imap_port,
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'security_type' => $this->security_type,
            'status' => $this->status,
            'last_synced_at' => optional($this->last_synced_at)->toIso8601String(),
            'sync_state' => $this->sync_state,
            'sync_interval_minutes' => $this->sync_interval_minutes,
            'sync_error' => $this->sync_error,
            'retry_count' => $this->retry_count,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
