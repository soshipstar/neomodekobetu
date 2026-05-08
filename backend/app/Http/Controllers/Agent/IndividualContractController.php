<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Company;
use App\Models\IndividualContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 代理店ユーザー向けの「個別契約書 (3者間契約)」CRUD。
 *
 * 認可: user_type='agent' かつ自代理店 (user.agent_id) のレコードのみ操作可能。
 *       他代理店の契約書は閲覧・編集不可 (404 ではなく 403 で明示的に拒否)。
 *       マスター管理者は別途 Admin\IndividualContractController で全件閲覧。
 */
class IndividualContractController extends Controller
{
    /**
     * 代理店ロール検証 + 自代理店 Agent を解決する。
     */
    private function resolveOwnAgent(Request $request): Agent|JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->user_type !== 'agent') {
            return response()->json(['success' => false, 'message' => '代理店アカウントが必要です。'], 403);
        }
        if (! $user->agent_id) {
            return response()->json(['success' => false, 'message' => '所属代理店が設定されていません。'], 422);
        }
        $agent = Agent::find($user->agent_id);
        if (! $agent) {
            return response()->json(['success' => false, 'message' => '所属代理店が見つかりません。'], 404);
        }
        return $agent;
    }

    /**
     * 自代理店の個別契約書一覧。
     */
    public function index(Request $request): JsonResponse
    {
        $agent = $this->resolveOwnAgent($request);
        if ($agent instanceof JsonResponse) return $agent;

        $contracts = IndividualContract::where('agent_id', $agent->id)
            ->with([
                'company:id,name,code',
                'creator:id,full_name',
                'updater:id,full_name',
            ])
            ->orderByDesc('contract_date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $contracts]);
    }

    /**
     * 個別契約書を新規作成。
     */
    public function store(Request $request): JsonResponse
    {
        $agent = $this->resolveOwnAgent($request);
        if ($agent instanceof JsonResponse) return $agent;

        $validated = $request->validate([
            'company_id'       => 'required|integer|exists:companies,id',
            'contract_date'    => 'nullable|date',
            'start_date'       => 'nullable|date',
            'end_date'         => 'nullable|date|after_or_equal:start_date',
            'terms'            => 'nullable|string|max:5000',
            'monthly_fee'      => 'nullable|integer|min:0',
            'commission_rate'  => 'nullable|numeric|between:0,1',
            // 代理店ユーザーが自分用の署名を一緒に立てる場合
            'agent_signed'     => 'sometimes|boolean',
        ]);

        // 代理店から会社の所有関係を強制チェック (companies.agent_id と一致しないものは弾く)
        $company = Company::find($validated['company_id']);
        if (! $company || (int) $company->agent_id !== (int) $agent->id) {
            return response()->json([
                'success' => false,
                'message' => '指定の顧客企業はこの代理店の担当ではありません。',
            ], 403);
        }

        // 重複チェック (DB制約も働くが、より親切な 422 メッセージで先に返す)
        $exists = IndividualContract::where('agent_id', $agent->id)
            ->where('company_id', $validated['company_id'])
            ->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'この顧客企業との個別契約書はすでに作成されています。',
            ], 422);
        }

        $contract = IndividualContract::create(array_merge(
            $validated,
            [
                'agent_id'        => $agent->id,
                'created_by'      => $request->user()->id,
                'updated_by'      => $request->user()->id,
                'agent_signed'    => $validated['agent_signed'] ?? false,
                'agent_signed_at' => ! empty($validated['agent_signed']) ? now() : null,
            ],
        ));

        return response()->json([
            'success' => true,
            'data'    => $contract->load(['company:id,name,code']),
            'message' => '個別契約書を作成しました。',
        ], 201);
    }

    /**
     * 個別契約書詳細。
     */
    public function show(Request $request, IndividualContract $contract): JsonResponse
    {
        $agent = $this->resolveOwnAgent($request);
        if ($agent instanceof JsonResponse) return $agent;
        if ($contract->agent_id !== $agent->id) {
            return response()->json(['success' => false, 'message' => 'この契約書を閲覧する権限がありません。'], 403);
        }

        $contract->load(['company:id,name,code,agent_id', 'creator:id,full_name', 'updater:id,full_name']);
        return response()->json(['success' => true, 'data' => $contract]);
    }

    /**
     * 個別契約書を更新。
     * 自分の署名 (agent_signed) は代理店から切り替え可能。
     * ソーシップ・顧客の署名フラグは admin 経由でのみ。
     */
    public function update(Request $request, IndividualContract $contract): JsonResponse
    {
        $agent = $this->resolveOwnAgent($request);
        if ($agent instanceof JsonResponse) return $agent;
        if ($contract->agent_id !== $agent->id) {
            return response()->json(['success' => false, 'message' => 'この契約書を更新する権限がありません。'], 403);
        }

        $validated = $request->validate([
            'contract_date'   => 'sometimes|nullable|date',
            'start_date'      => 'sometimes|nullable|date',
            'end_date'        => 'sometimes|nullable|date|after_or_equal:start_date',
            'terms'           => 'sometimes|nullable|string|max:5000',
            'monthly_fee'     => 'sometimes|nullable|integer|min:0',
            'commission_rate' => 'sometimes|nullable|numeric|between:0,1',
            'agent_signed'    => 'sometimes|boolean',
        ]);

        // agent_signed の変化を検知して agent_signed_at を自動更新
        if (array_key_exists('agent_signed', $validated)) {
            $newAgentSigned = (bool) $validated['agent_signed'];
            if ($newAgentSigned !== (bool) $contract->agent_signed) {
                $validated['agent_signed_at'] = $newAgentSigned ? now() : null;
            }
        }

        $contract->fill($validated);
        $contract->updated_by = $request->user()->id;
        $contract->save();

        return response()->json([
            'success' => true,
            'data'    => $contract->fresh(['company:id,name,code']),
            'message' => '個別契約書を更新しました。',
        ]);
    }

    /**
     * 個別契約書を削除。PDFが付いていれば物理削除。
     */
    public function destroy(Request $request, IndividualContract $contract): JsonResponse
    {
        $agent = $this->resolveOwnAgent($request);
        if ($agent instanceof JsonResponse) return $agent;
        if ($contract->agent_id !== $agent->id) {
            return response()->json(['success' => false, 'message' => 'この契約書を削除する権限がありません。'], 403);
        }

        if ($contract->contract_document_path) {
            Storage::disk('local')->delete($contract->contract_document_path);
        }
        $contract->delete();

        return response()->json(['success' => true, 'message' => '個別契約書を削除しました。']);
    }

    /**
     * 個別契約書 PDF をアップロード/差し替え (代理店本人のみ)。
     */
    public function uploadDocument(Request $request, IndividualContract $contract): JsonResponse
    {
        $agent = $this->resolveOwnAgent($request);
        if ($agent instanceof JsonResponse) return $agent;
        if ($contract->agent_id !== $agent->id) {
            return response()->json(['success' => false, 'message' => 'この契約書を更新する権限がありません。'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB
        ]);

        $oldPath = $contract->contract_document_path;
        $path = $request->file('file')->store(
            'individual-contracts/' . $agent->id . '/' . $contract->id,
            'local',
        );
        $contract->update([
            'contract_document_path' => $path,
            'updated_by'             => $request->user()->id,
        ]);
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'success' => true,
            'data'    => ['contract_document_path' => $path],
            'message' => '契約書PDFをアップロードしました。',
        ]);
    }

    /**
     * 個別契約書 PDF を削除。
     */
    public function deleteDocument(Request $request, IndividualContract $contract): JsonResponse
    {
        $agent = $this->resolveOwnAgent($request);
        if ($agent instanceof JsonResponse) return $agent;
        if ($contract->agent_id !== $agent->id) {
            return response()->json(['success' => false, 'message' => 'この契約書を更新する権限がありません。'], 403);
        }

        if (! $contract->contract_document_path) {
            return response()->json(['success' => false, 'message' => 'PDFが登録されていません。'], 422);
        }

        Storage::disk('local')->delete($contract->contract_document_path);
        $contract->update([
            'contract_document_path' => null,
            'updated_by'             => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'message' => '契約書PDFを削除しました。']);
    }

    /**
     * 個別契約書 PDF をダウンロード。
     */
    public function downloadDocument(Request $request, IndividualContract $contract): StreamedResponse|JsonResponse
    {
        $agent = $this->resolveOwnAgent($request);
        if ($agent instanceof JsonResponse) return $agent;
        if ($contract->agent_id !== $agent->id) {
            return response()->json(['success' => false, 'message' => 'この契約書を閲覧する権限がありません。'], 403);
        }

        if (! $contract->contract_document_path
            || ! Storage::disk('local')->exists($contract->contract_document_path)) {
            return response()->json(['success' => false, 'message' => 'PDFが登録されていません。'], 404);
        }

        $name = sprintf(
            'individual-contract_%d_%s.pdf',
            $contract->id,
            preg_replace('/[^A-Za-z0-9_\-]/u', '_', optional($contract->company)->name ?? 'company'),
        );
        return Storage::disk('local')->download(
            $contract->contract_document_path,
            $name,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
