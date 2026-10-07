<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A scheduled system shutdown (legacy "Application Maintenance"). Removed schedules are
 * kept with deleted_at set.
 */
class Application extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scheduled_date', 'scheduled_time', 'reason', 'posted_date', 'posted_time', 'deleted_at', 'server_name',
    ];

    /**
     * The attributes written to the audit log.
     *
     * @var list<string>
     */
    protected array $auditInclude = [
        'scheduled_date', 'scheduled_time', 'reason', 'posted_date', 'posted_time', 'deleted_at',
    ];

    public function scopeLive(Builder $query): void
    {
        $query->whereNull('applications.deleted_at');
    }

    public function scheduledAt(): Carbon
    {
        return Carbon::parse($this->scheduled_date.' '.substr((string) $this->scheduled_time, 0, 8));
    }

    /**
     * The next shutdown still ahead, if one is scheduled within the next five days (legacy banner window).
     */
    public static function upcoming(): ?self
    {
        return static::query()->live()
            ->whereBetween('scheduled_date', [today()->toDateString(), today()->addDays(5)->toDateString()])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get()
            ->first(fn (self $schedule) => $schedule->scheduledAt()->isFuture());
    }
}
