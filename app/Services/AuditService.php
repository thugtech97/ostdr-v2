<?php

namespace App\Services;

use App\Models\Audit;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * Record a manual audit entry in the same shape the legacy app writes,
     * so the existing audit-log reports keep working across both apps.
     */
    public function create(Request $request, string $action, string $event): Audit
    {
        return Audit::create([
            'user_type' => 'App\User',
            'user_id' => $request->user()?->id ?? 1,
            'event' => $event,
            'auditable_type' => '',
            'auditable_id' => 0,
            'old_values' => [],
            'new_values' => ['action' => $action],
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('user-agent'),
        ]);
    }
}
