<?php

namespace App\Support;

use App\Mail\NotificationMail;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifikasi email ke nasabah di setiap tahap: registrasi, KYC, SID, dan transaksi.
 * Gagal kirim (SMTP mati / belum dikonfigurasi) hanya dicatat di log, proses bisnis tetap jalan.
 */
class Notify
{
    public static function send(User $user, string $subject, array $lines, array $details = [], ?array $cta = null): void
    {
        if (! $user->email) return;

        try {
            Mail::to($user->email)->send(new NotificationMail(
                $subject . ' — ' . config('mail.from.name'),
                $user->name ?: $user->email,
                $lines,
                $details,
                $cta,
            ));
        } catch (\Throwable $e) {
            Log::warning('Gagal kirim email notifikasi: ' . $e->getMessage(), ['user_id' => $user->id, 'subject' => $subject]);
        }
    }

    public static function url(string $path): string
    {
        return rtrim(config('app.frontend_url'), '/') . $path;
    }

    public static function rupiah(float|int|string|null $n): string
    {
        return 'Rp ' . number_format((float) $n, 0, ',', '.');
    }

    public static function welcome(User $user): void
    {
        self::send($user, 'Akun Berhasil Dibuat', [
            'Terima kasih telah mendaftar. Akun Anda sudah aktif.',
            'Langkah berikutnya: masuk ke akun Anda dan lengkapi Pembukaan Rekening (foto e-KTP, swafoto, data diri, dan tanda tangan digital). Setelah diverifikasi, Anda bisa langsung berinvestasi.',
        ], ['Email' => $user->email], ['label' => 'Masuk & Buka Rekening', 'url' => self::url('/login')]);
    }

    public static function kycSubmitted(User $user): void
    {
        self::send($user, 'Pengajuan Pembukaan Rekening Diterima', [
            'Data pembukaan rekening Anda sudah kami terima dan sedang ditinjau oleh tim Operasional.',
            'Anda akan menerima email berikutnya setelah data disetujui dan Single Investor ID (SID) Anda diterbitkan.',
        ], ['Status' => 'Menunggu verifikasi'], ['label' => 'Lihat Status', 'url' => self::url('/dashboard')]);
    }

    public static function kycApproved(User $user): void
    {
        self::send($user, 'Data Pembukaan Rekening Disetujui', [
            'Data pembukaan rekening Anda telah disetujui.',
            'Tim kami sedang mendaftarkan data Anda ke KSEI (S-INVEST) untuk penerbitan Single Investor ID (SID) dan nomor rekening reksa dana (IFUA).',
        ], ['Status' => 'Disetujui — menunggu SID'], ['label' => 'Lihat Status', 'url' => self::url('/dashboard')]);
    }

    public static function kycRejected(User $user, string $reason): void
    {
        self::send($user, 'Data Pembukaan Rekening Perlu Diperbaiki', [
            'Mohon maaf, data pembukaan rekening Anda belum dapat kami setujui.',
            'Silakan perbaiki data sesuai catatan di bawah, lalu kirim ulang pengajuan.',
        ], ['Alasan' => $reason], ['label' => 'Perbaiki Data', 'url' => self::url('/pembukaan-rekening/ekyc')]);
    }

    public static function sidIssued(User $user): void
    {
        self::send($user, 'Rekening Reksa Dana Anda Aktif', [
            'Selamat! Single Investor ID (SID) dan nomor rekening reksa dana (IFUA) Anda telah terbit dari KSEI.',
            'Rekening Anda sudah aktif dan siap digunakan untuk membeli reksa dana.',
        ], [
            'SID'  => (string) $user->sid_number,
            'IFUA' => (string) $user->ifua_number,
        ], ['label' => 'Mulai Investasi', 'url' => self::url('/produk')]);
    }

