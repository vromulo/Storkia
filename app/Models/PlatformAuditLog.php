<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAuditLog extends Model
{
    protected $table = 'platform_audit_logs';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'actor_name',
        'ip_address',
        'user_agent',
        'action',
        'target_type',
        'target_id',
        'target_reference',
        'old_values',
        'new_values',
        'reason',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values'  => 'array',
            'new_values'  => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}