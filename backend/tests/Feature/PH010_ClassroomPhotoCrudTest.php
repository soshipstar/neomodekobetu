<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomPhoto;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PH010: 事業所写真ライブラリのテスト
 *
 * 差分カテゴリ: api
 */
class PH010_ClassroomPhotoCrudTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('public');
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
            'username' => 'staff_ph010',
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

    /**
     * GD で合成した画像をアップロードファイルとして渡す
     */
    private function makeJpeg(int $width = 800, int $height = 600): UploadedFile
    {
        $im = imagecreatetruecolor($width, $height);
        // ランダムな色のノイズ
        for ($y = 0; $y < $height; $y += 20) {
            for ($x = 0; $x < $width; $x += 20) {
                $c = imagecolorallocate($im, rand(0, 255), rand(0, 255), rand(0, 255));
                imagefilledrectangle($im, $x, $y, $x + 19, $y + 19, $c);
            }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'upl_') . '.jpg';
        imagejpeg($im, $tmp, 90);
        imagedestroy($im);
        return new UploadedFile($tmp, 'test.jpg', 'image/jpeg', null, true);
    }

    public function test_store_uploads_and_compresses_photo(): void
    {
        $f = $this->fixture();
        $file = $this->makeJpeg(1600, 1200);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/classroom-photos', [
                'photo' => $file,
                'classroom_id' => $f['classroom']->id,
                'activity_description' => '公園で遊びました',
                'activity_date' => '2026-04-12',
                'student_ids' => [$f['student']->id],
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals($f['classroom']->id, $data['classroom_id']);
        $this->assertEquals('公園で遊びました', $data['activity_description']);
        $this->assertLessThanOrEqual(ClassroomPhoto::TARGET_FILE_SIZE, $data['file_size']);
        $this->assertDatabaseHas('classroom_photos', [
            'classroom_id' => $f['classroom']->id,
            'uploader_id' => $f['staff']->id,
            'activity_description' => '公園で遊びました',
        ]);
        $this->assertDatabaseHas('classroom_photo_student', [
            'student_id' => $f['student']->id,
        ]);
        Storage::disk('public')->assertExists($data['file_path']);
    }

    public function test_store_rejects_foreign_classroom(): void
    {
        $f = $this->fixture();
        $file = $this->makeJpeg();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/classroom-photos', [
                'photo' => $file,
                'classroom_id' => $f['other']->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_store_rejects_non_image_file(): void
    {
        $f = $this->fixture();
        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/classroom-photos', [
                'photo' => UploadedFile::fake()->create('doc.pdf', 10),
                'classroom_id' => $f['classroom']->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('photo');
    }

    public function test_store_rejects_when_over_storage_limit(): void
    {
        $f = $this->fixture();

        // すでに 100MB 上限に達している状態を DB だけで作成
        ClassroomPhoto::create([
            'classroom_id' => $f['classroom']->id,
            'uploader_id' => $f['staff']->id,
            'file_path' => 'classroom_photos/dummy.jpg',
            'file_size' => ClassroomPhoto::STORAGE_LIMIT_BYTES,
            'mime' => 'image/jpeg',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/classroom-photos', [
                'photo' => $this->makeJpeg(),
                'classroom_id' => $f['classroom']->id,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('100MB', $response->json('message'));
    }

    public function test_index_filters_by_keyword_and_student(): void
    {
        $f = $this->fixture();

        $p1 = ClassroomPhoto::create([
            'classroom_id' => $f['classroom']->id,
            'uploader_id' => $f['staff']->id,
            'file_path' => 'classroom_photos/p1.jpg',
            'file_size' => 1000,
            'mime' => 'image/jpeg',
            'activity_description' => '工作の活動',
            'activity_date' => '2026-04-01',
        ]);
        $p1->students()->sync([$f['student']->id]);

        ClassroomPhoto::create([
            'classroom_id' => $f['classroom']->id,
            'uploader_id' => $f['staff']->id,
            'file_path' => 'classroom_photos/p2.jpg',
            'file_size' => 1000,
            'mime' => 'image/jpeg',
            'activity_description' => '散歩',
            'activity_date' => '2026-04-02',
        ]);

        // keyword
        $res = $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/classroom-photos?keyword=工作');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.data'));
        $this->assertEquals('工作の活動', $res->json('data.data.0.activity_description'));

        // student_id
        $res2 = $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/classroom-photos?student_id=' . $f['student']->id);
        $res2->assertStatus(200);
        $this->assertCount(1, $res2->json('data.data'));

        // date range
        $res3 = $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/classroom-photos?from=2026-04-02&to=2026-04-02');
        $res3->assertStatus(200);
        $this->assertCount(1, $res3->json('data.data'));
        $this->assertEquals('散歩', $res3->json('data.data.0.activity_description'));
    }

    public function test_destroy_removes_photo_and_file(): void
    {
        $f = $this->fixture();
        $file = $this->makeJpeg();
        $create = $this->actingAs($f['staff'], 'sanctum')
            ->postJson('/api/staff/classroom-photos', [
                'photo' => $file,
                'classroom_id' => $f['classroom']->id,
            ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');
        $path = $create->json('data.file_path');

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->deleteJson("/api/staff/classroom-photos/{$id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('classroom_photos', ['id' => $id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_storage_usage_returns_totals(): void
    {
        $f = $this->fixture();
        ClassroomPhoto::create([
            'classroom_id' => $f['classroom']->id,
            'uploader_id' => $f['staff']->id,
            'file_path' => 'classroom_photos/a.jpg',
            'file_size' => 50 * 1024,
            'mime' => 'image/jpeg',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->getJson('/api/staff/classroom-photos/storage-usage?classroom_id=' . $f['classroom']->id);

        $response->assertStatus(200);
        $this->assertEquals(50 * 1024, $response->json('data.used_bytes'));
        $this->assertEquals(ClassroomPhoto::STORAGE_LIMIT_BYTES, $response->json('data.limit_bytes'));
    }

    public function test_update_changes_metadata_and_students(): void
    {
        $f = $this->fixture();
        $p = ClassroomPhoto::create([
            'classroom_id' => $f['classroom']->id,
            'uploader_id' => $f['staff']->id,
            'file_path' => 'classroom_photos/x.jpg',
            'file_size' => 1000,
            'mime' => 'image/jpeg',
            'activity_description' => '旧',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->putJson("/api/staff/classroom-photos/{$p->id}", [
                'activity_description' => '新しい説明',
                'student_ids' => [$f['student']->id],
            ]);

        $response->assertStatus(200);
        $this->assertEquals('新しい説明', $p->fresh()->activity_description);
        $this->assertTrue($p->students()->where('students.id', $f['student']->id)->exists());
    }

    public function test_show_rejects_foreign_classroom_photo(): void
    {
        $f = $this->fixture();
        $p = ClassroomPhoto::create([
            'classroom_id' => $f['other']->id,
            'uploader_id' => $f['staff']->id,
            'file_path' => 'classroom_photos/y.jpg',
            'file_size' => 1000,
            'mime' => 'image/jpeg',
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->getJson("/api/staff/classroom-photos/{$p->id}");

        $response->assertStatus(403);
    }
}
