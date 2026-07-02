<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 利用者一括登録に「生徒氏名（ふりがな）」「保護者氏名（ふりがな）」を追加。
 *
 * 差分カテゴリ: screen (api/logic を含む)
 * - parse: CSVヘッダー名で列を対応付け、ふりがな列を抽出する（列順・任意列に対応）。
 *          ふりがな列の無い従来ヘッダーCSVも引き続き解析できる（後方互換）。
 * - execute: 生徒に student_name_kana、保護者(User)に full_name_kana を保存する。
 */
class BulkRegisterFuriganaTest extends TestCase
{
    use RefreshDatabase;

    private function setupStaff(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '教室A',
            'company_id'     => $company->id,
            'is_active'      => true,
        ]);
        $staff = User::create([
            'username'     => 'staff_bulk_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        return [$staff, $classroom];
    }

    public function test_parse_and_execute_saves_furigana(): void
    {
        [$staff, $classroom] = $this->setupStaff();

        $csv = "教室名,保護者氏名,保護者氏名(ふりがな),生徒氏名,生徒氏名(ふりがな),生年月日,保護者メールアドレス,支援開始日,学年調整,月,火,水,木,金,土\n"
            . "{$classroom->classroom_name},山田花子,やまだはなこ,山田太郎,やまだたろう,2015-04-01,,,0,1,0,0,0,0,0";

        $parse = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/bulk-register/parse', ['text' => $csv]);
        $parse->assertStatus(200);

        $rows = $parse->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('やまだはなこ', $rows[0]['guardian_name_kana']);
        $this->assertSame('やまだたろう', $rows[0]['student_name_kana']);
        $this->assertSame('valid', $rows[0]['status']);

        $exec = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/bulk-register/execute', ['rows' => $rows]);
        $exec->assertStatus(200);

        $this->assertDatabaseHas('students', [
            'student_name'      => '山田太郎',
            'student_name_kana' => 'やまだたろう',
        ]);
        $this->assertDatabaseHas('users', [
            'full_name'      => '山田花子',
            'full_name_kana' => 'やまだはなこ',
            'user_type'      => 'guardian',
        ]);
    }

    public function test_parse_without_furigana_columns_still_works(): void
    {
        [$staff, $classroom] = $this->setupStaff();

        // ふりがな列の無い従来ヘッダー（後方互換）
        $csv = "教室名,保護者氏名,生徒氏名,生年月日,保護者メールアドレス,支援開始日,学年調整,月,火,水,木,金,土\n"
            . "{$classroom->classroom_name},鈴木美子,鈴木一郎,2016-08-15,,,0,1,0,0,0,0,0";

        $parse = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/staff/bulk-register/parse', ['text' => $csv]);
        $parse->assertStatus(200);

        $rows = $parse->json('data');
        $this->assertSame('鈴木美子', $rows[0]['guardian_name']);
        $this->assertSame('鈴木一郎', $rows[0]['student_name']);
        $this->assertSame('', $rows[0]['guardian_name_kana']);
        $this->assertSame('', $rows[0]['student_name_kana']);
        $this->assertSame('valid', $rows[0]['status']);
    }
}
