<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * PaymentController — Konfirmasi pembayaran dan webhook dari payment gateway.
 *
 * Untuk demo: semua pembayaran berhasil tanpa validasi.
 * Di production: webhook akan memverifikasi signature dari payment gateway.
 */
class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private \App\Services\Payment\PaymentGatewayManager $gateways,
    ) {}

    /**
     * Konfirmasi pembayaran oleh user.
     * Untuk demo: apapun kode yang dimasukkan, pembayaran selalu berhasil.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|integer|exists:transactions,id',
            'payment_code'   => 'nullable|string', // Opsional di demo
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user        = JWTAuth::user();
        $transaction = Transaction::where('user_id', $user->id)
            ->findOrFail($request->transaction_id);

        try {
            $result = $this->paymentService->confirmPayment(
                $transaction,
                $request->payment_code
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Webhook endpoint dari payment gateway.
     * Tidak perlu autentikasi JWT — dipanggil oleh payment gateway.
     * Di production: wajib verifikasi signature header.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function webhook(Request $request): JsonResponse
    {
        // Verifikasi keaslian webhook via gateway aktif (Midtrans: signature_key SHA-512).
        $gateway = $this->gateways->gateway();
        if (! $gateway->verifyWebhook($request)) {
            \Illuminate\Support\Facades\Log::warning('[Payment] Webhook signature tidak valid', [
                'gateway' => $gateway->name(),
                'ip'      => $request->ip(),
            ]);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        // Normalisasi payload lintas-gateway → dipetakan ke format processWebhook.
        $parsed  = $gateway->parseWebhook($request);
        $payload = array_merge($request->all(), [
            'order_id' => $parsed['order_ref'],
            'status'   => $parsed['status'],
        ]);

        try {
            $result = $this->paymentService->processWebhook($payload);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
