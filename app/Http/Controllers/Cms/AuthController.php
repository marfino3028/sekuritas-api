<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * CMS AuthController — Autentikasi untuk admin/ops panel.
 * Hanya user dengan role admin_ops atau super_admin yang bisa login ke CMS.
 */
class AuthController extends Controller
{
    /**
     * Login CMS — hanya untuk admin.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $credentials = [
            'email'    => strtolower($request->email),
            'password' => $request->password,
        ];

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $user = JWTAuth::user();

        // Hanya admin yang bisa masuk ke CMS
        if (!$user->isAdmin()) {
            JWTAuth::invalidate();
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak memiliki hak akses ke panel admin.',
            ], 403);
        }

        if ($user->status === User::STATUS_SUSPENDED) {
            JWTAuth::invalidate();
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah disuspend.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login CMS berhasil!',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                ],
            ],
        ]);
    }

    /**
     * Logout CMS.
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // ignore
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Profil admin yang sedang login.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = JWTAuth::user();
        return response()->json([
            'success' => true,
            'data'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }
}
