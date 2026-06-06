<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Support\PiiMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 観点5 プライバシー保護: 生成AIへ送る前に児童・保護者の氏名を仮名化し、
 * 出力で実名へ復元する PiiMasker の検証。
 *
 * 差分カテゴリ: logic (外部AIへのPII送信防止)
 */
class PiiMaskerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mask_and_unmask_round_trip(): void
    {
        $m = (new PiiMasker())->add('田中太郎', '【児童】');

        $masked = $m->mask('田中太郎さんは元気に活動しました。');
        $this->assertStringNotContainsString('田中太郎', $masked);
        $this->assertStringContainsString('【児童】', $masked);

        $restored = $m->unmask($masked);
        $this->assertSame('田中太郎さんは元気に活動しました。', $restored);
    }

    public function test_longest_value_replaced_first(): void
    {
        // 「田中」と「田中太郎」が両方登録されても、長い方が優先され巻き込まれない
        $m = (new PiiMasker())
            ->add('田中太郎', '【児童】')
            ->add('田中花子', '【保護者】');

        $masked = $m->mask('田中太郎と田中花子');
        $this->assertSame('【児童】と【保護者】', $masked);
    }

    public function test_short_and_empty_values_ignored(): void
    {
        $m = (new PiiMasker())
            ->add('', '【空】')
            ->add('A', '【一文字】') // MIN_LENGTH=2 未満は無視
            ->add(null, '【null】');

        $this->assertTrue($m->isEmpty());
        $this->assertSame('A は残る', $m->mask('A は残る'));
    }

    public function test_unmask_array_recursively(): void
    {
        $m = (new PiiMasker())->add('山田一郎', '【児童】');

        $out = $m->unmaskArray([
            'long_term_goal' => '【児童】は自立を目指す',
            'details' => [
                ['comment' => '【児童】はよく頑張った'],
            ],
        ]);

        $this->assertSame('山田一郎は自立を目指す', $out['long_term_goal']);
        $this->assertSame('山田一郎はよく頑張った', $out['details'][0]['comment']);
    }

    public function test_for_student_masks_student_and_guardian_names(): void
    {
        $classroom = Classroom::create(['classroom_name' => 'PII教室', 'is_active' => true]);
        $guardian = User::create([
            'username' => 'g_pii_' . uniqid(), 'password' => Hash::make('p'),
            'full_name' => '佐藤花子', 'user_type' => 'guardian',
            'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => '佐藤健太',
            'guardian_id'  => $guardian->id,
            'grade_level'  => 'elementary_1',
            'status'       => 'active',
            'is_active'    => true,
        ]);

        $masker = PiiMasker::forStudent($student);
        $prompt = "【児童名】佐藤健太\n保護者: 佐藤花子";
        $masked = $masker->mask($prompt);

        $this->assertStringNotContainsString('佐藤健太', $masked);
        $this->assertStringNotContainsString('佐藤花子', $masked);
        // 復元できる
        $this->assertStringContainsString('佐藤健太', $masker->unmask($masked));
    }
}
