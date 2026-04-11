<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\HiyariHattoRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HH010: ヒヤリハット CRUD エンドポイントのテスト
 *
 * 差分カテゴリ: api
 */
class HH010_HiyariHattoCrudTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '本校',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $other = Classroom::create([
            'classroom_name' => '別校',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $staff = User::create([
            'username' => 'staff_hh010',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);
        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => 'テスト太郎',
            'status' => 'active',
            'is_active' => true,
        ]);

        return compact('classroom', 'other', 'staff', 'student');
    }

    public function test_store_creates_record_with_required_fields(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/hiyari-hatto', [
                'classroom_id' => $f['classroom']->id,
                'student_id' => $f['student']->id,
                'occurred_at' => '2026-04-12 14:30',
                'location' => 'プレイルーム',
                'situation' => '走っていて机の角にぶつかった',
                'severity' => 'low',
                'category' => 'collision',
                'immediate_response' => '患部を冷やし、様子を見ました',
                'prevention_measures' => '机の角にカバーを装着',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('hiyari_hatto_records', [
            'classroom_id' => $f['classroom']->id,
            'student_id' => $f['student']->id,
            'severity' => 'low',
            'category' => 'collision',
            'reporter_id' => $f['staff']->id,
        ]);
    }

    public function test_store_rejects_classroom_user_cannot_access(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/hiyari-hatto', [
                'classroom_id' => $f['other']->id,
                'occurred_at' => '2026-04-12 14:30',
                'situation' => 'テスト',
                'severity' => 'low',
            ]);

        $response->assertStatus(403);
    }

    public function test_store_validates_required_situation(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/hiyari-hatto', [
                'classroom_id' => $f['classroom']->id,
                'occurred_at' => '2026-04-12',
                'severity' => 'low',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('situation');
    }

    public function test_store_rejects_invalid_severity(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/hiyari-hatto', [
                'classroom_id' => $f['classroom']->id,
                'occurred_at' => '2026-04-12',
                'situation' => 'テスト',
                'severity' => 'extreme',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('severity');
    }

    public function test_index_lists_accessible_records_only(): void
    {
        $f = $this->fixture();

        HiyariHattoRecord::create([
            'classroom_id' => $f['classroom']->id,
            'reporter_id' => $f['staff']->id,
            'student_id' => $f['student']->id,
            'occurred_at' => '2026-04-12 10:00',
            'situation' => '自教室',
            'severity' => 'medium',
        ]);
        HiyariHattoRecord::create([
            'classroom_id' => $f['other']->id,
            'reporter_id' => $f['staff']->id,
            'occurred_at' => '2026-04-12 11:00',
            'situation' => '他教室',
            'severity' => 'low',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/hiyari-hatto');

        $response->assertStatus(200);
        $rows = $response->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertEquals('自教室', $rows[0]['situation']);
    }

    public function test_show_returns_record_with_relations(): void
    {
        $f = $this->fixture();
        $record = HiyariHattoRecord::create([
            'classroom_id' => $f['classroom']->id,
            'reporter_id' => $f['staff']->id,
            'student_id' => $f['student']->id,
            'occurred_at' => '2026-04-12 10:00',
            'situation' => '詳細確認用',
            'severity' => 'low',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->getJson("/api/staff/hiyari-hatto/{$record->id}");

        $response->assertStatus(200);
        $this->assertEquals('詳細確認用', $response->json('data.situation'));
        $this->assertEquals($f['student']->student_name, $response->json('data.student.student_name'));
    }

    public function test_show_rejects_foreign_classroom_record(): void
    {
        $f = $this->fixture();
        $record = HiyariHattoRecord::create([
            'classroom_id' => $f['other']->id,
            'reporter_id' => $f['staff']->id,
            'occurred_at' => '2026-04-12 10:00',
            'situation' => '他教室の記録',
            'severity' => 'low',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->getJson("/api/staff/hiyari-hatto/{$record->id}");

        $response->assertStatus(403);
    }

    public function test_update_changes_fields(): void
    {
        $f = $this->fixture();
        $record = HiyariHattoRecord::create([
            'classroom_id' => $f['classroom']->id,
            'reporter_id' => $f['staff']->id,
            'occurred_at' => '2026-04-12 10:00',
            'situation' => '元の状況',
            'severity' => 'low',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->putJson("/api/staff/hiyari-hatto/{$record->id}", [
                'severity' => 'high',
                'prevention_measures' => '対策を追加',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('high', $record->fresh()->severity);
        $this->assertEquals('対策を追加', $record->fresh()->prevention_measures);
    }

    public function test_destroy_deletes_record(): void
    {
        $f = $this->fixture();
        $record = HiyariHattoRecord::create([
            'classroom_id' => $f['classroom']->id,
            'reporter_id' => $f['staff']->id,
            'occurred_at' => '2026-04-12 10:00',
            'situation' => '削除対象',
            'severity' => 'low',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->deleteJson("/api/staff/hiyari-hatto/{$record->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('hiyari_hatto_records', ['id' => $record->id]);
    }

    public function test_index_filters_by_severity(): void
    {
        $f = $this->fixture();
        HiyariHattoRecord::create([
            'classroom_id' => $f['classroom']->id,
            'reporter_id' => $f['staff']->id,
            'occurred_at' => '2026-04-12 10:00',
            'situation' => '軽度',
            'severity' => 'low',
        ]);
        HiyariHattoRecord::create([
            'classroom_id' => $f['classroom']->id,
            'reporter_id' => $f['staff']->id,
            'occurred_at' => '2026-04-12 11:00',
            'situation' => '重度',
            'severity' => 'high',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/hiyari-hatto?severity=high');

        $response->assertStatus(200);
        $rows = $response->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertEquals('重度', $rows[0]['situation']);
    }
}
