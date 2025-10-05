<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreOrderMapper extends Model
{
    protected $table = 'pre_order_mappers';
    protected $fillable = [
        'userId',
        'productId',
        'productQuantity',
        'FinalProduct'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
