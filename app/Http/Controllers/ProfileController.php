<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function profile($id)
    {
        $user = User::find($id);
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Profile fetched successfully',
            'data' => ['user' => $user]
        ], 200);
    }
}
