<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth'  => \App\Http\Middleware\Authenticate::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Hitung ulang AUM semua produk setiap hari kerja pukul 16:30 WIB
        // (setelah cut-off redemption jam 13:00, sebelum settlement T+1)
        $schedule->command('nav:update-daily --all')
                 ->weekdays()
                 ->at('16:30')
                 ->timezone('Asia/Jakarta')
                 ->withoutOverlapping()
                 ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Layanan AI eKYC (FastAPI, mis. laptop via Cloudflare Tunnel) tidak terjangkau / error:
        // tampilkan pesan yang jelas (503), bukan "Server Error" 500.
        $aiDown = function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/ekyc/*')) {
                return null;
            }
            \Illuminate\Support\Facades\Log::warning('Layanan AI eKYC gagal: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Layanan verifikasi AI (eKYC) sedang tidak terjangkau. Silakan coba lagi beberapa saat lagi.',
            ], 503);
        };
        // AI menolak gambar (422: wajah tidak terdeteksi, >1 wajah, gambar rusak) → pesan yang bisa
        // ditindaklanjuti nasabah, bukan "layanan tidak terjangkau".
        $aiRejected = function (\Illuminate\Http\Client\RequestException $e, \Illuminate\Http\Request $request) use ($aiDown) {
            if (! $request->is('api/ekyc/*') || $e->response?->status() !== 422) {
                return $aiDown($e, $request);
            }
            $code = (string) ($e->response->json('error') ?? 'invalid_image');
            $isFaceMatch = $request->is('api/ekyc/face-match');
            $message = match ($code) {
                'face_not_found' => $isFaceMatch
                    ? 'Wajah tidak terdeteksi pada foto e-KTP atau swafoto. Gunakan e-KTP asli dengan pas foto yang terlihat jelas, dan swafoto dengan wajah menghadap kamera.'
                    : 'Wajah tidak terdeteksi pada swafoto. Pastikan wajah menghadap kamera, tidak tertutup, dan pencahayaan cukup.',
                'multiple_faces' => 'Terdeteksi lebih dari satu wajah. Pastikan hanya wajah Anda yang terlihat di foto.',
                default          => 'Foto tidak dapat dibaca. Gunakan foto JPG/PNG yang jelas dan tidak terpotong.',
            };
            \Illuminate\Support\Facades\Log::info('AI eKYC menolak gambar', ['code' => $code, 'path' => $request->path()]);

            return response()->json(['success' => false, 'code' => $code, 'message' => $message], 422);
        };
        $exceptions->render(fn (\Illuminate\Http\Client\ConnectionException $e, $request) => $aiDown($e, $request));
        $exceptions->render(fn (\Illuminate\Http\Client\RequestException $e, $request) => $aiRejected($e, $request));
    })->create();
