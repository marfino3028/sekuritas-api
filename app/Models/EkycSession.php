<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * EkycSession — satu sesi verifikasi identitas otomatis (eKYC).
 * Menjadi "payung" bagi dokumen OCR, selfie, tanda tangan, dan hasil akhir.
 */
class EkycSession extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ekyc_sessions';

    // Tahapan sesi (urut sesuai business flow)
    const STATUS_CREATED         = 'created';
    const STATUS_OCR_DONE        = 'ocr_done';
    const STATUS_SELFIE_DONE     = 'selfie_done';
    const STATUS_LIVENESS_PASSED = 'liveness_passed';
    const STATUS_FACE_MATCHED    = 'face_matched';
    const STATUS_SIGNED          = 'signed';
    const STATUS_VERIFIED        = 'verified';
    const STATUS_REJECTED        = 'rejected';
    const STATUS_EXPIRED         = 'expired';

    protected $fillable = [
        'id', 'user_id', 'status', 'provider', 'score',
        'auto_approved', 'reject_reason', 'meta', 'expires_at', 'completed_at',
    ];

    protected $casts = [
        'meta'          => 'array',
        'auto_approved' => 'boolean',
        'expires_at'    => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function document()
    {
        return $this->hasOne(EkycDocument::class, 'session_id');
    }

    public function selfie()
    {
        return $this->hasOne(EkycSelfie::class, 'session_id');
    }

    public function signature()
    {
        return $this->hasOne(EkycSignature::class, 'session_id');
    }

    public function result()
    {
        return $this->hasOne(EkycResult::class, 'session_id');
    }

    public function logs()
    {
        return $this->hasMany(EkycLog::class, 'session_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
