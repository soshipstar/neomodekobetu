<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\MeetingRequest;
use App\Models\Student;
use App\Models\StudentChatMessage;
use App\Models\StudentChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * バグ報告 #12 / #19: 9時間タイムゾーンズレの回帰テスト
 *
 * 差分カテゴリ: logic
 * 背景: datetime キャストが "Y-m-d H:i:s" 指定で TZ マーカー無しの
 *       UTC wall clock 文字列を返していたため、フロントの new Date() /
 *       parseISO が local 時刻として再解釈して 9 時間ズレていた。
 *       Laravel デフォルトの ISO 8601 with Z に戻すことで、フロント側で
 *       正しい local 変換が行われるようにした。
 */
class BugReport12_19_TimezoneSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_meeting_confirmed_date_is_serialized_with_timezone_marker(): void
    {
        $c = Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
        $staff = User::create([
            'username' => 's_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 's', 'user_type' => 'staff',
            'classroom_id' => $c->id, 'is_active' => true,
        ]);
        $guardian = User::create([
            'username' => 'g_' . uniqid(), 'password' => bcrypt('p'),
            'full_name' => 'g', 'user_type' => 'guardian', 'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => 'stu', 'classroom_id' => $c->id, 'guardian_id' => $guardian->id,
        ]);

        // JST の 21:19 で確定
        $jstConfirmed = Carbon::create(2026, 4, 16, 21, 19, 0, 'Asia/Tokyo');
        $meeting = MeetingRequest::create([
            'classroom_id' => $c->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'purpose' => 'test',
            'candidate_dates' => [],
            'confirmed_date' => $jstConfirmed,
            'status' => 'confirmed',
            'confirmed_by' => 'staff',
            'confirmed_at' => $jstConfirmed,
        ]);

        $json = $meeting->fresh()->toArray();

        // ISO 8601 with UTC (Z) であること。JS 側で new Date() が正しく local 変換できる。
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/',
            $json['confirmed_date'],
            'confirmed_date must be ISO 8601 UTC with Z marker'
        );
        // 中身が UTC の 12:19 であること（JST 21:19 の UTC 表現）
        $this->assertStringContainsString('2026-04-16T12:19:00', $json['confirmed_date']);
    }

    public function test_student_chat_message_created_at_has_timezone_marker(): void
    {
        $c = Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
        $student = Student::create(['student_name' => 'stu', 'classroom_id' => $c->id]);
        $room = StudentChatRoom::create(['student_id' => $student->id]);

        $msg = StudentChatMessage::create([
            'room_id' => $room->id,
            'sender_type' => 'student',
            'sender_id' => $student->id,
            'message' => 'hi',
        ]);

        $json = $msg->fresh()->toArray();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/',
            $json['created_at'],
            'student chat created_at must be ISO 8601 UTC with Z marker'
        );
    }

    public function test_student_chat_room_last_message_at_has_timezone_marker(): void
    {
        $c = Classroom::create(['classroom_name' => '教室', 'is_active' => true]);
        $student = Student::create(['student_name' => 'stu', 'classroom_id' => $c->id]);

        $jst = Carbon::create(2026, 4, 22, 21, 22, 26, 'Asia/Tokyo');
        $room = StudentChatRoom::create([
            'student_id' => $student->id,
            'last_message_at' => $jst,
        ]);

        $json = $room->fresh()->toArray();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/',
            $json['last_message_at']
        );
        $this->assertStringContainsString('2026-04-22T12:22:26', $json['last_message_at']);
    }
}
