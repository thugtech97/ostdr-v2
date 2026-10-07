<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestedItem extends Model
{
    use Auditable;

    /**
     * Everything issued against a requested item, as a correlated subquery.
     */
    public const ISSUED_QTY_SQL = '(SELECT COALESCE(SUM(issuance_qty), 0) FROM issued_items WHERE issued_items.item_id = requested_items.id)';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'stock_code', 'description', 'uom', 'available_qty', 'requested_qty', 'created_by', 'updated_by',
        'deleted_at', 'deleted_by', 'requested_by', 'transaction_no', 'remarks', 'isClosed', 'isClosed_remarks', 'batchno',
    ];

    /**
     * The attributes written to the audit log.
     *
     * @var list<string>
     */
    protected array $auditInclude = [
        'stock_code', 'description', 'uom', 'available_qty', 'requested_qty', 'created_by', 'updated_by',
        'deleted_at', 'deleted_by', 'requested_by', 'transaction_no', 'remarks', 'isClosed', 'isClosed_remarks', 'batchno',
    ];

    public function issuances(): HasMany
    {
        return $this->hasMany(IssuedItem::class, 'item_id');
    }

    /**
     * Items that have not been removed (legacy marks removed items with deleted_at).
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('requested_items.deleted_at');
    }

    /**
     * Adds issued_qty, the legacy appended attribute, as a column.
     */
    public function scopeWithIssued(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('requested_items.*');
        }

        $query->selectRaw(self::ISSUED_QTY_SQL.' AS issued_qty');
    }

    /**
     * How legacy reads an item on the receiver/records views.
     *
     * @return array{label: string, tone: string}
     */
    public function serveStatus(): array
    {
        $issued = (float) ($this->issued_qty ?? 0);

        return match (true) {
            (int) $this->isClosed === 1 => ['label' => '(CLOSED) '.$this->isClosed_remarks, 'tone' => 'danger'],
            $issued == (float) $this->requested_qty => ['label' => 'COMPLETED', 'tone' => 'success'],
            $issued == 0.0 => ['label' => 'UNSERVED', 'tone' => 'warning'],
            default => ['label' => 'PARTIALLY SERVED', 'tone' => 'info'],
        };
    }
}
