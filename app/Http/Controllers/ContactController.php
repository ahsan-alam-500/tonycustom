<?php

namespace App\Http\Controllers;

use App\Mail\contactMail;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        if($contact){
          $sent =  Mail::to('contact@momentocardgames.com')->send(new contactMail($request->name, $request->email, $request->sub, $request->mes));
        }

        if($sent){
            Log::info('Email sent successfully');
        }

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
