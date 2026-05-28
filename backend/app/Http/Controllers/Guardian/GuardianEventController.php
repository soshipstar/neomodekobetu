<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianEventController extends Controller
{
    /**
     * 保護者向けイベント一覧（今後のイベントのみ）
     *
     * 既定では「保護者の全子供の在籍教室の全イベント」を返すが、
     * classroom_id が指定された場合はその 1 教室のイベントのみに絞る。
     * 連絡帳のチャット画面 (= 特定の教室・児童とのルーム) からイベント
     * 申込フォームを出すケースで、他事業所のイベントを誤って候補に
     * 出さないため。アクセス権限は引き続き「保護者の子供の在籍教室」で制限。
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 保護者の子供の教室IDを取得
        $allowedClassroomIds = \App\Models\Student::whereHas('chatRooms', function ($q) use ($user) {
            $q->where('guardian_id', $user->id);
        })->pluck('classroom_id')->unique()->filter()->map(fn ($v) => (int) $v)->values()->all();

        // 明示的に classroom_id 絞り込みが要求された場合、許可教室の範囲内であれば適用
        $targetClassroomIds = $allowedClassroomIds;
        if ($request->filled('classroom_id')) {
            $requested = (int) $request->classroom_id;
            if (! in_array($requested, $allowedClassroomIds, true)) {
                // 権限外: 空 array を渡して空結果にする (= 他事業所のイベントは見せない)
                $targetClassroomIds = [];
            } else {
                $targetClassroomIds = [$requested];
            }
        }

        $query = Event::whereIn('classroom_id', $targetClassroomIds);

        // upcoming=true の場合、今日以降のイベントのみ
        if ($request->boolean('upcoming', false)) {
            $query->where('event_date', '>=', now()->startOfDay());
        }

        $events = $query->orderBy('event_date')->get();

        $data = $events->map(fn ($e) => [
            'id'          => $e->id,
            'event_name'  => $e->event_name,
            'event_date'  => $e->event_date?->format('Y-m-d'),
            'start_time'  => $e->start_time ? substr($e->start_time, 0, 5) : null,
            'end_time'    => $e->end_time ? substr($e->end_time, 0, 5) : null,
            'description' => $e->event_description,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
