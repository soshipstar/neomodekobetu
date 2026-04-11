<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IndividualSupportPlan;
use App\Models\KakehashiPeriod;
use App\Models\MonitoringRecord;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HiddenDocumentController extends Controller
{
    /**
     * 非表示ドキュメント一覧を取得
     * ドキュメントの詳細情報（生徒名、タイトルなど）を含む
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;
        $docTypeFilter = $request->input('document_type');

        try {
            $query = DB::table('hidden_documents')
                ->select('id', 'document_type', 'document_id', 'hidden_by', 'created_at');

            if ($classroomId) {
                $query->whereIn('classroom_id', $user->accessibleClassroomIds());
            }

            if ($docTypeFilter) {
                $query->where('document_type', $docTypeFilter);
            }

            $documents = $query->orderByDesc('created_at')->get();

            // ドキュメント詳細を付加
            $enriched = $documents->map(function ($doc) {
                $detail = $this->resolveDocumentDetail($doc->document_type, $doc->document_id);
                $hiddenByName = $doc->hidden_by
                    ? User::where('id', $doc->hidden_by)->value('full_name')
                    : null;

                return [
                    'id'              => $doc->id,
                    'document_type'   => $doc->document_type,
                    'document_id'     => $doc->document_id,
                    'student_name'    => $detail['student_name'] ?? null,
                    'document_title'  => $detail['title'] ?? null,
                    'document_date'   => $detail['date'] ?? null,
                    'hidden_by'       => $doc->hidden_by,
                    'hidden_by_name'  => $hiddenByName,
                    'created_at'      => $doc->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $enriched,
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

    /**
     * ドキュメントタイプとIDから詳細情報を取得
     */
    private function resolveDocumentDetail(string $type, int $id): array
    {
        try {
            switch ($type) {
                case 'support_plan':
                    $plan = IndividualSupportPlan::with('student:id,student_name')->find($id);
                    if ($plan) {
                        return [
                            'student_name' => $plan->student?->student_name,
                            'title'        => '個別支援計画書',
                            'date'         => $plan->created_date ?? $plan->created_at?->toDateString(),
                        ];
                    }
                    break;

                case 'monitoring':
                    $record = MonitoringRecord::with('student:id,student_name')->find($id);
                    if ($record) {
                        return [
                            'student_name' => $record->student?->student_name,
                            'title'        => 'モニタリング記録',
                            'date'         => $record->monitoring_date ?? $record->created_at?->toDateString(),
                        ];
                    }
                    break;

                case 'kakehashi':
                    $period = KakehashiPeriod::with('student:id,student_name')->find($id);
                    if ($period) {
                        return [
                            'student_name' => $period->student?->student_name,
                            'title'        => 'かけはし',
                            'date'         => $period->start_date ?? $period->created_at?->toDateString(),
                        ];
                    }
                    break;

                case 'newsletter':
                    $newsletter = Newsletter::find($id);
                    if ($newsletter) {
                        return [
                            'student_name' => null,
                            'title'        => $newsletter->title ?? 'お便り',
                            'date'         => $newsletter->created_at?->toDateString(),
                        ];
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::debug('Failed to resolve document detail', [
                'type' => $type,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'student_name' => null,
            'title'        => $this->getDocumentTypeLabel($type),
            'date'         => null,
        ];
    }

    /**
     * ドキュメントタイプの表示ラベルを取得
     */
    private function getDocumentTypeLabel(string $type): string
    {
        return match ($type) {
            'support_plan' => '個別支援計画書',
            'monitoring'   => 'モニタリング記録',
            'kakehashi'    => 'かけはし',
            'newsletter'   => 'お便り',
            default        => $type,
        };
    }
}
