<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Daftar exception yang tidak perlu di-report.
     */
    protected $dontReport = [
        //
    ];

    /**
     * Daftar input yang tidak perlu di-flash saat validasi gagal.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register exception handlers.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render exception sebagai HTTP response.
     * Selalu return JSON untuk API request.
     */
    public function render($request, Throwable $e)
    {
        // Untuk semua request ke /api, kembalikan JSON
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Format error response untuk API.
     */
    private function renderApiException($request, Throwable $e)
    {
        // Model tidak ditemukan
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return response()->json([
                'success' => false,
                'message' => "Data {$model} tidak ditemukan.",
            ], 404);
        }

        // Route tidak ditemukan
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint tidak ditemukan.',
            ], 404);
        }

        // Unauthenticated
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            ], 401);
        }

        // Validasi gagal
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        }

        // HTTP exception (403, 429, dll.)
        if ($e instanceof HttpException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'HTTP Error.',
            ], $e->getStatusCode());
        }

        // Semua exception lainnya — jangan leak detail di production
        $debug   = config('app.debug');
        $message = $debug ? $e->getMessage() : 'Terjadi kesalahan internal. Silakan coba beberapa saat lagi.';

        return response()->json([
            'success' => false,
            'message' => $message,
            'error'   => $debug ? [
                'class' => get_class($e),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ] : null,
        ], 500);
    }
}
