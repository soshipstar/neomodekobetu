<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotification;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * 出欠一覧を取得（欠席連絡・振替依頼）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AbsenceNotification::with([
            'student:id,student_name,classroom_id',
            'student.guardian:id,full_name',
        ]);

        // 教室フィルタ
        if ($user->classroom_id) {
            $query->whereHas('student', function ($q) use ($user) {
                $q->where('classroom_id', $user->classroom_id);
            });
        }

        // 日付フィルタ
        if ($request->filled('date')) {
            $query->whereDate('absence_date', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('absence_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('absence_date', '<=', $request->date_to);
        }

        // ステータスフィルタ
        if ($request->filled('makeup_status')) {
            $query->where('makeup_status', $request->makeup_status);
        }

        $records = $query->orderByDesc('absence_date')
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * 振替依頼を承認・却下
     */
    public function approveMakeup(Request $request, AbsenceNotification $absence): JsonResponse
    {
        $user = $request->user();

        // 教室アクセス権チェック
        if ($user->classroom_id) {
            $absence->load('student');
            if ($absence->student->classroom_id !== $user->classroom_id) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'note'   => 'nullable|string',
        ]);

        if ($absence->makeup_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'この依頼は既に処理済みです。',
            ], 422);
        }

        $action = $validated['action'];
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        DB::transaction(function () use ($absence, $user, $newStatus, $validated, $action) {
            $absence->update([
                'makeup_status'      => $newStatus,
                'makeup_approved_by' => $user->id,
                'makeup_note'        => $validated['note'] ?? $absence->makeup_note,
            ]);

            // チャット通知を送信
            $absence->load('student');
            $studentName = $absence->student->student_name;
            $makeupDate = $absence->makeup_request_date
                ? Carbon::parse($absence->makeup_request_date)->format('n月j日')
                : '';

            $statusLabel = $action === 'approve' ? '承認' : '却下';
            $notificationMessage = "【振替{$statusLabel}】{$studentName}さんの振替依頼を{$statusLabel}しました。";
            if ($makeupDate) {
                $label = $action === 'approve' ? '振替日' : '希望日';
                $notificationMessage .= "\n{$label}: {$makeupDate}";
            }

            $room = ChatRoom::whereHas('student', function ($q) use ($absence) {
                $q->where('id', $absence->student_id);
            })->first();

            if ($room) {
                ChatMessage::create([
                    'room_id'     => $room->id,
                    'sender_id'   => $user->id,
                    'sender_type' => 'staff',
                    'message'     => $notificationMessage,
                ]);

                $room->update(['last_message_at' => now()]);
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $absence->fresh(),
            'message' => $action === 'approve' ? '承認しました。' : '却下しました。',
        ]);
    }
}
