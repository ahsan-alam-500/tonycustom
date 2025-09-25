<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        $user = Auth::user()->email;
        $orders = Order::with('orderItems')->where('email', $user)->get();
        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Orders fetched successfully',
            'data'=>[
                'orders'=>$orders
            ],
        ]);
    }
}
