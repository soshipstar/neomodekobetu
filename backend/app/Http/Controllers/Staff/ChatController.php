<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatMessageStaffRead;
use App\Models\ChatRoom;
use App\Models\ChatRoomPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    /**
     * チャットルーム一覧を取得
     * 最新メッセージ、未読数、ピン状態を含む
     */
    public function rooms(Request $request): JsonResponse
    {
        $user = $request->user();

        $rooms = ChatRoom::forUser($user)
            ->with([
                'student:id,student_name,classroom_id,grade_level',
                'guardian:id,full_name',
            ])
            ->withCount([
                'messages as unread_count' => function ($q) use ($user) {
                    $q->notDeleted()
                      ->where('sender_type', '!=', 'staff')
                      ->whereDoesntHave('staffReads', function ($r) use ($user) {
                          $r->where('staff_id', $user->id);
                      });
                },
            ])
            ->get();

        // ピン情報をマージ
        $pinnedRoomIds = ChatRoomPin::where('staff_id', $user->id)
            ->pluck('room_id')
            ->toArray();

        // 各ルームの最新メッセージを取得
        $latestMessages = ChatMessage::notDeleted()
            ->whereIn('room_id', $rooms->pluck('id'))
            ->select('room_id', 'message', 'sender_type', 'created_at')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                  ->from('chat_messages')
                  ->where('is_deleted', false)
                  ->groupBy('room_id');
            })
            ->get()
            ->keyBy('room_id');

        $data = $rooms->map(function ($room) use ($pinnedRoomIds, $latestMessages) {
            $latest = $latestMessages->get($room->id);
            return [
                'id'              => $room->id,
                'student'         => $room->student,
                'guardian'        => $room->guardian,
                'is_pinned'       => in_array($room->id, $pinnedRoomIds),
                'unread_count'    => $room->unread_count,
                'last_message'    => $latest ? $latest->message : null,
                'last_sender'     => $latest ? $latest->sender_type : null,
                'last_message_at' => $room->last_message_at,
            ];
        })
        ->sortByDesc(function ($room) {
            // ピン固定を先に、その後日時順
            return ($room['is_pinned'] ? '1_' : '0_') . ($room['last_message_at'] ?? '');
        })
        ->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * 特定ルームのメッセージ一覧を取得（ページネーション付き）
     */
    public function messages(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        // アクセス権限チェック
        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $query = $room->messages()
            ->notDeleted()
            ->with('staffReads')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        // last_id より後のメッセージを取得（ポーリング用）
        if ($request->filled('last_id')) {
            $query->where('id', '>', $request->last_id);
        }

        $messages = $query->limit(50)->get();

        // 送信者名を付加
        $messages->each(function ($msg) {
            if ($msg->sender_type === 'staff' || $msg->sender_type === 'guardian') {
                $msg->sender_name = \App\Models\User::where('id', $msg->sender_id)->value('full_name');
            } elseif ($msg->sender_type === 'student') {
                $msg->sender_name = \App\Models\Student::where('id', $msg->sender_id)->value('student_name');
            }
        });

        return response()->json([
            'success'  => true,
            'data'     => $messages,
        ]);
    }

    /**
     * メッセージを送信（ファイル添付可）
     */
    public function sendMessage(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'message'    => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => 'nullable|file|max:3072', // 3MB
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
                'sender_type'     => $user->user_type,
                'message'         => $request->message ?? '',
                'message_type'    => 'normal',
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_size' => $attachmentSize,
                'attachment_mime' => $attachmentMime,
            ]);

            // ルームの最終メッセージ時刻を更新
            $room->update(['last_message_at' => now()]);

            return $msg;
        });

        return response()->json([
            'success'    => true,
            'data'       => $message,
            'message_id' => $message->id,
        ], 201);
    }

    /**
     * チャットルームのピン留め切り替え
     */
    public function togglePin(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        $existing = ChatRoomPin::where('room_id', $room->id)
            ->where('staff_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $pinned = false;
        } else {
            ChatRoomPin::create([
                'room_id'  => $room->id,
                'staff_id' => $user->id,
            ]);
            $pinned = true;
        }

        return response()->json([
            'success'   => true,
            'is_pinned' => $pinned,
        ]);
    }

    /**
     * メッセージを既読にする
     */
    public function markRead(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        // スタッフ以外から送信された未読メッセージを既読にする
        $unreadMessages = $room->messages()
            ->notDeleted()
            ->where('sender_type', '!=', 'staff')
            ->whereDoesntHave('staffReads', function ($q) use ($user) {
                $q->where('staff_id', $user->id);
            })
            ->pluck('id');

        if ($unreadMessages->isNotEmpty()) {
            $records = $unreadMessages->map(fn ($msgId) => [
                'message_id' => $msgId,
                'staff_id'   => $user->id,
                'read_at'    => now(),
            ])->toArray();

            ChatMessageStaffRead::insert($records);
        }

        return response()->json([
            'success'    => true,
            'read_count' => $unreadMessages->count(),
        ]);
    }

    /**
     * 全保護者チャットルームに一斉送信
     */
    public function broadcast(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $rooms = ChatRoom::forUser($user)->get();
        $sentCount = 0;

        DB::transaction(function () use ($rooms, $user, $request, &$sentCount) {
            foreach ($rooms as $room) {
                ChatMessage::create([
                    'room_id'      => $room->id,
                    'sender_id'    => $user->id,
                    'sender_type'  => 'staff',
                    'message'      => $request->message,
                    'message_type' => 'broadcast',
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
     * スタッフがルームにアクセスできるか確認
     */
    private function canAccessRoom($user, ChatRoom $room): bool
    {
        if (! $user->classroom_id) {
            return true; // admin without classroom can access all
        }

        $room->loadMissing('student');

        return $room->student && $room->student->classroom_id === $user->classroom_id;
    }
}
