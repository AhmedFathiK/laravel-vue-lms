<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Requests\TokenRequest;
use App\Http\Requests\RegisterRequest;

class TokenController extends Controller
{
    /**
     * Generate API token for authentication
     */
    public function createToken(TokenRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $abilities = $request->getAbilities();
        $expiresAt = $request->getExpiresAt();

        $token = $user->createToken($request->device_name, $abilities, $expiresAt);

        return response()->json([
            'message' => 'Token created successfully',
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * Register user and create token
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign default user role
        $user->assignRole('user');

        $deviceName = $request->input('device_name', 'API Registration');
        $token = $user->createToken($deviceName);

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token->plainTextToken,
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ], 201);
    }

    /**
     * Revoke current token
     */
    public function revokeToken(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token revoked successfully'
        ]);
    }

    /**
     * Revoke all tokens for the user
     */
    public function revokeAllTokens(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'All tokens revoked successfully'
        ]);
    }

    /**
     * Get all tokens for the user
     */
    public function getTokens(Request $request)
    {
        $tokens = $request->user()->tokens()->select('id', 'name', 'last_used_at', 'created_at')->get();

        return response()->json([
            'tokens' => $tokens
        ]);
    }

    /**
     * Revoke specific token by ID
     */
    public function revokeSpecificToken(Request $request, $tokenId)
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (!$token) {
            return response()->json([
                'message' => 'Token not found'
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Token revoked successfully'
        ]);
    }

    /**
     * Get authenticated user (for API)
     */
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'current_token' => [
                'id' => $request->user()->currentAccessToken()->id,
                'name' => $request->user()->currentAccessToken()->name,
                'last_used_at' => $request->user()->currentAccessToken()->last_used_at,
            ]
        ]);
    }
}
