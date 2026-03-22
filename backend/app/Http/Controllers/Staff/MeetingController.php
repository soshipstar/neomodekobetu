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
            'confirmed_date'  => 'nullable|date',
            'status'          => 'nullable|string|in:pending,confirmed,cancelled,guardian_counter',
            'purpose_detail'  => 'nullable|string',
            'candidate_dates' => 'nullable|array|max:3',
            'candidate_dates.*' => 'nullable|date',
            'meeting_notes'   => 'nullable|string',
            'meeting_guidance' => 'nullable|string',
        ]);

        if (isset($validated['confirmed_date'])) {
            $validated['status'] = 'confirmed';
        }

        $meeting->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $meeting->fresh(['student', 'guardian', 'staff']),
            'message' => '面談リクエストを更新しました。',
        ]);
    }
}
