<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prices extends Model
{
    use HasFactory;
    protected $table = 'prices';
    protected $fillable = [
        'exchangeRate', 'profit_to_50euro', 'profit_to_100euro', 'profit_to_200euro', 'profit_to_500euro', 'profit_above_50euro'
    ];
}
