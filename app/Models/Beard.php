<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beard extends Model
{
    protected $table = 'beards';

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
