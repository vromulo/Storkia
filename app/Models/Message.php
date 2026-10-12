<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'receiver_id',
        'role',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['time'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function getTimeAttribute()
    {
        if (!$this->created_at) return '';
        
        $date = Carbon::parse($this->created_at)->setTimezone('Asia/Manila');
        
        if ($date->isToday()) {
            return $date->format('h:i A');
        } elseif ($date->isYesterday()) {
            return 'Yesterday ' . $date->format('h:i A');
        }
        
        return $date->format('M d, Y h:i A');
    }
}