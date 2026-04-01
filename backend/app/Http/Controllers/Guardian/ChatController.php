<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * チャットルーム一覧を取得 (legacy chat.php と同等)
     */
    public function rooms(Request $request): JsonResponse
    {
        $user = $request->user();

        $rooms = ChatRoom::where('guardian_id', $user->id)
            ->with([
                'student:id,student_name,classroom_id,grade_level',
            ])
            ->withCount([
                'messages as unread_count' => function ($q) {
                    $q->notDeleted()
                      ->where('sender_type', '!=', 'guardian')
                      ->where('is_read', false);
                },
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rooms,
        ]);
    }

    /**
     * 特定ルームのメッセージ一覧を取得
     */
    public function messages(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        // 保護者のルームか確認
        if ($room->guardian_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $query = $room->messages()
            ->notDeleted()
            ->with('staffReads')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        // ポーリング用: last_id 以降のメッセージ
        if ($request->filled('last_id')) {
            $query->where('id', '>', $request->last_id);
        }

        // 過去メッセージ読み込み用: before_id より前のメッセージを取得
        if ($request->filled('before_id')) {
            $query = $room->messages()
                ->notDeleted()
                ->with('staffReads')
                ->where('id', '<', $request->before_id)
                ->orderBy('id', 'desc');
        }

        $limit = $request->integer('limit', 100);
        $messages = $query->limit(min($limit, 200))->get();

        // before_id の場合は逆順で取得したのでASCに戻す
        if ($request->filled('before_id')) {
            $messages = $messages->reverse()->values();
        }

        // 送信者名を付加 + 既読フラグ
        $messages->each(function ($msg) {
            if ($msg->sender_type === 'staff' || $msg->sender_type === 'guardian') {
                $msg->sender_name = \App\Models\User::where('id', $msg->sender_id)->value('full_name');
            } elseif ($msg->sender_type === 'student') {
                $msg->sender_name = \App\Models\Student::where('id', $msg->sender_id)->value('student_name');
            }

            // 既読フラグ: 保護者送信メッセージがスタッフに読まれたか
            $msg->is_read_by_staff = $msg->staffReads->isNotEmpty();
            // 統合既読フラグ: 自分の送信メッセージが相手に読まれたか
            $msg->is_read_by_recipient = ($msg->sender_type === 'guardian') ? $msg->is_read_by_staff : (bool) $msg->is_read;
            unset($msg->staffReads);
        });

        // 未読メッセージを既読にする（スタッフからのメッセージ）
        $room->messages()
            ->notDeleted()
            ->where('sender_type', '!=', 'guardian')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $hasMore = $messages->count() >= min($limit, 200);

        return response()->json([
            'success'  => true,
            'data'     => $messages,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * メッセージを送信（ファイル添付可）
     */
    public function sendMessage(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if ($room->guardian_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'message'    => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => 'nullable|file|max:3072', // 3MB (legacy limit)
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentSize = null;
        $attachmentMime = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat_attachments', 'public');
            $attachmentPath = $path;
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();
            $attachmentMime = $file->getMimeType();
        }

        $message = DB::transaction(function () use ($request, $user, $room, $attachmentPath, $attachmentName, $attachmentSize, $attachmentMime) {
            $msg = ChatMessage::create([
                'room_id'         => $room->id,
                'sender_id'       => $user->id,
                'sender_type'     => 'guardian',
                'message'         => $request->message ?? '',
                'message_type'    => 'normal',
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_size' => $attachmentSize,
                'attachment_mime' => $attachmentMime,
            ]);

            $room->update(['last_message_at' => now()]);

            return $msg;
        });

        // 教室のスタッフに通知を送信
        try {
            $notificationService = app(NotificationService::class);
            $senderName = $user->full_name ?? '保護者';
            $messagePreview = $request->message
                ? mb_substr($request->message, 0, 50)
                : '添付ファイルが送信されました';
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

            $room->loadMissing('student');
            $classroomId = $room->student?->classroom_id;

            if ($classroomId) {
                $staffUsers = User::where('classroom_id', $classroomId)
                    ->whereIn('user_type', ['staff', 'admin'])
                    ->where('is_active', true)
                    ->get();

                foreach ($staffUsers as $staff) {
                    $notificationService->notify(
                        $staff,
                        'chat_message',
                        '新着メッセージ',
                        "{$senderName}: {$messagePreview}",
                        ['url' => "{$frontendUrl}/staff/chat?room_id={$room->id}"]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Chat notification error (guardian→staff): ' . $e->getMessage());
        }

        return response()->json([
            'success'    => true,
            'data'       => $message,
            'message_id' => $message->id,
        ], 201);
    }

    /**
     * 既読マーク
     */
    public function markAsRead(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if ($room->guardian_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $room->messages()
            ->notDeleted()
            ->where('sender_type', '!=', 'guardian')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * 欠席連絡送信 (legacy chat_api.php send_absence_notification と同等)
     */
    public function sendAbsenceNotification(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if ($room->guardian_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'student_id'    => 'required|exists:students,id',
            'absence_date'  => 'required|date',
            'reason'        => 'nullable|string|max:500',
            'makeup_option' => 'required|string|in:decide_later,choose_date',
            'makeup_date'   => 'nullable|date',
        ]);

        $studentId = $request->student_id;
        $absenceDate = $request->absence_date;
        $reason = trim($request->reason ?? '');
        $makeupOption = $request->makeup_option;
        $makeupDate = $request->makeup_date;

        // 生徒名を取得
        $student = DB::table('students')->where('id', $studentId)->first();
        if (!$student) {
            return response()->json(['success' => false, 'message' => '生徒が見つかりません'], 404);
        }

        // 重複チェック
        $exists = DB::table('absence_notifications')
            ->where('student_id', $studentId)
            ->where('absence_date', $absenceDate)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'この日はすでに欠席連絡が登録されています'], 400);
        }

        // メッセージ本文を作成
        $dateObj = Carbon::parse($absenceDate);
        $dow = ['日', '月', '火', '水', '木', '金', '土'][$dateObj->dayOfWeek];
        $dateStr = $dateObj->format('n月j日');

        $message = "【欠席連絡】{$student->student_name}さん / {$dateStr}({$dow})";
        if ($reason) {
            $message .= " / {$reason}";
        }

        // 振替情報
        $makeupStatus = 'none';
        $saveMakeupDate = null;
        if ($makeupOption === 'decide_later') {
            $message .= " / 振替希望: 後日決定（イベント等で振替予定）";
            $makeupStatus = 'pending';
        } elseif ($makeupOption === 'choose_date' && $makeupDate) {
            $mkDateObj = Carbon::parse($makeupDate);
            $mkDow = ['日', '月', '火', '水', '木', '金', '土'][$mkDateObj->dayOfWeek];
            $message .= " / 振替希望: " . $mkDateObj->format('n月j日') . "({$mkDow})";
            $makeupStatus = 'pending';
            $saveMakeupDate = $makeupDate;
        }

        $result = DB::transaction(function () use ($room, $user, $message, $studentId, $absenceDate, $reason, $saveMakeupDate, $makeupStatus) {
            // メッセージを保存
            $msg = ChatMessage::create([
                'room_id'      => $room->id,
                'sender_id'    => $user->id,
                'sender_type'  => 'guardian',
                'message_type' => 'absence_notification',
                'message'      => $message,
            ]);

            // 欠席連絡レコード
            DB::table('absence_notifications')->insert([
                'message_id'          => $msg->id,
                'student_id'          => $studentId,
                'absence_date'        => $absenceDate,
                'reason'              => $reason ?: null,
                'makeup_request_date' => $saveMakeupDate,
                'makeup_status'       => $makeupStatus,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $room->update(['last_message_at' => now()]);

            return $msg;
        });

        return response()->json(['success' => true, 'message_id' => $result->id], 201);
    }

    /**
     * イベント参加申込送信 (legacy chat_api.php send_event_registration と同等)
     */
    public function sendEventRegistration(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if ($room->guardian_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'event_id'   => 'required|exists:events,id',
            'notes'      => 'nullable|string|max:500',
        ]);

        $studentId = $request->student_id;
        $eventId = $request->event_id;
        $notes = trim($request->notes ?? '');

        // イベント情報
        $event = DB::table('events')->where('id', $eventId)->first();
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'イベントが見つかりません'], 404);
        }

        // 生徒情報
        $student = DB::table('students')->where('id', $studentId)->first();
        if (!$student) {
            return response()->json(['success' => false, 'message' => '生徒が見つかりません'], 404);
        }

        // 重複チェック
        $exists = DB::table('event_registrations')
            ->where('event_id', $eventId)
            ->where('student_id', $studentId)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'このイベントは既に参加申込済みです'], 400);
        }

        // メッセージ本文
        $dateObj = Carbon::parse($event->event_date);
        $dow = ['日', '月', '火', '水', '木', '金', '土'][$dateObj->dayOfWeek];
        $dateStr = $dateObj->format('n月j日');

        $message = "【イベント参加申込】{$event->event_name} / {$student->student_name}さん / {$dateStr}({$dow})";
        if ($notes) {
            $message .= " / {$notes}";
        }

        $result = DB::transaction(function () use ($room, $user, $message, $studentId, $eventId, $notes) {
            $msg = ChatMessage::create([
                'room_id'      => $room->id,
                'sender_id'    => $user->id,
                'sender_type'  => 'guardian',
                'message_type' => 'event_registration',
                'message'      => $message,
            ]);

            DB::table('event_registrations')->insert([
                'event_id'   => $eventId,
                'student_id' => $studentId,
                'message_id' => $msg->id,
                'notes'      => $notes ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $room->update(['last_message_at' => now()]);

            return $msg;
        });

        return response()->json(['success' => true, 'message_id' => $result->id], 201);
    }

    /**
     * 面談申込送信 (legacy chat_api.php meeting_request と同等)
     */
    public function sendMeetingRequest(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if ($room->guardian_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'purpose'    => 'required|string|max:255',
            'detail'     => 'nullable|string|max:2000',
            'date1'      => 'required|date',
            'date2'      => 'nullable|date',
            'date3'      => 'nullable|date',
        ]);

        $studentId = $request->student_id;

        // 教室IDを取得
        $student = DB::table('students')->where('id', $studentId)->first();
        if (!$student || !$student->classroom_id) {
            return response()->json(['success' => false, 'message' => '教室情報が取得できません'], 400);
        }

        $result = DB::transaction(function () use ($request, $room, $user, $studentId, $student) {
            // 面談リクエストを保存
            $meetingRequestId = DB::table('meeting_requests')->insertGetId([
                'classroom_id'          => $student->classroom_id,
                'student_id'            => $studentId,
                'guardian_id'           => $user->id,
                'staff_id'              => null,
                'purpose'               => $request->purpose,
                'purpose_detail'        => $request->detail,
                'guardian_counter_date1' => $request->date1,
                'guardian_counter_date2' => $request->date2,
                'guardian_counter_date3' => $request->date3,
                'status'                => 'guardian_counter',
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            // チャットメッセージを作成
            $dateFormat = 'Y年n月j日 H:i';
            $date1Str = Carbon::parse($request->date1)->format($dateFormat);
            $date2Str = $request->date2 ? Carbon::parse($request->date2)->format($dateFormat) : '';
            $date3Str = $request->date3 ? Carbon::parse($request->date3)->format($dateFormat) : '';

            $messageText = "【面談のご依頼】\n\n";
            $messageText .= "面談目的：{$request->purpose}\n";
            if ($request->detail) {
                $messageText .= "詳細：{$request->detail}\n";
            }
            $messageText .= "\n以下の日程から、ご都合の良い日時をお選びください。\n\n";
            $messageText .= "① {$date1Str}\n";
            if ($date2Str) {
                $messageText .= "② {$date2Str}\n";
            }
            if ($date3Str) {
                $messageText .= "③ {$date3Str}\n";
            }
            $messageText .= "\n下記リンクから回答してください。\n";
            $messageText .= "ご都合が合わない場合は、別の希望日時を提案いただけます。";

            $msg = ChatMessage::create([
                'room_id'            => $room->id,
                'sender_id'          => $user->id,
                'sender_type'        => 'guardian',
                'message'            => $messageText,
                'message_type'       => 'meeting_request',
                'meeting_request_id' => $meetingRequestId,
            ]);

            $room->update(['last_message_at' => now()]);

            return ['message_id' => $msg->id, 'meeting_request_id' => $meetingRequestId];
        });

        return response()->json([
            'success' => true,
            'message_id' => $result['message_id'],
            'meeting_request_id' => $result['meeting_request_id'],
        ], 201);
    }
}
