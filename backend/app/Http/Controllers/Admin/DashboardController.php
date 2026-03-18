<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * 管理者ダッシュボードデータを返す
     *
     * マスター管理者: 全教室横断の統計（教室数、管理者数、スタッフ数、ミニマム版教室数）
     * 通常管理者: 自分の教室のみの統計（ユーザー数、生徒数、有効生徒数、記録数）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        if ($isMaster) {
            // マスター管理者：教室・アカウント関連の統計
            $totalClassrooms = Classroom::count();
            $totalAdmins = User::where('user_type', 'admin')->where('is_active', true)->count();
            $totalStaff = User::where('user_type', 'staff')->where('is_active', true)->count();
            try {
                $minimumClassrooms = Classroom::where('service_type', 'minimum')->count();
            } catch (\Throwable) {
                $minimumClassrooms = 0;
            }

            return response()->json([
                'success'   => true,
                'is_master' => true,
                'data'      => [
                    'total_classrooms'   => $totalClassrooms,
                    'total_admins'       => $totalAdmins,
                    'total_staff'        => $totalStaff,
                    'minimum_classrooms' => $minimumClassrooms,
                ],
            ]);
        }

        // 通常管理者：自分の教室のデータのみ
        $classroomId = $user->classroom_id;

        $totalUsers = User::where('is_active', true)->where('classroom_id', $classroomId)->count();
        $totalStudents = Student::where('classroom_id', $classroomId)->count();
        $activeStudents = Student::where('classroom_id', $classroomId)->where('status', 'active')->count();
        $totalRecords = DailyRecord::whereHas('staff', function ($q) use ($classroomId) {
            $q->where('classroom_id', $classroomId);
        })->count();

        return response()->json([
            'success'   => true,
            'is_master' => false,
            'data'      => [
                'total_users'    => $totalUsers,
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'total_records'  => $totalRecords,
            ],
        ]);
    }
}
