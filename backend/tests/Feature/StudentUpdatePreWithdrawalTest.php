<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 退所予定(pre_withdrawal)の生徒を編集モーダルから更新できる回帰テスト。
 *
 * 差分カテゴリ: logic
 * 背景: pre_withdrawal は DB の CHECK 制約・待機リスト機能で有効な status だが、
 *       Staff/StudentController の store/update バリデーション enum に含まれて
 *       いなかった。そのため退所予定の生徒を編集ボタン→保存すると、現状の
 *       status=pre_withdrawal がそのまま送られ `in:...` で 422 になっていた
 *       (報告: 通常利用日を変更しようとすると編集ボタンからエラーで変更できない)。
 */
class StudentUpdatePreWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_scheduled_days_for_pre_withdrawal_student(): void
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create(['classroom_name' => '教室A', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'スタッフ', 'user_type' => 'staff',
            'classroom_id' => $classroom->id, 'is_active' => true,
        ]);
        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => '退所予定の子',
            'status'       => 'pre_withdrawal',
            'is_active'    => true,
            'birth_date'   => '2018-04-01',
            'scheduled_monday' => false,
        ]);

        // 編集モーダルが送る代表的なペイロード (status は現状値のまま送られる)
        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/students/{$student->id}", [
                'student_name'    => $student->student_name,
                'status'          => 'pre_withdrawal',
                'birth_date'      => '2018-04-01',
                'scheduled_monday'    => true,  // 通常利用日を追加
                'scheduled_wednesday' => true,
            ]);

        $response->assertStatus(200);
        $student->refresh();
        $this->assertTrue((bool) $student->scheduled_monday, '通常利用日(月)が更新されていない');
        $this->assertTrue((bool) $student->scheduled_wednesday);
        $this->assertSame('pre_withdrawal', $student->status);
    }
}
