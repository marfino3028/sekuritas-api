<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * EkycDocument — hasil OCR dokumen identitas (KTP) dalam satu sesi eKYC.
 */
class EkycDocument extends Model
{
    use HasFactory;

    protected $table = 'ekyc_documents';

    protected $fillable = [
        'session_id', 'type', 'image_path',
        'nik', 'name', 'birth_place', 'birth_date', 'gender', 'address',
        'religion', 'marital_status', 'occupation',
        'raw_ocr', 'ocr_confidence',
        'is_blur', 'is_low_light', 'is_screenshot',
    ];

    protected $casts = [
        'birth_date'    => 'date',
        'raw_ocr'       => 'array',
        'is_blur'       => 'boolean',
        'is_low_light'  => 'boolean',
        'is_screenshot' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(EkycSession::class, 'session_id');
    }
}
