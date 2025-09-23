<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nose extends Model
{
    protected $table = 'noses';

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
