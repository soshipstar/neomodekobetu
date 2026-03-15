<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HiddenDocumentController extends Controller
{
    /**
     * 非表示ドキュメント一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        try {
            $query = DB::table('hidden_documents')
                ->select('id', 'document_type', 'document_id', 'hidden_by', 'created_at');

            if ($classroomId) {
                $query->where('classroom_id', $classroomId);
            }

            $documents = $query->orderByDesc('created_at')->get();

            return response()->json([
                'success' => true,
                'data'    => $documents,
            ]);
        } catch (\Exception $e) {
            Log::warning('hidden_documents table not available: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data'    => [],
            ]);
        }
    }

    /**
     * ドキュメントの表示/非表示を切り替え
     */
    public function toggle(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'document_type' => 'required|string|in:newsletter,support_plan,monitoring,kakehashi',
            'document_id'   => 'required|integer',
        ]);

        try {
            $existing = DB::table('hidden_documents')
                ->where('document_type', $validated['document_type'])
                ->where('document_id', $validated['document_id'])
                ->where('classroom_id', $user->classroom_id)
                ->first();

            if ($existing) {
                DB::table('hidden_documents')->where('id', $existing->id)->delete();
                $hidden = false;
            } else {
                DB::table('hidden_documents')->insert([
                    'document_type' => $validated['document_type'],
                    'document_id'   => $validated['document_id'],
                    'classroom_id'  => $user->classroom_id,
                    'hidden_by'     => $user->id,
                    'created_at'    => now(),
                ]);
                $hidden = true;
            }

            return response()->json([
                'success'   => true,
                'is_hidden' => $hidden,
                'message'   => $hidden ? '非表示にしました。' : '表示に戻しました。',
            ]);
        } catch (\Exception $e) {
            Log::warning('hidden_documents table not available: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'この機能は現在利用できません。',
            ], 503);
        }
    }
}
