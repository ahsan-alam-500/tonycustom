<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderHasPaid extends Model
{
    protected $table = 'order_has_paids';

    protected $fillable = [
        'order_id',
        'amount',
        'method',
        'status',
        'transaction_id',
        'notes',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
