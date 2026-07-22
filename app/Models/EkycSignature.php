<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * EkycSignature — tanda tangan digital (canvas lokal atau Privy) per sesi eKYC.
 */
class EkycSignature extends Model
{
    use HasFactory;

    protected $table = 'ekyc_signatures';

    const PROVIDER_CANVAS = 'canvas';
    const PROVIDER_PRIVY  = 'privy';

    protected $fillable = [
        'session_id', 'provider', 'image_path', 'document_path',
        'external_ref', 'status', 'raw_response', 'signed_at',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'signed_at'    => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(EkycSession::class, 'session_id');
    }
}
