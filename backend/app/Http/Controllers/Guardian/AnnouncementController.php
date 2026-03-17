<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * 公開済みお知らせ一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // この保護者に紐づく生徒IDを取得
        $myStudentIds = Student::where('guardian_id', $user->id)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        $query = Announcement::where('classroom_id', $user->classroom_id)
            ->where('is_published', true)
            ->where(function ($q) use ($myStudentIds) {
                $q->where('target_type', 'all');
                if (!empty($myStudentIds)) {
                    $q->orWhere(function ($q2) use ($myStudentIds) {
                        $q2->where('target_type', 'selected')
                           ->whereHas('targetStudents', function ($q3) use ($myStudentIds) {
                               $q3->whereIn('students.id', $myStudentIds);
                           });
                    });
                }
            });

        $announcements = $query->orderByDesc('published_at')
            ->get()
            ->map(function ($announcement) use ($user) {
                $announcement->is_read = AnnouncementRead::where('announcement_id', $announcement->id)
                    ->where('user_id', $user->id)
                    ->exists();
                return $announcement;
            });

        return response()->json([
            'success' => true,
            'data'    => $announcements,
        ]);
    }

    /**
     * お知らせを既読にする
     */
    public function markRead(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        AnnouncementRead::updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id'         => $user->id,
            ],
            [
                'read_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '既読にしました。',
        ]);
    }
}
