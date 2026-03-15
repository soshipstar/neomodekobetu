<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffChatMember;
use App\Models\StaffChatMessage;
use App\Models\StaffChatRead;
use App\Models\StaffChatRoom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StaffChatController extends Controller
{
    /**
     * 同教室のスタッフ一覧を返す（チャットルーム作成用）
     */
    public function staffList(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $staff = User::where('classroom_id', $classroomId)
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->select('id', 'full_name')
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $staff,
        ]);
    }

    /**
     * スタッフチャットルーム一覧を取得
     * 最新メッセージ、未読数、メンバー名を含む
     */
    public function rooms(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        // ユーザーが所属するルームのみ取得
        $roomIds = StaffChatMember::where('user_id', $user->id)
            ->pluck('room_id');

        $rooms = StaffChatRoom::whereIn('id', $roomIds)
            ->where('classroom_id', $classroomId)
            ->with(['members.user:id,full_name'])
            ->get();

        // 各ルームの最新メッセージを取得
        $latestMessages = StaffChatMessage::notDeleted()
            ->whereIn('room_id', $roomIds)
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                  ->from('staff_chat_messages')
                  ->where('is_deleted', false)
                  ->groupBy('room_id');
            })
            ->with('sender:id,full_name')
            ->get()
            ->keyBy('room_id');

        // 既読情報を取得
        $reads = StaffChatRead::where('user_id', $user->id)
            ->whereIn('room_id', $roomIds)
            ->pluck('last_read_message_id', 'room_id');

        // 未読数を算出
        $unreadCounts = [];
        foreach ($roomIds as $roomId) {
            $lastReadId = $reads->get($roomId, 0);
            $unreadCounts[$roomId] = StaffChatMessage::notDeleted()
                ->where('room_id', $roomId)
                ->where('id', '>', $lastReadId)
                ->where('sender_id', '!=', $user->id)
                ->count();
        }

        $data = $rooms->map(function ($room) use ($latestMessages, $unreadCounts, $user) {
            $latest = $latestMessages->get($room->id);
            $otherMembers = $room->members
                ->where('user_id', '!=', $user->id)
                ->map(fn ($m) => [
                    'id'        => $m->user->id ?? $m->user_id,
                    'full_name' => $m->user->full_name ?? '',
                ])
                ->values()
                ->toArray();

            $displayName = $room->room_type === 'group' && $room->room_name
                ? $room->room_name
                : implode('、', array_column($otherMembers, 'full_name'));

            return [
                'id'              => $room->id,
                'room_type'       => $room->room_type,
                'room_name'       => $room->room_name,
                'display_name'    => $displayName ?: '不明',
                'members'         => $otherMembers,
                'member_count'    => $room->members->count(),
                'unread_count'    => $unreadCounts[$room->id] ?? 0,
                'last_message'    => $latest ? $latest->message : null,
                'last_sender'     => $latest ? ($latest->sender->full_name ?? null) : null,
                'last_message_at' => $latest ? $latest->created_at : $room->created_at,
                'updated_at'      => $room->updated_at,
            ];
        })
        ->sortByDesc('last_message_at')
        ->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * チャットルームを作成
     * direct: 既存のDMルームがあれば再利用
     * group: 新規作成（メンバーは同一教室であること）
     */
    public function createRoom(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $request->validate([
            'room_type'   => 'required|in:direct,group',
            'room_name'   => 'nullable|string|max:100',
            'member_ids'  => 'required|array|min:1',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $memberIds = collect($request->member_ids)->unique()->values();

        // メンバーが同一教室に所属しているか確認
        $validMembers = User::whereIn('id', $memberIds)
            ->where('classroom_id', $classroomId)
            ->whereIn('user_type', ['staff', 'admin'])
            ->count();

        if ($validMembers !== $memberIds->count()) {
            return response()->json([
                'success' => false,
                'message' => '指定されたメンバーは同じ教室に所属している必要があります。',
            ], 422);
        }

        // direct の場合: 既存ルームの再利用チェック
        if ($request->room_type === 'direct') {
            if ($memberIds->count() !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'ダイレクトメッセージは1人のみ指定してください。',
                ], 422);
            }

            $partnerId = $memberIds->first();

            // 自分と相手の両方がメンバーの direct ルームを探す
            $existingRoom = StaffChatRoom::where('classroom_id', $classroomId)
                ->where('room_type', 'direct')
                ->whereHas('members', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->whereHas('members', function ($q) use ($partnerId) {
                    $q->where('user_id', $partnerId);
                })
                ->first();

            if ($existingRoom) {
                $existingRoom->load('members.user:id,full_name');
                $partner = $existingRoom->members
                    ->where('user_id', '!=', $user->id)
                    ->map(fn ($m) => ['id' => $m->user->id ?? $m->user_id, 'full_name' => $m->user->full_name ?? ''])
                    ->values()->toArray();

                return response()->json([
                    'success' => true,
                    'data'    => [
                        'id'              => $existingRoom->id,
                        'room_type'       => $existingRoom->room_type,
                        'room_name'       => $existingRoom->room_name,
                        'display_name'    => implode('、', array_column($partner, 'full_name')) ?: '不明',
                        'members'         => $partner,
                        'member_count'    => $existingRoom->members->count(),
                        'unread_count'    => 0,
                        'last_message'    => null,
                        'last_message_at' => null,
                    ],
                    'message' => '既存のルームを返却しました。',
                ]);
            }
        }

        // 新規作成
        $room = DB::transaction(function () use ($request, $user, $classroomId, $memberIds) {
            $room = StaffChatRoom::create([
                'classroom_id' => $classroomId,
                'room_type'    => $request->room_type,
                'room_name'    => $request->room_name,
                'created_by'   => $user->id,
            ]);

            // 作成者を含むメンバーを追加
            $allMemberIds = $memberIds->push($user->id)->unique();

            foreach ($allMemberIds as $memberId) {
                StaffChatMember::create([
                    'room_id'   => $room->id,
                    'user_id'   => $memberId,
                    'joined_at' => now(),
                ]);
            }

            return $room;
        });

        $room->load('members.user:id,full_name');

        $otherMembers = $room->members
            ->where('user_id', '!=', $user->id)
            ->map(fn ($m) => [
                'id'        => $m->user->id ?? $m->user_id,
                'full_name' => $m->user->full_name ?? '',
            ])
            ->values()
            ->toArray();

        $displayName = $room->room_type === 'group' && $room->room_name
            ? $room->room_name
            : implode('、', array_column($otherMembers, 'full_name'));

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $room->id,
                'room_type'       => $room->room_type,
                'room_name'       => $room->room_name,
                'display_name'    => $displayName ?: '不明',
                'members'         => $otherMembers,
                'member_count'    => $room->members->count(),
                'unread_count'    => 0,
                'last_message'    => null,
                'last_message_at' => null,
            ],
            'message' => 'チャットルームを作成しました。',
        ], 201);
    }

    /**
     * ルームのメッセージ一覧を取得（ポーリング対応）
     * last_id パラメータで差分取得可能
     */
    public function messages(Request $request, StaffChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $query = $room->messages()
            ->notDeleted()
            ->with('sender:id,full_name')
            ->orderBy('id', 'asc');

        // last_id より後のメッセージを取得（ポーリング用）
        if ($request->filled('last_id')) {
            $query->where('id', '>', $request->last_id);
        }

        $messages = $query->limit(50)->get();

        // 既読状態を更新
        if ($messages->isNotEmpty()) {
            $lastMessageId = $messages->last()->id;

            StaffChatRead::updateOrCreate(
                [
                    'room_id' => $room->id,
                    'user_id' => $user->id,
                ],
                [
                    'last_read_message_id' => $lastMessageId,
                    'read_at'              => now(),
                ]
            );
        }

        $data = $messages->map(function ($msg) {
            return [
                'id'                       => $msg->id,
                'room_id'                  => $msg->room_id,
                'sender_id'                => $msg->sender_id,
                'sender_name'              => $msg->sender->full_name ?? '不明',
                'message'                  => $msg->message,
                'attachment_path'          => $msg->attachment_path,
                'attachment_original_name' => $msg->attachment_original_name,
                'attachment_size'          => $msg->attachment_size,
                'is_deleted'               => $msg->is_deleted,
                'created_at'               => $msg->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * メッセージを送信（テキスト・ファイル添付対応）
     */
    public function sendMessage(Request $request, StaffChatRoom $room): JsonResponse
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

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('staff_chat_attachments', 'public');
            $attachmentPath = $path;
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();
        }

        $message = DB::transaction(function () use ($request, $user, $room, $attachmentPath, $attachmentName, $attachmentSize) {
            $msg = StaffChatMessage::create([
                'room_id'                  => $room->id,
                'sender_id'                => $user->id,
                'message'                  => $request->message,
                'attachment_path'          => $attachmentPath,
                'attachment_original_name' => $attachmentName,
                'attachment_size'          => $attachmentSize,
            ]);

            // ルームの updated_at を更新
            $room->touch();

            // 送信者の既読状態を更新
            StaffChatRead::updateOrCreate(
                [
                    'room_id' => $room->id,
                    'user_id' => $user->id,
                ],
                [
                    'last_read_message_id' => $msg->id,
                    'read_at'              => now(),
                ]
            );

            return $msg;
        });

        $message->load('sender:id,full_name');

        return response()->json([
            'success'    => true,
            'data'       => $message,
            'message_id' => $message->id,
        ], 201);
    }

    /**
     * ルームメンバー一覧を取得
     */
    public function members(Request $request, StaffChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $members = $room->members()
            ->with('user:id,full_name,user_type')
            ->get()
            ->map(fn ($m) => [
                'id'        => $m->user->id,
                'full_name' => $m->user->full_name,
                'user_type' => $m->user->user_type,
                'joined_at' => $m->joined_at,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $members,
        ]);
    }

    /**
     * ユーザーがルームにアクセスできるか確認
     * - 同一教室かつルームのメンバーであること
     */
    private function canAccessRoom($user, StaffChatRoom $room): bool
    {
        // 教室スコープチェック
        if ($user->classroom_id && $room->classroom_id !== $user->classroom_id) {
            return false;
        }

        // メンバーシップチェック
        return $room->members()->where('user_id', $user->id)->exists();
    }
}
