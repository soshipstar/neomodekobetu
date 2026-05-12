<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * 教室単位のチャット添付ファイル容量を集計し、上限チェックする。
 *
 * 対象テーブル (3種):
 *  - chat_messages           (保護者⇔スタッフチャット)        : room → chat_rooms.student_id → students.classroom_id
 *  - student_chat_messages   (生徒⇔スタッフチャット)          : room → student_chat_rooms.student_id → students.classroom_id
 *  - staff_chat_messages     (スタッフ間チャット)              : room → staff_chat_rooms.classroom_id (直接)
 *
 * 写真ライブラリ (classroom_photos) とは独立した枠で 200MB / 教室 を上限とする。
 * 写真は自動圧縮で 500KB ≒ 200枚で 100MB に達するのに対し、チャット添付は無圧縮
 * で 3MB / 件まで許容 (生のPDFや書類) のため、写真ライブラリより枠を大きめに取る。
 *
 * 既存 attachment_size カラムを利用して集計するためマイグレーション不要。
 */
class ChatAttachmentStorage
{
    /** 教室1つあたりの全チャット添付合計の上限 (バイト) */
    public const STORAGE_LIMIT_BYTES = 200 * 1024 * 1024; // 200MB

    /**
     * 指定教室の全チャット添付合計バイトを返す。
     */
    public function classroomUsed(int $classroomId): int
    {
        // 保護者⇔スタッフ (chat_messages → chat_rooms.student_id → students.classroom_id)
        $guardianStaff = (int) DB::table('chat_messages')
            ->join('chat_rooms', 'chat_rooms.id', '=', 'chat_messages.room_id')
            ->join('students', 'students.id', '=', 'chat_rooms.student_id')
            ->where('students.classroom_id', $classroomId)
            ->whereNotNull('chat_messages.attachment_size')
            ->sum('chat_messages.attachment_size');

        // 生徒⇔スタッフ (student_chat_messages → student_chat_rooms → students.classroom_id)
        $studentStaff = (int) DB::table('student_chat_messages')
            ->join('student_chat_rooms', 'student_chat_rooms.id', '=', 'student_chat_messages.room_id')
            ->join('students', 'students.id', '=', 'student_chat_rooms.student_id')
            ->where('students.classroom_id', $classroomId)
            ->whereNotNull('student_chat_messages.attachment_size')
            ->sum('student_chat_messages.attachment_size');

        // スタッフ間 (staff_chat_messages → staff_chat_rooms.classroom_id 直接)
        $staffOnly = 0;
        if (DB::getSchemaBuilder()->hasTable('staff_chat_messages')
            && DB::getSchemaBuilder()->hasTable('staff_chat_rooms')) {
            $staffOnly = (int) DB::table('staff_chat_messages')
                ->join('staff_chat_rooms', 'staff_chat_rooms.id', '=', 'staff_chat_messages.room_id')
                ->where('staff_chat_rooms.classroom_id', $classroomId)
                ->whereNotNull('staff_chat_messages.attachment_size')
                ->sum('staff_chat_messages.attachment_size');
        }

        return $guardianStaff + $studentStaff + $staffOnly;
    }

    /**
     * 容量サマリ (画面表示やレスポンス用)。
     *
     * @return array{
     *   classroom_id: int,
     *   used_bytes: int,
     *   limit_bytes: int,
     *   used_mb: float,
     *   limit_mb: float,
     *   available_bytes: int,
     *   is_full: bool,
     * }
     */
    public function summary(int $classroomId): array
    {
        $used = $this->classroomUsed($classroomId);
        $limit = self::STORAGE_LIMIT_BYTES;
        return [
            'classroom_id'    => $classroomId,
            'used_bytes'      => $used,
            'limit_bytes'     => $limit,
            'used_mb'         => round($used / 1024 / 1024, 2),
            'limit_mb'        => round($limit / 1024 / 1024, 2),
            'available_bytes' => max(0, $limit - $used),
            'is_full'         => $used >= $limit,
        ];
    }

    /**
     * 指定教室に sizeBytes の新規添付を追加して上限を超えないか判定する。
     * 既に上限到達済なら false。
     */
    public function canUpload(int $classroomId, int $sizeBytes): bool
    {
        $used = $this->classroomUsed($classroomId);
        if ($used >= self::STORAGE_LIMIT_BYTES) {
            return false;
        }
        return ($used + $sizeBytes) <= self::STORAGE_LIMIT_BYTES;
    }

    /**
     * 422 用の親切メッセージを返す (canUpload === false 時に使う想定)。
     */
    public function quotaMessage(int $classroomId): string
    {
        $s = $this->summary($classroomId);
        return sprintf(
            'チャット添付の保存容量 (%dMB) を超えています。使用量: %.1f MB / %.1f MB。古いチャット添付を整理してから再度お試しください。',
            (int) ($s['limit_bytes'] / 1024 / 1024),
            $s['used_mb'],
            $s['limit_mb'],
        );
    }
}
