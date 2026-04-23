<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserLocationController extends Controller
{
    /**
     * Response formatter yang konsisten.
     */
    private function jsonResponse(
        bool $success,
        string $message,
        mixed $data = null,
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * GET /user-locations/all - Get all users with last location (Admin only)
     */
    public function getAllUserLocations(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Check role
        if (!$user->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        try {
            $perPage = min((int) $request->query('per_page', 50), 100);
            $search = $request->query('search');
            $onlineOnly = $request->boolean('online_only', false);

            $query = User::with('position')
                ->select([
                    'id',
                    'name',
                    'email',
                    'position_id',
                    'image',
                    'last_latitude',
                    'last_longitude',
                    'last_location_at',
                ])
                ->whereNotNull('email_verified_at');

            // Filter by search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter online only
            if ($onlineOnly) {
                $fiveMinutesAgo = now()->subMinutes(5);
                $query->where('last_location_at', '>=', $fiveMinutesAgo);
            }

            $users = $query->orderBy('name')->paginate($perPage);

            $data = $users->through(function ($user) {
                return [
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'email'             => $user->email,
                    'position_name'     => $user->position?->name,
                    'avatar_url'        => $user->avatar_url,
                    'last_latitude'     => $user->last_latitude,
                    'last_longitude'    => $user->last_longitude,
                    'last_location_at'  => $user->last_location_at?->toISOString(),
                    'is_online'         => $this->isUserOnline($user->last_location_at),
                    'last_seen'         => $this->getLastSeenText($user->last_location_at),
                ];
            });

            // Summary statistics
            $totalUsers = User::whereNotNull('email_verified_at')->count();
            $onlineUsers = User::whereNotNull('email_verified_at')
                ->where('last_location_at', '>=', now()->subMinutes(5))
                ->count();
            $activeToday = User::whereNotNull('email_verified_at')
                ->whereDate('last_location_at', today())
                ->count();

            return $this->jsonResponse(true, 'Data lokasi user berhasil dimuat', [
                'data' => $data,
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'per_page'     => $users->perPage(),
                    'total'        => $users->total(),
                    'last_page'    => $users->lastPage(),
                ],
                'summary' => [
                    'total_users'   => $totalUsers,
                    'online_users'  => $onlineUsers,
                    'active_today'  => $activeToday,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get all user locations error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->jsonResponse(false, 'Gagal mengambil data lokasi', null, 500);
        }
    }

    /**
     * GET /user-locations/team - Get team locations (Manager only)
     */
    public function getTeamLocations(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Check role
        if (!$user->hasRole(['super_admin', 'admin', 'manager'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        try {
            $perPage = min((int) $request->query('per_page', 50), 100);
            $search = $request->query('search');
            $onlineOnly = $request->boolean('online_only', false);

            $query = User::with('position')
                ->select([
                    'id',
                    'name',
                    'email',
                    'position_id',
                    'image',
                    'last_latitude',
                    'last_longitude',
                    'last_location_at',
                ])
                ->whereNotNull('email_verified_at');

            // If manager (not admin), filter by team
            if (!$user->hasRole(['super_admin', 'admin'])) {
                // Manager sees users with same position
                $query->where('position_id', $user->position_id);
            }

            // Filter by search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter online only
            if ($onlineOnly) {
                $fiveMinutesAgo = now()->subMinutes(5);
                $query->where('last_location_at', '>=', $fiveMinutesAgo);
            }

            $users = $query->orderBy('name')->paginate($perPage);

            $data = $users->through(function ($user) {
                return [
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'email'             => $user->email,
                    'position_name'     => $user->position?->name,
                    'avatar_url'        => $user->avatar_url,
                    'last_latitude'     => $user->last_latitude,
                    'last_longitude'    => $user->last_longitude,
                    'last_location_at'  => $user->last_location_at?->toISOString(),
                    'is_online'         => $this->isUserOnline($user->last_location_at),
                    'last_seen'         => $this->getLastSeenText($user->last_location_at),
                ];
            });

            // Team summary
            $teamQuery = User::whereNotNull('email_verified_at');
            if (!$user->hasRole(['super_admin', 'admin'])) {
                $teamQuery->where('position_id', $user->position_id);
            }

            $totalTeam = $teamQuery->count();
            $onlineTeam = (clone $teamQuery)
                ->where('last_location_at', '>=', now()->subMinutes(5))
                ->count();
            $activeToday = (clone $teamQuery)
                ->whereDate('last_location_at', today())
                ->count();

            return $this->jsonResponse(true, 'Data lokasi tim berhasil dimuat', [
                'data' => $data,
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'per_page'     => $users->perPage(),
                    'total'        => $users->total(),
                    'last_page'    => $users->lastPage(),
                ],
                'summary' => [
                    'total_team'    => $totalTeam,
                    'online_team'   => $onlineTeam,
                    'active_today'  => $activeToday,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get team locations error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->jsonResponse(false, 'Gagal mengambil data lokasi tim', null, 500);
        }
    }

    /**
     * GET /user-locations/{userId} - Get single user location
     */
    public function getUserLocation($userId): JsonResponse
    {
        $currentUser = Auth::user();

        // Check permission
        $user = User::with('position')->find($userId);

        if (!$user) {
            return $this->jsonResponse(false, 'User tidak ditemukan', null, 404);
        }

        // Only admin/manager or the user themselves can view
        if (!$currentUser->hasRole(['super_admin', 'admin', 'manager']) && $currentUser->id != $userId) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        try {
            $data = [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'position_name'     => $user->position?->name,
                'avatar_url'        => $user->avatar_url,
                'last_latitude'     => $user->last_latitude,
                'last_longitude'    => $user->last_longitude,
                'last_location_at'  => $user->last_location_at?->toISOString(),
                'is_online'         => $this->isUserOnline($user->last_location_at),
                'last_seen'         => $this->getLastSeenText($user->last_location_at),
            ];

            return $this->jsonResponse(true, 'Data lokasi user berhasil dimuat', $data);
        } catch (\Exception $e) {
            Log::error('Get user location error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil data lokasi', null, 500);
        }
    }

    /**
     * GET /user-locations/summary - Get location summary (Admin/Manager)
     */
    public function summary(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->hasRole(['super_admin', 'admin', 'manager'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        try {
            $query = User::whereNotNull('email_verified_at');

            // If manager, only see team
            if (!$user->hasRole(['super_admin', 'admin'])) {
                $query->where('position_id', $user->position_id);
            }

            $total = $query->count();
            $online = (clone $query)->where('last_location_at', '>=', now()->subMinutes(5))->count();
            $activeToday = (clone $query)->whereDate('last_location_at', today())->count();
            $neverLogged = (clone $query)->whereNull('last_location_at')->count();

            return $this->jsonResponse(true, 'Ringkasan lokasi', [
                'total_users'    => $total,
                'online_users'   => $online,
                'active_today'   => $activeToday,
                'never_logged'   => $neverLogged,
                'online_rate'    => $total > 0 ? round(($online / $total) * 100, 1) : 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Location summary error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil ringkasan', null, 500);
        }
    }

    /**
     * Check if user is online based on last location time
     * Online if last location < 5 minutes ago
     */
    private function isUserOnline($lastLocationAt): bool
    {
        if (!$lastLocationAt) {
            return false;
        }

        $lastLocation = Carbon::parse($lastLocationAt);
        return $lastLocation->diffInMinutes(now()) < 5;
    }

    /**
     * Get human-readable last seen text
     */
    private function getLastSeenText($lastLocationAt): string
    {
        if (!$lastLocationAt) {
            return 'Belum pernah';
        }

        $last = Carbon::parse($lastLocationAt);
        $diffInMinutes = $last->diffInMinutes(now());
        $diffInHours = $last->diffInHours(now());
        $diffInDays = $last->diffInDays(now());

        if ($diffInMinutes < 1) {
            return 'Baru saja';
        } elseif ($diffInMinutes < 60) {
            return "{$diffInMinutes} menit lalu";
        } elseif ($diffInHours < 24) {
            return "{$diffInHours} jam lalu";
        } elseif ($diffInDays < 7) {
            return "{$diffInDays} hari lalu";
        } else {
            return $last->format('d M Y');
        }
    }
}
