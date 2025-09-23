<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
        'short_description',
        'description',
        'price',
        'offer_price',
        'category_id',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

}
