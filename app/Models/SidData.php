<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SidData extends Model
{
    use HasFactory;

    protected $table = 'sid_data';

    protected $fillable = [
        'user_id',
        'sid_number',
        'ifua_number',
        's_invest_response',
        'generated_at',
    ];

    protected $casts = [
        's_invest_response' => 'array',
        'generated_at'      => 'datetime',
    ];

    // ============================================
    // Relationships
    // ============================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
