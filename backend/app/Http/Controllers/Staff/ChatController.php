<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatMessageStaffRead;
use App\Models\ChatRoom;
use App\Models\ChatRoomPin;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        ->sort(function ($a, $b) {
            // ピン留め → 未読あり → 最新メッセージ順（レガシー互換）
            if ($a['is_pinned'] !== $b['is_pinned']) {
                return $b['is_pinned'] <=> $a['is_pinned'];
            }
            $aHasUnread = $a['unread_count'] > 0 ? 1 : 0;
            $bHasUnread = $b['unread_count'] > 0 ? 1 : 0;
            if ($aHasUnread !== $bHasUnread) {
                return $bHasUnread <=> $aHasUnread;
            }
            return ($b['last_message_at'] ?? '') <=> ($a['last_message_at'] ?? '');
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

        // before_id より前のメッセージを取得（過去メッセージ読み込み用）
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

        // 送信者情報をオブジェクトとして付加 + 既読フラグ
        $userCache = [];
        $messages->each(function ($msg) use (&$userCache) {
            // sender オブジェクト
            if (!isset($userCache[$msg->sender_id . '_' . $msg->sender_type])) {
                if ($msg->sender_type === 'student') {
                    $student = \App\Models\Student::where('id', $msg->sender_id)->first(['id', 'student_name']);
                    $userCache[$msg->sender_id . '_' . $msg->sender_type] = $student
                        ? ['id' => $student->id, 'full_name' => $student->student_name]
                        : null;
                } else {
                    $user = \App\Models\User::where('id', $msg->sender_id)->first(['id', 'full_name']);
                    $userCache[$msg->sender_id . '_' . $msg->sender_type] = $user
                        ? ['id' => $user->id, 'full_name' => $user->full_name]
                        : null;
                }
            }
            $msg->sender = $userCache[$msg->sender_id . '_' . $msg->sender_type];

            // 既読フラグ (スタッフに読まれたか) - 保護者送信メッセージ用
            $msg->is_read_by_staff = $msg->staffReads->isNotEmpty();
            unset($msg->staffReads); // レスポンスサイズ削減

            // 既読フラグ (保護者に読まれたか) - スタッフ送信メッセージ用
            // chat_messages.is_read は保護者がメッセージを閲覧した際にtrueになる
            $msg->is_read_by_recipient = ($msg->sender_type === 'staff') ? (bool) $msg->is_read : $msg->is_read_by_staff;
        });

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

        // 保護者に通知を送信
        try {
            $notificationService = app(NotificationService::class);
            $senderName = $user->full_name ?? 'スタッフ';
            $messagePreview = $request->message
                ? mb_substr($request->message, 0, 50)
                : '添付ファイルが送信されました';
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

            $guardian = User::find($room->guardian_id);
            if ($guardian && $guardian->is_active) {
                $notificationService->notify(
                    $guardian,
                    'chat_message',
                    '新着メッセージ',
                    "{$senderName}: {$messagePreview}",
                    ['url' => "{$frontendUrl}/guardian/chat?room_id={$room->id}"]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Chat notification error (staff→guardian): ' . $e->getMessage());
        }

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
            'message'    => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => 'nullable|file|max:3072', // 3MB
        ]);

        // ファイルアップロード処理（1回だけ、全ルームで共有）
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

        // 送信先の絞り込み（room_ids指定時は選択したルームのみ）
        $query = ChatRoom::forUser($user);
        if ($request->has('room_ids')) {
            $roomIds = $request->input('room_ids', []);
            if (is_array($roomIds) && count($roomIds) > 0) {
                $query->whereIn('id', $roomIds);
            }
        }
        $rooms = $query->get();
        $sentCount = 0;

        DB::transaction(function () use ($rooms, $user, $request, &$sentCount, $attachmentPath, $attachmentName, $attachmentSize, $attachmentMime) {
            foreach ($rooms as $room) {
                ChatMessage::create([
                    'room_id'         => $room->id,
                    'sender_id'       => $user->id,
                    'sender_type'     => 'staff',
                    'message'         => $request->message ?? '',
                    'message_type'    => 'broadcast',
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $attachmentName,
                    'attachment_size' => $attachmentSize,
                    'attachment_mime' => $attachmentMime,
                ]);

                $room->update(['last_message_at' => now()]);
                $sentCount++;
            }
        });

        // 全保護者に通知を送信
        try {
            $notificationService = app(NotificationService::class);
            $senderName = $user->full_name ?? 'スタッフ';
            $messagePreview = $request->message
                ? mb_substr($request->message, 0, 50)
                : '添付ファイルが送信されました';
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

            $guardianIds = $rooms->pluck('guardian_id')->unique()->filter();
            $guardians = User::whereIn('id', $guardianIds)
                ->where('is_active', true)
                ->get();

            foreach ($guardians as $guardian) {
                $notificationService->notify(
                    $guardian,
                    'chat_message',
                    '新着メッセージ（一斉送信）',
                    "{$senderName}: {$messagePreview}",
                    ['url' => "{$frontendUrl}/guardian/chat"]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Chat broadcast notification error: ' . $e->getMessage());
        }

        return response()->json([
            'success'    => true,
            'sent_count' => $sentCount,
            'message'    => "{$sentCount}件のチャットに送信しました。",
        ]);
    }

    /**
     * メッセージを削除（論理削除）
     * 自分が送信したスタッフメッセージのみ削除可能
     */
    public function deleteMessage(Request $request, ChatRoom $room, ChatMessage $message): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        // ルームのメッセージか確認
        if ($message->room_id !== $room->id) {
            return response()->json(['success' => false, 'message' => 'メッセージが見つかりません。'], 404);
        }

        // スタッフが送信したメッセージのみ削除可能
        if ($message->sender_type !== 'staff') {
            return response()->json(['success' => false, 'message' => '削除権限がありません。'], 403);
        }

        // 自分が送信したメッセージかチェック
        if ($message->sender_id !== $user->id) {
            return response()->json(['success' => false, 'message' => '削除権限がありません。'], 403);
        }

        $message->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        return response()->json(['success' => true]);
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
