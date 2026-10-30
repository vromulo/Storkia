<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuditLoggerService
{
    public static function log(
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?string $targetReference = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $actorName = null,
        ?Request $request = null
    ): ?PlatformAuditLog {
        if (! Schema::hasTable('platform_audit_logs')) {
            return null;
        }

        $req = $request ?: request();

        if (! $actorType) {
            if (Auth::guard('admin')->check()) {
                $admin = Auth::guard('admin')->user();
                $actorType = 'Admin';
                $actorId = (int) $admin->id;
                $actorName = $admin->name ?? ($admin->email ?? 'Admin #' . $admin->id);
            } elseif (Auth::guard('rider')->check()) {
                $rider = Auth::guard('rider')->user();
                $actorType = 'Rider';
                $actorId = (int) $rider->id;
                $actorName = $rider->full_name;
            } elseif (Auth::guard('courier')->check()) {
                $courier = Auth::guard('courier')->user();
                $actorType = 'Courier';
                $actorId = (int) $courier->id;
                $actorName = $courier->full_name;
            } elseif (Auth::check()) {
                $user = Auth::user();
                $actorType = $user->role ?: 'User';
                $actorId = (int) $user->id;
                $actorName = $user->full_name ?: $user->email;
            } else {
                $actorType = 'System';
                $actorName = 'Automated System Guard';
            }
        }

        try {
            return PlatformAuditLog::create([
                'actor_type'       => $actorType,
                'actor_id'         => $actorId,
                'actor_name'       => $actorName,
                'ip_address'       => $req?->ip() ?: '127.0.0.1',
                'user_agent'       => substr((string) ($req?->userAgent() ?: 'CLI/System'), 0, 250),
                'action'           => $action,
                'target_type'      => $targetType,
                'target_id'        => $targetId,
                'target_reference' => $targetReference,
                'old_values'       => $oldValues,
                'new_values'       => $newValues,
                'reason'           => $reason ? trim($reason) : null,
                'occurred_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}