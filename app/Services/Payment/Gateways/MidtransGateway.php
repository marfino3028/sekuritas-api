<?php

namespace App\Services\Payment\Gateways;

use App\Models\Transaction;
use App\Services\Payment\Contracts\PaymentGateway;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * MidtransGateway — Core API (sandbox/production).
 * VA (bank_transfer) & QRIS. Verifikasi webhook via signature_key SHA-512.
 * Aktif bila PAYMENT_GATEWAY=midtrans + MIDTRANS_SERVER_KEY diisi.
 */
class MidtransGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'midtrans';
    }

    private function serverKey(): string
    {
        $key = (string) config('payment.midtrans.server_key');
        if ($key === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diset.');
        }
        return $key;
    }

    public function createCharge(Transaction $transaction, string $method): array
    {
        $orderId = $transaction->order_number ?: ('VS-' . $transaction->id . '-' . time());
        $gross   = (int) round(($transaction->amount + $transaction->fee_amount));

        $payload = ['transaction_details' => ['order_id' => $orderId, 'gross_amount' => $gross]];

        if ($method === 'qris') {
            $payload['payment_type'] = 'qris';
            $payload['qris'] = ['acquirer' => 'gopay'];
        } else {
            $bank = str_replace('va_', '', $method) ?: 'bca';
            $payload['payment_type'] = 'bank_transfer';
            $payload['bank_transfer'] = ['bank' => $bank];
        }

        $res = Http::withBasicAuth($this->serverKey(), '')
            ->acceptJson()
            ->post(rtrim(config('payment.midtrans.base_url'), '/') . '/v2/charge', $payload)
            ->throw()
            ->json();

        // Ekstrak kode pembayaran sesuai metode
        $code = $res['va_numbers'][0]['va_number']
            ?? $res['permata_va_number']
            ?? $res['qr_string']
            ?? ($res['actions'][0]['url'] ?? null);

        return [
            'payment_code' => (string) $code,
            'gateway_ref'  => (string) ($res['transaction_id'] ?? $orderId),
            'expired_at'   => isset($res['expiry_time'])
                ? Carbon::parse($res['expiry_time'])
                : Carbon::now()->addHours((int) config('payment.expiry_hours', 24)),
            'raw'          => $res,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $orderId    = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $gross      = (string) $request->input('gross_amount');
        $signature  = (string) $request->input('signature_key');

        $expected = hash('sha512', $orderId . $statusCode . $gross . $this->serverKey());

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): array
    {
        $trxStatus = (string) $request->input('transaction_status');
        $fraud     = (string) $request->input('fraud_status', 'accept');

        $status = match ($trxStatus) {
            'capture'    => $fraud === 'challenge' ? 'pending' : 'paid',
            'settlement' => 'paid',
            'pending'    => 'pending',
            default      => 'failed', // deny, cancel, expire, failure
        };

        return [
            'order_ref' => (string) $request->input('order_id'),
            'status'    => $status,
            'raw'       => $request->all(),
        ];
    }
}
