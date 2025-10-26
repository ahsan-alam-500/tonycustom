<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function index()
    {
        $users = User::all()->map(function ($item) {
            $item->type = 'user';
            return $item;
        });

        $subscribers = Subscriber::all()->map(function ($item) {
            $item->type = 'subscriber';
            return $item;
        });

        // Merge both collections
        $all = $users->merge($subscribers);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Users and Subscribers fetched successfully',
            'data'    => $all,
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
