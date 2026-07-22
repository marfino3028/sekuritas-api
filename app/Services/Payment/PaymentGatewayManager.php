<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Gateways\MidtransGateway;
use App\Services\Payment\Gateways\MockGateway;
use InvalidArgumentException;

/**
 * Resolve gateway pembayaran aktif berdasar config('payment.gateway').
 * Tambah gateway baru (mis. Xendit) cukup daftarkan di sini.
 */
class PaymentGatewayManager
{
    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= config('payment.gateway', 'mock');

        return match ($name) {
            'mock'     => new MockGateway(),
            'midtrans' => new MidtransGateway(),
            default    => throw new InvalidArgumentException("Payment gateway tidak dikenal: {$name}"),
        };
    }
}
