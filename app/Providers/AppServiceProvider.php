<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind services
        $this->app->singleton(
            \App\Services\SInvestService::class,
            \App\Services\SInvestService::class
        );

        $this->app->singleton(
            \App\Services\PaymentService::class,
            \App\Services\PaymentService::class
        );

        // Modul eKYC — manager provider (adapter pattern) & orchestrator
        $this->app->singleton(\App\Services\Ekyc\EkycManager::class);
        $this->app->singleton(\App\Services\Ekyc\SignatureService::class);
        $this->app->singleton(\App\Services\Ekyc\EkycService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
