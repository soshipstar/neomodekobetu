<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\AbilityEvalItem;
use App\Models\AbilitySubjectiveScore;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 外部システム mynameis(本人の主観自己評価, https://fesvol.xyz)からの受信エンドポイント。
 *
 * 連携方式(kiduri 受信・共有シークレット): mynameis がサーバ間で本人の主観プロフィール
 * (項目ごとの1〜5 Likert)を push する。kiduri は児童↔mynameis user_id の紐づけで対象児童を
 * 特定し、ability_subjective_scores に最新値として upsert する。
 * 認証は kiduriacount SSO verify と同じ共有シークレット方式。本コントローラは受信専用。
 */
class MynameisSyncController extends Controller
{
    /**
     * 主観自己評価プロフィールを受信して保存する。
     */
    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'secret'           => 'required|string',
            'mynameis_user_id' => 'nullable|integer',
            'member_codes'     => 'nullable|array',
            'member_codes.*'   => 'string|max:16',
            'items'                 => 'required|array|min:1',
            'items.*.item_code'     => 'required|string',
            'items.*.value'         => 'required|integer|min:1|max:5',
            'items.*.axis_code'     => 'nullable|string|max:8',
            'items.*.responded_at'  => 'nullable|date',
        ]);

        // 共有シークレット検証(タイミング攻撃に強い比較)
        $expected = (string) config('services.mynameis.shared_secret');
        if ($expected === '' || ! hash_equals($expected, (string) $validated['secret'])) {
            return response()->json(['success' => false, 'message' => '認証に失敗しました。'], 401);
        }

        // 紐づく児童を特定(kiduri 側がマッピングを保持)。
        // メンバーID(member_code)優先、後方互換で mynameis_user_id も予備に使う。
        $codes = collect($validated['member_codes'] ?? [])
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->filter()
            ->values();

        $student = null;
        if ($codes->isNotEmpty()) {
            $student = Student::whereIn('mynameis_member_code', $codes->all())->first();
        }
        if (! $student && ! empty($validated['mynameis_user_id'])) {
            $student = Student::where('mynameis_user_id', $validated['mynameis_user_id'])->first();
        }
        if (! $student) {
            return response()->json(['success' => false, 'message' => '紐づく児童が見つかりません。'], 404);
        }

        // 有効な項目コード(両アプリ共通マスタ)のみ受け入れる
        $validItemIds = AbilityEvalItem::pluck('item_id')->flip();

        $saved = 0;
        DB::transaction(function () use ($validated, $student, $validItemIds, &$saved) {
            foreach ($validated['items'] as $row) {
                if (! $validItemIds->has($row['item_code'])) {
                    continue; // 未知の項目コードはスキップ
                }
                AbilitySubjectiveScore::updateOrCreate(
                    ['student_id' => $student->id, 'item_id' => $row['item_code']],
                    [
                        'axis_id'       => $row['axis_code'] ?? null,
                        'response_value' => $row['value'],
                        'responded_at'  => $row['responded_at'] ?? null,
                        'source'        => 'mynameis',
                    ]
                );
                $saved++;
            }
        });

        return response()->json([
            'success' => true,
            'data'    => ['student_id' => $student->id, 'saved' => $saved],
            'message' => '主観自己評価を受信しました。',
        ]);
    }
}
