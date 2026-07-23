<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * EkycSelfie — foto wajah untuk liveness & face match dalam satu sesi eKYC.
 */
class EkycSelfie extends Model
{
    use HasFactory;

    protected $table = 'ekyc_selfies';

    protected $fillable = [
        'session_id', 'image_path',
        'liveness_passed', 'liveness_score', 'is_printed_photo', 'is_replay',
        'ktp_detected', 'nik_in_photo', 'nik_match', 'id_face_match', 'id_face_match_score',
        'face_matched', 'face_match_score', 'face_embedding',
    ];

    protected $casts = [
        'liveness_passed'  => 'boolean',
        'is_printed_photo' => 'boolean',
        'is_replay'        => 'boolean',
        'ktp_detected'     => 'boolean',
        'nik_match'        => 'boolean',
        'id_face_match'    => 'boolean',
        'face_matched'     => 'boolean',
        'face_embedding'   => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(EkycSession::class, 'session_id');
    }
}
