<?php

namespace App\Http\Controllers\Staff;

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
     * 面談リクエスト一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = MeetingRequest::with([
            'student:id,student_name',
            'guardian:id,full_name',
            'staff:id,full_name',
        ]);

        if ($user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $meetings = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $meetings,
        ]);
    }

    /**
     * 面談リクエストを新規作成
     * チャットルームに面談案内メッセージも自動送信
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'              => 'required|exists:students,id',
            'guardian_id'             => 'required|exists:users,id',
            'purpose'                 => 'required|string|max:255',
            'purpose_detail'          => 'nullable|string',
            'meeting_notes'           => 'nullable|string',
            'related_plan_id'         => 'nullable|exists:individual_support_plans,id',
            'related_monitoring_id'   => 'nullable|exists:monitoring_records,id',
            'candidate_dates'         => 'required|array|min:1|max:3',
            'candidate_dates.*'       => 'required|date',
        ]);

        $user = $request->user();

        $meeting = DB::transaction(function () use ($user, $validated) {
            $meeting = MeetingRequest::create([
                'classroom_id'            => $user->classroom_id,
                'student_id'              => $validated['student_id'],
                'guardian_id'             => $validated['guardian_id'],
                'staff_id'                => $user->id,
                'purpose'                 => $validated['purpose'],
                'purpose_detail'          => $validated['purpose_detail'] ?? null,
                'meeting_notes'           => $validated['meeting_notes'] ?? null,
                'related_plan_id'         => $validated['related_plan_id'] ?? null,
                'related_monitoring_id'   => $validated['related_monitoring_id'] ?? null,
                'candidate_dates'         => $validated['candidate_dates'],
                'status'                  => 'pending',
            ]);

            // チャットルームを取得または作成
            $room = ChatRoom::firstOrCreate(
                [
                    'student_id'  => $validated['student_id'],
                    'guardian_id' => $validated['guardian_id'],
                ],
                ['last_message_at' => now()]
            );

            // 候補日時のフォーマット
            $dateFormat = 'Y年n月j日 H:i';
            $candidateDates = $validated['candidate_dates'];

            $messageText = "【面談予約のご案内】\n\n";
            $messageText .= "面談目的：{$validated['purpose']}\n";
            if (! empty($validated['purpose_detail'])) {
                $messageText .= "詳細：{$validated['purpose_detail']}\n";
            }
            $messageText .= "\n以下の日程から、ご都合の良い日時をお選びください。\n\n";
            $circleNumbers = ['①', '②', '③'];
            foreach ($candidateDates as $i => $date) {
                $formatted = Carbon::parse($date)->format($dateFormat);
                $messageText .= ($circleNumbers[$i] ?? ($i + 1) . '.') . " {$formatted}\n";
            }
            $messageText .= "\n下記リンクから回答してください。\nご都合が合わない場合は、別の希望日時を提案いただけます。";

            ChatMessage::create([
                'room_id'            => $room->id,
                'sender_type'        => 'staff',
                'sender_id'          => $user->id,
                'message'            => $messageText,
                'message_type'       => 'meeting_request',
                'meeting_request_id' => $meeting->id,
            ]);

            $room->update(['last_message_at' => now()]);

            return $meeting;
        });

        return response()->json([
            'success' => true,
            'data'    => $meeting->load(['student', 'guardian']),
            'message' => '面談予約リクエストを送信しました。',
        ], 201);
    }

    /**
     * 面談リクエスト詳細を取得
     */
    public function show(MeetingRequest $meeting): JsonResponse
    {
        $meeting->load(['student:id,student_name', 'guardian:id,full_name', 'staff:id,full_name']);

        return response()->json([
            'success' => true,
            'data'    => $meeting,
        ]);
    }

    /**
     * 面談リクエストを更新（日程確定など）
     */
    public function update(Request $request, MeetingRequest $meeting): JsonResponse
    {
        $validated = $request->validate([
            'action'            => 'nullable|string|in:confirm,counter,cancel',
            'confirmed_date'    => 'nullable|date',
            'status'            => 'nullable|string|in:pending,confirmed,cancelled,guardian_counter,staff_counter',
            'purpose_detail'    => 'nullable|string',
            'candidate_dates'   => 'nullable|array|max:3',
            'candidate_dates.*' => 'nullable|date',
            'meeting_notes'     => 'nullable|string',
            'meeting_guidance'  => 'nullable|string',
            'staff_counter_message' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $dateFormat = 'Y年n月j日 H:i';

        DB::transaction(function () use ($meeting, $user, $validated, $dateFormat) {
            $action = $validated['action'] ?? null;
            unset($validated['action']);

            if ($action === 'confirm' && !empty($validated['confirmed_date'])) {
                // スタッフが保護者対案の日程を確定
                $meeting->update([
                    'confirmed_date' => $validated['confirmed_date'],
                    'confirmed_by'   => 'staff',
                    'confirmed_at'   => now(),
                    'status'         => 'confirmed',
                ]);

                $room = ChatRoom::where('student_id', $meeting->student_id)
                    ->where('guardian_id', $meeting->guardian_id)->first();
                if ($room) {
                    $dateStr = Carbon::parse($validated['confirmed_date'])->format($dateFormat);
                    ChatMessage::create([
                        'room_id'            => $room->id,
                        'sender_type'        => 'staff',
                        'sender_id'          => $user->id,
                        'message'            => "【面談日時が確定しました】\n\n面談目的：{$meeting->purpose}\n確定日時：{$dateStr}\n\n当日はよろしくお願いいたします。",
                        'message_type'       => 'meeting_confirmed',
                        'meeting_request_id' => $meeting->id,
                    ]);
                    $room->update(['last_message_at' => now()]);
                }
            } elseif ($action === 'counter' && !empty($validated['candidate_dates'])) {
                // スタッフが再提案
                $meeting->update([
                    'candidate_dates'       => $validated['candidate_dates'],
                    'staff_counter_message'  => $validated['staff_counter_message'] ?? null,
                    'status'                 => 'staff_counter',
                ]);

                $room = ChatRoom::where('student_id', $meeting->student_id)
                    ->where('guardian_id', $meeting->guardian_id)->first();
                if ($room) {
                    $circleNumbers = ['①', '②', '③'];
                    $messageText = "【面談日程の再調整】\n\n以下の日程はいかがでしょうか。\n\n";
                    foreach ($validated['candidate_dates'] as $i => $date) {
                        $formatted = Carbon::parse($date)->format($dateFormat);
                        $messageText .= ($circleNumbers[$i] ?? ($i + 1) . '.') . " {$formatted}\n";
                    }
                    if (!empty($validated['staff_counter_message'])) {
                        $messageText .= "\nメッセージ：{$validated['staff_counter_message']}";
                    }

                    ChatMessage::create([
                        'room_id'            => $room->id,
                        'sender_type'        => 'staff',
                        'sender_id'          => $user->id,
                        'message'            => $messageText,
                        'message_type'       => 'meeting_counter',
                        'meeting_request_id' => $meeting->id,
                    ]);
                    $room->update(['last_message_at' => now()]);
                }
            } elseif ($action === 'cancel') {
                $meeting->update(['status' => 'cancelled']);
            } else {
                // 通常更新（メモ等）
                if (isset($validated['confirmed_date'])) {
                    $validated['status'] = 'confirmed';
                    $validated['confirmed_by'] = 'staff';
                    $validated['confirmed_at'] = now();
                }
                $meeting->update($validated);
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $meeting->fresh(['student', 'guardian', 'staff']),
            'message' => '面談リクエストを更新しました。',
        ]);
    }
}
