<?php

namespace App\Models\Concerns;

use App\Models\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes created/updated audit rows in the same format the legacy app's
 * owen-it/laravel-auditing package used, so both apps feed one audit report.
 *
 * Models list the attributes worth auditing in $auditInclude.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAudit('created', [], $model->only($model->auditInclude));
        });

        static::updated(function ($model) {
            $changed = array_keys(array_intersect_key($model->getChanges(), array_flip($model->auditInclude)));

            if ($changed === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), array_flip($changed));

            $model->writeAudit('updated', $old, $model->only($changed));
        });
    }

    protected function writeAudit(string $event, array $old, array $new): void
    {
        Audit::create([
            'user_type' => Auth::check() ? 'App\Models\User' : null,
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'url' => app()->runningInConsole() ? 'console' : Request::fullUrl(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);
    }
}
