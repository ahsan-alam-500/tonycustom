<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index()
    {
        $contacts = Contact::orderBy('id', 'desc')->get();
        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Contact fetched successfully',
            'data'=>[
                'contacts'=>$contacts
            ],
        ]);
    }

    public function store(Request $request)
    {
        $contact = Contact::create($request->all());
        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Contact sent successfully',
            'data'=>[
                'contact'=>$contact
            ],
        ]);
    }

    public function destroy($id){
        $contact = Contact::findOrFail($id);
        $contact->delete();
        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Contact deleted successfully',
        ]);
    }


}
