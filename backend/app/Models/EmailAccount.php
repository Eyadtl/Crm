<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmailAccount extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'display_name',
        'imap_host',
        'imap_port',
        'smtp_host',
        'smtp_port',
        'security_type',
        'auth_type',
        'encrypted_credentials',
        'status',
        'last_synced_uid',
        'last_synced_at',
        'sync_state',
        'sync_error',
        'sync_interval_minutes',
        'retry_count',
        'disabled_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'encrypted_credentials' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function lock(): HasOne
    {
        return $this->hasOne(MailboxLock::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function shouldSync(?CarbonInterface $referenceTime = null): bool
    {
        $referenceTime ??= now();
        $intervalMinutes = max((int) ($this->sync_interval_minutes ?? 15), 1);

        if ($this->status !== 'active') {
            return false;
        }

        if (in_array($this->sync_state, ['syncing', 'queued'], true)) {
            return false;
        }

        if (!$this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->copy()->addMinutes($intervalMinutes)->lte($referenceTime);
    }
}
