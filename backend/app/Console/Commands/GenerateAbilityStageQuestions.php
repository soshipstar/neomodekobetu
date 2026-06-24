<?php

namespace App\Console\Commands;

use App\Models\AbilityEvalAxis;
use App\Models\AbilityEvalBenchmark;
use App\Models\AbilityEvalItem;
use App\Models\AbilityStageQuestion;
use App\Services\AiGenerationService;
use App\Support\AbilityToolScope;
use Illuminate\Console\Command;

/**
 * 能力評価: 項目×学年帯ごとの「具体的で観察できる問い」を AI で一括生成し、
 * ability_stage_questions に保存する(P-C2)。
 *
 * 経緯: 到達目安テキストをそのまま設問にすると指導員が答えにくいため、各(項目×学年帯)に
 * 「どれくらいできているか」で答えられる具体設問＋観察ヒントを用意する。まず療育(DEV)の
 * 5領域 × 段階 S1〜S6 = 150 問を生成する。生成直後から日々の出題に使い、後で編集・再生成可。
 *
 * 使い方:
 *   php artisan ability:generate-stage-questions --tool=DEV --dry-run  # 表示のみ(保存なし)
 *   php artisan ability:generate-stage-questions --tool=DEV            # 生成して保存(既存はスキップ)
 *   php artisan ability:generate-stage-questions --tool=DEV --force    # 既存も再生成
 */
class GenerateAbilityStageQuestions extends Command
{
    protected $signature = 'ability:generate-stage-questions {--tool=DEV} {--force} {--dry-run}';

    protected $description = '能力評価: 項目×学年帯の具体設問をAIで生成し ability_stage_questions に保存する';

    public function handle(AiGenerationService $ai): int
    {
        $tool = strtoupper((string) $this->option('tool'));
        $axes = AbilityToolScope::axesForTool($tool);
        $items = AbilityEvalItem::where('tool_id', $tool)->orderBy('item_id')->get();
        $axisNames = AbilityEvalAxis::pluck('name', 'axis_id');

        if ($items->isEmpty()) {
            $this->error("ツール {$tool} の項目がありません。先にマスターを seed してください。");

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $records = [];

        foreach ($items as $item) {
            foreach ($axes as $axis) {
                $exists = AbilityStageQuestion::where('item_id', $item->item_id)
                    ->where('axis_id', $axis)->exists();
                if ($exists && ! $this->option('force')) {
                    $skipped++;
                    continue;
                }

                $benchmark = (string) (AbilityEvalBenchmark::where('item_id', $item->item_id)
                    ->where('axis_id', $axis)->value('benchmark') ?? '');
                // 到達目安が無いツール(WRK/UNV)は判断の観点を基準に生成する。
                // 到達目安も観点も無ければ基準が無いのでスキップ。
                if ($benchmark === '' && trim((string) $item->perspective) === '') {
                    continue;
                }

                $res = $ai->generateStageQuestion([
                    'domain' => (string) $item->domain,
                    'item_name' => (string) $item->name,
                    'definition' => (string) $item->definition,
                    'perspective' => (string) $item->perspective,
                    'stage_name' => (string) ($axisNames[$axis] ?? $axis),
                    'benchmark' => $benchmark,
                ]);

                $question = trim((string) ($res['question'] ?? ''));
                $hint = trim((string) ($res['hint'] ?? ''));
                if ($question === '') {
                    $this->warn("空の設問: {$item->item_id} {$axis}");
                    continue;
                }

                if (! $this->option('dry-run')) {
                    AbilityStageQuestion::updateOrCreate(
                        ['item_id' => $item->item_id, 'axis_id' => $axis],
                        [
                            'question' => $question,
                            'hint' => $hint !== '' ? $hint : null,
                            'model' => config('services.openai.model', 'gpt-5.4-2026-03-05'),
                            'is_active' => true,
                            'generated_at' => now(),
                        ],
                    );
                }

                $records[] = ['item_id' => $item->item_id, 'axis_id' => $axis, 'question' => $question, 'hint' => $hint];
                $created++;
                $this->line("✓ {$item->item_id} {$axis}  {$question}");
            }
        }

        // 確認用JSON(人が見て編集判断できるように)
        if (! $this->option('dry-run') && $records !== []) {
            $path = storage_path("app/ability_stage_questions_{$tool}.json");
            file_put_contents($path, json_encode(['records' => $records], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->info("確認用JSON: {$path}");
        }

        $this->info("生成 {$created} / スキップ {$skipped}" . ($this->option('dry-run') ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
