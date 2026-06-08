<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\ChatAttachmentStorage;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * チャット添付ファイルの管理 (一覧 + 選択削除)。
 *
 * 背景: チャット添付は教室あたり 200MB 上限 (ChatAttachmentStorage)。しかし各チャットの
 * deleteMessage はソフト削除のみで attachment_size も物理ファイルも残り、容量が解放されない。
 * 本コントローラは管理者・スタッフが添付を一覧し、選択して物理削除(=容量を実解放)できる
 * 管理機能を提供する。
 *
 * 権限: ルートは /api/staff/chat 配下で user_type:staff,admin ミドルウェアが適用される
 * (= 管理者・スタッフのみ。保護者・生徒・タブレットは到達不可)。
 *
 * 差分カテゴリ: screen
 */
class ChatAttachmentController extends Controller
{
    /** 添付の置き場所。これ以外 (写真ライブラリ共有実体など) は物理削除しない。 */
    private const OWN_PREFIX = 'chat_attachments/';

    private const PLACEHOLDER = '（添付ファイルは削除されました）';

    /**
     * GET /api/staff/chat/attachments?classroom_id=
     * 指定教室の全チャット添付 (保護者/生徒/スタッフ間) を一覧する。
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = (int) $request->input('classroom_id', $user->classroom_id);
        if (! in_array($classroomId, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $items = collect();

        // 保護者⇔スタッフ (chat_messages: attachment_name / attachment_mime あり)
        $items = $items->concat(
            DB::table('chat_messages as m')
                ->join('chat_rooms as r', 'r.id', '=', 'm.room_id')
                ->join('students as s', 's.id', '=', 'r.student_id')
                ->leftJoin('users as u', 'u.id', '=', 'm.sender_id')
                ->where('s.classroom_id', $classroomId)
                ->whereNotNull('m.attachment_path')
                ->whereNotNull('m.attachment_size')
                ->select([
                    'm.id', 'm.attachment_path', 'm.attachment_name as name', 'm.attachment_size as size',
                    'm.attachment_mime as mime', 'm.created_at', 'm.is_deleted',
                    's.student_name as room_label', 'u.full_name as uploader_name', 'm.sender_type',
                ])->get()
                ->map(fn ($row) => $this->mapRow('guardian', $row))
        );

        // 生徒⇔スタッフ (student_chat_messages: attachment_original_name / mime なし)
        $items = $items->concat(
            DB::table('student_chat_messages as m')
                ->join('student_chat_rooms as r', 'r.id', '=', 'm.room_id')
                ->join('students as s', 's.id', '=', 'r.student_id')
                ->leftJoin('users as u', 'u.id', '=', 'm.sender_id')
                ->where('s.classroom_id', $classroomId)
                ->whereNotNull('m.attachment_path')
                ->whereNotNull('m.attachment_size')
                ->select([
                    'm.id', 'm.attachment_path', 'm.attachment_original_name as name', 'm.attachment_size as size',
                    DB::raw('NULL as mime'), 'm.created_at', 'm.is_deleted',
                    's.student_name as room_label', 'u.full_name as uploader_name', 'm.sender_type',
                ])->get()
                ->map(fn ($row) => $this->mapRow('student', $row))
        );

        // スタッフ間 (staff_chat_messages: staff_chat_rooms.classroom_id 直接)
        if (DB::getSchemaBuilder()->hasTable('staff_chat_messages')) {
            $items = $items->concat(
                DB::table('staff_chat_messages as m')
                    ->join('staff_chat_rooms as r', 'r.id', '=', 'm.room_id')
                    ->leftJoin('users as u', 'u.id', '=', 'm.sender_id')
                    ->where('r.classroom_id', $classroomId)
                    ->whereNotNull('m.attachment_path')
                    ->whereNotNull('m.attachment_size')
                    ->select([
                        'm.id', 'm.attachment_path', 'm.attachment_original_name as name', 'm.attachment_size as size',
                        DB::raw('NULL as mime'), 'm.created_at', 'm.is_deleted',
                        'r.room_name as room_label', 'u.full_name as uploader_name', DB::raw("'staff' as sender_type"),
                    ])->get()
                    ->map(fn ($row) => $this->mapRow('staff', $row))
            );
        }

        // 容量を空けやすいよう大きい順
        $attachments = $items->sortByDesc('size')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'attachments' => $attachments,
                'summary'     => app(ChatAttachmentStorage::class)->summary($classroomId),
            ],
        ]);
    }

    /**
     * POST /api/staff/chat/attachments/delete
     * 選択した添付の物理ファイルと添付情報を削除し、容量を解放する。本文テキストは保持。
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = (int) $request->input('classroom_id', $user->classroom_id);
        if (! in_array($classroomId, $user->switchableClassroomIds(), true)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $validated = $request->validate([
            'items'          => ['required', 'array', 'min:1'],
            'items.*.source' => ['required', 'in:guardian,student,staff'],
            'items.*.id'     => ['required', 'integer'],
        ]);

        $deleted = 0;
        $freed = 0;
        foreach ($validated['items'] as $item) {
            $freedBytes = $this->deleteOne($item['source'], (int) $item['id'], $classroomId);
            if ($freedBytes !== null) {
                $deleted++;
                $freed += $freedBytes;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$deleted}件の添付ファイルを削除しました。",
            'data' => [
                'deleted_count' => $deleted,
                'freed_bytes'   => $freed,
                'summary'       => app(ChatAttachmentStorage::class)->summary($classroomId),
            ],
        ]);
    }

    /**
     * 1件の添付を削除する。対象外/権限外は null を返す。成功時は解放バイト数。
     */
    private function deleteOne(string $source, int $id, int $classroomId): ?int
    {
        [$table, $nameCol, $hasMime] = match ($source) {
            'guardian' => ['chat_messages', 'attachment_name', true],
            'student'  => ['student_chat_messages', 'attachment_original_name', false],
            'staff'    => ['staff_chat_messages', 'attachment_original_name', false],
            default    => [null, null, false],
        };
        if ($table === null) {
            return null;
        }
        if ($source === 'staff' && ! DB::getSchemaBuilder()->hasTable('staff_chat_messages')) {
            return null;
        }

        // 教室スコープ込みで対象行を取得 (他教室の改ざんを防止)
        $row = $this->scopedQuery($source, $classroomId)
            ->where('m.id', $id)
            ->whereNotNull('m.attachment_path')
            ->select('m.id', 'm.attachment_path', 'm.attachment_size', 'm.message')
            ->first();
        if (! $row) {
            return null;
        }

        $path = (string) $row->attachment_path;
        $size = (int) $row->attachment_size;

        // 物理ファイルは安全なときだけ削除 (共有実体は消さない)
        if ($this->canDeleteFile($path)) {
            Storage::disk('public')->delete($path);
        }

        // 添付情報を null 化 (容量集計から外れる)。本文が空ならプレースホルダ。
        $update = [
            'attachment_path' => null,
            $nameCol          => null,
            'attachment_size' => null,
        ];
        if ($hasMime) {
            $update['attachment_mime'] = null;
        }
        if (trim((string) $row->message) === '') {
            $update['message'] = self::PLACEHOLDER;
        }
        DB::table($table)->where('id', $id)->update($update);

        return $size;
    }

