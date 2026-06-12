<?php

namespace Tests\Feature;

use App\Models\AbilitySubjectiveScore;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AbilityEvalMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 能力評価 P5: mynameis 主観プロフィール受信(共有シークレット)と児童紐づけ。
 *
 * 差分カテゴリ: auth
 */
class MynameisSyncTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $room;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        $this->seed(AbilityEvalMasterSeeder::class);
        config(['services.mynameis.shared_secret' => 'test-secret']);

        $company = Company::create(['name' => '企業A']);
        $this->room = Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id,
            'is_active' => true, 'ability_assessment_enabled' => true,
        ]);
        $this->student = Student::create([
            'student_name' => '児A', 'classroom_id' => $this->room->id, 'grade_level' => 'elementary_5',
            'status' => 'active', 'is_active' => true,
            'mynameis_member_code' => 'ABC12345', 'mynameis_user_id' => 555,
        ]);
    }

    public function test_ingest_requires_valid_secret(): void
    {
        $res = $this->postJson('/api/external/mynameis/self-assessment', [
            'secret' => 'wrong',
            'mynameis_user_id' => 555,
            'items' => [['item_code' => 'DEV-1-1', 'value' => 4]],
        ]);
        $res->assertStatus(401);
    }

    public function test_ingest_unlinked_member_code_returns_404(): void
    {
        $res = $this->postJson('/api/external/mynameis/self-assessment', [
            'secret' => 'test-secret',
            'member_codes' => ['ZZZ99999'], // どの児童にも紐づいていない
            'items' => [['item_code' => 'DEV-1-1', 'value' => 4]],
        ]);
        $res->assertStatus(404);
    }

    public function test_ingest_matches_by_member_code_and_user_id_fallback(): void
    {
        // メンバーIDで突合(小文字でも大文字に正規化)
        $res = $this->postJson('/api/external/mynameis/self-assessment', [
            'secret' => 'test-secret',
            'member_codes' => ['abc12345'],
            'items' => [
                ['item_code' => 'DEV-1-1', 'value' => 3, 'axis_code' => 'S3', 'responded_at' => '2026-06-01T10:00:00Z'],
                ['item_code' => 'DEV-1-2', 'value' => 5],
                ['item_code' => 'BOGUS-9-9', 'value' => 2], // 未知コード → スキップ
            ],
        ]);
        $res->assertStatus(200);
        $res->assertJsonPath('data.saved', 2);

        $this->assertSame(3, AbilitySubjectiveScore::where('student_id', $this->student->id)
            ->where('item_id', 'DEV-1-1')->value('response_value'));
        $this->assertSame(2, AbilitySubjectiveScore::where('student_id', $this->student->id)->count());

        // 後方互換: member_code 無しでも mynameis_user_id で突合できる。再送は上書き。
        $this->postJson('/api/external/mynameis/self-assessment', [
            'secret' => 'test-secret',
            'mynameis_user_id' => 555,
            'items' => [['item_code' => 'DEV-1-1', 'value' => 5]],
        ])->assertStatus(200);
        $this->assertSame(5, AbilitySubjectiveScore::where('student_id', $this->student->id)
            ->where('item_id', 'DEV-1-1')->value('response_value'));
        $this->assertSame(2, AbilitySubjectiveScore::where('student_id', $this->student->id)->count());
    }

    public function test_staff_can_link_and_unlink_mynameis(): void
    {
        $staff = User::create([
            'username' => 'staff_lk_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);

        // メンバーIDで紐づけ(小文字入力は大文字に正規化)
        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/staff/ability/students/{$this->student->id}/link-mynameis", ['mynameis_member_code' => 'xyz12345'])
            ->assertStatus(200);
        $this->assertSame('XYZ12345', Student::find($this->student->id)->mynameis_member_code);

        // 解除
        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/staff/ability/students/{$this->student->id}/link-mynameis", ['mynameis_member_code' => null])
            ->assertStatus(200);
        $this->assertNull(Student::find($this->student->id)->mynameis_member_code);
    }

    private function makeStaff(): User
    {
        return User::create([
            'username' => 'staff_rv_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $this->room->id, 'is_active' => true,
        ]);
    }

    private function fakeResolve(string $orgName): void
    {
        config(['services.mynameis.resolve_url' => 'https://fesvol.test/resolve']);
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'data' => ['member_code' => 'ABC12345', 'organization_name' => $orgName, 'organization_prefix' => 'ABC'],
            ], 200),
        ]);
    }

    public function test_link_reports_classroom_match(): void
    {
        // mynameis の教室名が児童の教室名(事業所A)と一致 → matches=true
        $this->fakeResolve('事業所A');
        $this->actingAs($this->makeStaff(), 'sanctum')
            ->postJson("/api/staff/ability/students/{$this->student->id}/link-mynameis", ['mynameis_member_code' => 'ABC12345'])
            ->assertStatus(200)
            ->assertJsonPath('data.mynameis_classroom', '事業所A')
            ->assertJsonPath('data.classroom_matches', true);
    }

    public function test_link_warns_on_classroom_mismatch(): void
    {
        // mynameis の教室名が違う → matches=false(取り違え警告用)
        $this->fakeResolve('別の教室');
        $this->actingAs($this->makeStaff(), 'sanctum')
            ->postJson("/api/staff/ability/students/{$this->student->id}/link-mynameis", ['mynameis_member_code' => 'ABC12345'])
            ->assertStatus(200)
            ->assertJsonPath('data.mynameis_classroom', '別の教室')
            ->assertJsonPath('data.classroom_matches', false);
    }
}
