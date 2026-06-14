<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * AI学習基盤 同意基盤: 同意定義(consent_definitions)未投入のまま「同意」を記録しようとした。
 *
 * 版(version)・定義IDに紐づかない granted レコードは「どの文面版に同意したか」を後から立証できず、
 * append-only のため修正不可。fail-closed として grant を中断する(撤回=revoke はブロックしない)。
 * 本番では ConsentDefinitionSeeder の投入が前提。
 */
class ConsentDefinitionMissingException extends RuntimeException
{
    public function __construct(public readonly string $consentKey)
    {
        parent::__construct("consent_definitions が未登録のため同意を記録できません(consent_key={$consentKey})。ConsentDefinitionSeeder の投入を確認してください。");
    }

    /** Laravel が自動で呼ぶ。利用者には原因と対処を返す(422)。 */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => '同意定義が未投入のため、同意を記録できません。システム管理者にお問い合わせください。',
        ], 422);
    }
}
