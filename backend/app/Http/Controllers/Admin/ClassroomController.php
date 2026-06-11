<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\ServiceTypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClassroomController extends Controller
{
    // ARCH-AUTH-02: requireMaster は基底 Controller に集約済み (isMasterAdmin 判定で統一)。

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
            'service_type'   => ['nullable', Rule::in(ServiceTypeRegistry::ALL)],
            'address'        => 'nullable|string|max:500',
            'phone'          => 'nullable|string|max:20',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'settings'       => 'nullable|array',
            'is_active'      => 'boolean',
            'capacity'       => 'nullable|integer|min:0|max:999',
            'opening_days_per_month' => 'nullable|integer|min:0|max:31',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('classrooms/logos', 'public');
            $validated['logo_path'] = $path;
        }
        unset($validated['logo']);

        // 未指定時は放デイ既定 (DB 既定値と整合)
        if (empty($validated['service_type'])) {
            $validated['service_type'] = ServiceTypeRegistry::AFTER_SCHOOL;
        }

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
            'service_type'   => ['sometimes', 'nullable', Rule::in(ServiceTypeRegistry::ALL)],
            'address'        => 'nullable|string|max:500',
            'phone'          => 'nullable|string|max:20',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'settings'       => 'nullable|array',
            'is_active'      => 'boolean',
            'capacity'       => 'nullable|integer|min:0|max:999',
            'opening_days_per_month' => 'nullable|integer|min:0|max:31',
        ]);

        // 既存レコードへの service_type 変更は破壊的 (集計・領域・強みキーが変わる)。
        // 完全には禁止しないが、null を渡された場合は既存値を維持する。
        if (array_key_exists('service_type', $validated) && empty($validated['service_type'])) {
            unset($validated['service_type']);
        }

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
     * 教室を削除（マスター管理者専用、論理削除 = is_active を false にする）
     */
    public function destroy(Request $request, Classroom $classroom): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        // 生徒が紐づいている場合は削除不可
        if ($classroom->students()->exists()) {
            return response()->json([
                'success' => false,
                'message' => '生徒が在籍している教室は削除できません。',
            ], 422);
        }

        $classroom->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '教室を無効にしました。',
        ]);
    }
}
