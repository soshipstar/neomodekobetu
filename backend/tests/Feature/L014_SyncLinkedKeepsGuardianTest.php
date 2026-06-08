<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L-014: 「同一人物」同期(syncLinked)が guardian_id を上書きしないこと。
 *
 * 背景(P0 プライバシー/認証): syncLinked が guardian_id を同期対象に含めていたため、
 * person_id でリンクされた別教室レコードの保護者が同期元で上書きされ、別家庭の保護者が
 * 別生徒の連絡帳・アセスメント等を閲覧できる越境アクセスの原因になっていた。
 * guardian_id は各教室で独立管理とし、同期では変更しない。
 *
 * 差分カテゴリ: logic
 */
class L014_SyncLinkedKeepsGuardianTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    public function test_sync_linked_does_not_overwrite_guardian_id(): void
    {
        $company = Company::create(['name' => '企業A']);
        $roomA = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $roomB = Classroom::create(['classroom_name' => '教室B', 'company_id' => $company->id, 'is_active' => true]);

        $master = User::create([
            'username' => 'master_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'マスター',
            'user_type' => 'admin', 'is_master' => true, 'is_active' => true,
        ]);
        $guardianA = User::create([
            'username' => 'gA_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者A',
            'user_type' => 'guardian', 'classroom_id' => $roomA->id, 'is_active' => true,
        ]);
        $guardianB = User::create([
            'username' => 'gB_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者B',
            'user_type' => 'guardian', 'classroom_id' => $roomB->id, 'is_active' => true,
        ]);

        $personId = '11111111-1111-1111-1111-111111111111';
        // 同一 person_id だが別教室・別保護者 (本来あってはならないが、誤リンク時の越境を防ぐ)
        $studentX = Student::create([
            'student_name' => '正しい名前', 'classroom_id' => $roomA->id, 'guardian_id' => $guardianA->id,
            'person_id' => $personId, 'grade_level' => 'elementary_2', 'status' => 'active', 'is_active' => true,
        ]);
        $studentY = Student::create([
            'student_name' => '古い名前', 'classroom_id' => $roomB->id, 'guardian_id' => $guardianB->id,
            'person_id' => $personId, 'grade_level' => 'elementary_1', 'status' => 'active', 'is_active' => true,
        ]);

        $res = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$studentX->id}/sync-linked");
        $res->assertStatus(200);

        $studentY->refresh();
        // 保護者は上書きされない (越境防止)
        $this->assertSame($guardianB->id, $studentY->guardian_id);
        // 身元情報は同期される (機能自体は動作)
        $this->assertSame('正しい名前', $studentY->student_name);
        $this->assertSame('elementary_2', $studentY->grade_level);
    }
}
