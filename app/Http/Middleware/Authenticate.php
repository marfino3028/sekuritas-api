<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Middleware autentikasi. Aplikasi ini berbasis API (JWT), jadi request yang
 * tidak terautentikasi TIDAK di-redirect ke halaman login melainkan menerima
 * respons 401 JSON.
 */
class Authenticate extends Middleware
{
    /**
     * Tujuan redirect saat belum login. Null → tidak redirect (kembalikan 401).
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
