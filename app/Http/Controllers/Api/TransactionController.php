<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MutualFund;
use App\Models\Transaction;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * TransactionController — Pembelian (subscription) dan penjualan (redemption) reksa dana.
 *
 * Aturan Bisnis:
 * - User wajib KYC approved sebelum bertransaksi
 * - User wajib SID aktif sebelum bertransaksi
 * - Nominal min. subscription sesuai ketentuan produk
 * - Redemption hanya bisa dilakukan jika punya unit cukup
 */
class TransactionController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * Buat transaksi pembelian reksa dana (subscription).
     *
     * Flow:
     * 1. Validasi syarat KYC & SID
     * 2. Validasi nominal min. subscription
     * 3. Hitung biaya transaksi
     * 4. Buat transaksi dengan status pending
     * 5. Generate kode pembayaran
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fund_id'        => 'required|integer|exists:mutual_funds,id',
            'amount'         => 'required|numeric|min:10000',
            'payment_method' => 'required|in:va_bca,va_bri,va_mandiri,va_bni,qris',
        ], [
            'amount.min' => 'Nominal pembelian minimal Rp 10.000.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = JWTAuth::user();

        // ============================================
        // Cek syarat bertransaksi
        // ============================================

        if (!$user->isKycApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum bisa bertransaksi. KYC Anda belum disetujui.',
                'hint'    => $user->kyc?->status === 'pending'
                    ? 'KYC sedang dalam proses review.'
                    : 'Silakan lengkapi dan submit data KYC Anda.',
            ], 403);
        }

        if (!$user->isSidActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum bisa bertransaksi. SID (Single Investor ID) belum aktif.',
                'hint'    => $user->sid_status === 'processing'
                    ? 'SID sedang dalam proses aktivasi, harap tunggu.'
                    : 'Hubungi customer service.',
            ], 403);
        }

        $fund   = MutualFund::active()->findOrFail($request->fund_id);
        $amount = (float) $request->amount;

        // Cek minimum pembelian produk
        if ($amount < (float) $fund->min_subscription) {
            return response()->json([
                'success' => false,
                'message' => "Minimal pembelian produk ini adalah Rp " . number_format($fund->min_subscription, 0, ',', '.'),
            ], 400);
        }

        return DB::transaction(function () use ($user, $fund, $amount, $request) {
            // Hitung biaya subscription (dalam Rupiah)
            $feePercent = (float) $fund->subscription_fee;
            $feeAmount  = round($amount * ($feePercent / 100), 2);

            // Buat transaksi
            $transaction = Transaction::create([
                'user_id'        => $user->id,
                'fund_id'        => $fund->id,
                'type'           => Transaction::TYPE_SUBSCRIPTION,
                'amount'         => $amount,
                'fee_amount'     => $feeAmount,
                'status'         => Transaction::STATUS_PENDING,
            ]);

            // Generate kode pembayaran
            $paymentData = $this->paymentService->generatePaymentCode(
                $transaction,
                $request->payment_method
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaksi pembelian berhasil dibuat. Segera lakukan pembayaran.',
                'data'    => [
                    'transaction_id' => $transaction->id,
                    'order_number'   => $transaction->order_number,
                    'fund_name'      => $fund->name,
                    'fund_code'      => $fund->fund_code,
                    'amount'         => $amount,
                    'fee_amount'     => $feeAmount,
                    'total_bayar'    => $amount + $feeAmount,
                    'payment'        => $paymentData,
                    'status'         => Transaction::STATUS_PENDING,
                ],
            ], 201);
        });
    }

    /**
     * Buat transaksi penjualan reksa dana (redemption).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function redeem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fund_id' => 'required|integer|exists:mutual_funds,id',
            'units'   => 'required|numeric|min:0.00000001',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user  = JWTAuth::user();
        $units = (float) $request->units;

        // Cek syarat bertransaksi
        if (!$user->isKycApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum bisa bertransaksi. KYC Anda belum disetujui.',
            ], 403);
        }

        if (!$user->isSidActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum bisa bertransaksi. SID belum aktif.',
            ], 403);
        }

        $fund = MutualFund::active()->findOrFail($request->fund_id);

        // Cek minimum redemption
        if ($units < (float) $fund->min_redemption_unit) {
            return response()->json([
                'success' => false,
                'message' => "Minimal penjualan {$fund->min_redemption_unit} unit.",
            ], 400);
        }

        // Cek kepemilikan unit di portofolio
        $portfolio = $user->portfolios()
            ->where('fund_id', $fund->id)
            ->first();

        if (!$portfolio || (float) $portfolio->total_units < $units) {
            $available = $portfolio ? $portfolio->total_units : 0;
            return response()->json([
                'success' => false,
                'message' => "Unit tidak mencukupi. Unit tersedia: {$available} unit.",
            ], 400);
        }

        return DB::transaction(function () use ($user, $fund, $units) {
            // Hitung nilai redemption berdasarkan NAV terkini
            $navPrice    = (float) $fund->nav_per_unit;
            $amount      = round($units * $navPrice, 2);
            $feePercent  = (float) $fund->redemption_fee;
            $feeAmount   = round($amount * ($feePercent / 100), 2);
            $netAmount   = $amount - $feeAmount;

            $transaction = Transaction::create([
                'user_id'    => $user->id,
                'fund_id'    => $fund->id,
                'type'       => Transaction::TYPE_REDEMPTION,
                'amount'     => $amount,
                'units'      => $units,
                'nav_price'  => $navPrice,
                'fee_amount' => $feeAmount,
                'status'     => Transaction::STATUS_PAID, // Redemption langsung paid
                'notes'      => "Redemption {$units} unit @ NAV {$navPrice}",
            ]);

            // Langsung proses redemption (tidak butuh pembayaran dari user)
            $this->paymentService->processTransaction($transaction);

            return response()->json([
                'success' => true,
                'message' => 'Penjualan reksa dana berhasil diproses.',
                'data'    => [
                    'transaction_id' => $transaction->fresh()->id,
                    'order_number'   => $transaction->order_number,
                    'fund_name'      => $fund->name,
                    'units_sold'     => $units,
                    'nav_price'      => $navPrice,
                    'gross_amount'   => $amount,
                    'fee_amount'     => $feeAmount,
                    'net_diterima'   => $netAmount,
                    'status'         => Transaction::STATUS_SETTLED,
                    'estimated_dana' => '2-3 hari kerja',
                ],
            ], 201);
        });
    }

    /**
     * Daftar transaksi milik user yang sedang login.
     *
     * Query params:
     * - type: subscription|redemption
     * - status: pending|paid|processing|settled|failed
     * - fund_id: filter per produk
     * - per_page: default 15
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user  = JWTAuth::user();
        $query = Transaction::where('user_id', $user->id)
            ->with(['fund:id,fund_code,name,fund_type'])
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('fund_id')) {
            $query->where('fund_id', $request->fund_id);
        }

        $perPage      = min((int) $request->input('per_page', 15), 50);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $transactions->items(),
            'meta'    => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
                'total'        => $transactions->total(),
            ],
        ]);
    }

    /**
     * Detail satu transaksi berdasarkan ID.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $user        = JWTAuth::user();
        $transaction = Transaction::where('user_id', $user->id)
            ->with(['fund', 'payment'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => array_merge($transaction->toArray(), [
                'total_payable'  => $transaction->total_payable,
                'is_expired'     => $transaction->isExpired(),
            ]),
        ]);
    }
}
