<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            // Send OTP via Email
            Mail::to($request->email)->send(new OtpMail($otp));
            $user->otp = $otp;
            $user->save();
        } else {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'User not found',
            ]);
        }




        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'OTP sent successfully',
            'data' => ['otp' => "Check Your Email"],
        ]);
    }

        /**
     * Step 2: Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|numeric',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->otp != $request->otp) {
            return response()->json([
                'success' => false,
                'status'  => 400,
                'message' => 'Invalid OTP',
            ], 400);
        }

        $user->otp_varified = true;
        $user->save();

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'OTP verified successfully',
        ]);
    }


     public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'password' => 'required|min:6',
        ]);


        // Update password
        $user = User::where('email', $request->email)->first();

        if ($user->otp_varified == true) {

        $user->password = Hash::make($request->password);
        $user->otp_varified = false;
        $user->save();
        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Password reset successfully',
        ]);
        }
        return response()->json([
            'success' => false,
            'status'  => 401,
            'message' => 'Password reset failed',
        ]);
    }
}
