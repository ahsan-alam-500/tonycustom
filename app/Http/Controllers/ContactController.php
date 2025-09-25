<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index()
    {
        $contacts = contact::orderBy('id', 'desc')->get();
        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Contact fetched successfully',
            'data'=>[
                'contacts'=>$contacts
            ],
        ])
    }
}
