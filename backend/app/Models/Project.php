<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'deal_name',
        'product_name',
        'marketing_manager_id',
        'deal_owner_id',
        'deal_status_id',
        'closed_lost_reason',
        'estimated_value',
        'notes',
        'expected_close_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'expected_close_date' => 'date',
    ];

    public function status(): BelongsTo
    {
        return $this->belongsTo(DealStatus::class, 'deal_status_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deal_owner_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_manager_id');
    }

    public function emails(): BelongsToMany
    {
        return $this->belongsToMany(Email::class, 'project_email')
            ->withPivot(['linked_by', 'linked_at']);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_project')
            ->withPivot('role');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'entity_id')->where('entity_type', '=', 'project');
    }
}
