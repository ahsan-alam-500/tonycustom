<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mouth extends Model
{
    protected $table = 'mouths';

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
