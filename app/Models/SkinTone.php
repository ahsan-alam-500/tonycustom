<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkinTone extends Model
{
    protected $table = 'skin_tones';
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
