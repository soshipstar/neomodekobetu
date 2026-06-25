<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 保護者一斉送信の宛先を「在籍児童基準」にする(スレッド有無に依存しない)。
 *
 * 差分カテゴリ: logic
 * - GET /api/staff/chat/broadcast-recipients: 在籍児童(保護者紐づけ済み)を返す。
 *   スレッドの無い在籍児童(太田)も含む。退所も status 付きで含む。保護者なしは除外。
 * - POST /api/staff/chat/broadcast (student_ids): スレッドが無くてもルームを作成して送信。
 */
class ChatBroadcastRecipientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    /** @return array{0: Classroom, 1: User, 2: User} */
    private function context(): array
    {
        $company = Company::create(['name' => '企業A']);
        $room = Classroom::create(['classroom_name' => 'narZE', 'company_id' => $company->id, 'is_active' => true]);
        $staff = User::create([
            'username' => 'staff_bc_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => 'スタッフ',
            'user_type' => 'staff', 'classroom_id' => $room->id, 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'g_' . uniqid(), 'password' => bcrypt('p'), 'full_name' => '保護者',
            'user_type' => 'guardian', 'is_active' => true,
        ]);

        return [$room, $staff, $guardian];
    }

    public function test_recipients_include_enrolled_without_thread_and_exclude_no_guardian(): void
    {
        [$room, $staff, $g] = $this->context();

        // 在籍・保護者あり・スレッド無し(太田)
        $ota = Student::create(['student_name' => '太田', 'classroom_id' => $room->id, 'guardian_id' => $g->id, 'status' => 'active', 'is_active' => true]);
        // 在籍・スレッドあり(小川)
        $ogawa = Student::create(['student_name' => '小川', 'classroom_id' => $room->id, 'guardian_id' => $g->id, 'status' => 'active', 'is_active' => true]);
        ChatRoom::firstOrCreate(['student_id' => $ogawa->id, 'guardian_id' => $g->id]);
        // 退所(status付きで出る)
        $taro = Student::create(['student_name' => '退所太郎', 'classroom_id' => $room->id, 'guardian_id' => $g->id, 'status' => 'withdrawn', 'is_active' => false]);
        // 保護者なし(除外)
        $none = Student::create(['student_name' => '保護者なし', 'classroom_id' => $room->id, 'status' => 'active', 'is_active' => true]);

        $res = $this->actingAs($staff, 'sanctum')->getJson('/api/staff/chat/broadcast-recipients');
        $res->assertStatus(200);
        $rows = collect($res->json('data'));
        $ids = $rows->pluck('student_id');

        $this->assertTrue($ids->contains($ota->id));   // スレッド無しでも在籍児童は出る
        $this->assertTrue($ids->contains($ogawa->id));
        $this->assertTrue($ids->contains($taro->id));   // 退所も含む
        $this->assertFalse($ids->contains($none->id));  // 保護者なしは除外

        $otaRow = $rows->firstWhere('student_id', $ota->id);
        $this->assertNull($otaRow['room_id']);          // 太田はルーム未作成
        $this->assertTrue($otaRow['student']['is_active']);
        $taroRow = $rows->firstWhere('student_id', $taro->id);
        $this->assertFalse($taroRow['student']['is_active']);
        $this->assertSame('withdrawn', $taroRow['student']['status']);
    }

    public function test_broadcast_by_student_ids_creates_room_and_sends(): void
    {
        [$room, $staff, $g] = $this->context();
        $ota = Student::create(['student_name' => '太田', 'classroom_id' => $room->id, 'guardian_id' => $g->id, 'status' => 'active', 'is_active' => true]);
        $this->assertSame(0, ChatRoom::where('student_id', $ota->id)->count());

        $res = $this->actingAs($staff, 'sanctum')->postJson('/api/staff/chat/broadcast', [
            'message' => 'お知らせです',
            'student_ids' => [$ota->id],
        ]);
        $res->assertStatus(200);

        // スレッドが無くても作成され、メッセージが送られる(送信漏れなし)
        $created = ChatRoom::where('student_id', $ota->id)->first();
        $this->assertNotNull($created);
        $this->assertSame(1, ChatMessage::where('room_id', $created->id)->count());
    }
}
