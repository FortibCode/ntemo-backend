<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBelongsToOrganization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Le super admin peut agir avec ou sans organisation spécifique
        if ($user && $user->hasRole('super_admin')) {
            return $next($request);
        }

        if (!$user || !$user->organization_id || !$user->organization || !$user->organization->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Organisation invalide ou inactive.',
                'error_code' => 'ORGANIZATION_INACTIVE_OR_INVALID',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

