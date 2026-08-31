<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$m6I06P9rc5X02eY1Bws2Pe7SdjLCrSoNXoHUE0QwPmiRY8fZ5uWqO';

    private const MOBILE_TOKEN_ABILITIES = [
        'bookmarks:read',
        'bookmarks:write',
    ];

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::where('email', $email)->first();
        $passwordMatches = Hash::check(
            $validated['password'],
            $user?->password ?? self::DUMMY_PASSWORD_HASH,
        );

        if ($user === null || ! $passwordMatches) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = $user->createToken('mobile', self::MOBILE_TOKEN_ABILITIES)->plainTextToken;

        return response()->json(['data' => [
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => self::MOBILE_TOKEN_ABILITIES,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
