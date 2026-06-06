<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    const TYPE_BOOTH    = 'booth';
    const TYPE_SEMINAR  = 'seminar';
    const TYPE_WEBINAR  = 'webinar';
    const TYPE_ROADSHOW = 'roadshow';
    const TYPE_OTHER    = 'other';

    protected $fillable = [
        'code',
        'name',
        'description',
        'investment_manager',
        'location',
        'event_type',
        'reward_quota',
        'reward_description',
        'max_participants',
        'registered_count',
        'start_at',
        'end_at',
        'is_active',
        'banner_path',
        'created_by',
    ];

    protected $casts = [
        'start_at'       => 'datetime',
        'end_at'         => 'datetime',
        'is_active'      => 'boolean',
        'is_reward_eligible' => 'boolean',
        'registered_count'   => 'integer',
        'reward_quota'       => 'integer',
        'max_participants'   => 'integer',
    ];

    // ============================================
    // Relationships
    // ============================================

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================
    // Scopes
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOngoing($query)
    {
        return $query->where('start_at', '<=', now())
                     ->where('end_at', '>=', now());
    }

    // ============================================
    // Helpers
    // ============================================

    public function isFull(): bool
    {
        if (!$this->max_participants) return false;
        return $this->registered_count >= $this->max_participants;
    }

    public function isOngoing(): bool
    {
        return now()->between($this->start_at, $this->end_at);
    }

    public function isRewardStillAvailable(): bool
    {
        if (!$this->reward_quota) return true;
        return $this->registered_count < $this->reward_quota;
    }

    /** Sisa slot reward untuk user baru */
    public function rewardSlotsRemaining(): ?int
    {
        if (!$this->reward_quota) return null;
        return max(0, $this->reward_quota - $this->registered_count);
    }
}
