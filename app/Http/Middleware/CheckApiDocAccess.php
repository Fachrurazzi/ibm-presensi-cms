<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set timezone to WITA (Kalimantan)
        date_default_timezone_set('Asia/Makassar');
        
        // Set max upload size
        ini_set('upload_max_filesize', '10M');
        ini_set('post_max_size', '10M');

        // ========== GATES FOR PACKAGES ==========
        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('super_admin');
        });
        
        Gate::define('viewApiDocs', function (User $user) {
            return $user->hasRole(['super_admin', 'admin']);
        });
        
        Gate::define('viewHorizon', function (User $user) {
            return $user->hasRole('super_admin');
        });
        
        Gate::define('viewTelescope', function (User $user) {
            return $user->hasRole('super_admin');
        });

        // ========== API RATE LIMITING ==========
        RateLimiter::for('api', function (User $user) {
            return Limit::perMinute(60)->by($user->id);
        });
        
        RateLimiter::for('attendance', function (User $user) {
            return Limit::perMinute(10)->by($user->id);
        });
        
        RateLimiter::for('export', function (User $user) {
            return Limit::perMinute(5)->by($user->id);
        });

        // ========== API RESPONSE MACROS ==========
        JsonResponse::macro('success', function ($data = null, $message = 'Success', $code = 200) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
                'timestamp' => now()->toIso8601String(),
            ], $code);
        });
        
        JsonResponse::macro('error', function ($message = 'Error', $code = 400, $errors = null) {
            $response = [
                'success' => false,
                'message' => $message,
                'timestamp' => now()->toIso8601String(),
            ];
            
            if ($errors) {
                $response['errors'] = $errors;
            }
            
            return response()->json($response, $code);
        });

        // ========== CUSTOM VALIDATION RULES ==========
        Validator::extend('nik', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^\d{16}$/', $value);
        }, 'NIK harus terdiri dari 16 digit angka.');
        
        Validator::extend('phone', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^08[0-9]{8,12}$/', $value);
        }, 'Nomor telepon harus dimulai dengan 08 dan terdiri dari 10-14 digit.');
        
        Validator::extend('latitude', function ($attribute, $value, $parameters, $validator) {
            return is_numeric($value) && $value >= -90 && $value <= 90;
        }, 'Latitude harus antara -90 dan 90.');
        
        Validator::extend('longitude', function ($attribute, $value, $parameters, $validator) {
            return is_numeric($value) && $value >= -180 && $value <= 180;
        }, 'Longitude harus antara -180 dan 180.');

        // ========== SQL LOGGING (LOCAL ONLY) ==========
        if (app()->environment('local')) {
            DB::listen(function ($query) {
                Log::info('SQL Query: ' . $query->sql, [
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms'
                ]);
            });
        }

        // ========== CORS CONFIGURATION ==========
        // Untuk akses dari Flutter mobile
        config(['cors.allowed_origins' => array_merge(
            config('cors.allowed_origins', []),
            [
                'http://localhost:8080',      // Flutter web debug
                'http://localhost:3000',      // React/Vue (jika ada)
                'capacitor://localhost',      // Capacitor app
                'ionic://localhost',          // Ionic app
                'http://localhost:5173',      // Vite dev server
            ]
        )]);
        
        config(['cors.allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']]);
        config(['cors.allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept']]);

        // ========== SCRAMBLE API DOCUMENTATION ==========
        Scramble::configure()
            ->routes(function (Route $route) {
                return Str::startsWith($route->uri, 'api/v1');
            });

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer', 'JWT Authentication')
                    ->description('Masukkan token Bearer yang didapat dari endpoint login')
            );
        });
    }
}