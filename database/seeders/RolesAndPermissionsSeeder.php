<?php
// database/seeders/RolesAndPermissionsSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ========== PERMISSIONS ==========

        $permissions = [
            // User
            'view_user',
            'view_any_user',
            'create_user',
            'update_user',
            'delete_user',
            'delete_any_user',
            // Position
            'view_position',
            'view_any_position',
            'create_position',
            'update_position',
            'delete_position',
            // Office
            'view_office',
            'view_any_office',
            'create_office',
            'update_office',
            'delete_office',
            // Shift
            'view_shift',
            'view_any_shift',
            'create_shift',
            'update_shift',
            'delete_shift',
            // Schedule
            'view_schedule',
            'view_any_schedule',
            'create_schedule',
            'update_schedule',
            'delete_schedule',
            // Attendance
            'view_attendance',
            'view_any_attendance',
            'create_attendance',
            'update_attendance',
            'delete_attendance',
            // Leave
            'view_leave',
            'view_any_leave',
            'create_leave',
            'update_leave',
            'delete_leave',
            // AttendancePermission
            'view_attendancePermission',
            'view_any_attendancePermission',
            'create_attendancePermission',
            'update_attendancePermission',
            'delete_attendancePermission',
            // Reports
            'view_report_absensi',
            'view_report_indisipliner',
            'view_report_cuti',
            // Pages
            'page_Maps',
            'page_LeaveStats',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ========== ROLES ==========

        // Super Admin
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdminRole->givePermissionTo(Permission::all());

        // Admin
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_user',
            'view_any_position',
            'view_position',
            'create_position',
            'update_position',
            'delete_position',
            'view_any_office',
            'view_office',
            'create_office',
            'update_office',
            'delete_office',
            'view_any_shift',
            'view_shift',
            'create_shift',
            'update_shift',
            'delete_shift',
            'view_any_schedule',
            'view_schedule',
            'create_schedule',
            'update_schedule',
            'delete_schedule',
            'view_any_attendance',
            'view_attendance',
            'create_attendance',
            'update_attendance',
            'view_any_leave',
            'view_leave',
            'create_leave',
            'update_leave',
            'delete_leave',
            'view_any_attendancePermission',
            'view_attendancePermission',
            'create_attendancePermission',
            'update_attendancePermission',
            'view_report_absensi',
            'view_report_indisipliner',
            'view_report_cuti',
            'page_Maps',
            'page_LeaveStats',
        ]);

        // Karyawan
        $karyawanRole = Role::firstOrCreate(['name' => 'karyawan']);
        $karyawanRole->givePermissionTo([
            'view_attendance',
            'view_leave',
            'create_leave',
            'update_leave',
            'view_attendancePermission',
            'create_attendancePermission',
            'page_Maps',
        ]);

        // ========== ASSIGN ROLES ==========

        $adminUser = User::where('email', 'admin@intiboga.com')->first();
        if ($adminUser) {
            $adminUser->assignRole('super_admin');
        }

        $karyawanUsers = User::where('email', '!=', 'admin@intiboga.com')->get();
        foreach ($karyawanUsers as $user) {
            $user->assignRole('karyawan');
        }
    }
}
