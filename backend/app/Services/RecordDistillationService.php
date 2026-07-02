<?php

namespace App\Services;

use App\Models\Student;
use App\Support\PiiMasker;
use Illuminate\Support\Facades\Log;

/**
 * 連絡帳の「全件」二段集約(蒸留)サービス。
 *
 * 課題: 従来の職員アセスメント/モニタリング生成は、連絡帳を「領域あたり最新10件」
 * 「目標あたり最新20件」に切り捨てており、対象期間に多数の連絡帳があっても大半が
 * AIに渡らず "死んでいる" 状態だった。
 *
 * 本サービスは対象期間の連絡帳を**すべて**チャンクに分割し、各チャンクを領域別に
 * 要約(map)してから結合(reduce)する。各レコードは必ずいずれか1つのチャンクに属す
 * ため、件数上限による取りこぼしが原理的に発生しない。
 *
 * テスト容易性: 実際のAI呼び出し(summarizeChunk)は $summarizer で差し替え可能。
 * 単体テストはダミー summarizer を注入して「全レコードが要約対象に渡ること」を検証する。
 *
 * 観点5(プライバシー): 外部AIへ渡すチャンクは本サービス内で必ず PiiMasker を通す
 * (A-005 ガード対象)。
 */
class RecordDistillationService
{
    /** 連絡帳の5領域カラム → 表示名。 */
    public const DOMAINS = [
        'health_life'            => '健康・生活',
        'motor_sensory'          => '運動・感覚',
        'cognitive_behavior'     => '認知・行動',
        'language_communication' => '言語・コミュニケーション',
        'social_relations'       => '人間関係・社会性',
    ];

    /** チャンク要約に用いる軽量モデル。 */
    private const CHUNK_MODEL = 'gpt-5.4-mini-2026-03-17';

    /**
     * 連絡帳(StudentRecord)を領域別に蒸留する。
     *
     * @param  iterable  $records     dailyRecord をロード済みの StudentRecord 群(全件)
     * @param  callable|null  $summarizer  fn(string $maskedPrompt): array{0:string,1:int,2:int}
     *                                      (本文, 入力トークン, 出力トークン)。null で実AI。
     * @param  bool  $groupByMonth  true で時系列(月別)構造の蒸留にする。
     *   記録を古い月→新しい月に整列し、チャンクが月をまたがないよう分割して
     *   各要約に【YYYY年M月】ラベルを付ける。アセスメントの「直近重視＋変化(成長)の
     *   明記」を確実にするための時間構造。false は従来どおり(呼び出し元互換)。
     * @return array{
     *   digests: array<string,string>, counts: array<string,int>,
     *   source_count: int, input_tokens: int, output_tokens: int, chunks: int
     * }
     */
    public function distillByDomain(
        Student $student,
        $records,
        PiiMasker $masker,
        ?callable $summarizer = null,
        int $chunkSize = 50,
        int $rawThreshold = 24,
        bool $groupByMonth = false
    ): array {
        $records = collect($records)->values();
        if ($groupByMonth) {
            // 時系列構造: 古い→新しい順に整列(日付文字列は Y-m-d 前提で辞書順=時系列順)
            $records = $records->sortBy(fn ($r) => $this->recordDate($r))->values();
        }
        $counts = array_fill_keys(array_keys(self::DOMAINS), 0);
        $emptyDigests = array_fill_keys(array_keys(self::DOMAINS), '');

        // 領域別に全エントリを展開(取りこぼしを起こさないため全件を対象にする)
        $entriesByDomain = array_fill_keys(array_keys(self::DOMAINS), []);
        $usable = [];
        foreach ($records as $r) {
            $date = $this->recordDate($r);
            $has = false;
            foreach (self::DOMAINS as $col => $label) {
                $val = $r->{$col} ?? null;
                if (! empty($val)) {
                    $entriesByDomain[$col][] = "【{$date}】" . trim((string) $val);
                    $counts[$col]++;
                    $has = true;
                }
            }
            if ($has) {
                $usable[] = $r;
            }
        }
        $sourceCount = count($usable);
        $totalEntries = array_sum($counts);

        if ($sourceCount === 0) {
            return $this->result($emptyDigests, $counts, 0, 0, 0, 0);
        }

        // 小規模(しきい値以下)は要約せず全文をそのまま使う(忠実性優先・呼び出し節約)
        if ($totalEntries <= $rawThreshold) {
            $digests = [];
            foreach (self::DOMAINS as $col => $label) {
                $digests[$col] = implode("\n", $entriesByDomain[$col]);
            }

            return $this->result($digests, $counts, $sourceCount, 0, 0, 0);
        }

        $summarizer ??= fn (string $p) => $this->summarizeChunk($p);

        // レコード単位でチャンク化。各レコードは必ず1チャンクに入る = 取りこぼしゼロ。
        // groupByMonth 時はチャンクが月をまたがないよう「月ごと」に区切ってから分割し、
        // 各チャンク要約に月ラベルを付けて時間構造を保存する。
        $chunkGroups = [];
        if ($groupByMonth) {
            $byMonth = collect($usable)
                ->groupBy(function ($r) {
                    $d = $this->recordDate($r);

                    return $d !== '' ? substr($d, 0, 7) : '';
                })
                ->sortKeys();
            foreach ($byMonth as $month => $group) {
                foreach ($group->chunk($chunkSize) as $chunk) {
                    $chunkGroups[] = ['label' => $this->monthLabel((string) $month), 'records' => $chunk];
                }
            }
        } else {
            foreach (collect($usable)->chunk($chunkSize) as $chunk) {
                $chunkGroups[] = ['label' => null, 'records' => $chunk];
            }
        }

        $partials = array_fill_keys(array_keys(self::DOMAINS), []);
        $inTok = 0;
        $outTok = 0;
        $nChunks = 0;

        foreach ($chunkGroups as $chunkGroup) {
            $byDomain = [];
            foreach ($chunkGroup['records'] as $r) {
                $date = $this->recordDate($r);
                foreach (self::DOMAINS as $col => $label) {
                    $val = $r->{$col} ?? null;
                    if (! empty($val)) {
                        $byDomain[$col][] = "【{$date}】" . trim((string) $val);
                    }
                }
            }
            if (empty($byDomain)) {
                continue;
            }
            $nChunks++;
            $prefix = $chunkGroup['label'] !== null ? "【{$chunkGroup['label']}】\n" : '';

            [$content, $i, $o] = $summarizer($masker->mask($this->chunkPrompt($byDomain)));
            $inTok += (int) $i;
            $outTok += (int) $o;

            $data = json_decode((string) $content, true);
            if (is_array($data)) {
                foreach (self::DOMAINS as $col => $label) {
                    if (! empty($data[$col])) {
                        $partials[$col][] = $prefix . trim((string) $data[$col]);
                    }
                }
            } else {
                // 要約失敗時は当該チャンクの生記録を残す(取りこぼし防止のフォールバック)
                Log::warning('RecordDistillation: chunk summary parse failed; keeping raw records');
                foreach ($byDomain as $col => $lines) {
                    $partials[$col][] = $prefix . implode("\n", $lines);
                }
            }
        }

        $digests = [];
        foreach (self::DOMAINS as $col => $label) {
            $digests[$col] = implode("\n\n", $partials[$col]);
        }

        return $this->result($digests, $counts, $sourceCount, $inTok, $outTok, $nChunks);
    }

