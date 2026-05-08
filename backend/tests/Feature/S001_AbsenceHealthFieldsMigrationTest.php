<?php

namespace Tests\Feature;

use App\Models\AbsenceNotification;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LR-007 Phase 1 (schema): absence_notifications に体調・対応関連カラムを追加
 *
 * 差分カテゴリ: schema
 * 背景: 淡田由貴さんからの機能要望「欠席時対応加算の欄に体温記入、通院の有無、
 *       ほかの症状（腹痛、頭痛、咽頭痛、咳、くしゃみ、鼻水）のチェック欄、
 *       その他困っていること、アドバイスを記入できるとよいなと思いました」
 *
 * 入力責任:
 *  - 保護者: body_temperature / hospital_visit / symptom_* / other_concerns
 *  - スタッフ: advice (advice_by, advice_at は自動)
 */
class S001_AbsenceHealthFieldsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_new_columns_exist_on_absence_notifications(): void
    {
        $columns = [
            'body_temperature',
            'hospital_visit',
            'symptom_abdominal_pain',
            'symptom_headache',
            'symptom_sore_throat',
            'symptom_cough',
            'symptom_sneeze',
            'symptom_runny_nose',
            'other_concerns',
            'advice',
            'advice_by',
            'advice_at',
        ];

        foreach ($columns as $col) {
            $this->assertTrue(
                Schema::hasColumn('absence_notifications', $col),
                "absence_notifications.{$col} should exist after migration",
            );
        }
    }

    public function test_can_persist_and_retrieve_health_fields(): void
    {
        $classroom = Classroom::create(['classroom_name' => '教室A', 'is_active' => true]);
        $guardian = User::create([
            'username'  => 'g_' . uniqid(),
            'password'  => bcrypt('p'),
            'full_name' => '保護者A',
            'user_type' => 'guardian',
            'is_active' => true,
        ]);
        $student = Student::create([
            'student_name' => '生徒A',
            'classroom_id' => $classroom->id,
            'guardian_id'  => $guardian->id,
        ]);
        $staff = User::create([
            'username'     => 's_' . uniqid(),
            'password'     => bcrypt('p'),
            'full_name'    => 'スタッフA',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);

        $absence = AbsenceNotification::create([
            'student_id'             => $student->id,
            'absence_date'           => '2026-05-09',
            'reason'                 => '発熱のため',
            'body_temperature'       => 38.2,
            'hospital_visit'         => true,
            'symptom_abdominal_pain' => false,
            'symptom_headache'       => true,
            'symptom_sore_throat'    => true,
            'symptom_cough'          => true,
            'symptom_sneeze'         => false,
            'symptom_runny_nose'     => true,
            'other_concerns'         => '夜中に何度も起きていました',
            'advice'                 => '十分な水分補給と休養をお願いします。',
            'advice_by'              => $staff->id,
            'advice_at'              => now(),
        ]);

        $fresh = AbsenceNotification::find($absence->id);
        $this->assertEquals('38.2', (string) $fresh->body_temperature);
        $this->assertTrue($fresh->hospital_visit);
        $this->assertFalse($fresh->symptom_abdominal_pain);
        $this->assertTrue($fresh->symptom_headache);
        $this->assertTrue($fresh->symptom_sore_throat);
        $this->assertTrue($fresh->symptom_cough);
        $this->assertFalse($fresh->symptom_sneeze);
        $this->assertTrue($fresh->symptom_runny_nose);
        $this->assertEquals('夜中に何度も起きていました', $fresh->other_concerns);
        $this->assertEquals('十分な水分補給と休養をお願いします。', $fresh->advice);
        $this->assertEquals($staff->id, $fresh->advice_by);
        $this->assertNotNull($fresh->advice_at);
    }

    public function test_health_fields_are_optional(): void
    {
        // 既存の保存パス (体調情報なし) が壊れないこと
        $classroom = Classroom::create(['classroom_name' => '教室A', 'is_active' => true]);
        $student = Student::create([
            'student_name' => '生徒A',
            'classroom_id' => $classroom->id,
        ]);

        $absence = AbsenceNotification::create([
            'student_id'   => $student->id,
            'absence_date' => '2026-05-09',
            'reason'       => '私用',
        ]);

        $this->assertNull($absence->fresh()->body_temperature);
        $this->assertFalse($absence->fresh()->hospital_visit);
        $this->assertFalse($absence->fresh()->symptom_cough);
        $this->assertNull($absence->fresh()->advice);
    }
}
