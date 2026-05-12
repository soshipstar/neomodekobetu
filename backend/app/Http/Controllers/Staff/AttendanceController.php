<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotification;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'approver:id,full_name',
            'adviceAuthor:id,full_name',
        ]);

        // 教室フィルタ（主教室 + classroom_user ピボット）
        if ($user->classroom_id) {
            $accessibleIds = $user->accessibleClassroomIds();
            $query->whereHas('student', function ($q) use ($accessibleIds) {
                $q->whereIn('classroom_id', $accessibleIds);
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
            if (!in_array($absence->student->classroom_id, $user->switchableClassroomIds(), true)) {
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

    /**
     * 振替メモを更新（ステータス変更なし）
     */
    public function updateMakeupNote(Request $request, AbsenceNotification $absence): JsonResponse
    {
        $user = $request->user();

        // 教室アクセス権チェック
        if ($user->classroom_id) {
            $absence->load('student');
            if (!in_array($absence->student->classroom_id, $user->switchableClassroomIds(), true)) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $validated = $request->validate([
            'makeup_note' => 'required|string|max:500',
        ]);

        $absence->update([
            'makeup_note' => $validated['makeup_note'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $absence->fresh(),
            'message' => 'メモを保存しました。',
        ]);
    }

    /**
     * 欠席連絡にスタッフからのアドバイスを記入する (LR-007)。
     * 保護者から体温・症状などの体調情報が来た際の助言用。
     * advice_by / advice_at は呼び出し時のスタッフ・時刻を自動記録。
     */
    public function updateAdvice(Request $request, AbsenceNotification $absence): JsonResponse
    {
        $user = $request->user();

        // 教室アクセス権チェック (他の updateMakeupNote/approveMakeup と同パターン)
        if ($user->classroom_id) {
            $absence->load('student');
            if (! $absence->student
                || ! in_array($absence->student->classroom_id, $user->switchableClassroomIds(), true)) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $validated = $request->validate([
            'advice' => 'nullable|string|max:2000',
        ]);

        $advice = $validated['advice'] ?? null;
        $isCleared = $advice === null || trim($advice) === '';
        $previousAdvice = $absence->advice;

        $absence->update([
            'advice'    => $isCleared ? null : $advice,
            'advice_by' => $isCleared ? null : $user->id,
            'advice_at' => $isCleared ? null : now(),
        ]);

        // 新規アドバイス保存・更新時のみ保護者へ通知・チャット投稿
        if (! $isCleared && $previousAdvice !== $advice) {
            $this->notifyGuardianOfAdvice($absence, $advice, $user);
            $this->postAdviceToChat($absence, $advice, $user);
        }

        return response()->json([
            'success' => true,
            'data'    => $absence->fresh('adviceAuthor'),
            'message' => $isCleared ? 'アドバイスをクリアしました。' : 'アドバイスを保存しました。',
        ]);
    }

    /**
     * 欠席連絡へスタッフがアドバイスを記入したとき、対象生徒の保護者に通知する。
     * 例外は握り潰す (advice 保存自体は成功しているため、通知失敗で 500 にしない)。
     */
    private function notifyGuardianOfAdvice(AbsenceNotification $absence, string $advice, ?User $sender = null): void
    {
        try {
            $absence->loadMissing('student');
            $guardianId = $absence->student?->guardian_id;
            if (! $guardianId) {
                return;
            }

            $guardian = User::where('id', $guardianId)->where('is_active', true)->first();
            if (! $guardian) {
                return;
            }

            $service = app(NotificationService::class);
            $dateStr = Carbon::parse($absence->absence_date)->format('n月j日');
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
            $preview = mb_strimwidth($advice, 0, 80, '...', 'UTF-8');

            $service->notify(
                $guardian,
                'absence',
                "{$dateStr}の欠席連絡にアドバイスが届きました",
                $preview,
                ['url' => "{$frontendUrl}/guardian/absence"],
            );
        } catch (\Throwable $e) {
            Log::warning('absence advice notify failed: ' . $e->getMessage());
        }
    }

    /**
     * R7: 欠席アドバイスを保護者チャットルームにも自動投稿する。
     *
     * 報告: アドバイス入力後、保護者が欠席ページを開かない限り内容に気付けないため、
     * 保護者チャットへも自動転載して見落としを防ぐ。
     *
     * 例外は握り潰す (advice 保存自体は成功しているため、転載失敗で 500 にしない)。
     */
    private function postAdviceToChat(AbsenceNotification $absence, string $advice, User $staff): void
    {
        try {
            $absence->loadMissing('student');
            $student = $absence->student;
            if (! $student || ! $student->guardian_id) {
                return;
            }

            // 該当保護者のチャットルームを取得 (なければ作成)
            $room = ChatRoom::firstOrCreate(
                ['student_id' => $student->id, 'guardian_id' => $student->guardian_id],
                ['last_message_at' => now()],
            );

            $dateStr = Carbon::parse($absence->absence_date)->format('n月j日');
            $body = "【{$dateStr}の欠席連絡へのアドバイス】\n" . $advice;

            ChatMessage::create([
                'room_id'      => $room->id,
                'sender_id'    => $staff->id,
                'sender_type'  => 'staff',
                'message_type' => 'normal',
                'message'      => $body,
            ]);

            $room->update(['last_message_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('absence advice chat post failed: ' . $e->getMessage());
        }
    }
}