    /**
     * 教室スコープを適用したクエリビルダ (m = メッセージ)。
     */
    private function scopedQuery(string $source, int $classroomId): Builder
    {
        return match ($source) {
            'guardian' => DB::table('chat_messages as m')
                ->join('chat_rooms as r', 'r.id', '=', 'm.room_id')
                ->join('students as s', 's.id', '=', 'r.student_id')
                ->where('s.classroom_id', $classroomId),
            'student' => DB::table('student_chat_messages as m')
                ->join('student_chat_rooms as r', 'r.id', '=', 'm.room_id')
                ->join('students as s', 's.id', '=', 'r.student_id')
                ->where('s.classroom_id', $classroomId),
            'staff' => DB::table('staff_chat_messages as m')
                ->join('staff_chat_rooms as r', 'r.id', '=', 'm.room_id')
                ->where('r.classroom_id', $classroomId),
        };
    }

    /**
     * 物理ファイルを削除してよいか。
     *  - chat_attachments/ 配下の自前アップロードのみ対象
     *  - 他のチャットメッセージ(3テーブル)が同一 path を参照していない
     *  - 写真ライブラリ(classroom_photos.file_path)と共有していない
     */
    private function canDeleteFile(string $path): bool
    {
        if (! str_starts_with($path, self::OWN_PREFIX)) {
            return false;
        }

        // 自レコードを含む参照数。null 化前に呼ぶため、自分だけなら 1。
        $refs = DB::table('chat_messages')->where('attachment_path', $path)->count()
            + DB::table('student_chat_messages')->where('attachment_path', $path)->count();
        if (DB::getSchemaBuilder()->hasTable('staff_chat_messages')) {
            $refs += DB::table('staff_chat_messages')->where('attachment_path', $path)->count();
        }
        if ($refs > 1) {
            return false;
        }

        if (DB::table('classroom_photos')->where('file_path', $path)->exists()) {
            return false;
        }

        return true;
    }

    /**
     * @param  object  $row
     * @return array<string, mixed>
     */
    private function mapRow(string $source, object $row): array
    {
        $path = (string) $row->attachment_path;

        return [
            'source'         => $source,
            'id'             => (int) $row->id,
            'name'           => $row->name ?: basename($path),
            'size'           => (int) $row->size,
            'mime'           => $row->mime,
            'uploaded_at'    => $row->created_at,
            'uploader_name'  => $row->uploader_name,
            'room_label'     => $row->room_label,
            'sender_type'    => $row->sender_type ?? null,
            'url'            => Storage::disk('public')->url($path),
            'is_deleted'     => (bool) $row->is_deleted,
            'is_shared_photo' => ! str_starts_with($path, self::OWN_PREFIX),
        ];
    }
}
