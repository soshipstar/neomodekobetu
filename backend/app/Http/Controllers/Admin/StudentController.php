<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentHelperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /**
     * 生徒一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        $query = Student::with(['classroom', 'guardian']);

        // 非マスター管理者は自教室のみ
        if (!$isMaster && $user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        } elseif ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('student_name', 'like', "%{$request->search}%");
        }

        $students = $query->orderBy('student_name')->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * 生徒を新規作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id'         => 'required|exists:classrooms,id',
            'student_name'         => 'required|string|max:255',
            'username'             => 'required|string|max:100|unique:students',
            'password'             => 'required|string|min:4',
            'birth_date'           => 'nullable|date',
            'grade_level'          => 'nullable|string|max:50',
            'guardian_id'          => 'nullable|exists:users,id',
            'status'               => ['nullable', Rule::in(['active', 'waiting', 'withdrawn'])],
            'scheduled_monday'     => 'boolean',
            'scheduled_tuesday'    => 'boolean',
            'scheduled_wednesday'  => 'boolean',
            'scheduled_thursday'   => 'boolean',
            'scheduled_friday'     => 'boolean',
            'scheduled_saturday'   => 'boolean',
            'scheduled_sunday'     => 'boolean',
        ]);

        $validated['password_hash'] = Hash::make($validated['password']);
        unset($validated['password']);
        $validated['status'] = $validated['status'] ?? 'active';

        $student = Student::create($validated);

        // 主教室を必ず classroom_student pivot にも登録しておく
        // （以降 /api/admin/students/{student}/classrooms で複数教室を追加可能）
        $student->classrooms()->syncWithoutDetaching([$student->classroom_id]);

        return response()->json([
            'success' => true,
            'data'    => $student->load(['classroom', 'guardian']),
            'message' => '生徒を登録しました。',
        ], 201);
    }

    /**
     * 生徒詳細を取得
     */
    public function show(Student $student): JsonResponse
    {
        $student->load(['classroom', 'guardian', 'supportPlans', 'monitoringRecords']);

        return response()->json([
            'success' => true,
            'data'    => $student,
        ]);
    }

    /**
     * 生徒を更新
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'classroom_id'         => 'sometimes|required|exists:classrooms,id',
            'student_name'         => 'sometimes|required|string|max:255',
            'username'             => ['sometimes', 'required', 'string', 'max:100', Rule::unique('students')->ignore($student->id)],
            'password'             => 'nullable|string|min:4',
            'birth_date'           => 'nullable|date',
            'grade_level'          => 'nullable|string|max:50',
            'guardian_id'          => 'nullable|exists:users,id',
            'status'               => [Rule::in(['active', 'waiting', 'withdrawn'])],
            'scheduled_monday'     => 'boolean',
            'scheduled_tuesday'    => 'boolean',
            'scheduled_wednesday'  => 'boolean',
            'scheduled_thursday'   => 'boolean',
            'scheduled_friday'     => 'boolean',
            'scheduled_saturday'   => 'boolean',
            'scheduled_sunday'     => 'boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
        }
        unset($validated['password']);

        $student->update($validated);

        // 主教室が pivot に無ければ追加する（主教室は常に所属教室に含まれる）
        if (isset($validated['classroom_id'])) {
            $student->classrooms()->syncWithoutDetaching([$validated['classroom_id']]);
        }

        return response()->json([
            'success' => true,
            'data'    => $student->fresh(['classroom', 'guardian']),
            'message' => '生徒情報を更新しました。',
        ]);
    }

    /**
     * 学年更新プレビュー: 全active生徒のgrade_levelを再計算し、変更がある生徒を返す
     */
    public function gradePromotionPreview(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;
        $helper = app(StudentHelperService::class);

        $query = Student::whereIn('status', ['active', 'trial', 'short_term'])
            ->whereNotNull('birth_date');

        if (!$isMaster && $user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        }

        $students = $query->orderBy('student_name')->get();

        $changes = [];
        foreach ($students as $student) {
            $newGrade = $helper->calculateGradeLevel(
                $student->birth_date->toDateString(),
                null,
                $student->grade_adjustment ?? 0
            );
            if ($newGrade !== $student->grade_level) {
                $changes[] = [
                    'id' => $student->id,
                    'student_name' => $student->student_name,
                    'old_grade' => $student->grade_level,
                    'new_grade' => $newGrade,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $changes,
            'total_students' => $students->count(),
            'change_count' => count($changes),
        ]);
    }

    /**
     * 学年更新実行: 全active生徒のgrade_levelを再計算して更新
     */
    public function gradePromotionExecute(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;
        $helper = app(StudentHelperService::class);

        $query = Student::whereIn('status', ['active', 'trial', 'short_term'])
            ->whereNotNull('birth_date');

        if (!$isMaster && $user->classroom_id) {
            $query->where('classroom_id', $user->classroom_id);
        }

        $students = $query->get();
        $updatedCount = 0;

        foreach ($students as $student) {
            $newGrade = $helper->calculateGradeLevel(
                $student->birth_date->toDateString(),
                null,
                $student->grade_adjustment ?? 0
            );
            if ($newGrade !== $student->grade_level) {
                $student->update(['grade_level' => $newGrade]);
                $updatedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => "{$updatedCount}名の学年を更新しました。",
        ]);
    }

    /**
     * 生徒を削除（退所扱い）
     */
    public function destroy(Student $student): JsonResponse
    {
        $student->update(['status' => 'withdrawn']);

        return response()->json([
            'success' => true,
            'message' => '生徒を退所扱いにしました。',
        ]);
    }
}
