<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    /**
     * 教室の生徒一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $user->classroom_id;

        $query = Student::query()->with('guardian:id,full_name,email');

        if ($classroomId) {
            $query->byClassroom($classroomId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->active();
        }

        $students = $query->orderBy('student_name')->get();

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * 生徒詳細を取得
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $student->load('guardian:id,full_name,email', 'classroom:id,classroom_name');

        return response()->json([
            'success' => true,
            'data'    => $student,
        ]);
    }

    /**
     * 生徒を新規登録
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_name'           => 'required|string|max:255',
            'birth_date'             => 'nullable|date',
            'grade_adjustment'       => 'nullable|integer|min:-2|max:2',
            'guardian_id'            => 'nullable|exists:users,id',
            'support_start_date'     => 'nullable|date',
            'support_plan_start_type' => 'nullable|in:current,next',
            'status'                 => 'nullable|in:active,waiting,withdrawn',
            'username'               => 'nullable|string|max:100|unique:students,username',
            'password'               => 'nullable|string|min:4',
            'scheduled_monday'       => 'nullable|boolean',
            'scheduled_tuesday'      => 'nullable|boolean',
            'scheduled_wednesday'    => 'nullable|boolean',
            'scheduled_thursday'     => 'nullable|boolean',
            'scheduled_friday'       => 'nullable|boolean',
            'scheduled_saturday'     => 'nullable|boolean',
            'scheduled_sunday'       => 'nullable|boolean',
        ]);

        $classroomId = $user->classroom_id;
        $gradeLevel = null;
        if (!empty($validated['birth_date'])) {
            $gradeLevel = self::calculateGradeLevel($validated['birth_date'], $validated['grade_adjustment'] ?? 0);
        }

        $student = Student::create(array_merge($validated, [
            'classroom_id'    => $classroomId,
            'grade_level'     => $gradeLevel,
            'status'          => $validated['status'] ?? 'active',
            'is_active'       => ($validated['status'] ?? 'active') === 'active',
            'password_hash'   => !empty($validated['password']) ? Hash::make($validated['password']) : null,
            'password_plain'  => $validated['password'] ?? null,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $student,
            'message' => '生徒を登録しました。',
        ], 201);
    }

    /**
     * 生徒情報を更新
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $validated = $request->validate([
            'student_name'           => 'nullable|string|max:255',
            'birth_date'             => 'nullable|date',
            'grade_adjustment'       => 'nullable|integer|min:-2|max:2',
            'guardian_id'            => 'nullable|exists:users,id',
            'support_start_date'     => 'nullable|date',
            'support_plan_start_type' => 'nullable|in:current,next',
            'status'                 => 'nullable|in:active,waiting,withdrawn',
            'withdrawal_date'        => 'nullable|date',
            'username'               => 'nullable|string|max:100|unique:students,username,' . $student->id,
            'password'               => 'nullable|string|min:4',
            'scheduled_monday'       => 'nullable|boolean',
            'scheduled_tuesday'      => 'nullable|boolean',
            'scheduled_wednesday'    => 'nullable|boolean',
            'scheduled_thursday'     => 'nullable|boolean',
            'scheduled_friday'       => 'nullable|boolean',
            'scheduled_saturday'     => 'nullable|boolean',
            'scheduled_sunday'       => 'nullable|boolean',
        ]);

        // 学年再計算
        $birthDate = $validated['birth_date'] ?? $student->birth_date;
        if ($birthDate) {
            $adjustment = $validated['grade_adjustment'] ?? $student->grade_adjustment ?? 0;
            $validated['grade_level'] = self::calculateGradeLevel($birthDate, $adjustment);
        }

        // パスワード処理
        if (!empty($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
            $validated['password_plain'] = $validated['password'];
        }
        unset($validated['password']);

        // ステータス変更時のis_active同期
        if (isset($validated['status'])) {
            $validated['is_active'] = $validated['status'] === 'active';
        }

        $student->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $student->fresh('guardian:id,full_name,email', 'classroom:id,classroom_name'),
            'message' => '生徒情報を更新しました。',
        ]);
    }

    /**
     * 生徒を退所処理（ソフトデリート）
     */
    public function destroy(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroom($request->user(), $student);

        $student->update([
            'status'          => 'withdrawn',
            'is_active'       => false,
            'withdrawal_date' => now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '生徒を退所処理しました。',
        ]);
    }

    /**
     * 保護者一覧を取得（生徒登録時の選択用）
     */
    public function guardians(Request $request): JsonResponse
    {
        $user = $request->user();

        $guardians = User::where('user_type', 'guardian')
            ->when($user->classroom_id, function ($q) use ($user) {
                $q->where('classroom_id', $user->classroom_id);
            })
            ->where('is_active', true)
            ->select('id', 'full_name', 'email')
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $guardians,
        ]);
    }

    /**
     * 生年月日と学年調整値から学年区分を計算
     */
    public static function calculateGradeLevel(string $birthDate, int $adjustment = 0): string
    {
        $birth = Carbon::parse($birthDate);
        $now = Carbon::now();

        // 4月1日基準の年齢計算（4/2生まれ以降は翌年度の扱い）
        $fiscalYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $birthFiscalYear = $birth->month >= 4 || ($birth->month === 4 && $birth->day >= 2)
            ? $birth->year
            : $birth->year - 1;

        $gradeNumber = $fiscalYear - $birthFiscalYear - 6 + $adjustment;

        if ($gradeNumber < 0) return 'preschool';
        if ($gradeNumber === 0) return 'preschool';
        if ($gradeNumber >= 1 && $gradeNumber <= 6) return 'elementary_' . $gradeNumber;
        if ($gradeNumber >= 7 && $gradeNumber <= 9) return 'junior_high_' . ($gradeNumber - 6);
        if ($gradeNumber >= 10 && $gradeNumber <= 12) return 'high_school_' . ($gradeNumber - 9);

        return 'high_school_3'; // 卒業生以上
    }

    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id && $student->classroom_id !== $user->classroom_id) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
