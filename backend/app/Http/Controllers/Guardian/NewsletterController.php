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

        if ($user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        }

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
    public function show(Newsletter $newsletter): JsonResponse
    {
        if ($newsletter->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'このお便りは公開されていません。',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $newsletter,
        ]);
    }
}
