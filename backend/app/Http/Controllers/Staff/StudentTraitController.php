<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\StudentTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI学習基盤 S4e: 児童の特性(統制タグ)をスタッフが代理記録する。
 *
 * 特性は要配慮個人情報。統制語彙(StudentTrait)のコードのみを保存し、自由記述は受け付けない。
 * 多次元分析の軸として集計のみに使う(同意済み・k匿名)。プロンプトには既定で入れない。
 *
 * 分類: api
 */
class StudentTraitController extends Controller
{
    /** GET /api/staff/students/{student}/traits */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudent($request->user(), $student);

        return response()->json(['success' => true, 'data' => $this->payload($student)]);
    }

    /** PUT /api/staff/students/{student}/traits {traits: string[]} */
    public function update(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudent($request->user(), $student);

        $validated = $request->validate([
            'traits' => 'present|array',
            'traits.*' => 'string|in:'.implode(',', StudentTrait::codes()),
        ]);

        // 統制コードのみへ正規化して保存(未知コード/自由記述/重複を除去)。
        $student->traits = StudentTrait::sanitize($validated['traits']);
        $student->save();

        return response()->json([
            'success' => true,
            'data' => $this->payload($student->fresh()),
            'message' => '特性を保存しました。',
        ]);
    }

    private function authorizeStudent($user, Student $student): void
    {
        if ($user->classroom_id && ! in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'この児童へのアクセス権限がありません。');
        }
    }

    private function payload(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'available' => StudentTrait::vocabulary(),
            'selected' => StudentTrait::forStudent($student),
        ];
    }
}
