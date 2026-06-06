<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskProfile extends Model
{
    use HasFactory;

    // ============================================
    // Konstanta Hasil Profil Risiko
    // ============================================
    const RESULT_CONSERVATIVE          = 'conservative';
    const RESULT_MODERATE_CONSERVATIVE = 'moderate_conservative';
    const RESULT_MODERATE              = 'moderate';
    const RESULT_MODERATE_AGGRESSIVE   = 'moderate_aggressive';
    const RESULT_AGGRESSIVE            = 'aggressive';

    protected $fillable = [
        'user_id',
        'answers',
        'result',
        'score',
    ];

    protected $casts = [
        'answers' => 'array',
        'score'   => 'integer',
    ];

    // ============================================
    // Relationships
    // ============================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // Static: Deskripsi profil risiko
    // ============================================

    /**
     * Deskripsi rekomendasi produk berdasarkan profil risiko.
     */
    public static function getDescription(string $result): array
    {
        $map = [
            self::RESULT_CONSERVATIVE => [
                'label'       => 'Konservatif',
                'description' => 'Anda lebih menyukai keamanan modal dengan return stabil. Cocok untuk reksa dana Pasar Uang.',
                'recommended' => ['money_market'],
            ],
            self::RESULT_MODERATE_CONSERVATIVE => [
                'label'       => 'Moderat Konservatif',
                'description' => 'Anda dapat menerima sedikit risiko untuk return lebih baik. Reksa Dana Pendapatan Tetap sesuai.',
                'recommended' => ['money_market', 'fixed_income'],
            ],
            self::RESULT_MODERATE => [
                'label'       => 'Moderat',
                'description' => 'Anda seimbang antara pertumbuhan dan keamanan. Reksa Dana Campuran adalah pilihan tepat.',
                'recommended' => ['fixed_income', 'balanced'],
            ],
            self::RESULT_MODERATE_AGGRESSIVE => [
                'label'       => 'Moderat Agresif',
                'description' => 'Anda siap menanggung fluktuasi demi potensi return tinggi. Reksa Dana Saham cocok untuk Anda.',
                'recommended' => ['balanced', 'equity'],
            ],
            self::RESULT_AGGRESSIVE => [
                'label'       => 'Agresif',
                'description' => 'Anda memiliki toleransi risiko tinggi dan mengincar pertumbuhan maksimal. Reksa Dana Saham.',
                'recommended' => ['equity'],
            ],
        ];

        return $map[$result] ?? $map[self::RESULT_CONSERVATIVE];
    }

    /**
     * Tentukan hasil profil risiko berdasarkan total skor.
     */
    public static function calculateResult(int $score): string
    {
        if ($score < 20) return self::RESULT_CONSERVATIVE;
        if ($score < 40) return self::RESULT_MODERATE_CONSERVATIVE;
        if ($score < 60) return self::RESULT_MODERATE;
        if ($score < 80) return self::RESULT_MODERATE_AGGRESSIVE;
        return self::RESULT_AGGRESSIVE;
    }
}
