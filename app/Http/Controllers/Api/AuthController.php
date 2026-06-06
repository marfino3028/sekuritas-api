<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * AuthController — Autentikasi via Phone + OTP + PIN (flow seperti Makmur.id)
 *
 * Flow registrasi:
 * 1. POST /auth/send-otp   → masukkan nomor HP, dapatkan OTP
 * 2. POST /auth/verify-otp → verifikasi OTP (validasi saja, belum login)
 * 3. POST /auth/register   → phone + otp + pin → buat akun, dapat token
 *
 * Flow login:
 * 1. POST /auth/send-otp   → masukkan nomor HP, dapatkan OTP
 * 2. POST /auth/login      → phone + pin → dapat token
 */
class AuthController extends Controller
{
    /**
     * Kirim OTP ke nomor HP.
     * Di demo: OTP dikembalikan langsung dalam response.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:9|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor HP tidak valid',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        // DEMO: OTP selalu 123456 agar mudah ditest
        // Di production: ganti dengan mt_rand dan kirim via SMS
        $otp = '123456';

        // Simpan di cache selama 10 menit (key by phone)
        Cache::put("otp_phone_{$phone}", $otp, now()->addMinutes(10));

        // Di production: kirim via SMS gateway
        // SmsService::send($phone, "Kode OTP Sekuritas: {$otp}");

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP telah dikirim.',
            'data'    => [
                'phone'    => $phone,
                // DEMO ONLY: hapus di production
                'otp_demo' => $otp,
            ],
        ]);
    }

    /**
     * Verifikasi OTP (hanya validasi, belum membuat akun/login).
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp'   => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);
        $cachedOtp = Cache::get("otp_phone_{$phone}");

        if (!$cachedOtp) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP sudah expired. Minta kode baru.',
            ], 400);
        }

        if ($cachedOtp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP salah.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP berhasil diverifikasi.',
        ]);
    }

    /**
     * Registrasi akun baru via phone + OTP + PIN.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:9|max:15',
            'otp'   => 'required|string|size:6',
            'pin'   => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        // Cek apakah nomor sudah terdaftar dan aktif
        $existingUser = User::where('phone', $phone)->first();
        if ($existingUser && $existingUser->status === User::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor HP sudah terdaftar. Silakan login.',
            ], 409);
        }

        // Verifikasi OTP
        $cachedOtp = Cache::get("otp_phone_{$phone}");
        if (!$cachedOtp || $cachedOtp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak valid atau sudah expired.',
            ], 400);
        }

        // Buat atau update user
        $user = User::updateOrCreate(
            ['phone' => $phone],
            [
                'password'           => Hash::make($request->pin),
                'role'               => User::ROLE_USER,
                'status'             => User::STATUS_ACTIVE,
                'phone_verified_at'  => Carbon::now(),
            ]
        );

        // Hapus OTP dari cache
        Cache::forget("otp_phone_{$phone}");

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dibuat!',
            'data'    => [
                'token' => $token,
                'user'  => $this->formatUser($user),
            ],
        ], 201);
    }

    /**
     * Login via phone + PIN.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'pin'   => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $user = User::where('phone', $phone)->first();

        if (!$user || !Hash::check($request->pin, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor HP atau PIN salah.',
            ], 401);
        }

        if ($user->status === User::STATUS_SUSPENDED) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah disuspend. Hubungi customer service.',
            ], 403);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil!',
            'data'    => [
                'token' => $token,
                'user'  => $this->formatUser($user),
            ],
        ]);
    }

    /**
     * Profil user yang sedang login.
     */
    public function me(): JsonResponse
    {
        $user = JWTAuth::user()->load(['kyc', 'riskProfile', 'sidData']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user, true),
        ]);
    }

    /**
     * Logout — invalidate JWT token.
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // Token sudah expired/invalid
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Normalisasi nomor HP ke format 628xxx.
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }
        return $phone;
    }

    /**
     * Format data user untuk response API.
     */
    private function formatUser(User $user, bool $withDetails = false): array
    {
        $data = [
            'id'                  => $user->id,
            'name'                => $user->name,
            'phone'               => $user->phone,
            'email'               => $user->email,
            'role'                => $user->role,
            'status'              => $user->status,
            'sid_status'          => $user->sid_status,
            'sid_number'          => $user->sid_number,
            'ifua_number'         => $user->ifua_number,
            'risk_profile_result' => $user->risk_profile_result,
            'kyc_status'          => $user->kyc?->status ?? null,
            'can_transact'        => $user->canTransact(),
            'created_at'          => $user->created_at,
        ];

        if ($withDetails) {
            $data['kyc']          = $user->kyc;
            $data['risk_profile'] = $user->riskProfile;
        }

        return $data;
    }
}
