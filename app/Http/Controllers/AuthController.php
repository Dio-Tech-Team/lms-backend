<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'], // username or email
            'password' => ['required'],
        ]);

        $user = User::select('id', 'username', 'email', 'password', 'role', 'must_change_password')
            ->where('username', $credentials['login'])
            ->orWhere('email', $credentials['login'])
            ->with('employee:id,user_id')
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
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
    // public function resendVerification(Request $request)
    // {
    //     $request->validate(['email' => 'required|email']);
    //     $user = User::where('email', $request->email)->first();

    //     if (!$user) {
    //         return response()->json(['message' => 'User not found.'], 404);
    //     }

    //     // This method is provided by the MustVerifyEmail trait
    //     $user->sendEmailVerificationNotification();

    //     return response()->json(['message' => 'Verification link sent!']);
    // }

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
}
