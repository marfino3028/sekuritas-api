<?php

namespace App\Services\Payment\Contracts;

use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * Kontrak payment gateway. Mock, Midtrans, Xendit, dst mengimplementasikan ini
 * sehingga PaymentService/PaymentController tidak berubah saat gateway diganti
 * (config('payment.gateway')).
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Buat instruksi pembayaran (VA/QRIS) untuk sebuah transaksi.
     *
     * @return array{payment_code:string,gateway_ref:string,expired_at:\Carbon\Carbon,raw:array}
     */
    public function createCharge(Transaction $transaction, string $method): array;

    /** Verifikasi keaslian webhook (signature/token). */
    public function verifyWebhook(Request $request): bool;

    /**
     * Normalisasi payload webhook.
     *
     * @return array{order_ref:string,status:string,raw:array}
     *   status: paid|pending|failed
     */
    public function parseWebhook(Request $request): array;
}
