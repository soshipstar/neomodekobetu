<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\StudentHelperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        // 非マスター管理者はアクセス可能な教室のみ
        if (!$isMaster) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }
        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('student_name', 'like', "%{$request->search}%");
        }

        // 並び替え: sort=kana(既定=ふりがな/あいうえお順) / grade(学年順)。dir=asc|desc。
        $dir = strtolower((string) $request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($request->input('sort') === 'grade') {
            $query->orderByRaw(self::gradeOrderSql() . " {$dir}")
                  ->orderBy('student_name_kana')
                  ->orderBy('student_name');
        } else {
            // ふりがな優先で50音順 (未設定は漢字氏名でフォールバック)
            $query->orderBy('student_name_kana', $dir)
                  ->orderBy('student_name', $dir);
        }

        $students = $query->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * 学年(grade_level)を正しい序列で並べるための SQL CASE 式。
     * grade_level は文字列のため辞書順では未就学→小→中→高 にならない。
     * (orderByRaw 用。固定文字列のみで動的入力は含まないため安全)
     */
    private static function gradeOrderSql(): string
    {
        return "CASE grade_level
            WHEN 'preschool' THEN 0
            WHEN 'elementary' THEN 1
            WHEN 'elementary_1' THEN 1 WHEN 'elementary_2' THEN 2 WHEN 'elementary_3' THEN 3
            WHEN 'elementary_4' THEN 4 WHEN 'elementary_5' THEN 5 WHEN 'elementary_6' THEN 6
            WHEN 'junior_high' THEN 7
            WHEN 'junior_high_1' THEN 7 WHEN 'junior_high_2' THEN 8 WHEN 'junior_high_3' THEN 9
            WHEN 'high_school' THEN 10
            WHEN 'high_school_1' THEN 10 WHEN 'high_school_2' THEN 11 WHEN 'high_school_3' THEN 12
            ELSE 99 END";
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

        if (! empty($validated['guardian_id'])) {
            ChatRoom::firstOrCreate([
                'student_id'  => $student->id,
                'guardian_id' => $validated['guardian_id'],
            ]);
        }

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

        $prevGuardianId = $student->guardian_id ? (int) $student->guardian_id : null;
        $student->update($validated);
        $newGuardianId = $student->guardian_id ? (int) $student->guardian_id : null;

        // 紐づけが変わった場合 → 旧 chat_room を削除 (CASCADE で messages も連動削除)
        // FE 側で「既存のチャットが消える」旨を確認してから保存される想定。
        if ($prevGuardianId !== $newGuardianId && $prevGuardianId !== null) {
            ChatRoom::where('student_id', $student->id)
                ->where('guardian_id', $prevGuardianId)
                ->delete();
        }

        // 現在 guardian_id が set されていれば必ず chat_room を保証
        // (バグ: 「石田 洋将」が active 生徒に chat_room が作られていなかった)
        if ($newGuardianId !== null) {
            ChatRoom::firstOrCreate([
                'student_id'  => $student->id,
                'guardian_id' => $newGuardianId,
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $student->fresh(['classroom', 'guardian']),
            'message' => '生徒情報を更新しました。',
        ]);
    }

    /**
     * 既存の児童を別の教室に複製する。
     *
     * 同じ物理的な子どもが複数の教室に在籍するケースで、氏名・生年月日・
     * 学年・保護者・曜日スケジュールなどを引き継いだ新 Student レコードを
     * 指定教室に作成する。退所履歴や待機リスト用フィールドはクリアし、
     * username / password は新規に設定する。
     *
     * 制約:
     * - 複製先は必ず source と同じ企業の教室（会社跨ぎ不可）
     * - source 教室と同じ教室には複製できない
     * - 通常管理者は自身の accessibleClassroomIds() に含まれる教室のみ
     * - 新しい username は students テーブルで unique
     */
    public function copyToClassroom(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        $validated = $request->validate([
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:students,username',
            'password'     => 'nullable|string|min:4',
        ]);

        // 複製元と同じ教室には複製できない
        if ((int) $validated['classroom_id'] === (int) $student->classroom_id) {
            throw ValidationException::withMessages([
                'classroom_id' => ['複製元と同じ教室には複製できません。別の教室を選択してください。'],
            ]);
        }

        // 同一企業制約
        // R6: 旧実装は switchableClassroomIds() (= ユーザーの所属教室1個) で判定していたため
        // 「同企業の別教室の管理者」が複製できなかった。同企業内であれば管理者は児童複製を
        // 許可する。マスター管理者と「複製元教室の所属企業 == 自分の所属教室の所属企業」管理者を許可する。
        $student->loadMissing('classroom');
        $sourceCompanyId = $student->classroom?->company_id;
        if ($sourceCompanyId === null) {
            throw ValidationException::withMessages([
                'classroom_id' => ['複製元の教室に所属企業が設定されていません。先に教室を企業に所属させてください。'],
            ]);
        }
        $target = Classroom::find($validated['classroom_id']);
        if ($target->company_id !== $sourceCompanyId) {
            throw ValidationException::withMessages([
                'classroom_id' => ['複製先は複製元と同じ企業の教室である必要があります。'],
            ]);
        }

        // 非マスター: 自分の所属企業 (= 自教室の company_id) と複製元の company_id が一致する必要がある
        if (!$isMaster) {
            $user->loadMissing('classroom');
            $userCompanyId = $user->classroom?->company_id;
            if ($userCompanyId === null || $userCompanyId !== $sourceCompanyId) {
                return response()->json([
                    'success' => false,
                    'message' => '別企業の児童は複製できません。',
                ], 403);
            }
        }

        // 同一人物識別用の person_id: source に無ければ新規 uuid を両方に割り当てる
        if (empty($student->person_id)) {
            $student->person_id = (string) Str::uuid();
            $student->save();
        }

        // 複製: 退所履歴 / 待機フィールド / ログイン履歴などはクリア
        $copy = $student->replicate([
            'username', 'password_hash', 'password_plain',
            'last_login_at',
            'withdrawal_date', 'withdrawal_reason',
            'desired_start_date', 'desired_weekly_count', 'waiting_notes',
            'desired_monday', 'desired_tuesday', 'desired_wednesday',
            'desired_thursday', 'desired_friday', 'desired_saturday', 'desired_sunday',
        ]);
        $copy->classroom_id = (int) $validated['classroom_id'];
        $copy->username = $validated['username'];
        $copy->password_hash = !empty($validated['password'])
            ? Hash::make($validated['password'])
            : Hash::make(Str::random(16));
        $copy->status = 'active';
        $copy->is_active = true;
        $copy->person_id = $student->person_id;
        $copy->save();

        return response()->json([
            'success' => true,
            'data'    => $copy->load(['classroom', 'guardian']),
            'message' => '児童を別教室に複製しました。',
        ], 201);
    }

    /**
     * R6: 児童の別教室複製モーダル用に、複製先候補の教室一覧を返す。
     *
     * `/api/admin/classrooms` は管理者の権限階層 (master / company_admin / 通常)
     * 別に絞り込みをかけるため、通常管理者には自分の教室1つだけが返り、
     * 「同企業内の他教室」が見れなかった。本エンドポイントは複製専用の用途に
     * 限定して、複製元教室の所属企業の全教室 (source を除く) を返す。
     *
     * 認可: マスター、または「source の company と一致する admin」
     */
    public function copyTargets(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        $student->loadMissing('classroom');
        $sourceCompanyId = $student->classroom?->company_id;
        if ($sourceCompanyId === null) {
            return response()->json([
                'success' => false,
                'message' => '複製元の教室に所属企業が設定されていません。',
            ], 422);
        }

        if (! $isMaster) {
            $user->loadMissing('classroom');
            $userCompanyId = $user->classroom?->company_id;
            if ($userCompanyId === null || $userCompanyId !== $sourceCompanyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'この児童の複製先教室を閲覧する権限がありません。',
                ], 403);
            }
        }

        $targets = Classroom::where('company_id', $sourceCompanyId)
            ->where('id', '!=', $student->classroom_id)
            ->where('is_active', true)
            ->orderBy('classroom_name')
            ->get(['id', 'classroom_name', 'company_id', 'is_active']);

        // 複製先 username のサジェスト。
        // バグ報告 #62: 「ユーザーIDは末尾に v2,v3 のような数字をつけて、そのまま登録できるように」
        // 既存 username に対し _v2, _v3, ... を順に試し、最初に未使用のものを返す。
        // 元 username が無い (null/空) 場合は student_{id} を起点とする。
        $base = $student->username ?: ('student_' . $student->id);
        $suggested = null;
        for ($i = 2; $i <= 99; $i++) {
            $candidate = $base . '_v' . $i;
            if (!Student::where('username', $candidate)->exists()) {
                $suggested = $candidate;
                break;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $targets,
            'suggested_username' => $suggested,
        ]);
    }

    /**
     * 同一人物としてリンクされている他教室の Student レコードを返す。
     *
     * person_id が同じ Student を self を除いて返す。
     * 非マスター管理者には自身の accessibleClassroomIds() に入っているものだけ見せる。
     */
    public function linkedStudents(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        if (empty($student->person_id)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'person_id' => null,
                    'linked' => [],
                ],
            ]);
        }

        $query = Student::with(['classroom:id,classroom_name,company_id'])
            ->where('person_id', $student->person_id)
            ->where('id', '!=', $student->id)
            ->orderBy('classroom_id');

        if (!$isMaster) {
            $accessible = $user->accessibleClassroomIds();
            $query->whereIn('classroom_id', $accessible);
        }

        $linked = $query->get(['id', 'student_name', 'classroom_id', 'status', 'person_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'person_id' => $student->person_id,
                'linked' => $linked,
            ],
        ]);
    }

    /**
     * 同一人物としてリンクされている他の Student レコードに、
     * このレコードの身元情報（氏名・生年月日・学年・保護者など）を同期する。
     *
     * 同期する: student_name, birth_date, grade_level, grade_adjustment,
     *          notes, hide_initial_monitoring
     *
     * 同期しない（各教室で独立管理する）:
     * - guardian_id (各教室の保護者紐づけは独立管理。同期で別家庭の保護者へ
     *   越境上書きされる事故を防ぐため対象外。複製時に引き継いだ値を維持する)
     * - classroom_id, username, password, status, is_active
     * - scheduled_* (教室ごとのスケジュール)
     * - support_start_date, assessment_initial_date, support_plan_start_type
     * - withdrawal_*, last_login_at, desired_*
     */
    public function syncLinked(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        if (empty($student->person_id)) {
            throw ValidationException::withMessages([
                'person_id' => ['この児童は他の教室のレコードとリンクされていません。'],
            ]);
        }

        $query = Student::where('person_id', $student->person_id)
            ->where('id', '!=', $student->id);

        // 非マスターは自分がアクセスできる教室のレコードにだけ同期する
        if (!$isMaster) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }

        // guardian_id は意図的に同期しない (越境上書き防止。上記コメント参照)。
        $payload = [
            'student_name'            => $student->student_name,
            'birth_date'              => $student->birth_date,
            'grade_level'             => $student->grade_level,
            'grade_adjustment'        => $student->grade_adjustment,
            'notes'                   => $student->notes,
            'hide_initial_monitoring' => $student->hide_initial_monitoring,
        ];

        $updatedCount = $query->update($payload);

        return response()->json([
            'success' => true,
            'data' => [
                'updated_count' => $updatedCount,
                'person_id' => $student->person_id,
            ],
            'message' => "{$updatedCount} 件のレコードに同期しました。",
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

        if (!$isMaster) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
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

        if (!$isMaster) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
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

    /**
     * 重複候補の生徒グループ一覧。
     *
     * 経緯: 「石田 洋将」のように、同名の生徒が誤って複数レコードに分かれて
     * 登録されると、保護者画面で chat_room が二重表示されたり、退所済の旧
     * レコードと現役の新レコードが共存して紛らわしい。
     * 管理者が能動的に重複候補を洗い出してマージ判断できるように、
     * 「同 classroom + 正規化氏名」が一致するレコード群を返す。
     *
     * person_id が同じ生徒どうしは「正規に連結された別教室の同一人物」と
     * 見なし、別グループ扱いにはしない (= 1 グループ内で展開しない)。
     * person_id 未設定 (NULL) 同士が同氏名で並ぶケースが本来の警戒対象。
     */
    public function duplicates(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        $query = Student::query()
            ->select(['id', 'student_name', 'classroom_id', 'birth_date', 'grade_level',
                'status', 'is_active', 'guardian_id', 'person_id',
                'support_start_date', 'withdrawal_date', 'created_at'])
            ->with([
                'classroom:id,classroom_name',
                'guardian:id,full_name,email',
            ]);

        if (!$isMaster) {
            $query->whereIn('classroom_id', $user->accessibleClassroomIds());
        }

        $students = $query->orderBy('student_name')->orderBy('id')->get();

        // 正規化キー: 空白(全角/半角)を除去 + 小文字化
        $normalize = function (string $name): string {
            $n = preg_replace('/[\s\x{3000}]+/u', '', $name) ?? '';
            return mb_strtolower($n);
        };

        // (classroom_id, normalized_name) でグループ化
        $groups = [];
        foreach ($students as $s) {
            $key = $s->classroom_id . '::' . $normalize($s->student_name ?? '');
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $s;
        }

        // 2 件以上のグループだけ抽出 + person_id がすべて一致するグループは除外
        $duplicates = [];
        foreach ($groups as $key => $members) {
            if (count($members) < 2) continue;

            // 全員が同じ person_id (NULL も含めて一致) なら「正規に連結済」と見なしスキップ
            $personIds = array_unique(array_map(fn ($m) => $m->person_id, $members));
            if (count($personIds) === 1 && $personIds[0] !== null) {
                continue;
            }

            // classroom 名と name を先頭から取り出してグループ情報として返す
            $classroom = $members[0]->classroom?->classroom_name;
            $duplicates[] = [
                'classroom_id'   => $members[0]->classroom_id,
                'classroom_name' => $classroom,
                'student_name'   => $members[0]->student_name,
                'count'          => count($members),
                'students'       => $members,
            ];
        }

        // 件数の多いグループから返す
        usort($duplicates, fn ($a, $b) => $b['count'] <=> $a['count']);

        return response()->json([
            'success'    => true,
            'duplicates' => $duplicates,
            'total'      => count($duplicates),
        ]);
    }
}
