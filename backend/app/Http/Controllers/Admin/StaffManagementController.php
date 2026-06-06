<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffManagementController extends Controller
{
    /**
     * スタッフ一覧を取得（管理用：シフト・配置などの情報含む）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMaster = $user->user_type === 'admin' && $user->is_master;

        $isCompanyAdmin = $user->user_type === 'admin' && $user->is_company_admin;

        // classroom_user ピボット経由の全所属教室もロード
        $query = User::whereIn('user_type', ['staff', 'admin'])
            ->where('is_master', false)
            ->with(['classroom', 'classrooms']);

        // 通常管理者には企業管理者を表示しない
        if (!$isMaster && !$isCompanyAdmin) {
            $query->where('is_company_admin', false);
        }

        // 教室フィルタ: classroom_user ピボットも含めて検索
        $filterClassroomId = $request->filled('classroom_id') ? (int) $request->classroom_id : null;

        if ($isMaster) {
            if ($filterClassroomId) {
                $query->where(function ($q) use ($filterClassroomId) {
                    $q->where('classroom_id', $filterClassroomId)
                      ->orWhereHas('classrooms', fn ($cq) => $cq->where('classrooms.id', $filterClassroomId));
                });
            }
        } elseif ($isCompanyAdmin) {
            $companyId = $user->classroom?->company_id;
            if ($companyId) {
                $companyClassroomIds = Classroom::where('company_id', $companyId)->pluck('id')->toArray();
                // 主教室 OR ピボットのいずれかが自企業内
                $query->where(function ($q) use ($companyClassroomIds) {
                    $q->whereIn('classroom_id', $companyClassroomIds)
                      ->orWhereHas('classrooms', fn ($cq) => $cq->whereIn('classrooms.id', $companyClassroomIds));
                });
                if ($filterClassroomId) {
                    $query->where(function ($q) use ($filterClassroomId) {
                        $q->where('classroom_id', $filterClassroomId)
                          ->orWhereHas('classrooms', fn ($cq) => $cq->where('classrooms.id', $filterClassroomId));
                    });
                }
            }
        } elseif ($user->classroom_id) {
            $myId = $user->classroom_id;
            $query->where(function ($q) use ($myId) {
                $q->where('classroom_id', $myId)
                  ->orWhereHas('classrooms', fn ($cq) => $cq->where('classrooms.id', $myId));
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $staff = $query->orderBy('full_name')->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $staff,
        ]);
    }

    /**
     * スタッフを新規作成
     *
     * このエンドポイントはリクエストユーザーの現在の classroom_id に対して
     * スタッフを登録する想定。フロントエンドのフォームには classroom 選択が
     * 無いため、リクエストに classroom_id が含まれていなければ、認証ユーザー
     * の classroom_id をデフォルトとして使用する。
     *
     * classroom_id が決まらない場合（master で未切替など）は 422 を返す。
     * 教室には必ず所属企業が設定されていることを要求する。
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'username'     => 'required|string|max:100|unique:users,username',
            'password'     => 'required|string|min:6',
            'full_name'    => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'user_type'    => 'nullable|string|in:staff,admin',
            'is_active'    => 'boolean',
        ]);

        // classroom_id をリクエスト → 認証ユーザー → 422 の順に決定
        $classroomId = $validated['classroom_id'] ?? $authUser->classroom_id ?? null;
        if (empty($classroomId)) {
            throw ValidationException::withMessages([
                'classroom_id' => ['所属教室が未設定です。教室切替で教室を選択してから登録してください。'],
            ]);
        }

        // 教室は必ず所属企業を持っていること
        $classroom = Classroom::find($classroomId);
        if (!$classroom) {
            throw ValidationException::withMessages([
                'classroom_id' => ['指定した教室は存在しません。'],
            ]);
        }
        if ($classroom->company_id === null) {
            throw ValidationException::withMessages([
                'classroom_id' => ['選択した教室には所属企業が設定されていません。企業に属する教室を選択してください。'],
            ]);
        }

        // 企業管理者のみ通常管理者を作成可能＋他教室を指定可能
        $requestedType = $validated['user_type'] ?? 'staff';
        if (!$authUser->is_company_admin) {
            $requestedType = 'staff'; // 企業管理者以外はスタッフのみ
            // 通常管理者は自教室のみ（他教室の指定を無視）
            $classroomId = $authUser->classroom_id ?? $classroomId;
        }

        // 企業管理者: 選択教室が自企業内かチェック
        if ($authUser->is_company_admin) {
            $myCompanyId = $authUser->classroom?->company_id;
            if ($myCompanyId && $classroom->company_id !== $myCompanyId) {
                throw ValidationException::withMessages([
                    'classroom_id' => ['自企業内の教室のみ選択できます。'],
                ]);
            }
        }

        $user = User::create([
            'classroom_id'     => $classroomId,
            'username'         => $validated['username'],
            'password'         => Hash::make($validated['password']),
            'full_name'        => $validated['full_name'],
            'email'            => $validated['email'] ?? null,
            'user_type'        => $requestedType,
            'is_master'        => false,
            'is_company_admin' => false,
            'is_active'        => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $user->load('classroom'),
            'message' => 'スタッフを登録しました。',
        ], 201);
    }

    /**
     * スタッフ詳細を取得
     */
    public function show(User $user): JsonResponse
    {
        $user->load('classroom');

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * スタッフ情報を更新（配置・役職など）
     *
     * 旧アプリ整合性 (staff_accounts_save.php / staff_management_save.php):
     *  - email / full_name / is_active / password の更新は誰でも可
     *  - classroom_id の変更: マスター + 企業管理者のみ (旧 staff_accounts_save.php)
     *  - user_type の変更 (スタッフ↔通常管理者): マスター + 企業管理者のみ
     *    (旧アプリでは `convert_to_admin` 専用 action だったが、care-bridge では
     *     編集画面の「種別」セレクタから直接変更できる UI に統合)
     *  - is_master の変更は不可 (専用 master 設定経路でのみ)
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->is_master) {
            return response()->json(['success' => false, 'message' => 'マスター管理者は編集できません。'], 403);
        }

        $authUser = $request->user();
        $authIsMaster = $authUser->user_type === 'admin' && $authUser->is_master;
        $authIsCompanyAdmin = $authUser->user_type === 'admin' && $authUser->is_company_admin;
        $canChangeRoleOrClassroom = $authIsMaster || $authIsCompanyAdmin;

        $validated = $request->validate([
            'classroom_id' => 'nullable|exists:classrooms,id',
            'full_name'    => 'sometimes|required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'is_active'    => 'boolean',
            'password'     => 'nullable|string|min:6',
            'user_type'    => 'nullable|string|in:staff,admin',
        ]);

        // 権限変更・教室変更はマスター/企業管理者のみ。それ以外は無視して落とす。
        if (! $canChangeRoleOrClassroom) {
            unset($validated['classroom_id'], $validated['user_type']);
        }

        // 企業管理者: 教室変更先は自企業内でなければならない
        if ($authIsCompanyAdmin && !empty($validated['classroom_id'])) {
            $myCompanyId = $authUser->classroom?->company_id;
            $target = Classroom::find($validated['classroom_id']);
            if (! $target || $target->company_id !== $myCompanyId) {
                throw ValidationException::withMessages([
                    'classroom_id' => ['自企業内の教室のみ選択できます。'],
                ]);
            }
        }

        // パスワードは平文で来るので Hash 化、未指定なら触らない
        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // 権限変更は user_type のみ。is_master は触らない (専用経路でのみ昇格)
        // また企業管理者を一般 admin/staff に降格させる際は is_company_admin を外す等の
        // 副作用が必要だが、UI には企業管理者編集メニューを露出していないため、
        // ここでは触れず admin/staff の単純変換にとどめる。

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $user->fresh('classroom'),
            'message' => 'スタッフ情報を更新しました。',
        ]);
    }

    /**
     * スタッフを削除
     *
     * 旧アプリ (staff_accounts_save.php / staff_management_save.php) は
     *   DELETE FROM users WHERE id = ? AND user_type = 'staff'
     * の物理削除。これにより同じ username / email を再利用可能にしていた。
     *
     * care-bridge では参照整合性 (chat_rooms.staff_id, daily_records.staff_id 等)
     * のため、安全側として「論理削除 + username / email を解放」する。
     *  - is_active = false
     *  - email     = null            (再利用可)
     *  - username  = "deleted__{id}__{元の username}"  (一意制約を逃しつつ参照可)
     *
     * これにより削除後に同じメールアドレス・ユーザー名で再登録できる。
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->is_master) {
            return response()->json(['success' => false, 'message' => 'マスター管理者は削除できません。'], 403);
        }

        // username に "deleted__<id>__" prefix を付けて一意制約から解放
        // (50 文字制限内に収まるよう、元の username を切り詰める)
        $prefix = 'deleted__' . $user->id . '__';
        $remaining = max(0, 50 - strlen($prefix));
        $newUsername = $prefix . substr($user->username, 0, $remaining);

        $user->update([
            'is_active' => false,
            'email'     => null,
            'username'  => $newUsername,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'スタッフを削除しました。',
        ]);
    }
}
