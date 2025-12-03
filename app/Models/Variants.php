<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variants extends Model
{
    protected $connection = 'mysql-sklep';
    protected $table = 'product_variants';
    protected $fillable = [
        'name', 'slug', 'price', 'oryginal_price', 'ean', 'wee', 'weight', 'product_sheet_url', 'safety_sheet_url', 'manual_url', 'chemical_info', 'oryginal_url', 'selling_price', 'sku', 'in_stock'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
}
