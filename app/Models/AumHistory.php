<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AumHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'fund_id',
        'aum_date',
        'nav_per_unit',
        'total_units',
        'total_aum',
        'investor_count',
    ];

    protected $casts = [
        'aum_date'      => 'date',
        'nav_per_unit'  => 'decimal:4',
        'total_units'   => 'decimal:8',
        'total_aum'     => 'decimal:2',
        'investor_count'=> 'integer',
    ];

    public function fund()
    {
        return $this->belongsTo(MutualFund::class, 'fund_id');
    }
}
