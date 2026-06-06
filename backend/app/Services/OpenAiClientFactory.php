<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * OpenAI クライアントの生成を一元化するファクトリ。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R6 (2026-05-17) 対応:
 *  - organization / project ヘッダを config('services.openai.*') から付与
 *  - Zero Data Retention 契約状況のログ警告
 *  - API キー未設定時の早期検出
 *
 * 既存の `\OpenAI::client($apiKey)` 直接呼出を順次これに置き換えていく方針。
 * Phase A 着手段階では AiGenerationService のみ移行。コントローラ直書き 14 箇所は
 * Phase B (R1 / R2) と合わせて段階的に統合する。
 */
class OpenAiClientFactory
{
    /**
     * 設定済の OpenAI クライアントを生成する。
     *
     * @throws \RuntimeException API キー未設定時
     */
    public static function make(): \OpenAI\Client
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API キーが設定されていません (config services.openai.api_key)。');
        }

        $organization = config('services.openai.organization');
        $project = config('services.openai.project');
        $zdr = (bool) config('services.openai.zero_data_retention', false);

        // ZDR が未契約の場合、要配慮個人情報を含むデータが OpenAI 側で
        // 学習・キャッシュ利用される可能性があるため警告。本番運用では
        // OPENAI_ZDR=true を必須とする方針。
        if (! $zdr) {
            Log::warning('OpenAI client built without Zero Data Retention configuration', [
                'organization_set' => ! empty($organization),
                'reminder'         => 'set OPENAI_ZDR=true after confirming the Enterprise ZDR contract',
            ]);
        }

        $factory = \OpenAI::factory()->withApiKey($apiKey);

        if (! empty($organization)) {
            $factory = $factory->withOrganization($organization);
        }

        if (! empty($project)) {
            // openai-php/client が project ヘッダをサポートする場合のみ追加
            if (method_exists($factory, 'withProject')) {
                $factory = $factory->withProject($project);
            } else {
                $factory = $factory->withHttpHeader('OpenAI-Project', $project);
            }
        }

        return $factory->make();
    }

    /**
     * 現在の OpenAI 構成のサマリ (規約画面 / 監査資料向け)。
     */
    public static function summary(): array
    {
        return [
            'model'                 => config('services.openai.model'),
            'organization_set'      => ! empty(config('services.openai.organization')),
            'project_set'           => ! empty(config('services.openai.project')),
            'zero_data_retention'   => (bool) config('services.openai.zero_data_retention', false),
            'dpa_url'               => config('services.openai.dpa_url'),
        ];
    }
}
