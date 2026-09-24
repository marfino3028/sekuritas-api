<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * AdminMiddleware — Pastikan user yang mengakses CMS adalah staf.
 *
 *   'admin'                       → semua role staf (super_admin, admin, admin_ops, finance)
 *   'admin:super_admin,admin'     → hanya role yang disebut
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak memiliki hak akses admin.',
            ], 403);
        }

        if ($roles && !in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Role Anda tidak memiliki akses ke menu ini.',
            ], 403);
        }

        return $next($request);
    }
}
