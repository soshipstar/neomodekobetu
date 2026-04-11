<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Services\KakehashiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    /**
     * 教室の生徒一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Student::query()->with('guardian:id,full_name,email');

        // 主教室 + classroom_user ピボットで所属する全教室を対象にする
        if ($user->classroom_id) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
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
            if ($request->status === 'all') {
                // 全ステータス
            } else {
                $query->where('status', $request->status);
            }
        } else {
            $query->where('is_active', true);
        }

        $students = $query->orderByDesc('is_active')->orderBy('student_name')->get();

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
            'birth_date'             => 'required|date',
            'grade_adjustment'       => 'nullable|integer|min:-2|max:2',
            'guardian_id'            => 'nullable|exists:users,id',
            'support_start_date'     => 'nullable|date',
            'support_plan_start_type' => 'nullable|in:current,next',
            'status'                 => 'nullable|in:active,trial,short_term,waiting,withdrawn',
            'withdrawal_date'        => 'nullable|date',
            'username'               => 'nullable|string|max:100|unique:students,username',
            'password'               => 'nullable|string|min:4',
            'scheduled_monday'       => 'nullable|boolean',
            'scheduled_tuesday'      => 'nullable|boolean',
            'scheduled_wednesday'    => 'nullable|boolean',
            'scheduled_thursday'     => 'nullable|boolean',
            'scheduled_friday'       => 'nullable|boolean',
            'scheduled_saturday'     => 'nullable|boolean',
            'scheduled_sunday'       => 'nullable|boolean',
            'desired_start_date'     => 'nullable|date',
            'desired_weekly_count'   => 'nullable|integer|min:1|max:5',
            'desired_monday'         => 'nullable|boolean',
            'desired_tuesday'        => 'nullable|boolean',
            'desired_wednesday'      => 'nullable|boolean',
            'desired_thursday'       => 'nullable|boolean',
            'desired_friday'         => 'nullable|boolean',
            'desired_saturday'       => 'nullable|boolean',
            'desired_sunday'         => 'nullable|boolean',
            'waiting_notes'          => 'nullable|string|max:1000',
        ]);

        $status = $validated['status'] ?? 'active';

        // 待機児童の場合は支援開始日が未設定なら仮の値を設定
        if ($status === 'waiting' && empty($validated['support_start_date'])) {
            $validated['support_start_date'] = $validated['desired_start_date'] ?? now()->toDateString();
        }

        $classroomId = $user->classroom_id;
        $gradeLevel = null;
        if (!empty($validated['birth_date'])) {
            $gradeLevel = self::calculateGradeLevel($validated['birth_date'], $validated['grade_adjustment'] ?? 0);
        }

        // Remove password from validated (stored separately as password_hash)
        $password = $validated['password'] ?? null;
        unset($validated['password']);

        // is_active: waiting と withdrawn は無効、それ以外は有効
        $isActive = !in_array($status, ['waiting', 'withdrawn']);

        $student = Student::create(array_merge($validated, [
            'classroom_id'    => $classroomId,
            'grade_level'     => $gradeLevel,
            'status'          => $status,
            'is_active'       => $isActive,
            'password_hash'   => $password ? Hash::make($password) : null,
            'password_plain'  => $password,
        ]));

        // かけはし期間の自動生成（待機児童以外、かつ「現在の期間から作成する」の場合のみ）
        $supportPlanStartType = $validated['support_plan_start_type'] ?? 'current';
        if ($status !== 'waiting' && !empty($validated['support_start_date']) && $supportPlanStartType === 'current') {
            try {
                $kakehashiService = app(KakehashiService::class);
                $generatedPeriods = $kakehashiService->generateKakehashiPeriodsForStudent($student->id, $validated['support_start_date']);
                Log::info("Generated " . count($generatedPeriods) . " kakehashi periods for student {$student->id}");
            } catch (\Exception $e) {
                Log::error("かけはし期間生成エラー: " . $e->getMessage());
            }
        } elseif ($status !== 'waiting' && $supportPlanStartType === 'next') {
            Log::info("Student {$student->id} has support_plan_start_type='next'. Skipping initial kakehashi generation.");
        }

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
            'status'                 => 'nullable|in:active,trial,short_term,waiting,withdrawn',
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
            'desired_start_date'     => 'nullable|date',
            'desired_weekly_count'   => 'nullable|integer|min:1|max:5',
            'desired_monday'         => 'nullable|boolean',
            'desired_tuesday'        => 'nullable|boolean',
            'desired_wednesday'      => 'nullable|boolean',
            'desired_thursday'       => 'nullable|boolean',
            'desired_friday'         => 'nullable|boolean',
            'desired_saturday'       => 'nullable|boolean',
            'desired_sunday'         => 'nullable|boolean',
            'waiting_notes'          => 'nullable|string|max:1000',
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
        } elseif (array_key_exists('username', $validated) && empty($validated['username'])) {
            // ユーザー名が空の場合はログイン情報をクリア
            $validated['username'] = null;
            $validated['password_hash'] = null;
            $validated['password_plain'] = null;
        }
        unset($validated['password']);

        // ステータス変更時のis_active同期（waiting, withdrawnは無効）
        if (isset($validated['status'])) {
            $validated['is_active'] = !in_array($validated['status'], ['waiting', 'withdrawn']);
        }

        // 退所日は退所ステータスの時のみ、それ以外はNULL
        if (isset($validated['status']) && $validated['status'] !== 'withdrawn') {
            $validated['withdrawal_date'] = null;
        }

        // 待機児童の場合、支援開始日が未設定なら仮の値を設定
        if (isset($validated['status']) && $validated['status'] === 'waiting' && empty($validated['support_start_date']) && empty($student->support_start_date)) {
            $validated['support_start_date'] = $validated['desired_start_date'] ?? now()->toDateString();
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
                $q->whereIn('classroom_id', $user->accessibleClassroomIds());
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
     *
     * 4月1日生まれは前年度扱い（早生まれの最後）
     * legacy: student_helper.php の calculateGradeLevel と同じロジック
     */
    public static function calculateGradeLevel(string $birthDate, int $adjustment = 0): string
    {
        $birth = Carbon::parse($birthDate);
        $now = Carbon::now();

        // 現在の年度を計算（4月1日基準）
        $fiscalYear = $now->month >= 4 ? $now->year : $now->year - 1;

        // 誕生年度を計算（4月2日～翌年4月1日が同じ年度）
        // 4月1日生まれは前年度扱い（早生まれの最後）
        if ($birth->month < 4 || ($birth->month == 4 && $birth->day == 1)) {
            $birthFiscalYear = $birth->year - 1;
        } else {
            $birthFiscalYear = $birth->year;
        }

        // その年度での学年を計算
        $gradeYear = $fiscalYear - $birthFiscalYear;

        // 学年調整を適用
        $gradeYear += $adjustment;

        // 詳細な学年を返す
        if ($gradeYear < 7) {
            return 'preschool';
        } elseif ($gradeYear >= 7 && $gradeYear <= 12) {
            $grade = $gradeYear - 6;
            return 'elementary_' . $grade;
        } elseif ($gradeYear >= 13 && $gradeYear <= 15) {
            $grade = $gradeYear - 12;
            return 'junior_high_' . $grade;
        } elseif ($gradeYear >= 16 && $gradeYear <= 18) {
            $grade = $gradeYear - 15;
            return 'high_school_' . $grade;
        } else {
            return 'high_school_3';
        }
    }

    private function authorizeClassroom($user, Student $student): void
    {
        if ($user->classroom_id
            && !in_array($student->classroom_id, $user->accessibleClassroomIds(), true)) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
