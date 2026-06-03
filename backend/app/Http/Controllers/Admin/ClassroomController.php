<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClassroomController extends Controller
{
    /**
     * マスター管理者のみアクセス可能にする共通チェック
     */
    private function requireMaster(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->user_type !== 'admin' || !$user->is_master) {
            return response()->json([
                'success' => false,
                'message' => 'マスター管理者権限が必要です。',
            ], 403);
        }
        return null;
    }

    /**
     * 教室一覧を取得
     * マスター管理者: 全教室
     * 企業管理者: 自企業内の教室のみ
     * 通常管理者: 自分の所属教室のみ
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->user_type !== 'admin') {
            return response()->json(['success' => false, 'message' => '権限がありません。'], 403);
        }

        $query = Classroom::with('company')->withCount(['students', 'users']);

        // 企業管理者が自企業を特定するため classroom を eager load
        $user->loadMissing('classroom');
        $companyId = $user->classroom?->company_id;

        if ($user->is_master) {
            // マスター管理者: 全教室
        } elseif ($user->is_company_admin && $companyId) {
            // 企業管理者: 自企業の教室のみ
            $query->where('company_id', $companyId);
        } else {
            // 通常管理者: 所属教室のみ
            $ids = $user->accessibleClassroomIds();
            $query->whereIn('id', $ids);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $classrooms = $query->orderBy('classroom_name')->get();

        // フロントエンドはフラットな company_name を参照するため属性として付与
        $classrooms->each(function (Classroom $c) {
            $c->company_name = $c->company?->name;
        });

        return response()->json([
            'success' => true,
            'data'    => $classrooms,
        ]);
    }

    /**
     * 教室を新規作成（マスター管理者専用）
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'classroom_name' => 'required|string|max:255',
            'address'        => 'nullable|string|max:500',
            'phone'          => 'nullable|string|max:20',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'settings'       => 'nullable|array',
            'is_active'      => 'boolean',
            // 国保連請求システム(kiduriacount)連携をこの事業所で使うか
            'billing_system_enabled' => 'boolean',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('classrooms/logos', 'public');
            $validated['logo_path'] = $path;
        }
        unset($validated['logo']);

        $classroom = Classroom::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $classroom,
            'message' => '教室を作成しました。',
        ], 201);
    }

    /**
     * 教室詳細を取得（マスター管理者専用）
     */
    public function show(Request $request, Classroom $classroom): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $classroom->load(['students', 'users', 'tags', 'capacity']);
        $classroom->loadCount(['students', 'users']);

        return response()->json([
            'success' => true,
            'data'    => $classroom,
        ]);
    }

    /**
     * 教室を更新（マスター管理者専用）
     */
    public function update(Request $request, Classroom $classroom): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'classroom_name' => 'sometimes|required|string|max:255',
            'address'        => 'nullable|string|max:500',
            'phone'          => 'nullable|string|max:20',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'settings'       => 'nullable|array',
            'is_active'      => 'boolean',
            // 国保連請求システム(kiduriacount)連携をこの事業所で使うか
            'billing_system_enabled' => 'boolean',
        ]);

        if ($request->hasFile('logo')) {
            // 古いロゴを削除
            if ($classroom->logo_path) {
                Storage::disk('public')->delete($classroom->logo_path);
            }
            $path = $request->file('logo')->store('classrooms/logos', 'public');
            $validated['logo_path'] = $path;
        }
        unset($validated['logo']);

        $classroom->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $classroom->fresh(),
            'message' => '教室を更新しました。',
        ]);
    }

    /**
     * 教室を削除（マスター管理者専用）
     *
     * 2 モード:
     *  - mode=soft (既定): is_active = false に変えるだけ (論理削除)。
     *    生徒が在籍している場合は拒否。元に戻せる。
     *  - mode=hard: 物理削除。全 28 個の関連テーブル
     *    (students / users / daily_records / classroom_photos / events / 等)
     *    に 1 件でも参照があれば拒否。ON DELETE CASCADE が走るので、
     *    本当に空の教室だけを対象にする (テスト/誤作成 classroom の整理用)。
     */
    public function destroy(Request $request, Classroom $classroom): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $mode = $request->input('mode', 'soft');

        // 関連テーブルの件数を取得 (FE 側のエラー表示と hard delete の判定に使う)
        $relationCounts = $this->countClassroomDependencies($classroom);
        $totalRelated = array_sum($relationCounts);

        if ($mode === 'hard') {
            if ($totalRelated > 0) {
                $nonZero = array_filter($relationCounts, fn ($n) => $n > 0);
                $detail = collect($nonZero)->map(fn ($n, $k) => "{$k}: {$n} 件")->implode(' / ');
                return response()->json([
                    'success' => false,
                    'message' => "関連データが残っているため完全削除できません。先に整理してください ({$detail})。",
                    'data'    => ['relation_counts' => $relationCounts],
                ], 422);
            }
            $name = $classroom->classroom_name;
            $classroom->delete();
            return response()->json([
                'success' => true,
                'message' => "事業所「{$name}」を完全に削除しました。",
            ]);
        }

        // 既定 = 論理削除 (旧仕様維持)
        if ($classroom->students()->exists()) {
            return response()->json([
                'success' => false,
                'message' => '生徒が在籍している教室は無効化できません。先に生徒を退所処理してください。',
                'data'    => ['relation_counts' => $relationCounts],
            ], 422);
        }

        $classroom->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '教室を無効にしました。',
        ]);
    }

    /**
     * 教室削除時の安全チェック用に関連テーブルの件数を集計。
     * hard delete の許可判定および FE への詳細メッセージで使用。
     *
     * @return array<string,int>
     */
    private function countClassroomDependencies(Classroom $classroom): array
    {
        $cid = $classroom->id;
        return [
            'students'         => DB::table('students')->where('classroom_id', $cid)->count(),
            'users'            => DB::table('users')->where('classroom_id', $cid)->count(),
            'classroom_users'  => DB::table('classroom_user')->where('classroom_id', $cid)->count(),
            'daily_records'    => DB::table('daily_records')->where('classroom_id', $cid)->count(),
            'classroom_photos' => DB::table('classroom_photos')->where('classroom_id', $cid)->count(),
            'events'           => DB::table('events')->where('classroom_id', $cid)->count(),
            'newsletters'      => DB::table('newsletters')->where('classroom_id', $cid)->count(),
            'announcements'    => DB::table('announcements')->where('classroom_id', $cid)->count(),
            'meeting_requests' => DB::table('meeting_requests')->where('classroom_id', $cid)->count(),
            'support_plans'    => DB::table('individual_support_plans')->where('classroom_id', $cid)->count(),
            'monitoring'       => DB::table('monitoring_records')->where('classroom_id', $cid)->count(),
            'activity_plans'   => DB::table('activity_support_plans')->where('classroom_id', $cid)->count(),
        ];
    }
}
