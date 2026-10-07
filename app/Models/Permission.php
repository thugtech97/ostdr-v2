<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A legacy access-right page. Its description is the key the access rules use
 * (e.g. "Manage Receiving"), and module_type names the module it belongs to.
 */
class Permission extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'module_type',
        'description',
        'active',
    ];

    /**
     * The attributes written to the audit log.
     *
     * @var list<string>
     */
    protected array $auditInclude = [
        'module_type',
        'description',
        'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
