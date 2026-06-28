<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\AuthIdentifier;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AuthController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge(
            $this->registration->driverRules(),
            ['register_mode' => ['required', Rule::in(['driver'])]],
        ));

        try {
            $user = $this->registration->registerDriver($validated, $request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $token = $user->status === 'active' ? $user->createToken('api-token')->plainTextToken : null;

        return response()->json([
            'message' => $user->status === 'active'
                ? 'Registered successfully.'
                : 'Registered successfully. Account pending approval.',
            'user' => $user->load(['merchantProfile', 'driverProfile']),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = AuthIdentifier::findUserByLogin($validated['login']);

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->load(['merchantProfile', 'driverProfile']),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load(['merchantProfile', 'driverProfile', 'vehicles']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
