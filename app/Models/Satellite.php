<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Satellite extends Model
{
    /**
     * Departments the legacy user form always offered, on top of the satellites table.
     *
     * @var list<string>
     */
    public const EXTRA_DEPARTMENTS = ['MCD MINE', 'MCD MILL'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
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

    /**
     * The department choices for a user: active satellites plus the legacy extras.
     *
     * @return list<string>
     */
    public static function departmentOptions(): array
    {
        return static::query()
            ->where('active', true)
            ->pluck('name')
            ->merge(self::EXTRA_DEPARTMENTS)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
