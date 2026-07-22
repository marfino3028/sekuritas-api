<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * EkycLog — audit trail immutable tiap pemanggilan provider AI eKYC.
 */
class EkycLog extends Model
{
    use HasFactory;

    protected $table = 'ekyc_logs';

    const UPDATED_AT = null; // log tidak pernah di-update (append-only)

    protected $fillable = [
        'session_id', 'step', 'provider', 'status', 'latency_ms',
        'request_meta', 'response_meta', 'ip',
    ];

    protected $casts = [
        'request_meta'  => 'array',
        'response_meta' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(EkycSession::class, 'session_id');
    }
}
