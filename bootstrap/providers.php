<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    Tymon\JWTAuth\Providers\LaravelServiceProvider::class,
];
