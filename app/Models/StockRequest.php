<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockRequest extends Model
{
    use Auditable;

    /**
     * Legacy statuses. The approval ones are written by WFS, which is not integrated in v2 yet.
     */
    public const STATUS_PENDING = 'Pending';

    public const STATUS_SUBMITTED = 'Submitted';

    public const STATUS_FULLY_APPROVED = 'FULLY APPROVED';

    /**
     * The request has at least one live item and none is outstanding: every item is
     * closed or has an issuance that took its balance to zero. Same rule as legacy.
     */
    public const COMPLETED_SQL = "(
        EXISTS (
            SELECT 1 FROM requested_items ri
            WHERE ri.transaction_no = stock_requests.transaction_no
              AND ri.deleted_at IS NULL
        )
        AND NOT EXISTS (
            SELECT 1 FROM requested_items ri
            WHERE ri.transaction_no = stock_requests.transaction_no
              AND ri.deleted_at IS NULL
              AND COALESCE(ri.isClosed, 0) <> 1
              AND NOT EXISTS (
                  SELECT 1 FROM issued_items ii
                  WHERE ii.item_id = ri.id AND ii.balance = 0
              )
        )
    )";

    /**
     * At least one live item has nothing at all issued against it (closed items count).
     */
    public const HAS_UNSERVED_SQL = "(
        EXISTS (
            SELECT 1 FROM requested_items ri
            WHERE ri.transaction_no = stock_requests.transaction_no
              AND ri.deleted_at IS NULL
              AND (
                  SELECT COALESCE(SUM(ii.issuance_qty), 0)
                  FROM issued_items ii WHERE ii.item_id = ri.id
              ) = 0
        )
    )";

    /**
     * Nothing has been issued against any live item of the request (legacy `unserved`).
     */
    public const UNSERVED_SQL = "(
        NOT EXISTS (
            SELECT 1 FROM issued_items ii
            JOIN requested_items ri ON ri.id = ii.item_id
            WHERE ri.transaction_no = stock_requests.transaction_no
              AND ri.deleted_at IS NULL
        )
    )";

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'date_filed', 'time_filed', 'date_needed', 'dept', 'cost_code', 'remarks', 'requested_by',
        'created_by', 'updated_by', 'deleted_at', 'deleted_by', 'status', 'WFS_connection', 'isSaved', 'active', 'transaction_no',
        'isReceived', 'received_by', 'received_at', 'origin', 'requestor', 'approved_by', 'approved_at', 'batchno',
    ];

    /**
     * The attributes written to the audit log.
     *
     * @var list<string>
     */
    protected array $auditInclude = [
        'date_filed', 'time_filed', 'date_needed', 'dept', 'cost_code', 'remarks', 'requested_by',
        'created_by', 'updated_by', 'deleted_at', 'deleted_by', 'status', 'WFS_connection', 'isSaved', 'active', 'transaction_no',
        'isReceived', 'received_by', 'received_at', 'origin', 'requestor', 'approved_by', 'approved_at', 'batchno',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'isSaved' => 'boolean',
            'active' => 'boolean',
            'isReceived' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestedItem::class, 'transaction_no', 'transaction_no')
            ->whereNull('requested_items.deleted_at')
            ->orderBy('requested_items.id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('stock_requests.active', true);
    }

    /**
     * Adds is_completed / has_unserved / is_unserved (1/0) so lists can show status without per-row queries.
     */
    public function scopeWithProgress(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('stock_requests.*');
        }

        $query->selectRaw('CASE WHEN '.self::COMPLETED_SQL.' THEN 1 ELSE 0 END AS is_completed')
            ->selectRaw('CASE WHEN '.self::HAS_UNSERVED_SQL.' THEN 1 ELSE 0 END AS has_unserved')
            ->selectRaw('CASE WHEN '.self::UNSERVED_SQL.' THEN 1 ELSE 0 END AS is_unserved');
    }

    /**
     * The requestor's working list: finished requests drop off unless something was never served.
     */
    public function scopeStillOpen(Builder $query): void
    {
        $query->whereRaw('(NOT '.self::COMPLETED_SQL.' OR '.self::HAS_UNSERVED_SQL.')')
            ->whereRaw("(LOWER(COALESCE(stock_requests.status, '')) <> 'completed' OR ".self::HAS_UNSERVED_SQL.')');
    }

    /**
     * A requestor may still change the request: nothing downstream has acted on it yet.
     */
    public function isEditable(): bool
    {
        return ! $this->isReceived
            && ! in_array(strtolower((string) $this->status), ['fully approved', 'completed', 'submitted'], true);
    }

    public function isPending(): bool
    {
        return strtolower((string) $this->status) === 'pending' && ! $this->isReceived;
    }

    /**
     * The badge legacy shows for a request. Needs the columns from withProgress().
     *
     * @return array{label: string, variant: string, note?: string, alert?: bool}
     */
    public function statusBadge(): array
    {
        $status = strtolower((string) $this->status);
        $completed = (int) $this->is_completed === 1;
        $unservedNote = (int) $this->is_unserved === 1 ? 'Unserved' : 'Partially Served';

        return match (true) {
            $status === 'fully approved' && ! $this->isReceived => ['label' => 'Approved', 'variant' => 'warning'],
            $status === 'cancelled' && ! $this->isReceived => ['label' => 'Cancelled', 'variant' => 'danger'],
            $this->isReceived && ! $completed => ['label' => 'Received', 'variant' => 'info', 'note' => $unservedNote, 'alert' => (int) $this->is_unserved === 1],
            // Items closed without ever being issued read as completed; say what really happened.
            $completed && (int) $this->has_unserved === 1 => ['label' => 'Closed', 'variant' => 'info', 'note' => $unservedNote, 'alert' => true],
            $completed => ['label' => 'Completed', 'variant' => 'success'],
            $status === 'submitted' && ! $this->isReceived => ['label' => 'Submitted', 'variant' => 'success'],
            $status === 'hold' => ['label' => 'Hold', 'variant' => 'primary'],
            default => ['label' => 'Pending', 'variant' => 'muted'],
        };
    }

    /**
     * Legacy transaction number: date filed (Ymd) + zero-padded id, e.g. 20260804-000091.
     */
    public static function makeTransactionNo(string $dateFiled, int $id): string
    {
        return date('Ymd', strtotime($dateFiled)).'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
