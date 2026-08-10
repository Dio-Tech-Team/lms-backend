<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::select('id', 'username', 'email', 'password', 'role')
            ->where('email', $credentials['email'])
            ->with('employee:id,user_id')
            ->first();
        // $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
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
        ]);
    }
}
