<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Crown extends Model
{
    protected $table = 'crowns';
    protected $fillable = ['name', 'image', 'product_id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
