<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RiskProfileController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Cms\AuthController as CmsAuthController;
use App\Http\Controllers\Cms\EventController as CmsEventController;
use App\Http\Controllers\Cms\KycController as CmsKycController;
use App\Http\Controllers\Cms\ProductController as CmsProductController;
use App\Http\Controllers\Cms\UserController as CmsUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Sekuritas Demo Platform Reksa Dana
|--------------------------------------------------------------------------
|
| Prefix semua route dengan /api (dari RouteServiceProvider)
|
*/

// ============================================================
// HEALTH CHECK
// ============================================================
Route::get('/health', fn () => response()->json([
    'status'  => 'ok',
    'service' => config('app.name'),
    'version' => '1.0.0',
    'time'    => now()->toIso8601String(),
]));

// ============================================================
// AUTH — Publik (tidak perlu token)
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('/send-otp',    [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp',  [AuthController::class, 'verifyOtp']);
    Route::post('/register',    [AuthController::class, 'register']);
    Route::post('/login',       [AuthController::class, 'login']);
});

// ============================================================
// PRODUK — Publik (tidak perlu token, untuk landing page)
// ============================================================
Route::prefix('products')->group(function () {
    Route::get('/',                [ProductController::class, 'index']);
    Route::get('/managers',        [ProductController::class, 'managers']); // sebelum /{id}
    Route::get('/{id}',            [ProductController::class, 'show']);
    Route::get('/{id}/nav-history',[ProductController::class, 'navHistory']);
});

// ============================================================
// RISK PROFILE QUESTIONS — Publik
// ============================================================
Route::get('/risk-profile/questions', [RiskProfileController::class, 'getQuestions']);

// ============================================================
// EVENTS — Publik (cari & lihat leaderboard)
// ============================================================
Route::prefix('events')->group(function () {
    Route::get('/',                        [EventController::class, 'index']);
    Route::get('/{code}',                  [EventController::class, 'findByCode']);
    Route::get('/{code}/leaderboard',      [EventController::class, 'leaderboard']);
});

// ============================================================
// WEBHOOK PAYMENT — Tidak perlu JWT (dipanggil oleh gateway)
// ============================================================
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

// ============================================================
// AUTHENTICATED USER ROUTES
// ============================================================
Route::middleware(['auth:api'])->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me',           [AuthController::class, 'me']);

    // --------------------------------------------------------
    // KYC
    // --------------------------------------------------------
    Route::prefix('kyc')->group(function () {
        Route::get('/',        [KycController::class, 'show']);
        Route::post('/upload', [KycController::class, 'uploadDocument']);
        Route::post('/submit', [KycController::class, 'submit']);
    });

    // --------------------------------------------------------
    // PROFIL RISIKO
    // --------------------------------------------------------
    Route::prefix('risk-profile')->group(function () {
        Route::get('/',        [RiskProfileController::class, 'show']);
        Route::post('/submit', [RiskProfileController::class, 'submit']);
    });

    // --------------------------------------------------------
    // TRANSAKSI REKSA DANA
    // --------------------------------------------------------
    Route::prefix('transactions')->group(function () {
        Route::get('/',          [TransactionController::class, 'index']);
        Route::get('/{id}',      [TransactionController::class, 'show']);
        Route::post('/subscribe',[TransactionController::class, 'subscribe']);
        Route::post('/redeem',   [TransactionController::class, 'redeem']);
    });

    // --------------------------------------------------------
    // PEMBAYARAN
    // --------------------------------------------------------
    Route::prefix('payment')->group(function () {
        Route::post('/confirm', [PaymentController::class, 'confirm']);
    });

    // --------------------------------------------------------
    // PORTOFOLIO
    // --------------------------------------------------------
    Route::prefix('portfolio')->group(function () {
        Route::get('/',        [PortfolioController::class, 'index']);
        Route::get('/summary', [PortfolioController::class, 'summary']);
    });

    // --------------------------------------------------------
    // EVENTS — Daftar (butuh login)
    // --------------------------------------------------------
    Route::prefix('events')->group(function () {
        Route::post('/register',        [EventController::class, 'register']);
        Route::get('/my-registrations', [EventController::class, 'myRegistrations']);
    });

});

// ============================================================
// CMS — ADMIN ROUTES (membutuhkan role admin_ops/super_admin)
// ============================================================
Route::prefix('cms')->middleware(['auth:api', 'admin'])->group(function () {

    // CMS Auth
    Route::post('/auth/login',  [CmsAuthController::class, 'login'])->withoutMiddleware(['auth:api', 'admin']);
    Route::post('/auth/logout', [CmsAuthController::class, 'logout']);
    Route::get('/auth/me',      [CmsAuthController::class, 'me']);

    // --------------------------------------------------------
    // KYC Management
    // --------------------------------------------------------
    Route::prefix('kyc')->group(function () {
        Route::get('/',               [CmsKycController::class, 'index']);
        Route::get('/{id}',           [CmsKycController::class, 'show']);
        Route::put('/{id}/approve',   [CmsKycController::class, 'approve']);
        Route::put('/{id}/reject',    [CmsKycController::class, 'reject']);
    });

    // --------------------------------------------------------
    // User Management
    // --------------------------------------------------------
    Route::prefix('users')->group(function () {
        Route::get('/',              [CmsUserController::class, 'index']);
        Route::get('/{id}',          [CmsUserController::class, 'show']);
        Route::put('/{id}/status',   [CmsUserController::class, 'updateStatus']);
    });

    // --------------------------------------------------------
    // Product Management (CRUD + NAV/AUM)
    // --------------------------------------------------------
    Route::prefix('products')->group(function () {
        Route::get('/',              [CmsProductController::class, 'index']);
        Route::post('/nav-bulk',     [CmsProductController::class, 'updateNavBulk']); // bulk update sebelum /{id}
        Route::get('/{id}',          [CmsProductController::class, 'show']);
        Route::post('/',             [CmsProductController::class, 'store']);
        Route::put('/{id}',          [CmsProductController::class, 'update']);
        Route::delete('/{id}',       [CmsProductController::class, 'destroy']);
        Route::put('/{id}/nav',      [CmsProductController::class, 'updateNav']);
    });

    // --------------------------------------------------------
    // Event Management (CRUD + leaderboard)
    // --------------------------------------------------------
    Route::prefix('events')->group(function () {
        Route::get('/',                    [CmsEventController::class, 'index']);
        Route::post('/',                   [CmsEventController::class, 'store']);
        Route::get('/{id}',                [CmsEventController::class, 'show']);
        Route::put('/{id}',                [CmsEventController::class, 'update']);
        Route::delete('/{id}',             [CmsEventController::class, 'destroy']);
        Route::put('/{id}/toggle',         [CmsEventController::class, 'toggle']);
        Route::get('/{id}/leaderboard',    [CmsEventController::class, 'leaderboard']);
        Route::get('/{id}/export',         [CmsEventController::class, 'export']);
    });
});
