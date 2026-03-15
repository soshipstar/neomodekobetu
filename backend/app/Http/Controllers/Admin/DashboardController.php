<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * 管理者ダッシュボードデータを返す
     */
    public function index(Request $request): JsonResponse
    {
        $totalStudents = Student::where('status', 'active')->count();
        $totalStaff = User::where('user_type', 'staff')->where('is_active', true)->count();
        $totalGuardians = User::where('user_type', 'guardian')->where('is_active', true)->count();
        $totalClassrooms = Classroom::where('is_active', true)->count();
        $totalAdmins = User::where('user_type', 'admin')->where('is_active', true)->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_students'   => $totalStudents,
                'total_staff'      => $totalStaff,
                'total_guardians'  => $totalGuardians,
                'total_classrooms' => $totalClassrooms,
                'total_admins'     => $totalAdmins,
            ],
        ]);
    }
}
