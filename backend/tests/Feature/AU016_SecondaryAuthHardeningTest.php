<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AU016: 副次的な認可強化テスト (AUTH-07〜12)
 *
 * 差分カテゴリ: auth
 *
 * 放デイ業務リスク監査の P2 認可改善:
 *  AUTH-09 TabletController checkIn/checkOut が classroom_id=null でガード
 *          完全スキップ。
 *  AUTH-10 AnalyticsController studentGrowth が classroom_id 完全一致のみ /
 *          null バイパス。
 *  (AUTH-06/07/08/11/12 はコードレベルで修正、本テストは代表的な経路を検証)
 */
class AU016_SecondaryAuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $companyA = Company::create(['name' => '企業A']);
        $companyB = Company::create(['name' => '企業B']);
        $classA = Classroom::create(['classroom_name' => 'A1', 'company_id' => $companyA->id, 'is_active' => true]);
        $classB = Classroom::create(['classroom_name' => 'B1', 'company_id' => $companyB->id, 'is_active' => true]);

        $studentB = Student::create([
            'classroom_id' => $classB->id,
            'student_name' => '生徒B',
            'is_active'    => true,
        ]);

        return compact('companyA', 'companyB', 'classA', 'classB', 'studentB');
    }

    // =========================================================================
    // AUTH-10: AnalyticsController studentGrowth の cross-classroom 遮断
    // =========================================================================

    public function test_auth10_analytics_blocks_other_classroom(): void
    {
        $f = $this->fixture();

        $staffA = User::create([
            'username'     => 'staff_a_au016',
            'password'     => bcrypt('pass'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $f['classA']->id,
            'is_active'    => true,
        ]);

        // 企業 A スタッフが企業 B の児童の成長データ → 403
        $this->actingAs($staffA, 'sanctum')
            ->getJson('/api/analytics/student/' . $f['studentB']->id . '/growth')
            ->assertStatus(403);
    }

    public function test_auth10_analytics_blocks_null_classroom_user(): void
    {
        $f = $this->fixture();

        // classroom_id=null のスタッフ (旧実装では全児童参照可だった)
        $nullStaff = User::create([
            'username'     => 'staff_null_au016',
            'password'     => bcrypt('pass'),
            'full_name'    => 'スタッフNull',
            'user_type'    => 'staff',
            'classroom_id' => null,
            'is_active'    => true,
        ]);

        $this->actingAs($nullStaff, 'sanctum')
            ->getJson('/api/analytics/student/' . $f['studentB']->id . '/growth')
            ->assertStatus(403);
    }

    // =========================================================================
    // AUTH-09: TabletController checkIn が null classroom でガードされること
    // =========================================================================

    public function test_auth09_tablet_checkin_blocks_null_classroom(): void
    {
        $f = $this->fixture();

        // classroom_id=null のタブレットアカウント
        $tabletNull = User::create([
            'username'     => 'tablet_null_au016',
            'password'     => bcrypt('pass'),
            'full_name'    => 'タブレットNull',
            'user_type'    => 'tablet',
            'classroom_id' => null,
            'is_active'    => true,
        ]);

        // 旧実装では classroom_id=null でガードがスキップされ checkIn できた
        $this->actingAs($tabletNull, 'sanctum')
            ->postJson('/api/tablet/students/' . $f['studentB']->id . '/check-in')
            ->assertStatus(403);
    }
}
