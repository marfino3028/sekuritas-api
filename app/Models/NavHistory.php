<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NavHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'fund_id',
        'nav_date',
        'nav_per_unit',
        'nav_change',
        'nav_change_pct',
    ];

    protected $casts = [
        'nav_date'      => 'date',
        'nav_per_unit'  => 'decimal:4',
        'nav_change'    => 'decimal:4',
        'nav_change_pct'=> 'decimal:4',
    ];

    public function fund()
    {
        return $this->belongsTo(MutualFund::class, 'fund_id');
    }
}
