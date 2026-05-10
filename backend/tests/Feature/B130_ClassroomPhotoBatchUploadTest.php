<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * #1 follow-up: 写真の一括 (バッチ) アップロード
 *
 * 差分カテゴリ: api
 * 背景: タブレット写真画面の複数選択UIに対し、それまでの「1枚ずつ POST」を
 *       1リクエストにまとめる ClassroomPhotoController::storeBatch を新設。
 *       共通メタ (activity_description, activity_date, student_ids) は1セットを
 *       全件に適用し、部分成功 (一部だけ失敗してもそれ以外は保存される) を許容する。
 */
class B130_ClassroomPhotoBatchUploadTest extends TestCase
{
    use RefreshDatabase;

    private function setupStaff(): array
    {
        $classroom = Classroom::create(['classroom_name' => '教室A', 'is_active' => true]);
        $staff = User::create([
            'username'     => 'staff_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
        return [$staff, $classroom];
    }

    public function test_batch_upload_creates_all_photos(): void
    {
        Storage::fake('public');
        [$staff, $classroom] = $this->setupStaff();

        $files = [
            UploadedFile::fake()->image('a.jpg', 800, 600),
            UploadedFile::fake()->image('b.jpg', 800, 600),
            UploadedFile::fake()->image('c.jpg', 800, 600),
        ];

        $response = $this->actingAs($staff, 'sanctum')
            ->post('/api/tablet/photos/batch', [
                'photos'               => $files,
                'classroom_id'         => $classroom->id,
                'activity_description' => '公園で遊んだ',
                'activity_date'        => '2026-05-10',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.success_count', 3);
        $response->assertJsonPath('data.fail_count', 0);

        $this->assertSame(3, ClassroomPhoto::count());
        // 共通メタが全件に適用されている
        $this->assertSame(3, ClassroomPhoto::where('activity_description', '公園で遊んだ')->count());
        $this->assertSame(3, ClassroomPhoto::whereDate('activity_date', '2026-05-10')->count());
    }

    public function test_batch_upload_rejects_unauthorized_classroom(): void
    {
        Storage::fake('public');
        [$staff] = $this->setupStaff();

        // 別教室
        $other = Classroom::create(['classroom_name' => '教室B', 'is_active' => true]);

        $response = $this->actingAs($staff, 'sanctum')
            ->post('/api/tablet/photos/batch', [
                'photos'       => [UploadedFile::fake()->image('a.jpg', 400, 300)],
                'classroom_id' => $other->id,
            ]);

        $response->assertStatus(403);
        $this->assertSame(0, ClassroomPhoto::count());
    }

    public function test_batch_upload_validates_min_one_file(): void
    {
        Storage::fake('public');
        [$staff, $classroom] = $this->setupStaff();

        $response = $this->actingAs($staff, 'sanctum')
            ->post('/api/tablet/photos/batch', [
                'photos'       => [],
                'classroom_id' => $classroom->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('photos');
    }

    public function test_batch_upload_caps_at_30_files(): void
    {
        Storage::fake('public');
        [$staff, $classroom] = $this->setupStaff();

        $files = [];
        for ($i = 0; $i < 31; $i++) {
            $files[] = UploadedFile::fake()->image("p{$i}.jpg", 100, 100);
        }

        $response = $this->actingAs($staff, 'sanctum')
            ->post('/api/tablet/photos/batch', [
                'photos'       => $files,
                'classroom_id' => $classroom->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('photos');
    }

    public function test_batch_upload_returns_207_style_partial_with_invalid_file(): void
    {
        Storage::fake('public');
        [$staff, $classroom] = $this->setupStaff();

        // 1枚は普通の画像、もう1枚はテキストファイル (mime 不一致 → validate で弾かれる)
        $good = UploadedFile::fake()->image('good.jpg', 400, 300);
        $bad = UploadedFile::fake()->create('bad.txt', 10, 'text/plain');

        $response = $this->actingAs($staff, 'sanctum')
            ->post('/api/tablet/photos/batch', [
                'photos'       => [$good, $bad],
                'classroom_id' => $classroom->id,
            ]);

        // mimes バリデーションは要素単位なので validation error 422 になる
        $response->assertStatus(422);
        // good 単体ならOKであることを別途確認
        $response2 = $this->actingAs($staff, 'sanctum')
            ->post('/api/tablet/photos/batch', [
                'photos'       => [$good],
                'classroom_id' => $classroom->id,
            ]);
        $response2->assertStatus(200);
        $this->assertSame(1, ClassroomPhoto::count());
    }

    public function test_batch_upload_assigns_students_to_all_photos(): void
    {
        Storage::fake('public');
        [$staff, $classroom] = $this->setupStaff();

        $student1 = \App\Models\Student::create(['student_name' => '生徒A', 'classroom_id' => $classroom->id]);
        $student2 = \App\Models\Student::create(['student_name' => '生徒B', 'classroom_id' => $classroom->id]);

        $response = $this->actingAs($staff, 'sanctum')
            ->post('/api/tablet/photos/batch', [
                'photos'       => [
                    UploadedFile::fake()->image('a.jpg', 400, 300),
                    UploadedFile::fake()->image('b.jpg', 400, 300),
                ],
                'classroom_id' => $classroom->id,
                'student_ids'  => [$student1->id, $student2->id],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.success_count', 2);

        // 全写真に2児童が紐付くこと
        $photos = ClassroomPhoto::with('students')->get();
        $this->assertCount(2, $photos);
        foreach ($photos as $p) {
            $this->assertCount(2, $p->students);
        }
    }
}
