<?php

namespace App\Support;

use App\Models\Student;

/**
 * 生成AI(外部=OpenAI)へテキストを送る前に、児童・保護者の氏名等の個人情報
 * (要配慮個人情報) を仮名プレースホルダへ置換し、AIの出力を呼び出し側へ返す際に
 * 実値へ復元するためのユーティリティ。
 *
 * 目的(AIセーフティ ガイドライン 観点5 プライバシー保護):
 *  - 外部AIへ実名等のPIIを送信しない (マスキング/仮名化)
 *  - ai_generation_logs 等のログにも実名を残さない (マスク後を保存)
 *  - 一方でAI出力(下書き)は職員が使うため、出力テキスト内のプレースホルダは実名へ復元する
 *
 * 使い方:
 *   $masker = PiiMasker::forStudent($student);
 *   $maskedPrompt = $masker->mask($prompt);            // → OpenAIへ送信 & ログ保存
 *   $restored = $masker->unmaskArray($jsonOutput);     // → 呼び出し側へ返す(実名復元)
 */
class PiiMasker
{
    /**
     * 実値 => プレースホルダ の対応表。
     *
     * @var array<string, string>
     */
    private array $map = [];

    /**
     * 誤置換(部分一致による巻き込み)を避けるため、これ未満の長さの実値は登録しない。
     */
    private const MIN_LENGTH = 2;

    /**
     * 実値とプレースホルダの対応を登録する。null/空/短すぎる値は無視。
     */
    public function add(?string $real, string $placeholder): self
    {
        $real = is_string($real) ? trim($real) : '';
        if ($real !== '' && mb_strlen($real) >= self::MIN_LENGTH && ! isset($this->map[$real])) {
            $this->map[$real] = $placeholder;
        }

        return $this;
    }

    /**
     * 児童と、その保護者の氏名(ふりがな含む)を登録した masker を作る。
     */
    public static function forStudent(Student $student): self
    {
        $masker = new self();
        $masker->add($student->student_name, '【児童】');
        $masker->add($student->student_name_kana, '【児童カナ】');

        $guardian = $student->relationLoaded('guardian')
            ? $student->guardian
            : ($student->guardian_id ? $student->guardian()->first() : null);

        if ($guardian) {
            $masker->add($guardian->full_name, '【保護者】');
            $masker->add($guardian->full_name_kana ?? null, '【保護者カナ】');
        }

        return $masker;
    }

    /**
     * 登録された対応が一つも無い(=マスク対象なし)か。
     */
    public function isEmpty(): bool
    {
        return $this->map === [];
    }

    /**
     * 実値 → プレースホルダ。長い実値から順に置換し部分一致の巻き込みを防ぐ。
     */
    public function mask(string $text): string
    {
        if ($text === '' || $this->isEmpty()) {
            return $text;
        }

        $reals = array_keys($this->map);
        usort($reals, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($reals as $real) {
            $text = str_replace($real, $this->map[$real], $text);
        }

        return $text;
    }

    /**
     * プレースホルダ → 実値 (AI出力の復元)。
     */
    public function unmask(string $text): string
    {
        if ($text === '' || $this->isEmpty()) {
            return $text;
        }

        foreach ($this->map as $real => $placeholder) {
            $text = str_replace($placeholder, $real, $text);
        }

        return $text;
    }

    /**
     * 配列(ネスト含む)内の文字列値を再帰的に復元する。
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public function unmaskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->unmask($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->unmaskArray($value);
            }
        }

        return $data;
    }
}
