<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI002: 連絡帳 AI フォールバックの仮名復元テスト (AI-08)
 *
 * 差分カテゴリ: logic
 *
 * 放デイ業務リスク監査で検出:
 *  AI-08 RenrakuchoController::generateIntegrated() の AI 失敗時 fallback が
 *        mask 済みの $activityName/$notes をそのまま使い、保護者向けの
 *        integrated_notes に placeholder (「対象児童 A」) が混入していた。
 *        OpenAI 接続エラー時に「対象児童 A さんは本日…」が保護者画面に
 *        表示される事態。
 *
 * テスト環境では OPENAI_API_KEY 未設定により OpenAiClientFactory::make() が
 * 例外を投げ、必ず fallback 経路に入る。
 */
class AI002_RenrakuchoFallbackUnmaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai08_fallback_does_not_leak_placeholder_to_guardian(): void
    {
        // OpenAI を未設定にして fallback 経路を強制
        config(['services.openai.api_key' => '']);

        $classroom = Classroom::create(['classroom_name' => 'AI002教室', 'is_active' => true]);
        $staff = User::create([
            'username'     => 'staff_ai002',
            'password'     => bcrypt('pass'),
            'full_name'    => 'スタッフAI002',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
        // 「太郎」など mask 対象になる実名を持つ児童
        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => '山田太郎',
            'is_active'    => true,
        ]);

        $record = DailyRecord::create([
            'classroom_id'  => $classroom->id,
            'staff_id'      => $staff->id,
            'record_date'   => '2026-06-07',
            'activity_name' => '山田太郎の好きな工作活動',  // 児童名を含む活動名
        ]);

        StudentRecord::create([
            'daily_record_id'        => $record->id,
            'student_id'             => $student->id,
            'health_life'            => '山田太郎は落ち着いて食事できた',
            'notes'                  => '山田太郎は本日とても集中していた',
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/renrakucho/' . $record->id . '/generate-integrated', [
                'student_id' => $student->id,
            ]);

        $response->assertStatus(200);

        // 保存された連絡帳に placeholder が残っていないこと
        $note = IntegratedNote::where('daily_record_id', $record->id)
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($note);
        $this->assertStringNotContainsString('対象児童', $note->integrated_content, 'fallback に placeholder が漏れています (AI-08)。');
        // 応答にも placeholder が無いこと
        $this->assertStringNotContainsString('対象児童', $response->json('data.content') ?? '');
    }
}
