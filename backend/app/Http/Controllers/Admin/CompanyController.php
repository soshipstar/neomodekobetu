<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    /**
     * マスター管理者のみアクセス可能
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
     * 企業一覧
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $companies = Company::withCount(['classrooms', 'users'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }

    /**
     * 企業を作成
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:companies,code',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $company = Company::create($validated);

        return response()->json([
            'success' => true,
            'data' => $company,
            'message' => '企業を作成しました。',
        ], 201);
    }

    /**
     * 企業詳細
     */
    public function show(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        // Company::users() は classrooms 経由の hasManyThrough。
        // eager load 時は through テーブルの FK (users.classroom_id) を含める必要がある
        $company->load([
            'classrooms:id,company_id,classroom_name',
            'users:id,classroom_id,full_name,user_type,is_company_admin',
        ]);

        return response()->json([
            'success' => true,
            'data' => $company,
        ]);
    }

    /**
     * 企業を更新
     */
    public function update(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => 'nullable|string|max:50|unique:companies,code,' . $company->id,
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $company->update($validated);

        return response()->json([
            'success' => true,
            'data' => $company,
            'message' => '企業を更新しました。',
        ]);
    }

    /**
     * 企業を削除（所属教室がある場合は不可）
     */
    public function destroy(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($company->classrooms()->exists()) {
            return response()->json([
                'success' => false,
                'message' => '所属する教室があるため削除できません。',
            ], 422);
        }

        $company->delete();

        return response()->json([
            'success' => true,
            'message' => '企業を削除しました。',
        ]);
    }

    /**
     * 企業に教室を割り当て（この企業に属する教室集合を同期する）
     *
     * - 他企業に既に所属している教室を新たに割り当てようとした場合は 422 で拒否する。
     *   （その関係性を切るまで他企業では選択できないという業務ルールを強制）
     * - この企業に現在所属していて、新しい一覧に含まれない教室は company_id = null に戻す。
     */
    public function assignClassrooms(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'classroom_ids' => 'present|array',
            'classroom_ids.*' => 'integer|exists:classrooms,id',
        ]);

        $requestedIds = array_values(array_unique(array_map('intval', $validated['classroom_ids'])));

        // 他企業所属の教室が混ざっていないか確認
        $conflicting = Classroom::whereIn('id', $requestedIds)
            ->whereNotNull('company_id')
            ->where('company_id', '!=', $company->id)
            ->pluck('classroom_name', 'id');

        if ($conflicting->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '他の企業に割り当て済みの教室が含まれています: ' . $conflicting->values()->implode('、'),
                'conflicting_classroom_ids' => $conflicting->keys()->values(),
            ], 422);
        }

        DB::transaction(function () use ($company, $requestedIds) {
            // 現在この企業に属している教室のうち、新一覧に含まれないものを外す
            $unassignQuery = Classroom::where('company_id', $company->id);
            if (!empty($requestedIds)) {
                $unassignQuery->whereNotIn('id', $requestedIds);
            }
            $unassignQuery->update(['company_id' => null]);

            // 新一覧の教室をこの企業に割り当てる（未所属または既に自企業所属のみが対象）
            if (!empty($requestedIds)) {
                Classroom::whereIn('id', $requestedIds)
                    ->where(function ($q) use ($company) {
                        $q->whereNull('company_id')->orWhere('company_id', $company->id);
                    })
                    ->update(['company_id' => $company->id]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => '教室を割り当てました。',
        ]);
    }
}
