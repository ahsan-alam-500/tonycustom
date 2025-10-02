<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingBack extends Model
{
    protected $table = 'trading_backs';
    protected $fillable = ['name', 'product_id', 'image'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
