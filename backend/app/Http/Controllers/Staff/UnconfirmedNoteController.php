<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IntegratedNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnconfirmedNoteController extends Controller
{
    /**
     * 未送信/未確認の統合連絡帳一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = IntegratedNote::with([
            'student:id,student_name,classroom_id,guardian_id',
            'student.guardian:id,full_name',
            'dailyRecord:id,record_date,activity_name',
        ]);

        if ($classroomId) {
            $query->whereHas('student', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            });
        }

        // 未送信のもの、または送信済みだが保護者未確認のもの
        if ($request->input('filter') === 'unconfirmed') {
            $query->where('is_sent', true)->where('guardian_confirmed', false);
        } else {
            $query->where('is_sent', false);
        }

        $notes = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data'    => $notes,
        ]);
    }

    /**
     * 統合連絡帳を保護者に送信
     */
    public function send(Request $request, IntegratedNote $note): JsonResponse
    {
        $user = $request->user();

        if ($user->classroom_id) {
            $note->loadMissing('student');
            if ($note->student && $note->student->classroom_id !== $user->classroom_id) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        if ($note->is_sent) {
            return response()->json(['success' => false, 'message' => 'この連絡帳は既に送信済みです。'], 422);
        }

        $note->update([
            'is_sent' => true,
            'sent_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $note->fresh(),
            'message' => '送信しました。',
        ]);
    }
}
