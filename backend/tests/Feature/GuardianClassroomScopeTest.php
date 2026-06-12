<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 保護者の事業所切替時に、選択中の事業所のデータのみが見えることを検証する。
 *
 * バグ: 同じ子どもでも事業所(わくわく/プラス)ごとに別 Student レコードを持つため、
 * 保護者画面のモニタリング・支援計画・連絡帳・欠席連絡・生徒セレクタが
 * guardian_id だけで引くと両事業所のデータが混在していた。
 * 修正: User::studentsInSelectedClassroom() が classroom_id で絞る。
 */
class GuardianClassroomScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 2 事業所に同じ子どもが在籍する保護者を作成。
     * @return array{guardianId:int, classroomA:int, classroomB:int, studentA:int, studentB:int}
     */
    private function makeGuardianInTwoClassrooms(): array
    {
        return DB::transaction(function () {
            $classroomA = DB::table('classrooms')->insertGetId([
                'classroom_name' => 'わくわく',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $classroomB = DB::table('classrooms')->insertGetId([
                'classroom_name' => 'プラス',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // 保護者の現在の選択事業所は A
            $guardianId = DB::table('users')->insertGetId([
                'full_name' => 'テスト保護者',
                'username' => 'g_' . uniqid(),
                'email' => 'guardian_' . uniqid() . '@test.com',
                'password' => bcrypt('password'),
                'user_type' => 'guardian',
                'classroom_id' => $classroomA,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // 同じ子どもの A 在籍レコードと B 在籍レコード
            $studentA = DB::table('students')->insertGetId([
                'student_name' => '白砂　澪',
                'classroom_id' => $classroomA,
                'guardian_id' => $guardianId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $studentB = DB::table('students')->insertGetId([
                'student_name' => '白砂　澪',
                'classroom_id' => $classroomB,
                'guardian_id' => $guardianId,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return compact('guardianId', 'classroomA', 'classroomB', 'studentA', 'studentB');
        });
    }

    public function test_helper_filters_students_by_selected_classroom(): void
    {
        $ctx = $this->makeGuardianInTwoClassrooms();
        $guardian = User::find($ctx['guardianId']);

        // 選択中 = A → A の生徒だけ
        $ids = $guardian->studentsInSelectedClassroom()->pluck('id')->all();
        $this->assertEquals([$ctx['studentA']], $ids);

        // B に切替 → B の生徒だけ
        $guardian->classroom_id = $ctx['classroomB'];
        $guardian->save();
        $guardian->refresh();
        $ids = $guardian->studentsInSelectedClassroom()->pluck('id')->all();
        $this->assertEquals([$ctx['studentB']], $ids);
    }

    public function test_students_endpoint_returns_only_selected_classroom(): void
    {
        $ctx = $this->makeGuardianInTwoClassrooms();
        $guardian = User::find($ctx['guardianId']);
        $this->actingAs($guardian);

        $response = $this->getJson('/api/guardian/students');
        $response->assertStatus(200);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ctx['studentA'], $returnedIds);
        $this->assertNotContains(
            $ctx['studentB'],
            $returnedIds,
            '他事業所(プラス)の生徒が選択中(わくわく)のセレクタに混入している'
        );
    }

    public function test_classroom_null_falls_back_to_all_students(): void
    {
        $ctx = $this->makeGuardianInTwoClassrooms();
        $guardian = User::find($ctx['guardianId']);
        $guardian->classroom_id = null;
        $guardian->save();
        $guardian->refresh();

        $ids = $guardian->studentsInSelectedClassroom()->pluck('id')->sort()->values()->all();
        $this->assertEquals(
            collect([$ctx['studentA'], $ctx['studentB']])->sort()->values()->all(),
            $ids,
            'classroom_id 未選択時は後方互換で全生徒を返すべき'
        );
    }
}
