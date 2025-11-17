<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'email_id',
        'filename',
        'mime_type',
        'size_bytes',
        'storage_ref',
        'status',
        'downloaded_at',
        'checksum',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }
}
