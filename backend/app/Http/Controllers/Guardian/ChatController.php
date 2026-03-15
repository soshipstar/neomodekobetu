<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
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
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        // ポーリング用: last_id 以降のメッセージ
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

        // 未読メッセージを既読にする（スタッフからのメッセージ）
        $room->messages()
            ->notDeleted()
            ->where('sender_type', '!=', 'guardian')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'data'    => $messages,
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

        return response()->json([
            'success'    => true,
            'data'       => $message,
            'message_id' => $message->id,
        ], 201);
    }
}
