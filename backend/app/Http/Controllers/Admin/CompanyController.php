<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $company->load(['classrooms:id,company_id,classroom_name', 'users:id,company_id,full_name,user_type,is_company_admin']);

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
     * 企業に教室を割り当て
     */
    public function assignClassrooms(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'classroom_ids' => 'required|array',
            'classroom_ids.*' => 'integer|exists:classrooms,id',
        ]);

        \App\Models\Classroom::whereIn('id', $validated['classroom_ids'])
            ->update(['company_id' => $company->id]);

        return response()->json([
            'success' => true,
            'message' => '教室を割り当てました。',
        ]);
    }
}
