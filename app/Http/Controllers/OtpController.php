<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OtpController extends Controller
{
    public function otpSender(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $otp = rand(1000, 9999);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $user->otp = $otp;
            $user->save();
        }else {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'User not found',
            ]);
        }

        // Send OTP via Email
        Mail::to($request->email)->send(new OtpMail($otp));

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'OTP sent successfully',
            'data' => ['otp' => "Check Your Email"],
        ]);
    }
}
