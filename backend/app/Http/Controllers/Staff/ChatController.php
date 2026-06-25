<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatMessageStaffRead;
use App\Models\ChatRoom;
use App\Models\ChatRoomPin;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\ChatAttachmentStorage;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    /**
     * チャット添付ファイルの教室別ストレージ使用量
     * 写真ライブラリの storageUsage と同様のフォーマット
     */
    public function storageUsage(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = (int) $request->input('classroom_id', $user->classroom_id);

        if (! in_array($classroomId, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $storage = app(ChatAttachmentStorage::class);
        return response()->json([
            'success' => true,
            'data'    => $storage->summary($classroomId),
        ]);
    }

    /**
     * チャットルーム一覧を取得
     * 最新メッセージ、未読数、ピン状態を含む
     */
    public function rooms(Request $request): JsonResponse
    {
        $user = $request->user();

        $rooms = ChatRoom::forUser($user)
            ->with([
                // status / is_active: 退所生徒の保護者の既定非表示、一斉送信の
                // 「在籍中のみ」絞り込みに使用する。
                'student:id,student_name,classroom_id,grade_level,status,is_active',
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
                'student_id'      => $room->student_id,
                'guardian_id'     => $room->guardian_id,
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

        $limit = min($request->integer('limit', 100), 200);

        if ($request->filled('last_id')) {
            // last_id より後のメッセージを取得（ポーリング/WebSocket用）
            $messages = $room->messages()
                ->notDeleted()
                ->with('staffReads')
                ->where('id', '>', $request->last_id)
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->get();
        } elseif ($request->filled('before_id')) {
            // before_id より前のメッセージを取得（過去メッセージ読み込み用）
            $messages = $room->messages()
                ->notDeleted()
                ->with('staffReads')
                ->where('id', '<', $request->before_id)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
        } else {
            // 初期表示: 最新N件を取得（DESC→reverse で古い順に並べる）
            $messages = $room->messages()
                ->notDeleted()
                ->with('staffReads')
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
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
            'message'    => 'required_without_all:attachment,classroom_photo_id|nullable|string|max:5000',
            'attachment' => 'nullable|file|max:3072', // 3MB
            'classroom_photo_id' => 'nullable|integer|exists:classroom_photos,id',
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentSize = null;
        $attachmentMime = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            // 教室別チャット添付の合計容量チェック (room の student 経由で教室を特定)
            $room->loadMissing('student');
            $classroomId = $room->student?->classroom_id;
            if ($classroomId) {
                $storage = app(ChatAttachmentStorage::class);
                if (! $storage->canUpload((int) $classroomId, (int) $file->getSize())) {
                    return response()->json([
                        'success' => false,
                        'message' => $storage->quotaMessage((int) $classroomId),
                    ], 422);
                }
            }

            $path = $file->store('chat_attachments', 'public');
            $attachmentPath = $path;
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();
            $attachmentMime = $file->getMimeType();
        } elseif ($request->filled('classroom_photo_id')) {
            // 事業所写真ライブラリから参照 (新たにコピーせず同じファイルを指す)
            $photo = \App\Models\ClassroomPhoto::find($request->classroom_photo_id);
            if ($photo) {
                // アクセス可能教室の写真であることを確認
                if (in_array($photo->classroom_id, $user->switchableClassroomIds(), true)) {
                    $attachmentPath = $photo->file_path;
                    $attachmentName = basename($photo->file_path);
                    $attachmentSize = $photo->file_size;
                    $attachmentMime = $photo->mime;
                }
            }
        }

        $message = DB::transaction(function () use ($request, $user, $room, $attachmentPath, $attachmentName, $attachmentSize, $attachmentMime) {
            $msg = ChatMessage::create([
                'room_id'         => $room->id,
                'sender_id'       => $user->id,
                'sender_type'     => 'staff',
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
     * クイック通知の固定テンプレート (システム既定値)
     *
     * @return array{type:string,title:string,body:string}
     */
    private function defaultQuickTemplate(string $action): array
    {
        $templates = [
            'departure' => [
                'type' => 'quick_departure',
                'title' => 'これから帰ります',
                'body' => "【これから帰ります】\n\nこれより帰路につきます。無事の帰宅をご確認ください。",
            ],
            'arrival' => [
                'type' => 'quick_arrival',
                'title' => '到着しました',
                // バグ報告 #47: 旧文言「【到着しました】 ご対応ありがとうございました。」の
                // 2 行目は現場で不要との要望。タイトル相当の 1 行だけに簡素化する。
                'body' => '【到着しました】',
            ],
        ];
        return $templates[$action];
    }

    /**
     * R1: クイック通知テンプレート (departure/arrival) を返す。
     * 教室の settings.quick_broadcast_templates に保存があればそれを返し、
     * なければシステム既定値を返す。
     */
    public function quickBroadcastTemplates(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroom = $user->classroom_id ? Classroom::find($user->classroom_id) : null;
        $stored = $classroom?->settings['quick_broadcast_templates'] ?? [];

        $arrivalDefault = $this->defaultQuickTemplate('arrival');
        $departureDefault = $this->defaultQuickTemplate('departure');

        return response()->json([
            'success' => true,
            'data' => [
                'arrival' => [
                    'body'    => $stored['arrival']['body'] ?? $arrivalDefault['body'],
                    'enabled' => (bool) ($stored['arrival']['enabled'] ?? true),
                    'default_body' => $arrivalDefault['body'],
                ],
                'departure' => [
                    'body'    => $stored['departure']['body'] ?? $departureDefault['body'],
                    'enabled' => (bool) ($stored['departure']['enabled'] ?? true),
                    'default_body' => $departureDefault['body'],
                ],
            ],
        ]);
    }

    /**
     * R1: クイック通知テンプレートを保存する。
     */
    public function updateQuickBroadcastTemplates(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->classroom_id) {
            return response()->json([
                'success' => false,
                'message' => '所属教室が未設定のため保存できません。',
            ], 422);
        }

        $validated = $request->validate([
            'arrival.body'    => 'nullable|string|max:2000',
            'arrival.enabled' => 'nullable|boolean',
            'departure.body'  => 'nullable|string|max:2000',
            'departure.enabled' => 'nullable|boolean',
        ]);

        $classroom = Classroom::findOrFail($user->classroom_id);
        $settings = $classroom->settings ?? [];
        $current = $settings['quick_broadcast_templates'] ?? [];

        // arrival
        $arrival = $current['arrival'] ?? [];
        if (array_key_exists('arrival', $validated)) {
            if (isset($validated['arrival']['body'])) $arrival['body'] = $validated['arrival']['body'];
            if (isset($validated['arrival']['enabled'])) $arrival['enabled'] = (bool) $validated['arrival']['enabled'];
        }
        // departure
        $departure = $current['departure'] ?? [];
        if (array_key_exists('departure', $validated)) {
            if (isset($validated['departure']['body'])) $departure['body'] = $validated['departure']['body'];
            if (isset($validated['departure']['enabled'])) $departure['enabled'] = (bool) $validated['departure']['enabled'];
        }

        $settings['quick_broadcast_templates'] = [
            'arrival'   => $arrival,
            'departure' => $departure,
        ];
        $classroom->settings = $settings;
        $classroom->save();

        return $this->quickBroadcastTemplates($request);
    }

    /**
     * クイック通知の一斉送信 (これから帰ります / 到着しました)
     *
     * R1: body は教室の保存テンプレートを既定とし、リクエストで `custom_body`
     * を渡せばそれを優先する。enabled=false の場合は 422 で拒否する。
     */
    public function quickBroadcast(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'action' => 'required|string|in:departure,arrival',
            'room_ids' => 'required|array|min:1',
            'room_ids.*' => 'integer',
            'custom_body' => 'nullable|string|max:2000',
        ]);

        $tpl = $this->defaultQuickTemplate($validated['action']);

        // 教室 settings からテンプレート上書き / enabled 判定
        $classroom = $user->classroom_id ? Classroom::find($user->classroom_id) : null;
        $stored = $classroom?->settings['quick_broadcast_templates'][$validated['action']] ?? [];
        if (isset($stored['enabled']) && $stored['enabled'] === false) {
            return response()->json([
                'success' => false,
                'message' => 'このクイック通知は無効化されています。設定画面で有効化してください。',
            ], 422);
        }
        if (! empty($stored['body'])) {
            $tpl['body'] = $stored['body'];
        }
        // custom_body はリクエスト単位の上書き
        if (! empty($validated['custom_body'])) {
            $tpl['body'] = $validated['custom_body'];
        }

        $rooms = ChatRoom::forUser($user)
            ->whereIn('id', $validated['room_ids'])
            ->get();
        $sentCount = 0;

        DB::transaction(function () use ($rooms, $user, $tpl, &$sentCount) {
            foreach ($rooms as $room) {
                ChatMessage::create([
                    'room_id' => $room->id,
                    'sender_id' => $user->id,
                    'sender_type' => 'staff',
                    'message' => $tpl['body'],
                    'message_type' => $tpl['type'],
                ]);
                $room->update(['last_message_at' => now()]);
                $sentCount++;
            }
        });

        // 各保護者に通知
        try {
            $notificationService = app(NotificationService::class);
            $senderName = $user->full_name ?? 'スタッフ';
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
            $guardianIds = $rooms->pluck('guardian_id')->unique()->filter();
            $guardians = User::whereIn('id', $guardianIds)
                ->where('is_active', true)
                ->get();
            foreach ($guardians as $guardian) {
                $notificationService->notify(
                    $guardian,
                    'chat_message',
                    $tpl['title'],
                    $senderName,
                    ['url' => "{$frontendUrl}/guardian/chat"],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('quickBroadcast notify error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'sent_count' => $sentCount,
            'message' => "{$sentCount}件のチャットに「{$tpl['title']}」を送信しました。",
        ]);
    }

    /**
     * 全保護者チャットルームに一斉送信
     */
    /**
     * 一斉送信の宛先一覧。スレッドの有無に関わらず「在籍児童(保護者紐づけ済み)」を返す。
     * 在籍外(退所・待機)も status 付きで含め、既定選択(在籍中のみ)はフロントで行う。
     * これによりスレッドの無い在籍児童(例: まだやり取りが無い保護者)も送信対象に含められる。
     */
    public function broadcastRecipients(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessibleIds = $user->accessibleClassroomIds();

        $students = Student::whereIn('classroom_id', $accessibleIds)
            ->whereNotNull('guardian_id')
            ->with('guardian:id,full_name')
            ->orderBy('classroom_id')->orderBy('student_name')
            ->get(['id', 'student_name', 'classroom_id', 'grade_level', 'status', 'is_active', 'guardian_id']);

        $roomByStudent = ChatRoom::whereIn('student_id', $students->pluck('id'))
            ->get(['id', 'student_id'])
            ->keyBy('student_id');

        $recipients = $students->map(fn ($s) => [
            'student_id' => $s->id,
            'room_id' => optional($roomByStudent->get($s->id))->id,
            'student' => [
                'id' => $s->id, 'student_name' => $s->student_name, 'classroom_id' => $s->classroom_id,
                'grade_level' => $s->grade_level, 'status' => $s->status, 'is_active' => $s->is_active,
            ],
            'guardian' => $s->guardian ? ['id' => $s->guardian->id, 'full_name' => $s->guardian->full_name] : null,
        ])->values();

        return response()->json(['success' => true, 'data' => $recipients]);
    }

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

        // 送信先: student_ids 指定時は「在籍児童基準」(スレッドが無ければ作成して送信漏れを防ぐ)。
        // 後方互換: room_ids 指定時は従来どおり選択ルームのみ。
        if ($request->has('student_ids')) {
            $studentIds = array_values(array_filter((array) $request->input('student_ids', [])));
            $students = Student::whereIn('id', $studentIds)
                ->whereIn('classroom_id', $user->accessibleClassroomIds())
                ->whereNotNull('guardian_id')
                ->get(['id', 'guardian_id']);
            $rooms = $students->map(fn ($s) => ChatRoom::firstOrCreate([
                'student_id' => $s->id, 'guardian_id' => $s->guardian_id,
            ]));
        } else {
            $query = ChatRoom::forUser($user);
            if ($request->has('room_ids')) {
                $roomIds = $request->input('room_ids', []);
                if (is_array($roomIds) && count($roomIds) > 0) {
                    $query->whereIn('id', $roomIds);
                }
            }
            $rooms = $query->get();
        }
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

        // 自分のメッセージ、または管理者は所属教室の全スタッフメッセージを削除可能
        if ($message->sender_id !== $user->id) {
            $isAdmin = $user->user_type === 'admin';
            $roomClassroom = $room->student?->classroom_id;
            $canDelete = $isAdmin && $roomClassroom && in_array($roomClassroom, $user->switchableClassroomIds(), true);
            if (!$canDelete) {
                return response()->json(['success' => false, 'message' => '削除権限がありません。'], 403);
            }
        }

        $message->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * メッセージのアーカイブ切り替え
     */
    public function toggleArchive(Request $request, ChatRoom $room, ChatMessage $message): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        if ($message->room_id !== $room->id) {
            return response()->json(['success' => false, 'message' => 'メッセージが見つかりません。'], 404);
        }

        $message->update(['is_archived' => !$message->is_archived]);

        return response()->json([
            'success'     => true,
            'is_archived' => $message->is_archived,
        ]);
    }

    /**
     * アーカイブ済みメッセージ一覧
     */
    public function archivedMessages(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessRoom($user, $room)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $messages = $room->messages()
            ->notDeleted()
            ->where('is_archived', true)
            ->with('staffReads')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $userCache = [];
        $messages->each(function ($msg) use (&$userCache) {
            if (!isset($userCache[$msg->sender_id . '_' . $msg->sender_type])) {
                if ($msg->sender_type === 'student') {
                    $student = \App\Models\Student::where('id', $msg->sender_id)->first(['id', 'student_name']);
                    $userCache[$msg->sender_id . '_' . $msg->sender_type] = $student
                        ? ['id' => $student->id, 'full_name' => $student->student_name]
                        : null;
                } else {
                    $u = User::where('id', $msg->sender_id)->first(['id', 'full_name']);
                    $userCache[$msg->sender_id . '_' . $msg->sender_type] = $u
                        ? ['id' => $u->id, 'full_name' => $u->full_name]
                        : null;
                }
            }
            $msg->sender = $userCache[$msg->sender_id . '_' . $msg->sender_type];
            $msg->is_read_by_staff = $msg->staffReads->isNotEmpty();
            unset($msg->staffReads);
            $msg->is_read_by_recipient = ($msg->sender_type === 'staff') ? (bool) $msg->is_read : $msg->is_read_by_staff;
        });

        return response()->json([
            'success' => true,
            'data'    => $messages,
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

        return $room->student
            && in_array($room->student->classroom_id, $user->switchableClassroomIds(), true);
    }
}
