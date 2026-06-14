<?php

namespace App\Services;

use App\Support\PiiMasker;
use Illuminate\Support\Facades\Log;

/**
 * 施設記録基準(E1): GPT5.4 との対話で施設独自の「記録基準」を作るアドバイザ。
 *
 *  - 企業管理者の方針を引き出しつつ、良い記録基準に必要な観点(本人主語/事実と解釈の分離/
 *    具体性/強み起点/用語統一/禁止表現 等)を助言する。
 *  - 必要に応じて構造化された基準ドラフト(sections)を提案する。
 *  - PII安全: 対話は施設の方針が対象。万一の個人情報は外部送信前に scrubStructuredPii で除去(A005)。
 */
class RecordingStandardAdvisor
{
    private const MODEL = 'gpt-5.4-2026-03-05';

    /** 構造化セクションのキー(AIが返す構造をこの集合に制限する)。 */
    public const SECTION_KEYS = ['tone', 'required_points', 'terminology', 'avoid', 'good_examples', 'bad_examples'];

    /**
     * 対話の1ターン。会話履歴(+現在の基準ドラフト)を受け、助言メッセージと基準ドラフト案を返す。
     *
     * @param  array<int,array{role:string,content:string}>  $messages
     * @param  array<string,mixed>|null  $currentSections
     * @return array{reply:string, proposed_sections:?array<string,mixed>}
     */
    public function reply(array $messages, ?array $currentSections = null): array
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI APIキーが設定されていません。管理者に連絡してください。');
        }
        $client = \OpenAI::client($apiKey);

        // PII安全(A005): 外部送信前に各メッセージの個人情報痕跡を除去する。
        $convo = [];
        foreach ($messages as $m) {
            $role = in_array(($m['role'] ?? ''), ['user', 'assistant'], true) ? $m['role'] : 'user';
            $content = PiiMasker::scrubStructuredPii(trim((string) ($m['content'] ?? '')));
            if ($content !== '') {
                $convo[] = ['role' => $role, 'content' => mb_substr($content, 0, 4000)];
            }
        }
        if ($convo === []) {
            $convo[] = ['role' => 'user', 'content' => '施設の記録基準づくりを手伝ってください。'];
        }

        try {
            $res = $client->chat()->create([
                'model' => self::MODEL,
                'messages' => array_merge([['role' => 'system', 'content' => $this->systemPrompt($currentSections)]], $convo),
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.5,
                'max_completion_tokens' => 2200,
            ]);
            $data = json_decode($res->choices[0]->message->content ?? '', true);
            if (! is_array($data)) {
                throw new \RuntimeException('応答の解析に失敗しました。');
            }

            return [
                'reply' => trim((string) ($data['reply'] ?? '')),
                'proposed_sections' => isset($data['proposed_sections']) && is_array($data['proposed_sections'])
                    ? self::normalizeSections($data['proposed_sections'])
                    : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('RecordingStandardAdvisor.reply failed: '.$e->getMessage());
            throw new \RuntimeException('AIとの対話に失敗しました。時間をおいて再度お試しください。');
        }
    }

    private function systemPrompt(?array $currentSections): string
    {
        $current = $currentSections ? "\n\n【現在の基準ドラフト】\n".json_encode(self::normalizeSections($currentSections), JSON_UNESCAPED_UNICODE) : '';

        return <<<PROMPT
あなたは放課後等デイサービスの記録・支援の専門家です。事業所の管理者と対話して、その施設独自の「記録基準」を一緒に作ります。

役割:
- 管理者の意図・大切にしたい方針を引き出す問いを投げる。
- 「良い記録基準」に必要な観点を助言する。例: 本人を主語にする / 事実と解釈(支援者の見立て)を分けて書く / 場面・頻度・手立て・環境設定まで具体的に / 強み・できたことを起点にする / 専門用語や言い回しを施設内で統一する / 断定的・否定的・差別的・レッテル的表現を避ける / 個人情報(実名・住所等)を本文に書かない。
- 児童個人の話ではなく、施設全体の方針(文体・観点・用語)に焦点を当てる。具体的な児童名や個人情報は扱わない。
- ある程度方針が固まったら、構造化した基準ドラフト(proposed_sections)を提案する。まだ情報が足りなければ proposed_sections は null にして、足りない点を質問する。

必ず次のJSON形式のみで出力する:
{
  "reply": "管理者への対話メッセージ(助言・質問・提案の説明)",
  "proposed_sections": {
    "tone": "文体方針(敬体/簡潔等)を1〜2文",
    "required_points": ["必ず書く観点", "..."],
    "terminology": ["使う用語・言い回し(統一語)", "..."],
    "avoid": ["避ける表現", "..."],
    "good_examples": ["良い記述例(架空・個人名なし)", "..."],
    "bad_examples": ["避けたい記述例(架空・個人名なし)", "..."]
  }
}
proposed_sections を出さない場合は null にする。各配列は最大8件、各要素は120文字以内。{$current}
PROMPT;
    }

    /**
     * AIが返したセクションを既知キー・文字列配列に正規化する(任意構造の混入を防ぐ)。
     *
     * @param  array<string,mixed>  $sections
     * @return array<string,mixed>
     */
    public static function normalizeSections(array $sections): array
    {
        $out = [];
        foreach (self::SECTION_KEYS as $key) {
            if ($key === 'tone') {
                $tone = trim((string) ($sections['tone'] ?? ''));
                if ($tone !== '') {
                    $out['tone'] = mb_substr($tone, 0, 300);
                }

                continue;
            }
            $list = $sections[$key] ?? null;
            if (! is_array($list)) {
                continue;
            }
            $items = [];
            foreach ($list as $v) {
                if (! is_string($v)) {
                    continue;
                }
                $v = trim($v);
                if ($v !== '') {
                    $items[] = mb_substr($v, 0, 120);
                }
                if (count($items) >= 8) {
                    break;
                }
            }
            if ($items !== []) {
                $out[$key] = array_values(array_unique($items));
            }
        }

        return $out;
    }

    /** 構造化セクション → 生成プロンプト注入用の基準テキスト。 */
    public static function compileGuidance(array $sections): string
    {
        $sections = self::normalizeSections($sections);
        $parts = [];
        if (! empty($sections['tone'])) {
            $parts[] = '■ 文体方針: '.$sections['tone'];
        }
        $labels = [
            'required_points' => '■ 必ず書く観点',
            'terminology' => '■ 使う用語・言い回し',
            'avoid' => '■ 避ける表現',
        ];
        foreach ($labels as $key => $label) {
            if (! empty($sections[$key])) {
                $parts[] = $label."\n".implode("\n", array_map(fn ($v) => '・'.$v, $sections[$key]));
            }
        }
        if (! empty($sections['good_examples'])) {
            $parts[] = '■ 良い例'."\n".implode("\n", array_map(fn ($v) => "「{$v}」", $sections['good_examples']));
        }
        if (! empty($sections['bad_examples'])) {
            $parts[] = '■ 避けたい例'."\n".implode("\n", array_map(fn ($v) => "「{$v}」", $sections['bad_examples']));
        }

        return implode("\n", $parts);
    }
}
