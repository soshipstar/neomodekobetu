<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    /**
     * 公開済みお便り一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Newsletter::where('status', 'published');

        // R2: 保護者が複数教室の児童を持つ場合に対応するため、児童経由で取得した
        // 教室IDの集合 (accessibleClassroomIds) で必ず絞り込む(空なら 0 件)。
        // 以前は空のとき未絞り込みで全教室の公開お便りが漏れて閲覧できていた。
        $classroomIds = $user->accessibleClassroomIds();
        $query->whereIn('classroom_id', $classroomIds);

        $newsletters = $query->orderByDesc('published_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $newsletters,
        ]);
    }

    /**
     * お便り詳細を取得
     */
    public function show(Request $request, Newsletter $newsletter): JsonResponse
    {
        if ($newsletter->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'このお便りは公開されていません。',
            ], 404);
        }

        // R2: 保護者がアクセス可能な教室のお便りでなければ 403
        $user = $request->user();
        // 在籍児童の教室のお便りのみ閲覧可(児童未紐付け=空なら一律 403)。
        // 以前は空のときガードが素通りし、任意の公開お便りを ID 指定で閲覧できた。
        $classroomIds = $user->accessibleClassroomIds();
        if (! in_array((int) $newsletter->classroom_id, $classroomIds, true)) {
            return response()->json([
                'success' => false,
                'message' => 'このお便りを閲覧する権限がありません。',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $newsletter,
        ]);
    }
}
