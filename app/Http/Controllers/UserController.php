<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\ActivityLog;

class UserController extends Controller
{

    // List all accounts, 20 per page, sorted by username.
    public function index(Request $request)
    {
        return User::select('id', 'username', 'email', 'role', 'created_at')
            ->when($request->filled('role'), fn($q) => $q->where('role', $request->role))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('username', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('email', 'LIKE', '%' . $request->search . '%');
                });
            })
            ->orderBy('username')
            ->paginate(20);
    }

    // Create a new account with a hashed password.

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'unique:users,username'],
            'email'    => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', Rule::in(['super_admin', 'hr_admin'])],
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'email'    => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
            'must_change_password' => true,
        ]);

        return response()->json([
            'id'       => $user->id,
            'username' => $user->username,
            'email'    => $user->email,
            'role'     => $user->role,
        ], 201);
    }

    // Fix a mistyped username or email. Email stays optional.
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email'    => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $changes = [];
        if ($validated['username'] !== $user->username) {
            $changes[] = "username {$user->username} → {$validated['username']}";
        }
        if (($validated['email'] ?? null) !== $user->email) {
            $changes[] = 'email ' . ($user->email ?: 'none') . ' → ' . ($validated['email'] ?? 'none');
        }

        $user->update([
            'username' => $validated['username'],
            'email'    => $validated['email'] ?? null,
        ]);

        if ($changes) {
            ActivityLog::create([
                'user_id'      => $request->user()->id,
                'action'       => 'user.updated',
                'description'  => "Updated account {$user->username}: " . implode('; ', $changes),
                'subject_type' => 'User',
                'subject_id'   => $user->id,
            ]);
        }

        return response()->json([
            'message' => 'Account updated.',
            'user'    => $user->only(['id', 'username', 'email', 'role']),
        ]);
    }

    // Delete an account. Blocks deleting the currently logged-in user.

    public function destroy(User $user, Request $request)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }
    // Reset a locked-out user's password. Employees get their ID number
    // (same as account creation); admin accounts get a random password.
    // Either way they must change it on next login.
    public function resetPassword(User $user, Request $request)
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot reset your own password here. Use Change Password instead.',
            ], 422);
        }

        $employee = $user->employee;
        $tempPassword = $employee?->id_number ?: Str::random(10);

        $user->password = Hash::make($tempPassword);
        $user->must_change_password = true;
        $user->save();

        // Log out every device still using the old password
        $user->tokens()->delete();

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'user.password_reset',
            'description'  => "Reset password for {$user->username}",
            'subject_type' => 'User',
            'subject_id'   => $user->id,
        ]);

        return response()->json([
            'message'            => "Password reset for {$user->username}.",
            'username'           => $user->username,
            'temporary_password' => $tempPassword,
            'is_id_number'       => (bool) $employee?->id_number,
        ]);
    }
}
