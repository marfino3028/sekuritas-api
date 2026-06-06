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
    public function __construct(private PaymentService $paymentService) {}

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
        // Di production: verifikasi signature
        // $signature = $request->header('X-Signature');
        // if (!$this->verifyWebhookSignature($signature, $request->getContent())) {
        //     return response()->json(['error' => 'Invalid signature'], 401);
        // }

        $payload = $request->all();

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
