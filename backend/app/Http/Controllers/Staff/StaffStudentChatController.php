<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentChatMessage;
use App\Models\StudentChatRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffStudentChatController extends Controller
{
    /**
     * 生徒チャットルーム一覧を取得
     */
    public function rooms(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = StudentChatRoom::with('student:id,student_name,classroom_id');

        if ($classroomId) {
            $query->whereHas('student', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            });
        }

        $rooms = $query->orderByDesc('last_message_at')->get();

        // 各ルームの最新メッセージと未読数を付加
        $data = $rooms->map(function ($room) {
            $lastMessage = $room->messages()
                ->where('is_deleted', false)
                ->orderByDesc('created_at')
                ->first();

            $unreadCount = $room->messages()
                ->where('is_deleted', false)
                ->where('sender_type', 'student')
                ->where('is_read', false)
                ->count();

            return [
                'id'              => $room->id,
                'student'         => $room->student,
                'last_message'    => $lastMessage ? $lastMessage->message : null,
                'last_message_at' => $room->last_message_at,
                'unread_count'    => $unreadCount,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * 特定ルームのメッセージ一覧を取得
     */
    public function messages(Request $request, StudentChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $query = $room->messages()
            ->where('is_deleted', false)
            ->orderBy('created_at', 'asc');

        if ($request->filled('last_id')) {
            $query->where('id', '>', $request->last_id);
        }

        $messages = $query->limit(100)->get();

        // 送信者名を付加
        $messages->each(function ($msg) {
            if ($msg->sender_type === 'staff') {
                $msg->sender_name = \App\Models\User::where('id', $msg->sender_id)->value('full_name');
            } elseif ($msg->sender_type === 'student') {
                $msg->sender_name = Student::where('id', $msg->sender_id)->value('student_name');
            }
        });

        // スタッフが閲覧したので生徒メッセージを既読にする
        $room->messages()
            ->where('sender_type', 'student')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'data'    => $messages,
        ]);
    }

    /**
     * 生徒チャットにメッセージを送信
     */
    public function sendMessage(Request $request, StudentChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $message = DB::transaction(function () use ($request, $user, $room) {
            $msg = StudentChatMessage::create([
                'room_id'     => $room->id,
                'sender_id'   => $user->id,
                'sender_type' => 'staff',
                'message'     => $request->message,
            ]);

            $room->update(['last_message_at' => now()]);

            return $msg;
        });

        return response()->json([
            'success' => true,
            'data'    => $message,
        ], 201);
    }

    /**
     * 全生徒チャットに一斉送信
     */
    public function broadcast(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $query = StudentChatRoom::query();
        if ($classroomId) {
            $query->whereHas('student', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId)->where('status', 'active');
            });
        }

        $rooms = $query->get();
        $sentCount = 0;

        DB::transaction(function () use ($rooms, $user, $request, &$sentCount) {
            foreach ($rooms as $room) {
                StudentChatMessage::create([
                    'room_id'     => $room->id,
                    'sender_id'   => $user->id,
                    'sender_type' => 'staff',
                    'message'     => $request->message,
                ]);

                $room->update(['last_message_at' => now()]);
                $sentCount++;
            }
        });

        return response()->json([
            'success'    => true,
            'sent_count' => $sentCount,
            'message'    => "{$sentCount}件のチャットに送信しました。",
        ]);
    }

    /**
     * メッセージを削除（論理削除）
     */
    public function deleteMessage(Request $request, StudentChatMessage $message): JsonResponse
    {
        $message->update(['is_deleted' => true]);

        return response()->json([
            'success' => true,
            'message' => '削除しました。',
        ]);
    }

    /**
     * スタッフがルームにアクセスできるか確認
     */
    private function canAccessRoom($user, StudentChatRoom $room): bool
    {
        if (! $user->classroom_id) {
            return true;
        }

        $room->loadMissing('student');

        return $room->student && $room->student->classroom_id === $user->classroom_id;
    }
}
