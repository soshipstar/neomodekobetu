<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| WebSocket チャンネル認可定義 (Laravel Reverb)
| 仕様書 Section 4.8 に基づくチャンネル設計
|
*/

// ---------------------------------------------------------------------------
// チャットルーム (保護者-スタッフ間)
// chat.room.{roomId} - MessageSent, MessageRead, TypingStarted
// ---------------------------------------------------------------------------
Broadcast::channel('chat.room.{roomId}', function ($user, $roomId) {
    $room = \App\Models\ChatRoom::find($roomId);

    if (!$room) {
        return false;
    }

    // スタッフ/管理者: 同一教室のチャットルームにアクセス可
    if (in_array($user->user_type, ['staff', 'admin'])) {
        $student = $room->student;
        return $student && $student->classroom_id === $user->classroom_id;
    }

    // 保護者: 自分が参加しているチャットルームのみ
    if ($user->user_type === 'guardian') {
        return $room->guardian_id === $user->id;
    }

    return false;
});

// ---------------------------------------------------------------------------
// 生徒チャットルーム (スタッフ-生徒間)
// student-chat.room.{roomId} - MessageSent
// ---------------------------------------------------------------------------
Broadcast::channel('student-chat.room.{roomId}', function ($user, $roomId) {
    $room = \App\Models\StudentChatRoom::find($roomId);

    if (!$room) {
        return false;
    }

    // スタッフ/管理者: 同一教室の生徒チャットにアクセス可
    if (in_array($user->user_type, ['staff', 'admin'])) {
        $student = $room->student;
        return $student && $student->classroom_id === $user->classroom_id;
    }

    return false;
});

// ---------------------------------------------------------------------------
// ユーザー個人通知チャンネル
// user.{userId} - NotificationCreated
// ---------------------------------------------------------------------------
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// ---------------------------------------------------------------------------
// 教室チャンネル (出欠更新等の教室単位イベント)
// classroom.{classroomId} - AttendanceUpdated
// ---------------------------------------------------------------------------
Broadcast::channel('classroom.{classroomId}', function ($user, $classroomId) {
    // マスター管理者は全教室にアクセス可
    if ($user->user_type === 'admin' && $user->is_master) {
        return true;
    }

    return (int) $user->classroom_id === (int) $classroomId;
});

// ---------------------------------------------------------------------------
// 支援計画ステータスチャンネル
// plan.{planId} - PlanStatusChanged
// ---------------------------------------------------------------------------
Broadcast::channel('plan.{planId}', function ($user, $planId) {
    $plan = \App\Models\IndividualSupportPlan::find($planId);

    if (!$plan) {
        return false;
    }

    // スタッフ/管理者: 同一教室の計画にアクセス可
    if (in_array($user->user_type, ['staff', 'admin'])) {
        return $plan->classroom_id === $user->classroom_id;
    }

    // 保護者: 自分の子供の計画のみ
    if ($user->user_type === 'guardian') {
        $student = $plan->student;
        return $student && $student->guardian_id === $user->id;
    }

    return false;
});