    public static function subscriptionCreated(Transaction $t, array $payment): void
    {
        $t->loadMissing('fund', 'user');
        $details = [
            'No. Order'   => (string) $t->order_number,
            'Produk'      => (string) $t->fund?->name,
            'Nominal'     => self::rupiah($t->amount),
            'Biaya'       => self::rupiah($t->fee_amount),
            'Total Bayar' => self::rupiah((float) $t->amount + (float) $t->fee_amount),
        ];
        $methods = ['va_bca' => 'Virtual Account BCA', 'va_bri' => 'Virtual Account BRI', 'va_mandiri' => 'Virtual Account Mandiri',
                    'va_bni' => 'Virtual Account BNI', 'qris' => 'QRIS'];
        if (! empty($payment['method']))       $details['Metode Bayar']    = $methods[$payment['method']] ?? (string) $payment['method'];
        if (! empty($payment['payment_code'])) $details['Kode Pembayaran'] = (string) $payment['payment_code'];
        if (! empty($payment['expired_at']))   $details['Bayar Sebelum']   = \Illuminate\Support\Carbon::parse($payment['expired_at'])
            ->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') . ' WIB';

        self::send($t->user, 'Instruksi Pembayaran Pembelian Reksa Dana', [
            'Pesanan pembelian reksa dana Anda sudah kami terima. Segera selesaikan pembayaran sebelum batas waktu.',
        ], $details, ['label' => 'Lihat Transaksi', 'url' => self::url('/transaksi')]);
    }

    public static function paymentReceived(Transaction $t): void
    {
        $t->loadMissing('fund', 'user');
        self::send($t->user, 'Pembayaran Diterima', [
            'Pembayaran pembelian reksa dana Anda sudah kami terima.',
            'Order Anda diteruskan ke S-INVEST (KSEI) dan bank kustodian. Unit penyertaan masuk ke portofolio setelah diproses dengan NAB hari bursa yang berlaku.',
        ], [
            'No. Order' => (string) $t->order_number,
            'Produk'    => (string) $t->fund?->name,
            'Nominal'   => self::rupiah($t->amount),
            'Status'    => 'Dibayar — menunggu proses S-INVEST',
        ], ['label' => 'Lihat Transaksi', 'url' => self::url('/transaksi')]);
    }

    public static function redemptionReceived(Transaction $t): void
    {
        $t->loadMissing('fund', 'user');
        self::send($t->user, 'Permintaan Penjualan Diterima', [
            'Permintaan penjualan kembali (redemption) unit reksa dana Anda sudah kami terima dan akan diteruskan ke S-INVEST (KSEI).',
            'Nilai akhir dihitung dengan NAB hari bursa saat order diproses. Dana dikirim ke rekening bank terdaftar Anda.',
        ], [
            'No. Order' => (string) $t->order_number,
            'Produk'    => (string) $t->fund?->name,
            'Unit'      => number_format((float) $t->units, 4, ',', '.'),
            'Estimasi'  => self::rupiah($t->amount),
        ], ['label' => 'Lihat Transaksi', 'url' => self::url('/transaksi')]);
    }

    public static function transactionSettled(Transaction $t): void
    {
        $t->loadMissing('fund', 'user');
        $buy = $t->type === Transaction::TYPE_SUBSCRIPTION;
        self::send($t->user, $buy ? 'Pembelian Reksa Dana Berhasil' : 'Penjualan Reksa Dana Berhasil', [
            $buy
                ? 'Pembayaran Anda telah diterima dan unit penyertaan sudah masuk ke portofolio Anda.'
                : 'Penjualan kembali (redemption) unit reksa dana Anda telah diproses. Dana akan ditransfer ke rekening bank terdaftar.',
        ], [
            'No. Order'  => (string) $t->order_number,
            'Produk'     => (string) $t->fund?->name,
            'Nominal'    => self::rupiah($t->amount),
            'Unit'       => number_format((float) $t->units, 4, ',', '.'),
            'NAB / Unit' => number_format((float) $t->nav_price, 4, ',', '.'),
        ], ['label' => 'Lihat Portofolio', 'url' => self::url('/portofolio')]);
    }
}
