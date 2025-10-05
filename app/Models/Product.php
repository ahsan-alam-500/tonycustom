<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
        'type',
        'short_description',
        'description',
        'price',
        'status',
        'offer_price',
        'category_id',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductHasImage::class);
    }

    public function skin_tones()
    {
        return $this->hasMany(SkinTone::class);
    }

    public function hairs()
    {
        return $this->hasMany(Hair::class);
    }

    public function noses()
    {
        return $this->hasMany(Nose::class);
    }

    public function eyes()
    {
        return $this->hasMany(Eye::class);
    }

    public function mouths()
    {
        return $this->hasMany(Mouth::class);
    }

    public function dresses()
    {
        return $this->hasMany(Dress::class);
    }

    public function crowns()
    {
        return $this->hasMany(Crown::class);
    }

    public function base_cards()
    {
        return $this->hasMany(BaseCard::class);
    }

    public function beards()
    {
        return $this->hasMany(Beard::class);
    }

    public function trading_fronts()
    {
        return $this->hasMany(TradingFront::class);
    }

    public function trading_backs()
    {
        return $this->hasMany(TradingBack::class);
    }

    public function PreOrderMapper()
    {
        return $this->hasMany(PreOrderMapper::class);
    }



}
