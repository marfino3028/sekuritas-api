<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Portfolio;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentService — Mock integrasi payment gateway.
 *
 * Di production, service ini akan terhubung ke payment gateway seperti
 * Midtrans, Doku, atau Xendit untuk memproses pembayaran Virtual Account,
 * QRIS, dan metode pembayaran lainnya.
 *
 * Untuk demo: semua pembayaran selalu berhasil.
 */
class PaymentService
{
    /**
     * Generate kode pembayaran (Virtual Account / kode unik) untuk transaksi.
     *
     * @param Transaction $transaction
     * @param string      $method Metode pembayaran: va_bca, va_bri, va_mandiri, qris, dll.
     * @return array Data pembayaran yang di-generate
     */
    public function generatePaymentCode(Transaction $transaction, string $method = 'va_bca'): array
    {
        // Simulasi kode VA: prefix bank + 10 digit acak
        $prefixMap = [
            'va_bca'     => '1260',
            'va_bri'     => '8855',
            'va_mandiri' => '8889',
            'va_bni'     => '8808',
            'qris'       => 'QR',
        ];

        $prefix      = $prefixMap[$method] ?? '9999';
        $paymentCode = $prefix . str_pad(mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);

        // Masa berlaku pembayaran (default 24 jam)
        $expiryHours   = (int) config('app.payment_expiry_hours', 24);
        $expiredAt     = Carbon::now()->addHours($expiryHours);

        // Buat referensi gateway (mock)
        $gatewayRef = 'MOCK-' . strtoupper($method) . '-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));

        // Buat record payment
        $payment = Payment::create([
            'transaction_id'   => $transaction->id,
            'gateway_ref'      => $gatewayRef,
            'method'           => $method,
            'amount'           => $transaction->amount + $transaction->fee_amount,
            'status'           => Payment::STATUS_PENDING,
            'gateway_response' => [
                'mock'         => true,
                'payment_code' => $paymentCode,
                'method'       => $method,
                'amount'       => $transaction->amount + $transaction->fee_amount,
                'expired_at'   => $expiredAt->toIso8601String(),
                'instructions' => $this->getPaymentInstructions($method, $paymentCode),
            ],
        ]);

        // Update transaksi dengan kode pembayaran
        $transaction->update([
            'payment_method'    => $method,
            'payment_code'      => $paymentCode,
            'payment_expired_at'=> $expiredAt,
        ]);

        Log::info("[Payment Mock] Kode pembayaran di-generate", [
            'transaction_id' => $transaction->id,
            'order_number'   => $transaction->order_number,
            'method'         => $method,
            'payment_code'   => $paymentCode,
            'amount'         => $transaction->amount,
        ]);

        return [
            'payment_id'    => $payment->id,
            'gateway_ref'   => $gatewayRef,
            'method'        => $method,
            'payment_code'  => $paymentCode,
            'amount'        => $transaction->amount + $transaction->fee_amount,
            'expired_at'    => $expiredAt,
            'instructions'  => $this->getPaymentInstructions($method, $paymentCode),
            'mock'          => true,
        ];
    }

    /**
     * Konfirmasi pembayaran.
     *
     * Untuk demo: pembayaran selalu berhasil tanpa validasi apapun.
     * Di production: akan memverifikasi webhook dari payment gateway.
     *
     * @param Transaction $transaction
     * @param string|null $paymentCode Kode pembayaran dari user (opsional, selalu berhasil di demo)
     * @return array Hasil konfirmasi pembayaran
     * @throws \Exception
     */
    public function confirmPayment(Transaction $transaction, ?string $paymentCode = null): array
    {
        if ($transaction->status === Transaction::STATUS_SETTLED) {
            throw new \Exception('Transaksi sudah settled.');
        }

        if ($transaction->status === Transaction::STATUS_FAILED) {
            throw new \Exception('Transaksi sudah gagal dan tidak bisa dikonfirmasi.');
        }

        if ($transaction->isExpired()) {
            $transaction->update(['status' => Transaction::STATUS_FAILED]);
            throw new \Exception('Transaksi sudah expired. Silakan buat transaksi baru.');
        }

        return DB::transaction(function () use ($transaction) {
            $confirmedAt = Carbon::now();

            // Update status payment
            $payment = $transaction->payment;
            if ($payment) {
                $payment->update([
                    'status'  => Payment::STATUS_SUCCESS,
                    'paid_at' => $confirmedAt,
                    'gateway_response' => array_merge($payment->gateway_response ?? [], [
                        'confirmed_at' => $confirmedAt->toIso8601String(),
                        'confirmed_by' => 'mock_gateway',
                        'mock'         => true,
                    ]),
                ]);
            }

            // Update status transaksi ke paid, lalu proses
            $transaction->update([
                'status'               => Transaction::STATUS_PAID,
                'payment_confirmed_at' => $confirmedAt,
            ]);

            // Langsung proses alokasi unit (di production bisa pakai queue/job)
            $this->processTransaction($transaction);

            Log::info("[Payment Mock] Pembayaran dikonfirmasi", [
                'transaction_id' => $transaction->id,
                'order_number'   => $transaction->order_number,
                'amount'         => $transaction->amount,
            ]);

            return [
                'success'        => true,
                'transaction_id' => $transaction->id,
                'order_number'   => $transaction->order_number,
                'status'         => Transaction::STATUS_SETTLED,
                'confirmed_at'   => $confirmedAt,
                'message'        => 'Pembayaran berhasil dikonfirmasi dan unit telah dialokasikan.',
            ];
        });
    }

