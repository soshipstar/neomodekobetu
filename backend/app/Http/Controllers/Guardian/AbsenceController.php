<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    /**
     * 欠席連絡一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id');

        $absences = AbsenceNotification::whereIn('student_id', $studentIds)
            ->with('student:id,student_name')
            ->orderByDesc('absence_date')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $absences,
        ]);
    }

    /**
     * 欠席連絡を送信（振替依頼含む）
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id'          => 'required|exists:students,id',
            'absence_date'        => 'required|date|after_or_equal:today',
            'reason'              => 'required|string|max:1000',
            'makeup_request'      => 'boolean',
            'makeup_request_date' => 'required_if:makeup_request,true|nullable|date|after:absence_date',
        ]);

        // 保護者の子どもか確認
        $studentIds = $user->students()->pluck('id')->toArray();
        if (! in_array($validated['student_id'], $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        // 同日の重複チェック
        $existing = AbsenceNotification::where('student_id', $validated['student_id'])
            ->where('absence_date', $validated['absence_date'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'この日はすでに欠席連絡が登録されています。',
            ], 422);
        }

        $absence = AbsenceNotification::create([
            'student_id'          => $validated['student_id'],
            'guardian_id'         => $user->id,
            'absence_date'        => $validated['absence_date'],
            'reason'              => $validated['reason'],
            'makeup_request_date' => $validated['makeup_request_date'] ?? null,
            'makeup_status'       => ! empty($validated['makeup_request']) ? 'pending' : null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $absence,
            'message' => '欠席連絡を送信しました。',
        ], 201);
    }
}
