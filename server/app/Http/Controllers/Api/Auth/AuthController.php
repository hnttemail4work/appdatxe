<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $mode = $request->input('register_mode', 'customer');

        if (! in_array($mode, ['customer', 'driver'], true)) {
            return response()->json(['message' => 'Invalid register_mode. Use customer or driver.'], 422);
        }

        $rules = match ($mode) {
            'driver' => $this->registration->driverRules(),
            default  => $this->registration->customerRules(),
        };

        $validated = $request->validate(array_merge($rules, [
            'register_mode' => ['required', Rule::in(['customer', 'driver'])],
        ]));

        $user = $mode === 'driver'
            ? $this->registration->registerDriver($validated)
            : $this->registration->registerCustomer($validated);

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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
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
            'user' => $request->user()->load(['merchantProfile', 'driverProfile', 'vehicles', 'bookings']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
