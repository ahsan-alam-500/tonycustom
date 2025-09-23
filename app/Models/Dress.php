<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dress extends Model
{
    protected $table = 'dresses';

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
