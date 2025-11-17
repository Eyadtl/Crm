<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxLock extends Model
{
    use HasFactory;

    protected $table = 'mailbox_locks';

    protected $primaryKey = 'email_account_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'email_account_id',
        'lock_owner',
        'locked_until',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }
}
