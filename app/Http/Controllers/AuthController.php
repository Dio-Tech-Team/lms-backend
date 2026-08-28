<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $user = User::select('id', 'username', 'email', 'password', 'role', 'must_change_password', 'email_verified_at')
            ->where('username', $credentials['login'])
            ->orWhere('email', $credentials['login'])
            ->with('employee:id,user_id')
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->role === 'employee' && $user->email && !$user->email_verified_at) {
            $this->generateAndSendOtp($user);

            return response()->json([
                'otp_required' => true,
                'message' => 'A verification code has been sent to your email.',
                'user_id' => $user->id,
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'employee_id' => $user->employee ? $user->employee->id : null,
                'must_change_password' => $user->must_change_password,
            ]
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = User::select('id', 'username', 'email', 'role', 'must_change_password', 'otp_code', 'otp_expires_at')
            ->with('employee:id,user_id')
            ->find($request->user_id);

        if (!$user || !$user->otp_code) {
            throw ValidationException::withMessages([
                'otp' => ['No verification code found. Please log in again.'],
            ]);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp' => ['This code has expired. Please log in again to request a new one.'],
            ]);
        }

        if ($request->otp !== $user->otp_code) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code you entered is incorrect.'],
            ]);
        }

        $user->update([
            'email_verified_at' => now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'employee_id' => $user->employee ? $user->employee->id : null,
                'must_change_password' => $user->must_change_password,
            ]
        ]);
    }
    public function resendOtp(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $user = User::select('id', 'email', 'role', 'email_verified_at')->find($request->user_id);

        if (!$user || !$user->email || $user->email_verified_at) {
            throw ValidationException::withMessages([
                'user_id' => ['Unable to resend verification code.'],
            ]);
        }

        $this->generateAndSendOtp($user);

        return response()->json([
            'message' => 'A new verification code has been sent to your email.',
        ]);
    }
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
    public function logout(Request $request)
    {
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return response()->json([
            'message'
            => 'Logged out successfully'

        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'       => $user->id,
            'username' => $user->username,
            'email'    => $user->email,
            'role'     => $user->role,
            'employee_id' => $user->employee ? $user->employee->id : null,
            'must_change_password' => $user->must_change_password,
        ]);
    }

    private function generateAndSendOtp(User $user)
    {
        $otp = random_int(100000, 999999); // 6-digit code

        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        Mail::raw("Your LeaveSync verification code is: {$otp}\nThis code expires in 10 minutes.", function ($msg) use ($user) {
            $msg->to($user->email)->subject('LeaveSync Email Verification Code');
        });
    }
}
