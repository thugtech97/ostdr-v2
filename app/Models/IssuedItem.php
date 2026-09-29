<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class IssuedItem extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'item_id', 'item_code', 'issuance_qty', 'balance', 'received_by', 'issued_by',
    ];

    /**
     * The attributes written to the audit log.
     *
     * @var list<string>
     */
    protected array $auditInclude = [
        'item_id', 'item_code', 'issuance_qty', 'balance', 'received_by', 'issued_by',
    ];
}
