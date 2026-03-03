<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\SendOtpMail;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OtpController extends Controller
{
    public function sendOtp(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            $user = User::where('email', $request->email)->first();

            // dd($user);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $otp = rand(100000, 999999);

            Otp::create([
                'user_id' => $user->id,
                'otp' => $otp,
                'expired_at' => now()->addMinutes(1),
            ]);

            Mail::to($user->email)->send(new SendOtpMail($otp));
            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'OTP sent successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while sending OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function verifyOtp(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp'   => 'required|digits:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            $otpData = Otp::where('user_id', $user->id)
                ->where('otp', $request->otp)
                ->where('expired_at', '>', now())
                ->latest()
                ->first();

            if (!$otpData) {
                return response()->json([
                    'status' => false,
                    'message' => 'OTP salah atau kedaluwarsa'
                ], 400);
            }

            // Generate token sekali pakai
            $session_token = bin2hex(random_bytes(32));

            // Simpan token ini
            $otpData->session_token = $session_token;
            $otpData->expired_at = now()->addMinutes(10);
            $otpData->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'OTP valid',
                'otp_token' => $session_token
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error verifying OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $token = $request->header('X-OTP-TOKEN');

        if (!$token) {
            return response()->json([
                'status' => false,
                'message' => 'OTP token is missing'
            ], 401);
        }

        $otpData = Otp::where('session_token', $token)
            ->where('expired_at', '>', now())
            ->first();

        if (!$otpData) {
            return response()->json([
                'status' => false,
                'message' => 'Sesi reset password kedaluwarsa, silakan minta OTP lagi.'
            ], 401);
        }

        $user = User::find($otpData->user_id);

        // Update password
        $user->password = bcrypt($request->password);
        $user->save();

        // Hapus OTP session setelah dipakai
        $otpData->delete();

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully'
        ]);
    }
}
