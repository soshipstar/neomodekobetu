<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IndividualContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * マスター管理者向けの「個別契約書」管理。
 *
 * - 全代理店・全顧客にわたる契約書を閲覧・絞り込み可能
 * - ソーシップ署名フラグの切り替え (代理店からは操作不可)
 * - 顧客署名フラグの切り替え (紙面サイン受領後の事務処理用)
 * - 詳細・PDFダウンロードは代理店ユーザー側と同じ
 *
 * 認可: master 管理者 (admin かつ is_master=true) のみ。
 */
class IndividualContractController extends Controller
{
    private function requireMaster(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->user_type !== 'admin' || ! $user->is_master) {
            return response()->json(['success' => false, 'message' => 'マスター管理者権限が必要です。'], 403);
        }
        return null;
    }

    /**
     * 全代理店・全顧客の個別契約書一覧。?agent_id, ?company_id で絞り込み可。
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $query = IndividualContract::query()
            ->with([
                'agent:id,name,code',
                'company:id,name,code',
                'creator:id,full_name',
                'updater:id,full_name',
            ])
            ->orderByDesc('contract_date')
            ->orderByDesc('id');

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->integer('per_page', 30)),
        ]);
    }

    public function show(Request $request, IndividualContract $contract): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $contract->load([
            'agent:id,name,code',
            'company:id,name,code,agent_id',
            'creator:id,full_name',
            'updater:id,full_name',
        ]);
        return response()->json(['success' => true, 'data' => $contract]);
    }

    /**
     * マスター操作: 任意フィールド + 3者署名フラグの更新。
     * 代理店ユーザー側 update より権限が広い (soship_signed / customer_signed が変更可能)。
     */
    public function update(Request $request, IndividualContract $contract): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'contract_date'    => 'sometimes|nullable|date',
            'start_date'       => 'sometimes|nullable|date',
            'end_date'         => 'sometimes|nullable|date|after_or_equal:start_date',
            'terms'            => 'sometimes|nullable|string|max:5000',
            'monthly_fee'      => 'sometimes|nullable|integer|min:0',
            'commission_rate'  => 'sometimes|nullable|numeric|between:0,1',
            'soship_signed'    => 'sometimes|boolean',
            'agent_signed'     => 'sometimes|boolean',
            'customer_signed'  => 'sometimes|boolean',
        ]);

        // 各 *_signed の変化を検知して *_signed_at を自動セット
        foreach (['soship', 'agent', 'customer'] as $party) {
            $key = "{$party}_signed";
            if (array_key_exists($key, $validated)) {
                $newVal = (bool) $validated[$key];
                if ($newVal !== (bool) $contract->{$key}) {
                    $validated["{$party}_signed_at"] = $newVal ? now() : null;
                }
            }
        }

        $contract->fill($validated);
        $contract->updated_by = $request->user()->id;
        $contract->save();

        return response()->json([
            'success' => true,
            'data'    => $contract->fresh(['agent:id,name,code', 'company:id,name,code']),
            'message' => '個別契約書を更新しました。',
        ]);
    }

    public function destroy(Request $request, IndividualContract $contract): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($contract->contract_document_path) {
            Storage::disk('local')->delete($contract->contract_document_path);
        }
        $contract->delete();

        return response()->json(['success' => true, 'message' => '個別契約書を削除しました。']);
    }

    public function downloadDocument(Request $request, IndividualContract $contract): StreamedResponse|JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

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
