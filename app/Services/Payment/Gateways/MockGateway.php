<?php

namespace App\Services\Payment\Gateways;

use App\Models\Transaction;
use App\Services\Payment\Contracts\PaymentGateway;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * MockGateway — simulasi lokal (default). Selalu menghasilkan kode VA/QRIS
 * dan menerima webhook tanpa signature (khusus demo/UAT internal).
 */
class MockGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mock';
    }

    public function createCharge(Transaction $transaction, string $method): array
    {
        $prefixMap = [
            'va_bca' => '1260', 'va_bri' => '8855', 'va_mandiri' => '8889',
            'va_bni' => '8808', 'qris' => 'QR',
        ];
        $prefix = $prefixMap[$method] ?? '9999';
        $code   = $prefix . str_pad((string) mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);

        return [
            'payment_code' => $code,
            'gateway_ref'  => 'MOCK-' . strtoupper($method) . '-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4)),
            'expired_at'   => Carbon::now()->addHours((int) config('payment.expiry_hours', 24)),
            'raw'          => ['mock' => true, 'method' => $method, 'payment_code' => $code],
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        return true; // demo: tanpa signature
    }

    public function parseWebhook(Request $request): array
    {
        return [
            'order_ref' => (string) $request->input('order_id', $request->input('order_ref', '')),
            'status'    => (string) $request->input('status', 'paid'),
            'raw'       => $request->all(),
        ];
    }
}
