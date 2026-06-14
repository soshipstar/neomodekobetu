<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 支援知蒸留 D2: AI記録支援(問い返し)の入力検証・権限・APIキーガード。
 * 実際のOpenAI呼び出しは対象外(キー未設定で422)。
 *
 * 差分カテゴリ: api
 */
class AiAssistApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Classroom $room;
    private Student $student;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        config(['services.openai.api_key' => null]);
        putenv('OPENAI_API_KEY');

        $this->company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create(['classroom_name' => '事業所A', 'company_id' => $this->company->id, 'is_active' => true]);
        $this->student = Student::create(['student_name' => '児A', 'classroom_id' => $this->room->id, 'status' => 'active', 'is_active' => true]);
        $this->staff = User::create([
            'username' => 'staff_aa_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    public function test_text_required(): void
    {
        $this->actingAs($this->staff, 'sanctum')->postJson('/api/staff/ai-assist/inquiry', [])->assertStatus(422);
    }

    public function test_cross_classroom_student_forbidden(): void
    {
        $otherRoom = Classroom::create(['classroom_name' => '別', 'company_id' => $this->company->id, 'is_active' => true]);
        $otherStaff = User::create([
            'username' => 'o_'.uniqid(), 'password' => bcrypt('p'), 'full_name' => '別',
            'user_type' => 'staff', 'classroom_id' => $otherRoom->id, 'is_active' => true,
        ]);
        $this->actingAs($otherStaff, 'sanctum')->postJson('/api/staff/ai-assist/inquiry', [
            'text' => '今日は落ち着いていた', 'student_id' => $this->student->id,
        ])->assertStatus(403);
    }

    public function test_valid_request_reaches_key_guard(): void
    {
        // 検証・権限を通過し、APIキー未設定で422(=OpenAI手前まで到達)
        $this->actingAs($this->staff, 'sanctum')->postJson('/api/staff/ai-assist/inquiry', [
            'text' => '今日は落ち着いて活動に参加できた', 'student_id' => $this->student->id,
        ])->assertStatus(422)->assertJsonPath('message', 'OpenAI APIキーが設定されていません。');
    }
}
