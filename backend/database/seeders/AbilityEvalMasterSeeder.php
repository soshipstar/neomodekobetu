<?php

namespace Database\Seeders;

use App\Models\AbilityEvalAxis;
use App\Models\AbilityEvalBenchmark;
use App\Models\AbilityEvalItem;
use App\Models\AbilityEvalScoreCriterion;
use App\Models\AbilityEvalTool;
use App\Models\AbilitySupportCode;
use App\Models\AbilityTalentCriterion;
use App\Models\AbilityTalentObservationTask;
use App\Models\AbilityTalentSign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 能力評価マスタ(ものさし)を docs/評価表 由来の JSON から投入する。
 *
 * 正本は database/data/ability_eval/*.json(「能力評価データベース.xlsx」から抽出)。
 * 冪等(updateOrCreate)なので本番・CI・開発で繰り返し実行できる。
 * 値は位置(array_values)で取り出し、Excel 見出し文字列の表記揺れに依存しない。
 */
class AbilityEvalMasterSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('data/ability_eval');

        DB::transaction(function () use ($dir) {
            // 依存順に投入する。

            // 評価ツール: [tool_id, name, target, axis_type]
            foreach ($this->load($dir, 'tools') as $r) {
                AbilityEvalTool::updateOrCreate(
                    ['tool_id' => $r[0]],
                    ['name' => $r[1], 'target' => $this->n($r[2]), 'axis_type' => $this->n($r[3])]
                );
            }

            // 軸: [axis_id, axis_type, name, sort_order]
            foreach ($this->load($dir, 'axes') as $r) {
                AbilityEvalAxis::updateOrCreate(
                    ['axis_id' => $r[0]],
                    ['axis_type' => $r[1], 'name' => $r[2], 'sort_order' => (int) ($r[3] ?: 0)]
                );
            }

            // 項目: [item_id, tool_id, domain, name, definition, perspective, source]
            foreach ($this->load($dir, 'items') as $r) {
                AbilityEvalItem::updateOrCreate(
                    ['item_id' => $r[0]],
                    [
                        'tool_id' => $r[1], 'domain' => $r[2], 'name' => $r[3],
                        'definition' => $this->n($r[4]), 'perspective' => $this->n($r[5]), 'source' => $this->n($r[6]),
                    ]
                );
            }

            // 到達目安: [item_id, axis_id, benchmark]
            foreach ($this->load($dir, 'benchmarks') as $r) {
                AbilityEvalBenchmark::updateOrCreate(
                    ['item_id' => $r[0], 'axis_id' => $r[1]],
                    ['benchmark' => $r[2]]
                );
            }

            // 評価基準: [score, name, criteria, guardian_words, example, evidence]
            foreach ($this->load($dir, 'score_criteria') as $r) {
                AbilityEvalScoreCriterion::updateOrCreate(
                    ['score' => (int) $r[0]],
                    [
                        'name' => $r[1], 'criteria' => $this->n($r[2]), 'guardian_words' => $this->n($r[3]),
                        'example' => $this->n($r[4]), 'evidence' => $this->n($r[5]),
                    ]
                );
            }

            // 支援コード: [code, content, score_band] (sort_order は出現順)
            foreach ($this->load($dir, 'support_codes') as $i => $r) {
                AbilitySupportCode::updateOrCreate(
                    ['code' => $r[0]],
                    ['content' => $r[1], 'score_band' => $this->n($r[2]), 'sort_order' => $i]
                );
            }

            // 才能サイン: [sign_id, strength, sign, grow_activities, careers, related_item_id]
            foreach ($this->load($dir, 'talent_signs') as $r) {
                AbilityTalentSign::updateOrCreate(
                    ['sign_id' => $r[0]],
                    [
                        'strength' => $r[1], 'sign' => $this->n($r[2]), 'grow_activities' => $this->n($r[3]),
                        'careers' => $this->n($r[4]), 'related_item_id' => $this->n($r[5]),
                    ]
                );
            }

            // 才能観察課題: [sign_id, strength, method, notes]
            foreach ($this->load($dir, 'talent_observation_tasks') as $r) {
                AbilityTalentObservationTask::updateOrCreate(
                    ['sign_id' => $r[0]],
                    ['strength' => $r[1], 'method' => $this->n($r[2]), 'notes' => $this->n($r[3])]
                );
            }

            // 才能判定基準: [sign_id, strength, level, level_name, criteria]
            foreach ($this->load($dir, 'talent_criteria') as $r) {
                AbilityTalentCriterion::updateOrCreate(
                    ['sign_id' => $r[0], 'level' => (int) $r[2]],
                    ['level_name' => $this->n($r[3]), 'criteria' => $this->n($r[4])]
                );
            }
        });
    }

    /**
     * JSON を読み込み、各レコードを位置配列(array_values)で返す。
     *
     * @return array<int, array<int, string>>
     */
    private function load(string $dir, string $name): array
    {
        $path = "{$dir}/{$name}.json";
        $json = json_decode(file_get_contents($path), true);

        return array_map(fn ($rec) => array_values($rec), $json['records']);
    }

    /** 空文字を null に正規化する。 */
    private function n(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return ($v === null || $v === '') ? null : $v;
    }
}
