<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseCard extends Model
{
    protected $table = 'base_cards';

    protected $fillable = [
        'name',
        'image',
        'product_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}
