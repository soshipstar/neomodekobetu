<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ST013: 児童の同一人物グルーピング（person_id）と同期テスト
 *
 * 差分カテゴリ: api / logic
 * 背景: 1 児童 = 1 Student = 1 教室のモデルで、同じ物理的な子どもを複数教室に
 *       複製した際に、それらが同一人物であることを識別する person_id を導入した。
 *       また「氏名や生年月日の一括更新」を行う syncLinked エンドポイントを追加した。
 */
class ST013_StudentPersonGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function master(): User
    {
        return User::create([
            'username' => 'master_st013',
            'password' => bcrypt('pass'),
            'full_name' => 'Master',
            'user_type' => 'admin',
            'is_master' => true,
            'is_company_admin' => false,
            'is_active' => true,
        ]);
    }

    private function setupWithCopy(): array
    {
        $company = Company::create(['name' => '企業A']);
        $c1 = Classroom::create(['classroom_name' => 'A1', 'company_id' => $company->id, 'is_active' => true]);
        $c2 = Classroom::create(['classroom_name' => 'A2', 'company_id' => $company->id, 'is_active' => true]);
        $c3 = Classroom::create(['classroom_name' => 'A3', 'company_id' => $company->id, 'is_active' => true]);

        $guardian = User::create([
            'username' => 'guardian_st013',
            'password' => bcrypt('pass'),
            'full_name' => '保護者',
            'user_type' => 'guardian',
            'is_active' => true,
        ]);

        $source = Student::create([
            'classroom_id' => $c1->id,
            'student_name' => '同期太郎',
            'username' => 'taro_a1',
            'password_hash' => Hash::make('pass'),
            'birth_date' => '2018-04-01',
            'grade_level' => 'elementary_1',
            'guardian_id' => $guardian->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        return compact('company', 'c1', 'c2', 'c3', 'guardian', 'source');
    }

    public function test_copying_assigns_shared_person_id(): void
    {
        $f = $this->setupWithCopy();
        $master = $this->master();

        // 複製前: person_id は null
        $this->assertNull($f['source']->person_id);

        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c2']->id,
                'username' => 'taro_a2',
            ]);

        $response->assertStatus(201);
        $copyId = $response->json('data.id');
        $copy = Student::find($copyId);

        $source = $f['source']->fresh();
        $this->assertNotNull($source->person_id);
        $this->assertEquals($source->person_id, $copy->person_id);
    }

    public function test_copying_twice_shares_same_person_id(): void
    {
        $f = $this->setupWithCopy();
        $master = $this->master();

        // 1 回目の複製
        $r1 = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c2']->id,
                'username' => 'taro_a2',
            ]);
        $r1->assertStatus(201);

        // 2 回目: 元レコードをさらに別教室に複製
        $r2 = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c3']->id,
                'username' => 'taro_a3',
            ]);
        $r2->assertStatus(201);

        // 3 レコード全てが同じ person_id を持つ
        $source = $f['source']->fresh();
        $copy1 = Student::find($r1->json('data.id'));
        $copy2 = Student::find($r2->json('data.id'));
        $this->assertNotNull($source->person_id);
        $this->assertEquals($source->person_id, $copy1->person_id);
        $this->assertEquals($source->person_id, $copy2->person_id);
    }

    public function test_linked_endpoint_returns_linked_records(): void
    {
        $f = $this->setupWithCopy();
        $master = $this->master();

        // 複製して person_id を設定
        $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c2']->id,
                'username' => 'taro_a2',
            ])->assertStatus(201);

        $response = $this->actingAs($master, 'sanctum')
            ->getJson("/api/admin/students/{$f['source']->id}/linked");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.person_id'));
        $linked = $response->json('data.linked');
        $this->assertCount(1, $linked);
        $this->assertEquals($f['c2']->id, $linked[0]['classroom_id']);
    }

    public function test_linked_endpoint_returns_empty_for_unlinked_student(): void
    {
        $f = $this->setupWithCopy();
        $master = $this->master();

        $response = $this->actingAs($master, 'sanctum')
            ->getJson("/api/admin/students/{$f['source']->id}/linked");

        $response->assertStatus(200);
        $this->assertNull($response->json('data.person_id'));
        $this->assertEquals([], $response->json('data.linked'));
    }

    public function test_sync_linked_propagates_identity_fields(): void
    {
        $f = $this->setupWithCopy();
        $master = $this->master();

        // 複製
        $r = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c2']->id,
                'username' => 'taro_a2',
            ]);
        $copyId = $r->json('data.id');

        // source 側を編集
        $f['source']->fresh()->update([
            'student_name' => '同期太郎 (更新後)',
            'birth_date' => '2017-12-01',
            'grade_level' => 'elementary_2',
            'notes' => '更新されたメモ',
        ]);

        // 同期実行
        $syncResponse = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/sync-linked");

        $syncResponse->assertStatus(200);
        $this->assertEquals(1, $syncResponse->json('data.updated_count'));

        // copy の氏名・生年月日・学年・メモが source に揃っている
        $copy = Student::find($copyId);
        $this->assertEquals('同期太郎 (更新後)', $copy->student_name);
        $this->assertEquals('2017-12-01', $copy->birth_date->format('Y-m-d'));
        $this->assertEquals('elementary_2', $copy->grade_level);
        $this->assertEquals('更新されたメモ', $copy->notes);
    }

    public function test_sync_linked_does_not_touch_classroom_specific_fields(): void
    {
        $f = $this->setupWithCopy();
        $master = $this->master();

        // source に scheduled_monday=true, status=active
        $f['source']->update(['scheduled_monday' => true, 'status' => 'active']);

        // 複製（copy は scheduled_monday=true を継承）
        $r = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/copy-to-classroom", [
                'classroom_id' => $f['c2']->id,
                'username' => 'taro_a2',
            ]);
        $copyId = $r->json('data.id');

        // copy 側で scheduled_monday=false, status=waiting に変える
        $copy = Student::find($copyId);
        $copy->update(['scheduled_monday' => false, 'status' => 'waiting']);

        // source を同期元として sync 実行
        $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/sync-linked")
            ->assertStatus(200);

        // copy の教室固有フィールドは変わっていない
        $copy = Student::find($copyId);
        $this->assertFalse((bool) $copy->scheduled_monday, 'scheduled_* は同期対象外');
        $this->assertEquals('waiting', $copy->status, 'status は同期対象外');
    }

    public function test_sync_linked_rejects_unlinked_student(): void
    {
        $f = $this->setupWithCopy();
        $master = $this->master();

        // person_id が null のまま同期実行
        $response = $this->actingAs($master, 'sanctum')
            ->postJson("/api/admin/students/{$f['source']->id}/sync-linked");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('person_id');
    }
}
