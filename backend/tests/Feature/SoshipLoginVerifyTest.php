<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SOSHIP Growth OS のログイン連携（サーバ間・共有シークレット）。
 * POST /api/integration/soship/verify-login
 *
 * 差分カテゴリ: auth
 * - 共有シークレット不一致は 401（呼び出し元の偽装を弾く）。
 * - きづりの username + password を照合し、スタッフ/管理者のみ許可（保護者/生徒は不可）。
 * - 企業単位の SOSHIP 連携が無効（classrooms.soship_enabled=false）なら 403 not_enabled。
 * - 2FA 有効ユーザーは username+password 経路を許可しない（403 two_factor）。
 * - トークンは一切発行しない（成功時はユーザー情報のみ返す）。
 */
class SoshipLoginVerifyTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'soship-shared-secret-xyz';

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
        config(['services.soship.sso_secret' => self::SECRET]);
    }

    private function enabledRoom(): Classroom
    {
        $company = Company::create(['name' => '企業A', 'soship_enabled' => true]);
        return Classroom::create([
            'classroom_name' => '事業所A', 'company_id' => $company->id,
            'is_active' => true, 'soship_enabled' => true,
        ]);
    }

    private function staff(Classroom $room, array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'staff_' . uniqid(),
            'password' => Hash::make('correct-horse'),
            'full_name' => 'スタッフ', 'user_type' => 'staff',
            'classroom_id' => $room->id, 'is_active' => true,
        ], $overrides));
    }

    private function verify(array $payload)
    {
        return $this->postJson('/api/integration/soship/verify-login', $payload);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $room = $this->enabledRoom();
        $staff = $this->staff($room);

        $res = $this->verify([
            'secret' => 'WRONG', 'username' => $staff->username, 'password' => 'correct-horse',
        ]);

        $res->assertStatus(401);
        $res->assertJsonPath('success', false);
    }

    public function test_valid_login_returns_user_without_token(): void
    {
        $room = $this->enabledRoom();
        $staff = $this->staff($room);

        $res = $this->verify([
            'secret' => self::SECRET, 'username' => $staff->username, 'password' => 'correct-horse',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.user.id', $staff->id);
        $res->assertJsonPath('data.user.username', $staff->username);
        $res->assertJsonPath('data.user.user_type', 'staff');
        $this->assertContains($room->id, $res->json('data.user.accessible_classroom_ids'));
        // トークンは発行されない（連携経路でセッション/トークンを作らない）
        $res->assertJsonMissingPath('data.token');
        $this->assertSame(0, \DB::table('personal_access_tokens')->count());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $room = $this->enabledRoom();
        $staff = $this->staff($room);

        $res = $this->verify([
            'secret' => self::SECRET, 'username' => $staff->username, 'password' => 'NOPE',
        ]);

        $res->assertStatus(401);
    }

    public function test_unknown_user_is_rejected(): void
    {
        $this->enabledRoom();

        $res = $this->verify([
            'secret' => self::SECRET, 'username' => 'ghost', 'password' => 'whatever',
        ]);

        $res->assertStatus(401);
    }

    public function test_inactive_user_is_rejected(): void
    {
        $room = $this->enabledRoom();
        $staff = $this->staff($room, ['is_active' => false]);

        $res = $this->verify([
            'secret' => self::SECRET, 'username' => $staff->username, 'password' => 'correct-horse',
        ]);

        $res->assertStatus(401);
    }

    public function test_guardian_is_rejected_even_with_valid_credentials(): void
    {
        $room = $this->enabledRoom();
        // 保護者は連携対象外（user_type が staff/admin でない）
        $guardian = User::create([
            'username' => 'guardian_' . uniqid(), 'password' => Hash::make('correct-horse'),
            'full_name' => '保護者', 'user_type' => 'guardian',
            'classroom_id' => $room->id, 'is_active' => true,
        ]);

        $res = $this->verify([
            'secret' => self::SECRET, 'username' => $guardian->username, 'password' => 'correct-horse',
        ]);

        $res->assertStatus(401);
    }

    public function test_classroom_with_soship_disabled_is_rejected(): void
    {
        $company = Company::create(['name' => '企業B', 'soship_enabled' => false]);
        $room = Classroom::create([
            'classroom_name' => '事業所B', 'company_id' => $company->id,
            'is_active' => true, 'soship_enabled' => false,
        ]);
        $staff = $this->staff($room);

        $res = $this->verify([
            'secret' => self::SECRET, 'username' => $staff->username, 'password' => 'correct-horse',
        ]);

        $res->assertStatus(403);
        $res->assertJsonPath('code', 'not_enabled');
    }

    public function test_two_factor_user_is_rejected_with_code(): void
    {
        $room = $this->enabledRoom();
        $staff = $this->staff($room);
        // two_factor_* は $fillable 外（mass-assign では入らない）ため直接設定して保存
        $staff->two_factor_secret = 'BASE32SECRET';
        $staff->two_factor_confirmed_at = now();
        $staff->save();
        $this->assertTrue($staff->fresh()->hasTwoFactorEnabled());

        $res = $this->verify([
            'secret' => self::SECRET, 'username' => $staff->username, 'password' => 'correct-horse',
        ]);

        $res->assertStatus(403);
        $res->assertJsonPath('code', 'two_factor');
    }

    public function test_missing_fields_is_validation_error(): void
    {
        $res = $this->verify(['secret' => self::SECRET]);
        $res->assertStatus(422);
    }
}
