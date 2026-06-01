<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 既存の生徒・保護者のふりがな(student_name_kana / full_name_kana)を
 * 氏名(漢字)から AI で一括生成して補完する。
 *
 * 経緯: 50音順ソートのために kana 列を追加したが、既存レコードは未入力のため
 * そのままでは正しく並ばない。氏名から「よみ(ひらがな)」を生成して埋める。
 *
 * 重要な前提:
 *   - 人名の漢字読みは多義(同じ漢字でも読みが異なる)ため 100% 正確にはできない。
 *   - そのため confidence が "low" のものは書き込まず空のまま残し、現場で
 *     手入力・確認してもらう運用とする (--min-confidence で閾値調整可)。
 *   - 既存のふりがな(入力済み)は上書きしない (--overwrite 指定時のみ上書き)。
 *
 * 使い方:
 *   php artisan kana:backfill --dry-run                 # 生成結果を表示のみ(書き込みなし)
 *   php artisan kana:backfill                            # students + guardians を補完
 *   php artisan kana:backfill --type=students
 *   php artisan kana:backfill --type=guardians
 *   php artisan kana:backfill --limit=50                # 先頭50件だけ
 *   php artisan kana:backfill --min-confidence=high     # high のみ書き込む(既定: medium)
 *   php artisan kana:backfill --overwrite               # 既存ふりがなも上書き
 */
class BackfillKana extends Command
{
    protected $signature = 'kana:backfill
        {--type=all : 対象 (students|guardians|all)}
        {--limit=0 : 処理件数の上限 (0=無制限)}
        {--batch=20 : 1リクエストあたりの氏名数}
        {--min-confidence=medium : 書き込む最低信頼度 (high|medium|low)}
        {--overwrite : 既存のふりがなも上書きする}
        {--dry-run : 生成結果を表示するだけで書き込まない}';

    protected $description = '既存の生徒・保護者の氏名から AI でふりがなを一括生成して補完 (低信頼は空のまま残す)';

    /** confidence 文字列を数値ランクへ */
    private const RANK = ['low' => 1, 'medium' => 2, 'high' => 3];

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $limit = (int) $this->option('limit');
        $batchSize = max(1, (int) $this->option('batch'));
        $overwrite = (bool) $this->option('overwrite');
        $dryRun = (bool) $this->option('dry-run');
        $minRank = self::RANK[(string) $this->option('min-confidence')] ?? self::RANK['medium'];

        if (! in_array($type, ['students', 'guardians', 'all'], true)) {
            $this->error("--type は students|guardians|all のいずれかを指定してください。");
            return self::INVALID;
        }

        $totals = ['updated' => 0, 'skipped_low' => 0, 'failed' => 0, 'seen' => 0];

        if ($type === 'students' || $type === 'all') {
            $this->info('=== 生徒 (students.student_name_kana) ===');
            $this->process(
                Student::query()
                    ->when(! $overwrite, fn ($q) => $q->where(fn ($w) => $w->whereNull('student_name_kana')->orWhere('student_name_kana', '')))
                    ->orderBy('id')
                    ->when($limit > 0, fn ($q) => $q->limit($limit))
                    ->get(['id', 'student_name']),
                fn ($row) => $row->student_name,
                fn ($row, $kana) => $this->saveStudent($row, $kana),
                $batchSize, $minRank, $dryRun, $totals,
            );
        }

        if ($type === 'guardians' || $type === 'all') {
            $this->info('=== 保護者 (users.full_name_kana) ===');
            $this->process(
                User::query()
                    ->where('user_type', 'guardian')
                    ->when(! $overwrite, fn ($q) => $q->where(fn ($w) => $w->whereNull('full_name_kana')->orWhere('full_name_kana', '')))
                    ->orderBy('id')
                    ->when($limit > 0, fn ($q) => $q->limit($limit))
                    ->get(['id', 'full_name']),
                fn ($row) => $row->full_name,
                fn ($row, $kana) => $this->saveUser($row, $kana),
                $batchSize, $minRank, $dryRun, $totals,
            );
        }

