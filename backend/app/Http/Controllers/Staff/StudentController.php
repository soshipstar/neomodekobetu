<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\AssessmentService;
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
            // 待機児童 (status=waiting) は生年月日が未確定なケースがあるので
            // その場合のみ任意。待機以外 (active/trial/short_term/withdrawn) は必須。
            'birth_date'             => 'required_unless:status,waiting|nullable|date',
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
        // 待機児童 (status=waiting) は生年月日未入力で登録できるようにしたため、
        // birth_date が空のときは grade_level の自動計算をスキップする。
        // 一方 students.grade_level カラムは NOT NULL + DEFAULT 'elementary' のため、
        // 計算できない場合は明示的に default 'elementary' を入れる
        // (実運用ではコメント参照として表示。後で生年月日が判明したら再計算される)。
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
            // null だと NOT NULL 制約に引っかかるので default 'elementary' に
            'grade_level'     => $gradeLevel ?? 'elementary',
            'status'          => $status,
            'is_active'       => $isActive,
            'password_hash'   => $password ? Hash::make($password) : null,
            'password_plain'  => $password,
        ]));

        if (! empty($validated['guardian_id'])) {
            ChatRoom::firstOrCreate([
                'student_id'  => $student->id,
                'guardian_id' => $validated['guardian_id'],
            ]);
        }

        // アセスメント期間の自動生成（待機児童以外、かつ「現在の期間から作成する」の場合のみ）
        $supportPlanStartType = $validated['support_plan_start_type'] ?? 'current';
        if ($status !== 'waiting' && !empty($validated['support_start_date']) && $supportPlanStartType === 'current') {
            try {
                $assessmentService = app(AssessmentService::class);
                $generatedPeriods = $assessmentService->generateAssessmentPeriodsForStudent($student->id, $validated['support_start_date']);
                Log::info("Generated " . count($generatedPeriods) . " assessment periods for student {$student->id}");
            } catch (\Exception $e) {
                Log::error("アセスメント期間生成エラー: " . $e->getMessage());
            }
        } elseif ($status !== 'waiting' && $supportPlanStartType === 'next') {
            Log::info("Student {$student->id} has support_plan_start_type='next'. Skipping initial assessment generation.");
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

        $hadNoSupportDate = empty($student->support_start_date);
        $previousGuardianId = $student->guardian_id;
        $student->update($validated);

        // バグ報告 + ユーザー要望:
        //  (1) guardian_id を後から付与した active 生徒に chat_room が作られず
        //      保護者画面にチャットが現れない (例 id=266 石田洋将) → firstOrCreate で保証
        //  (2) 「紐づけを変え保存をしたら旧チャットルームは削除するようにしましょう」
        //      旧保護者用 chat_room と中のメッセージを整理する。
        //      FE 側で「既存の保護者チャットは消されることを警告」してから保存される想定。
        $newGuardianId = $student->guardian_id ? (int) $student->guardian_id : null;
        $prevGuardianId = $previousGuardianId ? (int) $previousGuardianId : null;

        if ($prevGuardianId !== $newGuardianId) {
            // 旧保護者の chat_room を削除 (CASCADE で messages も連動削除)
            if ($prevGuardianId !== null) {
                ChatRoom::where('student_id', $student->id)
                    ->where('guardian_id', $prevGuardianId)
                    ->delete();
            }
        }

        // 現在 guardian_id が set されていれば必ず chat_room を保証
        if ($newGuardianId !== null) {
            ChatRoom::firstOrCreate([
                'student_id'  => $student->id,
                'guardian_id' => $newGuardianId,
            ]);
        }

        // support_start_dateが新たに設定された場合、アセスメント期間を自動生成
        if ($hadNoSupportDate && !empty($validated['support_start_date'])) {
            $periodCount = \App\Models\AssessmentPeriod::where('student_id', $student->id)->count();
            if ($periodCount === 0) {
                try {
                    $assessmentService = app(\App\Services\AssessmentService::class);
                    $assessmentService->generateAssessmentPeriodsForStudent($student->id, $validated['support_start_date']);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("アセスメント生成エラー（更新時）: " . $e->getMessage());
                }
            }
        }

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
     *
     * 設計思想:
     *   「保護者は教室属性を持たず、企業 (company) に属する」が正しい設計。
     *   ただし現実装は legacy で users.classroom_id を保護者にも持たせて
     *   いる + classroom_user pivot で多対多も併存 + 児童 (students) 経由でも
     *   関連付け可能、という 3 経路が並走している。
     *
     *   そのため「同企業内のどこかの教室と関連付くか」を以下 3 経路の OR で
     *   判定し、設計のズレを吸収する:
     *     (a) 保護者の児童が同企業内の教室にいる    (students.classroom_id 経由)
     *     (b) classroom_user pivot に同企業の教室がある (多対多経由)
     *     (c) users.classroom_id が同企業内 (legacy 1対1)
     *
     *   この方式なら保護者の users.classroom_id がどこに設定されていても、
     *   その人の児童が我が社の教室にいれば候補に出る。
     *
     * レスポンス:
     *   FE 表示用に classroom_name (児童在籍教室名の代表 or primary) も同梱。
     */
    public function guardians(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = (bool) ($user->is_master ?? false);

        $query = User::where('user_type', 'guardian')
            ->where('is_active', true)
            ->with('classroom:id,classroom_name');

        if (!$isMaster) {
            $user->loadMissing('classroom');
            $companyId = $user->classroom?->company_id;
            if ($companyId !== null) {
                $companyClassroomIds = Classroom::where('company_id', $companyId)
                    ->pluck('id')->all();

                // 3 経路の OR (詳細は phpdoc 参照)
                $query->where(function ($q) use ($companyClassroomIds) {
                    // (a) 児童経由
                    $q->whereIn('id', function ($sub) use ($companyClassroomIds) {
                        $sub->select('guardian_id')
                            ->from('students')
                            ->whereIn('classroom_id', $companyClassroomIds)
                            ->whereNotNull('guardian_id');
                    })
                    // (b) classroom_user pivot 経由
                    ->orWhereIn('id', function ($sub) use ($companyClassroomIds) {
                        $sub->select('user_id')
                            ->from('classroom_user')
                            ->whereIn('classroom_id', $companyClassroomIds);
                    })
                    // (c) users.classroom_id (legacy 1対1)
                    ->orWhereIn('classroom_id', $companyClassroomIds);
                });
            } elseif ($user->classroom_id) {
                $query->where('classroom_id', $user->classroom_id);
            } else {
                $query->whereRaw('1=0');
            }
        }

        // 児童在籍教室の名前を別途取得して表示ラベルに使う
        // (users.classroom_id ベースだと、児童だけが別教室の保護者で
        //  「[教室名なし]」になり判別不能になる)
        $guardians = $query
            ->select('id', 'full_name', 'email', 'classroom_id')
            ->orderBy('full_name')
            ->get();

        // 各保護者の児童が在籍している教室名を全て取得 (FE表示用)
        $guardianIds = $guardians->pluck('id')->all();
        $studentClassrooms = Student::whereIn('guardian_id', $guardianIds)
            ->whereNotNull('classroom_id')
            ->with('classroom:id,classroom_name')
            ->get(['id', 'guardian_id', 'classroom_id'])
            ->groupBy('guardian_id');

        $data = $guardians->map(function ($g) use ($studentClassrooms) {
            $childClassroomNames = ($studentClassrooms[$g->id] ?? collect())
                ->pluck('classroom.classroom_name')
                ->filter()
                ->unique()
                ->values()
                ->all();
            // 表示優先順: 児童在籍教室 > primary classroom_id
            $displayName = !empty($childClassroomNames)
                ? implode(' / ', $childClassroomNames)
                : ($g->classroom?->classroom_name);
            return [
                'id'             => $g->id,
                'full_name'      => $g->full_name,
                'email'          => $g->email,
                'classroom_id'   => $g->classroom_id,
                'classroom_name' => $displayName, // 児童の在籍教室を優先
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
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
            && !in_array($student->classroom_id, $user->switchableClassroomIds(), true)) {
            abort(403, 'この生徒へのアクセス権限がありません。');
        }
    }
}