    /**
     * Proses transaksi setelah pembayaran dikonfirmasi.
     * Alokasi unit ke portofolio investor.
     *
     * @param Transaction $transaction
     */
    public function processTransaction(Transaction $transaction): void
    {
        $transaction->update(['status' => Transaction::STATUS_PROCESSING]);

        $fund       = $transaction->fund;
        $navPrice   = $fund->nav_per_unit; // Gunakan NAV terkini (T+1 dalam production)
        $units      = round($transaction->amount / $navPrice, 8);
        $settledAt  = Carbon::now();

        // Update data transaksi: isi unit & NAV yang digunakan
        $transaction->update([
            'nav_price'  => $navPrice,
            'units'      => $units,
            'status'     => Transaction::STATUS_SETTLED,
            'settled_at' => $settledAt,
        ]);

        // Alokasi ke portofolio
        if ($transaction->type === Transaction::TYPE_SUBSCRIPTION) {
            $this->allocateSubscription($transaction, $units, $navPrice);
        } elseif ($transaction->type === Transaction::TYPE_REDEMPTION) {
            $this->allocateRedemption($transaction, $units);
        }

        Log::info("[Payment] Transaksi berhasil diproses", [
            'transaction_id' => $transaction->id,
            'order_number'   => $transaction->order_number,
            'units'          => $units,
            'nav_price'      => $navPrice,
        ]);
    }

    /**
     * Alokasi unit ke portofolio nasabah setelah subscription settle.
     */
    private function allocateSubscription(Transaction $transaction, float $units, float $navPrice): void
    {
        $portfolio = Portfolio::firstOrCreate(
            [
                'user_id' => $transaction->user_id,
                'fund_id' => $transaction->fund_id,
            ],
            [
                'total_units'    => 0,
                'avg_nav'        => 0,
                'current_value'  => 0,
                'unrealized_gain'=> 0,
                'total_invested' => 0,
            ]
        );

        $portfolio->addUnits($units, $navPrice, (float) $transaction->amount);
        $portfolio->updateMarketValue((float) $transaction->fund->nav_per_unit);
    }

    /**
     * Kurangi unit dari portofolio nasabah setelah redemption settle.
     */
    private function allocateRedemption(Transaction $transaction, float $units): void
    {
        $portfolio = Portfolio::where('user_id', $transaction->user_id)
            ->where('fund_id', $transaction->fund_id)
            ->first();

        if ($portfolio) {
            $portfolio->removeUnits($units, (float) $transaction->amount);
            $portfolio->updateMarketValue((float) $transaction->fund->nav_per_unit);
        }
    }

    /**
     * Proses webhook dari payment gateway.
     * (Mock: langsung konfirmasi transaksi berdasarkan gateway_ref)
     *
     * @param array $payload Data webhook dari payment gateway
     * @return array
     */
    public function processWebhook(array $payload): array
    {
        // Di production: validasi signature webhook
        $gatewayRef = $payload['order_id'] ?? $payload['gateway_ref'] ?? null;

        if (!$gatewayRef) {
            return ['success' => false, 'message' => 'gateway_ref tidak ditemukan dalam payload'];
        }

        $payment = Payment::where('gateway_ref', $gatewayRef)->first();
        if (!$payment) {
            return ['success' => false, 'message' => "Payment dengan ref {$gatewayRef} tidak ditemukan"];
        }

        if ($payment->status === Payment::STATUS_SUCCESS) {
            return ['success' => true, 'message' => 'Pembayaran sudah diproses sebelumnya'];
        }

        return $this->confirmPayment($payment->transaction);
    }

    /**
     * Dapatkan instruksi pembayaran berdasarkan metode.
     */
    private function getPaymentInstructions(string $method, string $paymentCode): array
    {
        $instructions = [
            'va_bca' => [
                'title'  => 'Virtual Account BCA',
                'steps'  => [
                    "1. Buka aplikasi BCA Mobile atau ATM BCA",
                    "2. Pilih menu Transfer > Virtual Account",
                    "3. Masukkan nomor VA: {$paymentCode}",
                    "4. Pastikan nominal sesuai, lalu konfirmasi",
                    "5. Simpan bukti pembayaran Anda",
                ],
            ],
            'va_bri' => [
                'title'  => 'Virtual Account BRI',
                'steps'  => [
                    "1. Buka aplikasi BRImo atau ATM BRI",
                    "2. Pilih menu Pembayaran > BRIVA",
                    "3. Masukkan nomor VA: {$paymentCode}",
                    "4. Pastikan nominal sesuai, lalu konfirmasi",
                    "5. Simpan bukti pembayaran Anda",
                ],
            ],
            'va_mandiri' => [
                'title'  => 'Virtual Account Mandiri',
                'steps'  => [
                    "1. Buka Livin' by Mandiri atau ATM Mandiri",
                    "2. Pilih menu Bayar > Multi Payment",
                    "3. Masukkan kode perusahaan dan nomor VA: {$paymentCode}",
                    "4. Pastikan nominal sesuai, lalu konfirmasi",
                    "5. Simpan bukti pembayaran Anda",
                ],
            ],
            'qris' => [
                'title'  => 'QRIS',
                'steps'  => [
                    "1. Buka aplikasi e-wallet (GoPay, OVO, DANA, dll.)",
                    "2. Scan QR Code yang ditampilkan",
                    "3. Pastikan nominal sesuai",
                    "4. Konfirmasi pembayaran",
                ],
            ],
        ];

        return $instructions[$method] ?? [
            'title' => 'Instruksi Pembayaran',
            'steps' => ["Gunakan kode: {$paymentCode} untuk melakukan pembayaran"],
        ];
    }
}
