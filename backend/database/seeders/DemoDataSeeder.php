<?php

namespace Database\Seeders;

use App\Models\AbsenceNotification;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\IndividualSupportPlan;
use App\Models\KakehashiPeriod;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\SupportPlanDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    /**
     * Create sample students, chat rooms, support plans, and other demo data.
     */
    public function run(): void
    {
        $classroom1 = Classroom::where('classroom_name', 'きづり教室 本校')->first();
        $classroom2 = Classroom::where('classroom_name', 'きづり教室 第2校')->first();

        $guardian1 = User::where('username', 'guardian1')->first();
        $guardian2 = User::where('username', 'guardian2')->first();
        $guardian3 = User::where('username', 'guardian3')->first();
        $staffMaster1 = User::where('username', 'staff_master1')->first();

        if (! $classroom1 || ! $guardian1) {
            $this->command->warn('Required users/classrooms not found. Run previous seeders first.');

            return;
        }

        // =====================================================================
        // Students
        // =====================================================================

        $student1 = Student::firstOrCreate(
            ['username' => 'student_tanaka'],
            [
                'classroom_id' => $classroom1->id,
                'student_name' => '田中 翔太',
                'username' => 'student_tanaka',
                'password_hash' => bcrypt('student1234'),
                'birth_date' => '2016-05-15',
                'grade_level' => 'elementary_3',
                'guardian_id' => $guardian1->id,
                'status' => 'active',
                'scheduled_monday' => true,
                'scheduled_wednesday' => true,
                'scheduled_friday' => true,
            ]
        );

        $student2 = Student::firstOrCreate(
            ['username' => 'student_takahashi'],
            [
                'classroom_id' => $classroom1->id,
                'student_name' => '高橋 さくら',
                'username' => 'student_takahashi',
                'password_hash' => bcrypt('student1234'),
                'birth_date' => '2015-08-20',
                'grade_level' => 'elementary_4',
                'guardian_id' => $guardian2->id,
                'status' => 'active',
                'scheduled_tuesday' => true,
                'scheduled_thursday' => true,
                'scheduled_saturday' => true,
            ]
        );

        $student3 = Student::firstOrCreate(
            ['username' => 'student_ito'],
            [
                'classroom_id' => $classroom2->id,
                'student_name' => '伊藤 大輝',
                'username' => 'student_ito',
                'password_hash' => bcrypt('student1234'),
                'birth_date' => '2017-01-10',
                'grade_level' => 'elementary_2',
                'guardian_id' => $guardian3->id,
                'status' => 'active',
                'scheduled_monday' => true,
                'scheduled_tuesday' => true,
                'scheduled_thursday' => true,
                'scheduled_friday' => true,
            ]
        );

        // =====================================================================
        // Chat Rooms & Messages
        // =====================================================================

        $room1 = ChatRoom::firstOrCreate(
            ['student_id' => $student1->id, 'guardian_id' => $guardian1->id],
            ['last_message_at' => now()]
        );

        $room2 = ChatRoom::firstOrCreate(
            ['student_id' => $student2->id, 'guardian_id' => $guardian2->id],
            ['last_message_at' => now()->subHours(2)]
        );

        // Sample messages for room 1
        if ($room1->messages()->count() === 0) {
            ChatMessage::create([
                'room_id' => $room1->id,
                'sender_id' => $guardian1->id,
                'sender_type' => 'guardian',
                'message' => '翔太が明日お休みさせていただきます。体調不良のためです。',
                'message_type' => 'text',
            ]);

            ChatMessage::create([
                'room_id' => $room1->id,
                'sender_id' => $staffMaster1->id,
                'sender_type' => 'staff',
                'message' => '承知しました。お大事にしてください。来週の振替はいかがでしょうか？',
                'message_type' => 'text',
            ]);

            ChatMessage::create([
                'room_id' => $room1->id,
                'sender_id' => $guardian1->id,
                'sender_type' => 'guardian',
                'message' => 'ありがとうございます。来週の火曜日でお願いしたいです。',
                'message_type' => 'text',
            ]);
        }

        // =====================================================================
        // Individual Support Plans
        // =====================================================================

        $plan1 = IndividualSupportPlan::firstOrCreate(
            ['student_id' => $student1->id, 'created_date' => '2026-01-15'],
            [
                'classroom_id' => $classroom1->id,
                'student_name' => $student1->student_name,
                'created_date' => '2026-01-15',
                'life_intention' => 'お友達と仲良く遊びたい。楽しく過ごしたい。',
                'overall_policy' => 'コミュニケーション能力の向上と社会性の発達を支援する。',
                'long_term_goal' => '自分の気持ちを言葉で伝えることができるようになる。',
                'short_term_goal' => 'スタッフの声かけで順番を待つことができる。',
                'status' => 'approved',
                'is_official' => true,
                'created_by' => $staffMaster1->id,
            ]
        );

        if ($plan1->details()->count() === 0) {
            $details = [
                [
                    'domain' => '健康・生活',
                    'current_status' => '基本的な生活習慣は身についている。手洗いうがいを促せばできる。',
                    'goal' => '自分から手洗いうがいができる。',
                    'support_content' => '声かけを段階的に減らし、自主的な行動を促す。',
                    'sort_order' => 0,
                ],
                [
                    'domain' => '運動・感覚',
                    'current_status' => '体を動かすことが好き。バランス感覚に課題あり。',
                    'goal' => '片足立ちが10秒できる。',
                    'support_content' => 'バランスボールや平均台を使った運動遊びを取り入れる。',
                    'sort_order' => 1,
                ],
                [
                    'domain' => '認知・行動',
                    'current_status' => '集中できる時間が短い。好きな活動には集中できる。',
                    'goal' => '15分間座って活動に取り組める。',
                    'support_content' => 'タイマーを活用し、視覚的に残り時間を示す。',
                    'sort_order' => 2,
                ],
                [
                    'domain' => '言語・コミュニケーション',
                    'current_status' => '単語での表現が多い。要求は伝えられる。',
                    'goal' => '2語文で気持ちを伝えることができる。',
                    'support_content' => 'モデル文を示し、復唱を促す。絵カードも併用。',
                    'sort_order' => 3,
                ],
                [
                    'domain' => '人間関係・社会性',
                    'current_status' => '自分から友達に関わることは少ない。',
                    'goal' => 'スタッフと一緒に友達の遊びに参加できる。',
                    'support_content' => '少人数のグループ活動を設定し、成功体験を積む。',
                    'sort_order' => 4,
                ],
            ];

            foreach ($details as $detail) {
                SupportPlanDetail::create(array_merge($detail, ['plan_id' => $plan1->id]));
            }
        }

        // =====================================================================
        // Kakehashi Periods
        // =====================================================================

        KakehashiPeriod::firstOrCreate(
            [
                'student_id' => $student1->id,
                'start_date' => '2025-10-01',
                'end_date' => '2026-03-31',
            ],
            [
                'period_name' => '2025年度 後期',
                'submission_deadline' => '2026-04-15',
                'is_active' => true,
                'is_auto_generated' => true,
            ]
        );

        KakehashiPeriod::firstOrCreate(
            [
                'student_id' => $student2->id,
                'start_date' => '2025-10-01',
                'end_date' => '2026-03-31',
            ],
            [
                'period_name' => '2025年度 後期',
                'submission_deadline' => '2026-04-15',
                'is_active' => true,
                'is_auto_generated' => true,
            ]
        );

        // =====================================================================
        // Daily Records & Student Records
        // =====================================================================

        $today = Carbon::today();
        for ($i = 0; $i < 5; $i++) {
            $date = $today->copy()->subDays($i);
            if ($date->isWeekend()) {
                continue;
            }

            $dailyRecord = DailyRecord::firstOrCreate(
                ['classroom_id' => $classroom1->id, 'record_date' => $date],
                [
                    'activity_name' => collect(['工作', '運動遊び', '音楽活動', 'ことば遊び', '自由遊び'])->random(),
                    'common_activity' => 'はじまりの会、おやつ、帰りの会',
                    'staff_id' => $staffMaster1->id,
                ]
            );

            StudentRecord::firstOrCreate(
                ['daily_record_id' => $dailyRecord->id, 'student_id' => $student1->id],
                [
                    'health_life' => '元気に過ごした。',
                    'motor_sensory' => 'バランスボールに挑戦した。',
                    'cognitive_behavior' => '10分間集中して取り組めた。',
                    'language_communication' => '「やりたい」と伝えられた。',
                    'social_relations' => 'お友達の様子を見ていた。',
                    'notes' => null,
                ]
            );
        }

        // =====================================================================
        // Absence Notifications
        // =====================================================================

        AbsenceNotification::firstOrCreate(
            ['student_id' => $student1->id, 'absence_date' => $today->copy()->subDays(3)],
            [
                'reason' => '体調不良のため',
                'makeup_request_date' => $today->copy()->addDays(2),
                'makeup_status' => 'pending',
            ]
        );

        $this->command->info('Demo data seeded successfully.');
    }
}
