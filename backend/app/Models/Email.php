<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'email_account_id',
        'message_id',
        'thread_id',
        'direction',
        'subject',
        'snippet',
        'sent_at',
        'received_at',
        'body_ref',
        'body_cached_at',
        'size_bytes',
        'sync_id',
        'is_archived',
        'project_flag',
        'has_attachments',
        'body_checksum',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'body_cached_at' => 'datetime',
        'is_archived' => 'boolean',
        'project_flag' => 'boolean',
        'has_attachments' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EmailParticipant::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_email')
            ->withPivot(['linked_by', 'linked_at']);
    }

    public function scopeForAccount($query, ?string $accountId)
    {
        if ($accountId) {
            $query->where('email_account_id', $accountId);
        }

        return $query;
    }

    public function scopeBetweenDates($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('received_at', '>=', $from);
        }
        if ($to) {
            $query->where('received_at', '<=', $to);
        }

        return $query;
    }
}
