<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Development mode: lebih mudah debug
        if (app()->environment('local') && !Auth::check()) {
            abort(403, 'Unauthorized - Admin access required');
        }

        // Cek apakah user login
        if (!Auth::check()) {
            Log::warning('Unauthorized admin access attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Silakan login terlebih dahulu.'
                ], 401);
            }

            return redirect('/admin/login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $user = Auth::user();

        // Cache role check untuk performa (5 menit)
        $hasAdminRole = cache()->remember(
            'user_admin_role_' . $user->id,
            300,
            fn() => $user->hasRole(['super_admin', 'admin'])
        );

        if ($hasAdminRole) {
            return $next($request);
        }

        // Log akses ditolak
        Log::warning('Admin access denied', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->getRoleNames(),
            'ip' => $request->ip(),
            'url' => $request->fullUrl()
        ]);

        // Response berdasarkan tipe request
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses administrator.',
                'code' => 403
            ], 403);
        }

        // Redirect untuk web request
        return redirect('/admin')->with('error', 'Anda tidak memiliki akses ke halaman tersebut.');
    }
}
