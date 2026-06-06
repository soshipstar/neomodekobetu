<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomPhoto;
use App\Models\Company;
use App\Models\DailyRecord;
use App\Models\IntegratedNote;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L-010: 連絡帳の写真サーバ側自動添付フォールバック。
 *
 * 背景: 写真添付がフロント任せ・ベストエフォートで、古いタブ/モーダル開閉タイミング
 *       により photo_ids が空のまま送信され、日付・教室・生徒タグが一致する写真が
 *       あっても保護者に届かない不具合が発生した (てらこやプラスで再現)。
 * 対応: send-to-guardians で photo_ids が空かつ「意図的に全削除 (photos_cleared)」
 *       でない場合、サーバ側で suggestPhotos 同条件の写真を自動添付する。
 *       職員が意図的に外した場合は photos_cleared=true で抑止する。
 *
 * 差分カテゴリ: logic
 */
class L010_RenrakuchoServerSidePhotoAttachTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // テストDBには telescope_entries が無い環境があるため、リクエスト中の
        // Telescope 記録でテストトランザクションが汚染されるのを防ぐ。
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    /** @return array{0:User,1:Student,2:DailyRecord,3:ClassroomPhoto} */
    private function setupContext(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => 'てらこやプラス',
            'company_id'     => $company->id,
            'is_active'      => true,
        ]);
        $staff = User::create([
            'username'     => 'staff_photo_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
        $guardian = User::create([
            'username'     => 'g_photo_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => '保護者A',
            'user_type'    => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
        $student = Student::create([
            'student_name' => '生徒A',
            'classroom_id' => $classroom->id,
            'guardian_id'  => $guardian->id,
        ]);

        $record = DailyRecord::create([
            'record_date'     => '2026-06-05',
            'staff_id'        => $staff->id,
            'classroom_id'    => $classroom->id,
            'activity_name'   => 'ストレッチ',
            'common_activity' => '本日の活動。',
        ]);
        StudentRecord::create([
            'daily_record_id' => $record->id,
            'student_id'      => $student->id,
            'health_life'     => '元気でした。',
        ]);

        // 日付・教室一致 + 生徒タグ付きの写真 (suggestPhotos がマッチする写真)
        $photo = ClassroomPhoto::create([
            'classroom_id'  => $classroom->id,
            'uploader_id'   => $staff->id,
            'file_path'     => 'photos/test1.jpg',
            'file_size'     => 1000,
            'mime'          => 'image/jpeg',
            'activity_date' => '2026-06-05',
        ]);
        $photo->students()->attach($student->id);

        return [$staff, $student, $record, $photo];
    }

    public function test_auto_attaches_matching_photo_when_photo_ids_empty(): void
    {
        [$staff, $student, $record, $photo] = $this->setupContext();

        // FE が photo_ids を送れなかった状況 (空・cleared フラグ無し) を再現
        $res = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/staff/renrakucho/{$record->id}/send-to-guardians", [
                'notes' => [
                    ['student_id' => $student->id, 'content' => '本日の様子です。', 'photo_ids' => []],
                ],
            ]);

        $res->assertStatus(200);

        $note = IntegratedNote::where('daily_record_id', $record->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        // サーバ側フォールバックでマッチ写真が自動添付される
        $this->assertEquals(1, $note->photos()->count());
        $this->assertEquals($photo->id, $note->photos()->first()->id);
    }

    public function test_respects_intentional_clear(): void
    {
        [$staff, $student, $record] = $this->setupContext();

        // 職員が意図的に全て外した → photos_cleared=true で自動添付を抑止
        $res = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/staff/renrakucho/{$record->id}/send-to-guardians", [
                'notes' => [
                    ['student_id' => $student->id, 'content' => '本日の様子です。', 'photo_ids' => [], 'photos_cleared' => true],
                ],
            ]);

        $res->assertStatus(200);

        $note = IntegratedNote::where('daily_record_id', $record->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->assertEquals(0, $note->photos()->count());
    }

    public function test_uses_explicit_photo_ids_when_provided(): void
    {
        [$staff, $student, $record, $photo] = $this->setupContext();

        $res = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/staff/renrakucho/{$record->id}/send-to-guardians", [
                'notes' => [
                    ['student_id' => $student->id, 'content' => '本日の様子です。', 'photo_ids' => [$photo->id]],
                ],
            ]);

        $res->assertStatus(200);

        $note = IntegratedNote::where('daily_record_id', $record->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->assertEquals(1, $note->photos()->count());
        $this->assertEquals($photo->id, $note->photos()->first()->id);
    }
}
