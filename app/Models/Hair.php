<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hair extends Model
{
    protected $table = 'hairs';

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
