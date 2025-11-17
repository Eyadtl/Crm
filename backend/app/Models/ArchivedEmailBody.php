<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivedEmailBody extends Model
{
    use HasFactory;

    protected $primaryKey = 'email_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'email_id',
        'storage_ref',
        'archived_at',
        'restored_at',
        'notes',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }
}
