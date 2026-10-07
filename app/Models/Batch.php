<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An MCD Records batch: a named group of issued items. Items carry the batch number
 * (requested_items.batchno), not the batch id, exactly as legacy stores them.
 */
class Batch extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'batchno', 'description', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by',
    ];

    /**
     * The attributes written to the audit log.
     *
     * @var list<string>
     */
    protected array $auditInclude = [
        'batchno', 'description', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RequestedItem::class, 'batchno', 'batchno')->orderBy('requested_items.id');
    }

    public function scopeLive(Builder $query): void
    {
        $query->whereNull('batches.deleted_at');
    }
}
