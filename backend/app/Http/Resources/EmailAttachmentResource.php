<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EmailAttachment */
class EmailAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'status' => $this->status,
            'storage_ref' => $this->storage_ref,
        ];
    }
}
