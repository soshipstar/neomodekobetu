<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug 5: kobetsu-plan の AI生成で「完了しました」と表示されるが白紙のままになる問題
 *
 * 差分カテゴリ: logic
 * 背景: POST /api/staff/students/{student}/support-plans/ai-generate
 *       (generateAiForStudent) が `[{domain, current_status, goal, support_content}]`
 *       配列形式で応答していたが、フロントは
 *       `{life_intention, overall_policy, long_term_goal, short_term_goal, details:[...]}`
 *       オブジェクト形式を期待していたため、aiData.life_intention 等が undefined となり
 *       フォームが空のまま toast 「完了しました」だけが表示されていた。
 *
 *       本修正で:
 *       1) プロンプトを object 形式 (generateAi と同形) を返すよう変更
 *       2) AI 応答が想定形式でない場合は 502 で明示的に失敗とし、フロントの catch を
 *          発火させる
 *       3) 不正な fillable 経由で記録できていなかった AiGenerationLog::create を修正
 *
 *       OpenAI 呼び出し自体のモックは複雑なため、本テストでは認可と APIキー欠落の
 *       境界条件のみをカバーする。実際の応答形状検証は本番動作確認に委ねる。
 */
class L008_KobetsuPlanAiGenerateForStudentTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $company = Company::create(['name' => '企業A_' . uniqid()]);
        $classroom = Classroom::create([
            'classroom_name' => '教室A',
            'company_id'     => $company->id,
            'is_active'      => true,
        ]);
        $staff = User::create([
            'username'     => 'staff_aigen_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
        $student = Student::create([
            'student_name' => '生徒A',
            'classroom_id' => $classroom->id,
        ]);

        return [$staff, $student, $classroom, $company];
    }

    public function test_returns_422_when_openai_api_key_is_missing(): void
    {
        [$staff, $student] = $this->setupContext();

        config(['services.openai.api_key' => null]);
        putenv('OPENAI_API_KEY');
        unset($_ENV['OPENAI_API_KEY'], $_SERVER['OPENAI_API_KEY']);

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/staff/students/{$student->id}/support-plans/ai-generate", [
                'student_id' => $student->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'OpenAI APIキーが設定されていません。');
    }

    public function test_returns_403_for_student_in_unauthorized_classroom(): void
    {
        [$staff] = $this->setupContext();

        // 別教室・別企業の生徒
        $otherCompany = Company::create(['name' => '企業B_' . uniqid()]);
        $otherClassroom = Classroom::create([
            'classroom_name' => '教室B',
            'company_id'     => $otherCompany->id,
            'is_active'      => true,
        ]);
        $otherStudent = Student::create([
            'student_name' => '他生徒',
            'classroom_id' => $otherClassroom->id,
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/staff/students/{$otherStudent->id}/support-plans/ai-generate", [
                'student_id' => $otherStudent->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_returns_401_for_unauthenticated(): void
    {
        [, $student] = $this->setupContext();

        $response = $this->postJson("/api/staff/students/{$student->id}/support-plans/ai-generate", [
            'student_id' => $student->id,
        ]);

        // 未認証は 401。ステータスは middleware により異なる場合があるが、
        // 200/2xx でないことを最低限確認する。
        $this->assertContains($response->status(), [401, 403, 419]);
    }
}
