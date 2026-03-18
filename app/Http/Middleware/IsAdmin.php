<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Pastikan user login dan memiliki role super_admin (sesuai spatie/permission)
        if (Auth::check() && Auth::user()->hasRole('super_admin')) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki akses administrator.'
        ], 403);
    }
}
