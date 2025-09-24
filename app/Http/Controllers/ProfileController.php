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

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        $user->update($request->all());
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Profile updated successfully',
            'data' => ['user' => $user]
        ], 200);
    }
}
