<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'registration_rank',
        'is_reward_eligible',
        'registered_at',
        'note',
    ];

    protected $casts = [
        'registered_at'      => 'datetime:Y-m-d H:i:s.u', // presisi microsecond
        'is_reward_eligible' => 'boolean',
        'registration_rank'  => 'integer',
    ];

    // ============================================
    // Relationships
    // ============================================

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // Helpers
    // ============================================

    /** Label rank untuk ditampilkan ke user: "Ke-1", "Ke-23", dll */
    public function getRankLabelAttribute(): string
    {
        return "Ke-{$this->registration_rank}";
    }

    /** Apakah masuk top 3 (podium)? */
    public function isPodium(): bool
    {
        return $this->registration_rank <= 3;
    }
}
