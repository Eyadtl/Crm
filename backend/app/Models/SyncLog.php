<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'email_account_id',
        'event',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }
}
