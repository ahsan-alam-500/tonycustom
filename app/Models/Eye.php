<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Eye extends Model
{
    protected $table = 'eyes';

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
