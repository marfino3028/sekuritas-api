<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * EkycResult — ringkasan skor & keputusan akhir sebuah sesi eKYC.
 */
class EkycResult extends Model
{
    use HasFactory;

    protected $table = 'ekyc_results';

    const DECISION_APPROVED = 'approved';
    const DECISION_REVIEW   = 'review';
    const DECISION_REJECTED = 'rejected';

    protected $fillable = [
        'session_id', 'ocr_score', 'liveness_score', 'face_match_score',
        'final_score', 'decision', 'flags',
    ];

    protected $casts = [
        'flags' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(EkycSession::class, 'session_id');
    }
}
