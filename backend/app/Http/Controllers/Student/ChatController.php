<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentChatMessage;
use App\Models\StudentChatRoom;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * 生徒のチャットメッセージ一覧を取得
     */
    public function messages(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        // チャットルームを取得または作成
        $room = StudentChatRoom::firstOrCreate(
            ['student_id' => $student->id],
            ['classroom_id' => $student->classroom_id]
        );

        $query = StudentChatMessage::where('room_id', $room->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        // ポーリング用
        if ($request->filled('last_id')) {
            $query->where('id', '>', $request->last_id);
        }

        $messages = $query->limit(50)->get();

        // 送信者名を付加
        $messages->each(function ($msg) {
            if ($msg->sender_type === 'staff') {
                $msg->sender_name = \App\Models\User::where('id', $msg->sender_id)->value('full_name');
            } elseif ($msg->sender_type === 'student') {
                $msg->sender_name = \App\Models\Student::where('id', $msg->sender_id)->value('student_name');
            }
        });

        // スタッフからのメッセージを既読にする
        StudentChatMessage::where('room_id', $room->id)
            ->where('sender_type', 'staff')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'data'    => [
                'room_id'  => $room->id,
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * メッセージを送信
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => '生徒情報が見つかりません。'], 404);
        }

        $request->validate([
            'message'    => 'required_without:attachment|nullable|string|max:2000',
            'attachment' => 'nullable|file|max:3072', // 3MB
        ]);

        $room = StudentChatRoom::firstOrCreate(
            ['student_id' => $student->id],
            ['classroom_id' => $student->classroom_id]
        );

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('student_chat_attachments', 'public');
            $attachmentPath = $path;
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();
        }

        $message = DB::transaction(function () use ($student, $room, $request, $attachmentPath, $attachmentName, $attachmentSize) {
            $msg = StudentChatMessage::create([
                'room_id'              => $room->id,
                'sender_id'            => $student->id,
                'sender_type'          => 'student',
                'message'              => $request->message ?? '',
                'attachment_path'      => $attachmentPath,
                'attachment_original_name' => $attachmentName,
                'attachment_size'      => $attachmentSize,
            ]);

            $room->update(['last_message_at' => now()]);

            return $msg;
        });

        // スタッフにプッシュ通知（レガシー互換）
        try {
            $notificationService = app(NotificationService::class);
            $studentName = $student->student_name ?? '生徒';
            $notificationTitle = '【生徒チャット】' . $studentName . 'さんからメッセージがあります';
            $notificationBody = $request->message ?: '添付ファイルが送信されました';

            // staff と admin 両方に通知
            foreach (['staff', 'admin'] as $userType) {
                $notificationService->notifyClassroom(
                    $student->classroom_id,
                    $userType,
                    'student_chat',
                    $notificationTitle,
                    $notificationBody,
                    ['url' => '/staff/student-chats'],
                );
            }
        } catch (\Exception $e) {
            Log::error('Student chat push notification error: ' . $e->getMessage());
        }

        return response()->json([
            'success'    => true,
            'data'       => $message,
            'message_id' => $message->id,
        ], 201);
    }

    /**
     * リクエストユーザーに紐づく生徒を取得
     */
    private function getStudent(Request $request): ?Student
    {
        $user = $request->user();

        return Student::where('username', $user->username)->first();
    }
}
