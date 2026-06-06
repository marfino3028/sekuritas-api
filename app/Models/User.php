<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    // ============================================
    // Konstanta Role
    // ============================================
    const ROLE_USER        = 'user';
    const ROLE_ADMIN_OPS   = 'admin_ops';
    const ROLE_SUPER_ADMIN = 'super_admin';

    // ============================================
    // Konstanta Status Akun
    // ============================================
    const STATUS_PENDING   = 'pending';
    const STATUS_ACTIVE    = 'active';
    const STATUS_SUSPENDED = 'suspended';

    // ============================================
    // Konstanta Status SID
    // ============================================
    const SID_NOT_GENERATED = 'not_generated';
    const SID_PROCESSING    = 'processing';
    const SID_ACTIVE        = 'active';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'email_verified_at',
        'phone_verified_at',
        'sid_status',
        'sid_number',
        'ifua_number',
        'risk_profile_result',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    // ============================================
    // JWT Interface Methods
    // ============================================

    /**
     * Get the identifier that will be stored in the JWT subject claim.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role'   => $this->role,
            'status' => $this->status,
        ];
    }

    // ============================================
    // Relationships (Relasi)
    // ============================================

    /**
     * Relasi ke data KYC nasabah.
     */
    public function kyc()
    {
        return $this->hasOne(Kyc::class);
    }

    /**
     * Relasi ke data profil risiko.
     */
    public function riskProfile()
    {
        return $this->hasOne(RiskProfile::class)->latest();
    }

    /**
     * Relasi ke data SID dari S-INVEST.
     */
    public function sidData()
    {
        return $this->hasOne(SidData::class);
    }

    /**
     * Relasi ke semua transaksi nasabah.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Relasi ke portofolio nasabah.
     */
    public function portfolios()
    {
        return $this->hasMany(Portfolio::class);
    }

    // ============================================
    // Accessors & Helper Methods
    // ============================================

    /**
     * Cek apakah nasabah sudah KYC approved.
     */
    public function isKycApproved(): bool
    {
        return $this->kyc && $this->kyc->status === Kyc::STATUS_APPROVED;
    }

    /**
     * Cek apakah SID nasabah sudah aktif.
     */
    public function isSidActive(): bool
    {
        return $this->sid_status === self::SID_ACTIVE;
    }

    /**
     * Cek apakah nasabah boleh bertransaksi.
     */
    public function canTransact(): bool
    {
        return $this->isKycApproved() && $this->isSidActive();
    }

    /**
     * Cek apakah user adalah admin (ops atau super admin).
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN_OPS, self::ROLE_SUPER_ADMIN]);
    }
}
