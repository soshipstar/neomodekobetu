<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentClassroomController extends Controller
{
    /**
     * 児童が在籍する教室一覧を取得
     *
     * レスポンスにはフロントエンドが割当可能教室を絞り込むために
     * 児童の主教室の所属企業 (student.classroom.company_id) も含める。
     */
    public function index(Student $student): JsonResponse
    {
        $student->loadMissing('classroom');

        $classrooms = $student->classrooms()
            ->select('classrooms.id', 'classrooms.classroom_name')
            ->orderBy('classrooms.classroom_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'student_id' => $student->id,
                'primary_classroom_id' => $student->classroom_id,
                'company_id' => $student->classroom?->company_id,
                'classroom_ids' => $classrooms->pluck('id')->toArray(),
                'classrooms' => $classrooms,
            ],
        ]);
    }

    /**
     * 児童の所属教室を同期（置換）
     *
     * - 指定される classroom_ids は全て児童の主教室と同じ企業に属する必要がある
     * - 主教室は常に含まれている必要がある（外すと支援の主担当が消える）
     * - 児童の主教室に企業が無い場合は拒否（まず主教室を企業付きにする）
     * - 他企業の教室や所属企業なしの教室が混ざっていたら 422
     */
    public function sync(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'classroom_ids' => 'required|array',
            'classroom_ids.*' => 'integer|exists:classrooms,id',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['classroom_ids'])));

        $student->loadMissing('classroom');
        $studentCompanyId = $student->classroom?->company_id;

        if ($studentCompanyId === null) {
            throw ValidationException::withMessages([
                'classroom_ids' => ['この児童の主教室に所属企業が設定されていないため、複数教室を割り当てできません。先に主教室を企業に所属させてください。'],
            ]);
        }

        // 主教室は必ず含まれていなければならない
        if (!in_array((int) $student->classroom_id, $ids, true)) {
            throw ValidationException::withMessages([
                'classroom_ids' => ['主たる所属教室 (' . ($student->classroom?->classroom_name ?? '不明') . ') を外すことはできません。主教室を変更したい場合は先に児童編集画面で変更してください。'],
            ]);
        }

        // 他企業の教室 / 所属企業なしの教室を拒否
        $conflicting = Classroom::whereIn('id', $ids)
            ->where(function ($q) use ($studentCompanyId) {
                $q->whereNull('company_id')
                  ->orWhere('company_id', '!=', $studentCompanyId);
            })
            ->pluck('classroom_name', 'id');

        if ($conflicting->isNotEmpty()) {
            throw ValidationException::withMessages([
                'classroom_ids' => [
                    '他の企業に属する教室、または所属企業のない教室が含まれています: '
                        . $conflicting->values()->implode('、'),
                ],
            ]);
        }

        $student->classrooms()->sync($ids);

        return response()->json([
            'success' => true,
            'message' => '所属教室を更新しました。',
        ]);
    }
}
