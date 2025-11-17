<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'notes',
        'tags',
        'created_from_email_id',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'contact_project')->withPivot('role');
    }

    public function sourceEmail(): BelongsTo
    {
        return $this->belongsTo(Email::class, 'created_from_email_id');
    }
}
