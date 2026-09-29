<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'name',
        'dept',
        'isActive',
        'role_id',
        'role',
        'email',
    ];

    /**
     * The attributes written to the audit log. The password hash is left out on purpose.
     *
     * @var list<string>
     */
    protected array $auditInclude = [
        'username',
        'name',
        'dept',
        'isActive',
        'role_id',
        'role',
        'email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'isActive' => 'integer',
            'isLoggedIn' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * The role record. Named to avoid clashing with the legacy denormalized `role` name column.
     */
    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Legacy stores the admin role as "ADMIN" (occasionally lowercase).
     */
    public function isAdmin(): bool
    {
        return strtoupper((string) $this->role) === 'ADMIN';
    }

    /**
     * Users that appear in user maintenance. The built-in ADMIN account is hidden, as in legacy.
     */
    public function scopeManageable(Builder $query): void
    {
        $query->where('username', '<>', 'ADMIN');
    }
}
