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

        $students = $query->orderBy('student_name')->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * 生徒を新規作成
     *
     * 認可:
     * - マスター管理者: 全教室に登録可 (admin 管理コンテキストでは cross-company を許容)
     * - 非マスター: switchableClassroomIds() に含まれる教室にのみ登録可
     *   (同企業内の教室は OK、他企業は 403)
     *
     * 児童 1 名 = 教室 1 つ = Student レコード 1 つ。複数教室にまたがる場合は
     * copy-to-classroom (person_id 共有) を経由する。これにより「登録時に
     * 意図せず他施設に所属させる」事故を防ぐ。
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        // 登録のハードルを下げるため、最低限 classroom_id + student_name のみ必須。
        // username / password / 生年月日 等は未入力なら自動補完して登録できる。
        $validated = $request->validate([
            'classroom_id'         => 'required|exists:classrooms,id',
            'student_name'         => 'required|string|max:255',
            'username'             => 'nullable|string|max:100|unique:students',
            'password'             => 'nullable|string|min:4',
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
            // Phase L-2: サービス種別固有 (契約 / 利用期限)
            'contract_start_date'  => 'nullable|date',
            'contract_end_date'    => 'nullable|date|after_or_equal:contract_start_date',
            'usage_limit_date'     => 'nullable|date',
        ]);

        // 非マスターは自分が switch 可能な教室にしか登録できない
        if (!$isMaster && !in_array((int) $validated['classroom_id'], $user->switchableClassroomIds(), true)) {
            return response()->json([
                'success' => false,
                'message' => '指定した教室への登録権限がありません。',
            ], 403);
        }

        // username / password が未指定なら自動採番する。
        // 例: student_001, student_002, ... (既存と衝突しない最小番号)
        if (empty($validated['username'])) {
            $validated['username'] = $this->generateUniqueStudentUsername();
        }
        if (empty($validated['password'])) {
            // 8 文字の英数字 (混同しやすい O/0/I/1 などは除外)
            $validated['password'] = $this->generateRandomPassword(8);
        }

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
     *
     * 認可: 対象生徒の所属教室がユーザーの switchableClassroomIds() に
     *       含まれること (マスターは除外)。AUTH-01 修正。
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroomId($request->user(), $student->classroom_id, 'この生徒へのアクセス権限がありません。');

        $student->load(['classroom', 'guardian', 'supportPlans', 'monitoringRecords']);

        return response()->json([
            'success' => true,
            'data'    => $student,
        ]);
    }

    /**
     * 生徒を更新
     *
     * 認可: 元の所属教室・変更先の教室の両方について、ユーザーの
     *       switchableClassroomIds() に含まれること (マスターは除外)。
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        // 既存所属教室への変更権限チェック
        if (!$isMaster && !in_array((int) $student->classroom_id, $user->switchableClassroomIds(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'この生徒の更新権限がありません。',
            ], 403);
        }

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
            // Phase L-2: サービス種別固有 (契約 / 利用期限)
            'contract_start_date'  => 'nullable|date',
            'contract_end_date'    => 'nullable|date|after_or_equal:contract_start_date',
            'usage_limit_date'     => 'nullable|date',
        ]);

        // 移動先教室への登録権限チェック (classroom_id を変更する場合)
        if (!$isMaster && isset($validated['classroom_id']) &&
            !in_array((int) $validated['classroom_id'], $user->switchableClassroomIds(), true)) {
            return response()->json([
                'success' => false,
                'message' => '指定した移動先教室への権限がありません。',
            ], 403);
        }

        if (! empty($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
        }
        unset($validated['password']);

        $student->update($validated);

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

        // 非マスターは自分がアクセス可能な教室のみ
        if (!$isMaster && !in_array((int) $validated['classroom_id'], $user->switchableClassroomIds(), true)) {
            return response()->json([
                'success' => false,
                'message' => '指定した教室への権限がありません。',
            ], 403);
        }

        // 同一企業制約
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
     * 同一人物としてリンクされている他教室の Student レコードを返す。
     *
     * person_id が同じ Student を self を除いて返す。
     *
     * 認可:
     *  - 必ず source 教室と同企業のレコードのみを返す (defense in depth)。
     *    copyToClassroom() が同企業制約を強制しているはずだが、過去の不正データや
     *    別経路で異企業の person_id 共有が発生していた場合に備える。
     *  - 非マスター管理者には自身の switchableClassroomIds() に入っているもののみ。
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

        // source 教室の所属企業
        $student->loadMissing('classroom:id,classroom_name,company_id');
        $sourceCompanyId = $student->classroom?->company_id;

        $query = Student::with(['classroom:id,classroom_name,company_id'])
            ->where('person_id', $student->person_id)
            ->where('id', '!=', $student->id)
            ->orderBy('classroom_id');

        // 同企業フィルタ (defense in depth): cross-company な person_id 共有レコードを表示しない
        if ($sourceCompanyId !== null) {
            $query->whereHas('classroom', fn ($q) => $q->where('company_id', $sourceCompanyId));
        }

        if (!$isMaster) {
            $accessible = $user->switchableClassroomIds();
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
     *          guardian_id, notes, hide_initial_monitoring
     *
     * 同期しない（各教室で独立管理する）:
     * - classroom_id, username, password, status, is_active
     * - scheduled_* (教室ごとのスケジュール)
     * - support_start_date, assessment_initial_date, support_plan_start_type
     * - withdrawal_*, last_login_at, desired_*
     */
    public function syncLinked(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        // AUTH-07 修正: 同期元の児童への認可を追加 (非マスターは自身のアクセス範囲のみ)
        if (! $isMaster) {
            $this->authorizeClassroomId($user, $student->classroom_id, 'この児童の同期権限がありません。');
        }

        if (empty($student->person_id)) {
            throw ValidationException::withMessages([
                'person_id' => ['この児童は他の教室のレコードとリンクされていません。'],
            ]);
        }

        $query = Student::where('person_id', $student->person_id)
            ->where('id', '!=', $student->id);

        // AUTH-07 修正: 同企業制約を追加 (copyToClassroom と同じ defense in depth)。
        // 異企業に同一 person_id が混在した場合でも、別企業のデータを上書きしない。
        $student->loadMissing('classroom');
        $sourceCompanyId = $student->classroom?->company_id;
        if ($sourceCompanyId !== null) {
            $query->whereHas('classroom', fn ($q) => $q->where('company_id', $sourceCompanyId));
        }

        // 非マスターは自分が switch できる教室のレコードにだけ同期する
        // (旧: accessibleClassroomIds() は企業管理者だと単一教室しか返さず同期漏れ)
        if (!$isMaster) {
            $query->whereIn('classroom_id', $user->switchableClassroomIds());
        }

        $payload = [
            'student_name'            => $student->student_name,
            'birth_date'              => $student->birth_date,
            'grade_level'             => $student->grade_level,
            'grade_adjustment'        => $student->grade_adjustment,
            'guardian_id'             => $student->guardian_id,
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
     *
     * 認可: 対象生徒の所属教室がユーザーの switchableClassroomIds() に
     *       含まれること (マスターは除外)。AUTH-01 修正。
     */
    public function destroy(Request $request, Student $student): JsonResponse
    {
        $this->authorizeClassroomId($request->user(), $student->classroom_id, 'この生徒の削除権限がありません。');

        $student->update(['status' => 'withdrawn']);

        return response()->json([
            'success' => true,
            'message' => '生徒を退所扱いにしました。',
        ]);
    }

    /**
     * student_NNN 形式の重複しない username を生成する。
     * 「個人情報の詳細を登録なしでスタート」する運用のため、未入力時の自動採番用。
     */
    private function generateUniqueStudentUsername(): string
    {
        $last = Student::where('username', 'like', 'student_%')
            ->orderByRaw("CAST(SUBSTRING(username FROM 'student_([0-9]+)') AS INTEGER) DESC NULLS LAST")
            ->value('username');

        $next = 1;
        if ($last && preg_match('/student_(\d+)/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        $username = 'student_' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        while (Student::where('username', $username)->exists()) {
            $next++;
            $username = 'student_' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        }
        return $username;
    }

    /**
     * ランダムなパスワードを生成 (混同しやすい 0/O/1/I を除外)。
     */
    private function generateRandomPassword(int $length = 8): string
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
