<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\MeetingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MeetingController extends Controller
{
    /**
     * 保護者の面談リクエスト一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $meetings = MeetingRequest::where('guardian_id', $user->id)
            ->with([
                'student:id,student_name',
                'staff:id,full_name',
            ])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $meetings,
        ]);
    }

    /**
     * 面談リクエスト詳細を取得
     */
    public function show(MeetingRequest $meeting): JsonResponse
    {
        $meeting->load(['student:id,student_name', 'staff:id,full_name']);

        return response()->json([
            'success' => true,
            'data'    => $meeting,
        ]);
    }

    /**
     * 面談リクエストに回答する（日時選択 or 別日程提案）
     */
    public function update(Request $request, MeetingRequest $meeting): JsonResponse
    {
        $user = $request->user();

        // 保護者のリクエストか確認
        if ($meeting->guardian_id !== $user->id) {
            $studentIds = $user->students()->pluck('id')->toArray();
            if (! in_array($meeting->student_id, $studentIds)) {
                return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
            }
        }

        $validated = $request->validate([
            'action'           => 'required|string|in:select,counter',
            'selected_date'    => 'required_if:action,select|nullable|date',
            'counter_date1'    => 'required_if:action,counter|nullable|date',
            'counter_date2'    => 'nullable|date',
            'counter_date3'    => 'nullable|date',
            'counter_message'  => 'nullable|string|max:1000',
        ]);

        $dateFormat = 'Y年n月j日 H:i';

        DB::transaction(function () use ($meeting, $user, $validated, $dateFormat) {
            $room = ChatRoom::where('student_id', $meeting->student_id)
                ->where('guardian_id', $user->id)
                ->first();

            if ($validated['action'] === 'select') {
                // 候補日時から選択して確定
                $meeting->update([
                    'confirmed_date' => $validated['selected_date'],
                    'confirmed_by'   => 'guardian',
                    'confirmed_at'   => now(),
                    'status'         => 'confirmed',
                ]);

                if ($room) {
                    $dateStr = Carbon::parse($validated['selected_date'])->format($dateFormat);
                    $messageText = "【面談日時が確定しました】\n\n"
                        . "面談目的：{$meeting->purpose}\n"
                        . "確定日時：{$dateStr}\n\n"
                        . "当日はよろしくお願いいたします。";

                    ChatMessage::create([
                        'room_id'            => $room->id,
                        'sender_type'        => 'guardian',
                        'sender_id'          => $user->id,
                        'message'            => $messageText,
                        'message_type'       => 'meeting_confirmed',
                        'meeting_request_id' => $meeting->id,
                    ]);

                    $room->update(['last_message_at' => now()]);
                }
            } else {
                // 別日程を提案 - candidate_dates JSONBに格納
                $counterDates = array_values(array_filter([
                    $validated['counter_date1'],
                    $validated['counter_date2'] ?? null,
                    $validated['counter_date3'] ?? null,
                ]));

                $meeting->update([
                    'candidate_dates'          => $counterDates,
                    'guardian_counter_message'  => $validated['counter_message'] ?? null,
                    'status'                   => 'guardian_counter',
                ]);

                if ($room) {
                    $date1Str = Carbon::parse($validated['counter_date1'])->format($dateFormat);
                    $date2Str = isset($validated['counter_date2']) ? Carbon::parse($validated['counter_date2'])->format($dateFormat) : '';
                    $date3Str = isset($validated['counter_date3']) ? Carbon::parse($validated['counter_date3'])->format($dateFormat) : '';

                    $messageText = "【面談日程の再調整】\n\n"
                        . "申し訳ございませんが、ご提案いただいた日程での都合がつきませんでした。\n"
                        . "以下の日程はいかがでしょうか。\n\n"
                        . "① {$date1Str}\n";

                    if ($date2Str) {
                        $messageText .= "② {$date2Str}\n";
                    }
                    if ($date3Str) {
                        $messageText .= "③ {$date3Str}\n";
                    }
                    if (! empty($validated['counter_message'])) {
                        $messageText .= "\nメッセージ：{$validated['counter_message']}";
                    }

                    ChatMessage::create([
                        'room_id'            => $room->id,
                        'sender_type'        => 'guardian',
                        'sender_id'          => $user->id,
                        'message'            => $messageText,
                        'message_type'       => 'meeting_counter',
                        'meeting_request_id' => $meeting->id,
                    ]);

                    $room->update(['last_message_at' => now()]);
                }
            }
        });

        $message = $validated['action'] === 'select'
            ? '面談日時が確定しました。'
            : '別日程を提案しました。スタッフからの回答をお待ちください。';

        return response()->json([
            'success' => true,
            'data'    => $meeting->fresh(),
            'message' => $message,
        ]);
    }

    /**
     * 面談リクエストに回答する（respondエイリアス）
     */
    public function respond(Request $request, MeetingRequest $meeting): JsonResponse
    {
        return $this->update($request, $meeting);
    }
}
