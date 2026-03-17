<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\IndividualSupportPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportPlanController extends Controller
{
    /**
     * 保護者が閲覧可能な支援計画書一覧を取得
     * 下書きは非表示、提出済み・正式版のみ表示
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentIds = $user->students()->pluck('id');

        $plans = IndividualSupportPlan::whereIn('student_id', $studentIds)
            ->where('status', '!=', 'draft')
            ->with(['student:id,student_name', 'details'])
            ->orderByDesc('created_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    /**
     * 支援計画書に対してレビューコメントを送信
     */
    public function review(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $user = $request->user();

        // 保護者の子どもの計画か確認
        $studentIds = $user->students()->pluck('id')->toArray();
        if (! in_array($plan->student_id, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'comment' => 'present|nullable|string|max:2000',
        ]);

        $plan->update([
            'guardian_review_comment'    => $request->comment,
            'guardian_review_comment_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh(),
            'message' => 'コメントを送信しました。',
        ]);
    }

    /**
     * 支援計画書に電子署名を行う
     */
    public function sign(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $user = $request->user();

        $studentIds = $user->students()->pluck('id')->toArray();
        if (! in_array($plan->student_id, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'signature' => 'required|string', // base64 image
        ]);

        if (! str_starts_with($request->signature, 'data:image')) {
            return response()->json([
                'success' => false,
                'message' => '署名データが無効です。',
            ], 422);
        }

        $plan->update([
            'guardian_signature'      => $request->signature,
            'guardian_signature_date' => now()->toDateString(),
            'guardian_reviewed_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $plan->fresh(),
            'message' => '署名を保存しました。',
        ]);
    }

    /**
     * 支援計画にコメントを追加
     */
    public function addComment(Request $request, IndividualSupportPlan $plan): JsonResponse
    {
        $user = $request->user();

        $studentIds = $user->students()->pluck('id')->toArray();
        if (! in_array($plan->student_id, $studentIds)) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません。'], 403);
        }

        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        DB::table('support_plan_comments')->insert([
            'plan_id'        => $plan->id,
            'commenter_id'   => $user->id,
            'commenter_type' => 'guardian',
            'comment'        => $request->comment,
            'created_at'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'コメントを送信しました。',
        ]);
    }
}
