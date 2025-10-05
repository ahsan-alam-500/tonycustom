<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PreOrderController extends Controller
{
    public function store(Request $request){
        $request->validate([
            'userId' => 'required|exists:users,id',
            'productId' => 'required|exists:products,id',
            'productQuantity' => 'required|integer|min:1',
            'finalProductPrice' => 'nullable',
        ]);
    }
}
