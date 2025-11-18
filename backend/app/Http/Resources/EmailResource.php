<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Email */
class EmailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email_account_id' => $this->email_account_id,
            'subject' => $this->subject,
            'snippet' => $this->snippet,
            'direction' => $this->direction,
            'received_at' => optional($this->received_at)->toIso8601String(),
            'sent_at' => optional($this->sent_at)->toIso8601String(),
            'body_ref' => $this->body_ref,
            'body_cached_at' => optional($this->body_cached_at)->toIso8601String(),
            'has_attachments' => $this->has_attachments,
            'project_flag' => $this->project_flag,
            'email_account' => EmailAccountResource::make($this->whenLoaded('account')),
            'participants' => EmailParticipantResource::collection($this->whenLoaded('participants')),
            'attachments' => EmailAttachmentResource::collection($this->whenLoaded('attachments')),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
        ];
    }
}
