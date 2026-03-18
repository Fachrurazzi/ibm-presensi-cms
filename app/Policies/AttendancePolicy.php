<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        // Karyawan butuh ini untuk melihat menu presensi
        return $user->can('view_any_attendance');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->can('view_attendance');
    }

    public function create(User $user): bool
    {
        // Karyawan butuh ini untuk melakukan absensi
        return $user->can('create_attendance');
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->can('update_attendance');
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->can('delete_attendance');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_attendance');
    }

    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return $user->can('force_attendance');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_any_attendance');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Attendance $attendance): bool
    {
        return $user->can('restores_attendance');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_attendance');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Attendance $attendance): bool
    {
        return $user->can('replicate_attendance');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_attendance');
    }
}
