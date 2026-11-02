<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Connexion sécurisée multi-client (Web et Mobile).
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants de connexion invalides.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Ce compte utilisateur a été désactivé.',
            ], Response::HTTP_FORBIDDEN);
        }

        $deviceName = $request->input('device_name', 'Web Application');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'organization' => $user->organization ? [
                        'id' => $user->organization->id,
                        'name' => $user->organization->name,
                        'slug' => $user->organization->slug,
                    ] : null,
                    'roles' => $user->roles->pluck('slug'),
                ],
            ],
        ]);
    }

    /**
     * Profil de l'utilisateur connecté.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['organization', 'roles.permissions', 'employee']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'organization' => $user->organization,
                'employee' => $user->employee,
                'roles' => $user->roles->pluck('slug'),
                'permissions' => $user->hasRole('super_admin') 
                    ? ['*'] 
                    : $user->roles->flatMap->permissions->pluck('slug')->unique()->values(),
            ],
        ]);
    }

    /**
     * Déconnexion et révocation du token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion effectuée avec succès.',
        ]);
    }
}

