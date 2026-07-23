<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kyc extends Model
{
    use HasFactory;

    protected $table = 'kyc';

    // ============================================
    // Konstanta Status KYC
    // ============================================
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'nik',
        'mother_maiden_name',
        'birth_date',
        'gender',
        'marital_status',
        'education',
        'occupation',
        'income_level',
        'source_of_fund',
        'investment_objective',
        'address',
        'province',
        'city',
        'postal_code',
        'ktp_photo_path',
        'selfie_photo_path',
        'npwp_photo_path',
        'bank_book_photo_path',
        'signature_path',
        'paraf_path',
        'employment',
        'additional_info',
        'status',
        'rejected_reason',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'birth_date'      => 'date:Y-m-d',
        'reviewed_at'     => 'datetime',
        'submitted_at'    => 'datetime',
        'employment'      => 'array',
        'additional_info' => 'array',
    ];

    // ============================================
    // Relationships (Relasi)
    // ============================================

    /**
     * Pemilik data KYC.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin yang melakukan review KYC.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ============================================
    // Scopes
    // ============================================

    /**
     * Scope hanya menampilkan KYC yang menunggu review.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope hanya menampilkan KYC yang sudah disetujui.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    // ============================================
    // Helper Methods
    // ============================================

    /**
     * Cek apakah dokumen foto KTP sudah diupload.
     */
    public function hasKtpPhoto(): bool
    {
        return !empty($this->ktp_photo_path);
    }

    /**
     * Cek apakah selfie sudah diupload.
     */
    public function hasSelfiePhoto(): bool
    {
        return !empty($this->selfie_photo_path);
    }

    /**
     * Cek apakah semua dokumen sudah lengkap untuk disubmit.
     */
    public function isDocumentComplete(): bool
    {
        return $this->hasKtpPhoto() && $this->hasSelfiePhoto();
    }
}
