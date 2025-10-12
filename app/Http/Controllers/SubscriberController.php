<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function index(){
        $users = User::all();
        $subscribers = Subscriber::all();
       return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Subscribers fetched successfully',
            'data'    => ['users' => $users, 'subscribers' => $subscribers],
        ]);
    }

    public function store(Request $request){
        $request->validate([
            'email' => 'required|email|unique:subscribers,email',
        ]);

        $subscriber = Subscriber::create([
            'email' => $request->email,
        ]);

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => 'Subscribed successfully',
            'data'    => ['subscriber' => $subscriber],
        ]);
    }
}
