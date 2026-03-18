<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class CheckApiDocAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Cek apakah user TIDAK login
        if (!Auth::check()) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        // 2. Cek apakah user yang login memiliki role super_admin
        // Kita gunakan Auth::user() karena sudah dipastikan login di langkah 1
        if (Auth::user()->hasRole('super_admin')) {
            return $next($request);
        }

        // 3. Jika login tapi bukan super_admin
        abort(403, "Anda tidak memiliki izin untuk mengakses halaman ini.");
    }
}
