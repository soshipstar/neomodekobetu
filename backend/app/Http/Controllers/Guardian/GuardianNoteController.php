<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\IntegratedNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianNoteController extends Controller
{
    /**
     * 保護者が閲覧可能な連絡帳一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id');

        $query = IntegratedNote::whereIn('student_id', $studentIds)
            ->where('is_sent', true)
            ->with([
                'student:id,student_name',
                'dailyRecord:id,record_date,activity_name',
            ]);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $notes = $query->orderByDesc('sent_at')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $notes,
        ]);
    }

    /**
     * 特定日付の連絡帳を取得
     */
    public function byDate(Request $request, string $date): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id');

        $notes = IntegratedNote::whereIn('student_id', $studentIds)
            ->where('is_sent', true)
            ->whereHas('dailyRecord', function ($q) use ($date) {
                $q->where('record_date', $date);
            })
            ->with([
                'student:id,student_name',
                'dailyRecord:id,record_date,activity_name',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $notes,
        ]);
    }

    /**
     * 連絡帳を確認済みにする
     */
    public function confirm(Request $request, IntegratedNote $note): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id')->toArray();

        if (! in_array($note->student_id, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        if (! $note->is_sent) {
            return response()->json(['success' => false, 'message' => 'この連絡帳はまだ送信されていません。'], 422);
        }

        if ($note->guardian_confirmed) {
            return response()->json(['success' => false, 'message' => 'この連絡帳は既に確認済みです。'], 422);
        }

        $note->update([
            'guardian_confirmed'    => true,
            'guardian_confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '確認しました。',
        ]);
    }
}