        $this->newLine();
        $this->info(sprintf(
            '完了%s: 対象 %d 件 / 書込 %d 件 / 低信頼スキップ %d 件 / 失敗 %d 件',
            $dryRun ? ' [DRY RUN]' : '',
            $totals['seen'], $totals['updated'], $totals['skipped_low'], $totals['failed'],
        ));
        if (! $dryRun) {
            $this->warn('※ 人名の読みは AI 推定です。必ず現場で確認・修正してください。低信頼の氏名は空のまま残しています。');
        }

        Log::info('kana:backfill completed', array_merge($totals, [
            'type' => $type, 'dry_run' => $dryRun, 'min_confidence' => $this->option('min-confidence'),
        ]));

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection  $rows
     * @param  callable  $nameOf      fn($row): string
     * @param  callable  $persist     fn($row, string $kana): void
     */
    private function process($rows, callable $nameOf, callable $persist, int $batchSize, int $minRank, bool $dryRun, array &$totals): void
    {
        if ($rows->isEmpty()) {
            $this->line('  対象なし');
            return;
        }

        foreach ($rows->chunk($batchSize) as $chunk) {
            $names = $chunk->map($nameOf)->map(fn ($n) => trim((string) $n))->values()->all();

            try {
                $results = $this->generateYomi($names); // ['氏名' => ['yomi'=>..,'confidence'=>..], ...]
            } catch (\Throwable $e) {
                $totals['failed'] += count($names);
                $this->error('  バッチ生成に失敗: ' . $e->getMessage());
                Log::warning('kana:backfill batch failed', ['error' => $e->getMessage(), 'names' => $names]);
                continue;
            }

            foreach ($chunk as $row) {
                $totals['seen']++;
                $name = trim((string) $nameOf($row));
                $entry = $results[$name] ?? null;
                $yomi = $entry['yomi'] ?? null;
                $conf = $entry['confidence'] ?? 'low';
                $rank = self::RANK[$conf] ?? 1;

                if (! $yomi || $rank < $minRank) {
                    $totals['skipped_low']++;
                    $this->line(sprintf('  - %s → (%s) スキップ', $name, $yomi ? "{$yomi}/{$conf}" : '生成不可'));
                    continue;
                }

                $this->line(sprintf('  ✓ %s → %s [%s]', $name, $yomi, $conf));
                if (! $dryRun) {
                    $persist($row, $yomi);
                }
                $totals['updated']++;
            }
        }
    }

    private function saveStudent(Student $s, string $kana): void
    {
        $s->student_name_kana = $kana;
        $s->save();
    }

    private function saveUser(User $u, string $kana): void
    {
        $u->full_name_kana = $kana;
        $u->save();
    }

    /**
     * 氏名配列 → ['氏名' => ['yomi'=>'ひらがな','confidence'=>'high|medium|low']]
     */
    private function generateYomi(array $names): array
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $client = \OpenAI::client($apiKey);

        $list = collect($names)->values()
            ->map(fn ($n, $i) => ($i + 1) . '. ' . $n)
            ->implode("\n");

        $response = $client->chat()->create([
            'model' => config('services.openai.model', 'gpt-5.4-2026-03-05'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'あなたは日本語の人名のふりがな(よみ)を判定するアシスタントです。'
                        . '与えられた氏名それぞれについて、ひらがなの読みを推定してください。'
                        . '姓と名の間に半角スペースを1つ入れてください。'
                        . '人名は同じ漢字でも複数の読みがあり得るため、'
                        . '一般的で確度が高いものは confidence を "high"、'
                        . '読みが複数あり得るが妥当な推定ができるものは "medium"、'
                        . '読みの判断が難しい・自信がないものは "low" としてください。'
                        . '必ず次の JSON 形式のみで回答してください: '
                        . '{"items":[{"name":"<入力された氏名そのまま>","yomi":"<ひらがな>","confidence":"high|medium|low"}]}',
                ],
                [
                    'role' => 'user',
                    'content' => "次の氏名のよみを判定してください:\n" . $list,
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
            'max_completion_tokens' => 2000,
        ]);

        $content = json_decode($response->choices[0]->message->content ?? '{}', true) ?: [];
        $out = [];
        foreach (($content['items'] ?? []) as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[$name] = [
                'yomi' => trim((string) ($item['yomi'] ?? '')),
                'confidence' => (string) ($item['confidence'] ?? 'low'),
            ];
        }

        return $out;
    }
}
