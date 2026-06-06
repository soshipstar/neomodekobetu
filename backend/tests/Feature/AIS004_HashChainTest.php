<?php

namespace Tests\Feature;

use App\Models\Concerns\HashChainable;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R9 / R12 (2026-05-17):
 *  - V6 セキュリティ確保 / 表 3-7 ⑤ 改ざん不可能な監査ログ
 *  - V10 検証可能性 / 表 3-11 ③ 改ざん防止が実装
 *
 * HashChainable トレイトの computeHash 計算ロジックの単体検証。
 * (実際の INSERT 自動計算は MySQL/PostgreSQL の Feature テストで別途検証)
 */
class AIS004_HashChainTest extends TestCase
{
    public function test_hash_is_deterministic_for_same_inputs(): void
    {
        $model = $this->makeFakeModel(['user_id' => 1, 'action' => 'create', 'target_id' => 100]);
        $fields = ['user_id', 'action', 'target_id'];

        $h1 = HashChainable::computeHash($model, $fields, null);
        $h2 = HashChainable::computeHash($model, $fields, null);

        $this->assertSame($h1, $h2);
        $this->assertSame(64, strlen($h1));   // sha256 hex
    }

    public function test_hash_changes_when_prev_hash_differs(): void
    {
        $model = $this->makeFakeModel(['user_id' => 1, 'action' => 'create']);
        $fields = ['user_id', 'action'];

        $hA = HashChainable::computeHash($model, $fields, 'previous_hash_a');
        $hB = HashChainable::computeHash($model, $fields, 'previous_hash_b');

        $this->assertNotSame($hA, $hB);
    }

    public function test_hash_changes_when_any_field_value_changes(): void
    {
        $fields = ['user_id', 'action', 'target_id'];
        $modelOriginal = $this->makeFakeModel(['user_id' => 1, 'action' => 'create', 'target_id' => 100]);
        $modelTampered = $this->makeFakeModel(['user_id' => 1, 'action' => 'delete', 'target_id' => 100]);

        $hOriginal = HashChainable::computeHash($modelOriginal, $fields, 'abc');
        $hTampered = HashChainable::computeHash($modelTampered, $fields, 'abc');

        $this->assertNotSame($hOriginal, $hTampered);
    }

    public function test_hash_uses_only_declared_fields(): void
    {
        $fields = ['user_id'];
        $modelA = $this->makeFakeModel(['user_id' => 1, 'action' => 'create']);
        $modelB = $this->makeFakeModel(['user_id' => 1, 'action' => 'delete']);

        // hashFields に action が含まれないため、action 改変は検知できない
        $hA = HashChainable::computeHash($modelA, $fields, 'p');
        $hB = HashChainable::computeHash($modelB, $fields, 'p');

        $this->assertSame($hA, $hB);
    }

    public function test_array_fields_are_serialized_stably(): void
    {
        $fields = ['old_values'];
        $modelA = $this->makeFakeModel(['old_values' => ['a' => 1, 'b' => 2]]);
        $modelB = $this->makeFakeModel(['old_values' => ['a' => 1, 'b' => 2]]);

        $hA = HashChainable::computeHash($modelA, $fields, null);
        $hB = HashChainable::computeHash($modelB, $fields, null);

        $this->assertSame($hA, $hB);
    }

    public function test_array_field_value_change_is_detected(): void
    {
        $fields = ['old_values'];
        $modelA = $this->makeFakeModel(['old_values' => ['a' => 1]]);
        $modelB = $this->makeFakeModel(['old_values' => ['a' => 2]]);

        $hA = HashChainable::computeHash($modelA, $fields, null);
        $hB = HashChainable::computeHash($modelB, $fields, null);

        $this->assertNotSame($hA, $hB);
    }

    /**
     * テスト用のフェイクモデル (DB に紐付かない)。
     * Eloquent Model の getAttribute を介して attributes 配列を返す最小実装。
     */
    private function makeFakeModel(array $attributes): Model
    {
        return new class($attributes) extends Model {
            public function __construct(array $attrs)
            {
                parent::__construct();
                foreach ($attrs as $k => $v) $this->setAttribute($k, $v);
            }
        };
    }
}
