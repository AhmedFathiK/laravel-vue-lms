<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Requests\Auth\TokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Mobile\MobileUserResource;

class TokenController extends Controller
{
    /**
     * Generate API token for authentication (Mobile App)
     */
    public function createToken(TokenRequest $request)
    {
        $user = User::with(['roles', 'permissions', 'entitlements.features'])
            ->where('email', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $deviceName = $request->input('device_name', 'Mobile App');
        $abilities = $request->getAbilities() ?? ['*'];
        $expiresAt = $request->getExpiresAt();

        $token = $user->createToken($deviceName, $abilities, $expiresAt);

        return response()->json([
            'message' => 'Token created successfully',
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new MobileUserResource($user),
        ]);
    }

    /**
     * Register user and create token (Mobile App)
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign default user role
        $user->assignRole('student');

        $deviceName = $request->input('device_name', 'Mobile App Registration');
        $token = $user->createToken($deviceName);

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new MobileUserResource($user),
        ], 201);
    }

    /**
     * Logout: Revoke current token only
     */
    public function logout(Request $request)
    {
        // Revoke only the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out from mobile device successfully'
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
     * Get authenticated user (Mobile App)
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => new MobileUserResource($request->user()),
        ]);
    }
}