    /**
     * 蒸留結果を「■領域名\n本文」形式のテキストに整形(プロンプト差し込み用)。
     *
     * @param  array<string,string>  $digests
     * @param  list<string>|null  $onlyColumns  指定領域のみ(null で全領域)
     */
    public function toPromptText(array $digests, ?array $onlyColumns = null): string
    {
        $out = '';
        foreach (self::DOMAINS as $col => $label) {
            if ($onlyColumns !== null && ! in_array($col, $onlyColumns, true)) {
                continue;
            }
            if (! empty($digests[$col])) {
                $out .= "\n■ {$label}\n" . $digests[$col] . "\n";
            }
        }

        return $out;
    }

    private function recordDate($record): string
    {
        $d = $record->dailyRecord?->record_date ?? null;

        return $d ? (string) $d : '';
    }

    /** 'YYYY-MM' → 'YYYY年M月'。日付が取れない場合は '日付不明'。 */
    private function monthLabel(string $yearMonth): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $m)) {
            return $m[1] . '年' . (int) $m[2] . '月';
        }

        return '日付不明';
    }

    /**
     * @param  array<string,array<int,string>>  $byDomain
     */
    private function chunkPrompt(array $byDomain): string
    {
        $p = "あなたは発達支援の記録を整理するアシスタントです。以下は児童の連絡帳記録の一群(対象期間の一部)です。\n"
            . "領域ごとに、観察された具体的な事実・エピソード・変化を、日付の手がかりを保ちながら簡潔な箇条書きで要約してください。\n"
            . "記録にある事実のみを用い、推測や創作はしないでください。記録が無い領域は空文字にしてください。\n\n";
        foreach (self::DOMAINS as $col => $label) {
            if (! empty($byDomain[$col])) {
                $p .= "■{$label}\n" . implode("\n", $byDomain[$col]) . "\n\n";
            }
        }
        $keys = array_keys(self::DOMAINS);
        $p .= "出力は次のJSONのみ(各値は要約テキスト、無ければ空文字):\n{"
            . implode(', ', array_map(fn ($k) => "\"{$k}\": \"...\"", $keys))
            . "}";

        return $p;
    }

    /**
     * 既定の要約器(実AI)。$maskedPrompt はマスク済みであること。
     *
     * @return array{0:string,1:int,2:int}
     */
    private function summarizeChunk(string $maskedPrompt): array
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI APIキーが設定されていません。');
        }

        $res = \OpenAI::client($apiKey)->chat()->create([
            'model'                 => self::CHUNK_MODEL,
            'messages'              => [['role' => 'user', 'content' => $maskedPrompt]],
            'response_format'       => ['type' => 'json_object'],
            'temperature'           => 0.3,
            'max_completion_tokens' => 1500,
        ]);

        return [
            $res->choices[0]->message->content ?? '',
            $res->usage->promptTokens ?? 0,
            $res->usage->completionTokens ?? 0,
        ];
    }

    /**
     * @param  array<string,string>  $digests
     * @param  array<string,int>  $counts
     * @return array<string,mixed>
     */
    private function result(array $digests, array $counts, int $sourceCount, int $inTok, int $outTok, int $chunks): array
    {
        return [
            'digests'       => $digests,
            'counts'        => $counts,
            'source_count'  => $sourceCount,
            'input_tokens'  => $inTok,
            'output_tokens' => $outTok,
            'chunks'        => $chunks,
        ];
    }
}
