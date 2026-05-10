<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\CompanyInternship;
use App\Models\DailyRecord;
use App\Models\JobApplication;
use App\Models\JobPlacement;
use App\Models\JobPlacementContact;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Services\ServiceTypeRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * 就労 A/B/移行 のデモ事業所 + 利用者 + 家族 + 6 ヶ月分の連絡帳記録を投入する。
 * carebridge.link を多種別の事業所として体験できるようにするためのデータ。
 *
 * 走らせるには:
 *   php artisan db:seed --class=Database\\Seeders\\EmploymentDemoSeeder
 */
class EmploymentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::create(2026, 5, 11);
        $startDate = $today->copy()->subMonths(6);

        $configs = [
            ServiceTypeRegistry::EMPLOYMENT_A => [
                'classroom_name' => 'ケアブリッジ就労支援センター A',
                'staff_username' => 'staff_employa',
                'staff_full_name' => '田村 健一',
                'users' => [
                    [
                        'student_username' => 'user_emp_a_001',
                        'student_name'     => '佐々木 涼',
                        'birth_date'       => '1995-04-12',
                        'guardian_username' => 'family_emp_a_001',
                        'guardian_full_name' => '佐々木 義雄',
                        'contract_start_date' => '2025-04-01',
                        'usage_limit_date' => '2027-03-31',
                    ],
                    [
                        'student_username' => 'user_emp_a_002',
                        'student_name'     => '渡辺 美咲',
                        'birth_date'       => '1988-09-23',
                        'guardian_username' => 'family_emp_a_002',
                        'guardian_full_name' => '渡辺 啓子',
                        'contract_start_date' => '2024-10-01',
                        'usage_limit_date' => '2026-09-30',
                    ],
                ],
            ],
            ServiceTypeRegistry::EMPLOYMENT_B => [
                'classroom_name' => 'ケアブリッジ就労支援センター B',
                'staff_username' => 'staff_employb',
                'staff_full_name' => '中村 香織',
                'users' => [
                    [
                        'student_username' => 'user_emp_b_001',
                        'student_name'     => '小林 健太',
                        'birth_date'       => '1992-11-05',
                        'guardian_username' => 'family_emp_b_001',
                        'guardian_full_name' => '小林 久美',
                        'contract_start_date' => '2024-06-01',
                        'usage_limit_date' => null,
                    ],
                    [
                        'student_username' => 'user_emp_b_002',
                        'student_name'     => '山口 直美',
                        'birth_date'       => '2001-02-18',
                        'guardian_username' => 'family_emp_b_002',
                        'guardian_full_name' => '山口 隆',
                        'contract_start_date' => '2025-09-01',
                        'usage_limit_date' => null,
                    ],
                ],
            ],
            ServiceTypeRegistry::TRANSITION => [
                'classroom_name' => 'ケアブリッジ就労移行センター',
                'staff_username' => 'staff_transition',
                'staff_full_name' => '木村 由希',
                'users' => [
                    [
                        'student_username' => 'user_trans_001',
                        'student_name'     => '加藤 翔平',
                        'birth_date'       => '1999-07-30',
                        'guardian_username' => 'family_trans_001',
                        'guardian_full_name' => '加藤 美智子',
                        'contract_start_date' => '2025-04-01',
                        'usage_limit_date' => '2027-03-31',
                    ],
                    [
                        'student_username' => 'user_trans_002',
                        'student_name'     => '吉田 葵',
                        'birth_date'       => '2002-12-08',
                        'guardian_username' => 'family_trans_002',
                        'guardian_full_name' => '吉田 雅人',
                        'contract_start_date' => '2024-09-01',
                        'usage_limit_date' => '2026-08-31',
                    ],
                ],
            ],
        ];

        foreach ($configs as $serviceType => $config) {
            $this->seedServiceType($serviceType, $config, $startDate, $today);
        }

        $this->command->info('Employment demo data seeded successfully.');
    }

    /**
     * @param  array<string,mixed>  $config
     */
    private function seedServiceType(string $serviceType, array $config, Carbon $startDate, Carbon $today): void
    {
        // 1. 教室
        $defaultCapacity = match ($serviceType) {
            ServiceTypeRegistry::EMPLOYMENT_A => 20,
            ServiceTypeRegistry::EMPLOYMENT_B => 25,
            ServiceTypeRegistry::TRANSITION   => 10,
            default                           => 10,
        };
        $classroom = Classroom::firstOrCreate(
            ['classroom_name' => $config['classroom_name']],
            [
                'service_type' => $serviceType,
                'is_active'    => true,
                'capacity'     => $defaultCapacity,
                'opening_days_per_month' => 22,
            ]
        );
        if ($classroom->service_type !== $serviceType) {
            $classroom->update(['service_type' => $serviceType]);
        }
        if (!$classroom->capacity) {
            $classroom->update(['capacity' => $defaultCapacity]);
        }

        // 2. スタッフ (master)
        $staff = User::firstOrCreate(
            ['username' => $config['staff_username']],
            [
                'classroom_id' => $classroom->id,
                'username'  => $config['staff_username'],
                'password'  => Hash::make('staff1234'),
                'full_name' => $config['staff_full_name'],
                'email'     => $config['staff_username'].'@care-bridge.example.com',
                'user_type' => 'staff',
                'is_master' => true,
                'is_active' => true,
            ]
        );

        // 3. 各利用者 + 家族
        foreach ($config['users'] as $u) {
            $guardian = User::firstOrCreate(
                ['username' => $u['guardian_username']],
                [
                    'classroom_id' => $classroom->id,
                    'username'  => $u['guardian_username'],
                    'password'  => Hash::make('family1234'),
                    'full_name' => $u['guardian_full_name'],
                    'email'     => $u['guardian_username'].'@care-bridge.example.com',
                    'user_type' => 'guardian',
                    'is_active' => true,
                ]
            );

            // 工賃計算用のデフォルト値 (種別ごとに想定値を入れる)
            $wageDefaults = match ($serviceType) {
                ServiceTypeRegistry::EMPLOYMENT_A => [
                    'wage_calculation_type' => 'hourly',
                    'hourly_rate'           => 1100,  // A型は最低賃金以上
                    'employment_status'     => 'part_time',
                    'paid_leave_days'       => 10,
                ],
                ServiceTypeRegistry::EMPLOYMENT_B => [
                    'wage_calculation_type' => 'hourly',
                    'hourly_rate'           => 250,   // B型平均工賃 ~250円/h
                    'employment_status'     => null,
                    'paid_leave_days'       => 0,
                ],
                default => [
                    'wage_calculation_type' => null,
                    'hourly_rate'           => null,
                    'employment_status'     => null,
                    'paid_leave_days'       => 0,
                ],
            };

            // Phase D-1: 国保連請求用のサンプル属性
            $billingDefaults = [
                'beneficiary_number'        => '14' . str_pad((string) (count($config['users']) * 100 + array_search($u, $config['users'], true)), 8, '0', STR_PAD_LEFT),
                'municipality_code'         => '141305', // 神奈川県横浜市西区 (例)
                'disability_category'       => $serviceType === ServiceTypeRegistry::EMPLOYMENT_A ? 'mental' : 'intellectual',
                'disability_grade'          => $serviceType === ServiceTypeRegistry::EMPLOYMENT_A ? '区分3' : '区分4',
                'monthly_copay_cap'         => 9300,    // 一般 1
                'copay_management_provider' => '自事業所',
                'certificate_issued_date'   => $u['contract_start_date'],
                'certificate_expiry_date'   => $u['usage_limit_date'] ?: $startDate->copy()->addYears(2)->toDateString(),
                'monthly_usage_days_cap'    => 23,
            ];

            $student = Student::firstOrCreate(
                ['username' => $u['student_username']],
                array_merge([
                    'classroom_id'        => $classroom->id,
                    'student_name'        => $u['student_name'],
                    'username'            => $u['student_username'],
                    'password_hash'       => bcrypt('user1234'),
                    'birth_date'          => $u['birth_date'],
                    'guardian_id'         => $guardian->id,
                    'status'              => 'active',
                    'support_start_date'  => $u['contract_start_date'],
                    'contract_start_date' => $u['contract_start_date'],
                    'usage_limit_date'    => $u['usage_limit_date'],
                    'scheduled_monday'    => true,
                    'scheduled_tuesday'   => true,
                    'scheduled_wednesday' => true,
                    'scheduled_thursday'  => true,
                    'scheduled_friday'    => true,
                ], $wageDefaults, $billingDefaults)
            );

            // 4. 6 ヶ月分の連絡帳記録 (週 3 日 → ~78 件)
            $this->seedRecords($classroom, $student, $staff, $serviceType, $startDate, $today);

            // 5. 就労移行 のみ: 求職活動 / 企業実習 / 就職後定着 のサンプル
            if ($serviceType === ServiceTypeRegistry::TRANSITION) {
                $this->seedTransitionSupport($classroom, $student, $staff, $today);
            }
        }

        $this->command->info("[{$serviceType}] {$config['classroom_name']} のデモデータを投入しました。");
    }

    private function seedRecords(Classroom $classroom, Student $student, User $staff, string $serviceType, Carbon $startDate, Carbon $today): void
    {
        // 既に十分なレコードがあればスキップ (重複投入防止)
        $existingCount = StudentRecord::where('student_id', $student->id)->count();
        if ($existingCount >= 50) {
            return;
        }

        $cursor = $startDate->copy();
        $strengthKeys = ServiceTypeRegistry::strengthKeys($serviceType);
        $weekIndex = 0;

        while ($cursor->lte($today)) {
            // 月-水-金 だけ記録
            $dow = $cursor->dayOfWeek;
            if (! in_array($dow, [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::FRIDAY], true)) {
                $cursor->addDay();
                continue;
            }

            $daily = DailyRecord::firstOrCreate(
                [
                    'classroom_id' => $classroom->id,
                    'record_date'  => $cursor->toDateString(),
                ],
                [
                    'staff_id' => $staff->id,
                    'activity_name' => $this->dailyActivityName($serviceType, $weekIndex),
                ]
            );

            $strengths = $this->generateStrengths($strengthKeys, $weekIndex);
            $serviceData = $this->generateServiceTypeData($serviceType, $cursor, $weekIndex);

            StudentRecord::firstOrCreate(
                [
                    'daily_record_id' => $daily->id,
                    'student_id'      => $student->id,
                ],
                [
                    'notes'             => $this->generateNotes($serviceType, $weekIndex),
                    'strengths'         => $strengths,
                    'service_type_data' => $serviceData,
                ]
            );

            $weekIndex++;
            $cursor->addDay();
        }
    }

    /** @return array<string,int> */
    private function generateStrengths(array $strengthKeys, int $weekIndex): array
    {
        // 段階的に伸びる傾向 + ランダム揺らぎ
        $base = min(7, 3 + intdiv($weekIndex, 12)); // 3 → 4 → 5 → 6 と段階的に上昇
        $result = [];
        foreach ($strengthKeys as $key) {
            $jitter = ($weekIndex + crc32($key)) % 4 - 1; // -1,0,1,2
            $value = max(1, min(10, $base + $jitter));
            $result[$key] = $value;
        }
        return $result;
    }

    /** @return array<string,mixed> */
    private function generateServiceTypeData(string $serviceType, Carbon $date, int $weekIndex): array
    {
        if ($serviceType === ServiceTypeRegistry::EMPLOYMENT_A || $serviceType === ServiceTypeRegistry::EMPLOYMENT_B) {
            $works = $serviceType === ServiceTypeRegistry::EMPLOYMENT_A
                ? ['データ入力', '袋詰め', '検品', '梱包', '清掃']
                : ['シール貼り', '軽作業', '清掃', '備品整理', '園芸補助'];
            $hourBase = $serviceType === ServiceTypeRegistry::EMPLOYMENT_A ? 6 : 4;

            return [
                'wage_eligible_hours' => $hourBase + ($weekIndex % 3) * 0.5,
                'clock_in'            => sprintf('%02d:%02d', 8 + ($weekIndex % 2), 30 + ($weekIndex % 2) * 15),
                'clock_out'           => sprintf('%02d:%02d', 14 + ($weekIndex % 3), ($weekIndex % 4) * 15),
                'work_content'        => $works[$weekIndex % count($works)],
            ];
        }

        if ($serviceType === ServiceTypeRegistry::TRANSITION) {
            $practices = ['ビジネスマナー研修', 'PC スキル訓練', '面接練習', '企業見学', 'グループワーク'];
            $jobActivities = ['ハローワーク訪問', '履歴書作成', '面接準備', '企業実習', '職務経歴書作成'];
            return [
                'practice_content'      => $practices[$weekIndex % count($practices)],
                'job_search_record'     => $weekIndex >= 12 ? $jobActivities[$weekIndex % count($jobActivities)] : null,
                'business_manner_score' => max(1, min(5, 2 + intdiv($weekIndex, 10))),
            ];
        }

        return [];
    }

    private function generateNotes(string $serviceType, int $weekIndex): string
    {
        $afterSchoolStyle = [
            'おやつの時間に「ありがとう」と自然に言えました。',
            '休憩時間にスタッフと将棋を楽しみました。',
            '宿題に集中して取り組めました。',
        ];

        $employmentNotes = [
            '作業中の集中力が向上し、ミス率が低下しました。',
            '同僚への声かけが増え、チームワークが改善しています。',
            '休憩時間の取り方が安定してきました。',
            '作業手順書を確認しながら丁寧に取り組めました。',
            '体調管理を意識して水分補給ができていました。',
        ];

        $transitionNotes = [
            'グループワークで自分の意見を発言できました。',
            '訓練後のフィードバックを素直に受け止められました。',
            '朝のスケジュール確認を主体的に行えました。',
            '対人面の課題について自己理解が深まっています。',
            '面接練習でハキハキと受け答えできました。',
        ];

        if ($serviceType === ServiceTypeRegistry::EMPLOYMENT_A || $serviceType === ServiceTypeRegistry::EMPLOYMENT_B) {
            return $employmentNotes[$weekIndex % count($employmentNotes)];
        }
        if ($serviceType === ServiceTypeRegistry::TRANSITION) {
            return $transitionNotes[$weekIndex % count($transitionNotes)];
        }
        return $afterSchoolStyle[$weekIndex % count($afterSchoolStyle)];
    }

    /**
     * 就労移行支援用: 求職活動・企業実習・就職後定着のサンプルを 1〜2 件ずつ投入する。
     */
    private function seedTransitionSupport(Classroom $classroom, Student $student, User $staff, Carbon $today): void
    {
        // 1. 求職応募 (2 件)
        $apps = [
            [
                'company_name' => 'XYZ株式会社',
                'industry' => 'IT・サービス',
                'job_title' => '事務補助',
                'employment_type' => 'part_time',
                'application_date' => $today->copy()->subMonths(2)->toDateString(),
                'source' => 'hello_work',
                'status' => 'interviewed',
                'interview_date' => $today->copy()->subMonths(2)->addDays(10)->toDateString(),
                'feedback' => '面接で挨拶がしっかりできた。配属候補は事務部。',
            ],
            [
                'company_name' => 'ABC物流',
                'industry' => '物流',
                'job_title' => '軽作業',
                'employment_type' => 'full_time',
                'application_date' => $today->copy()->subMonths(1)->toDateString(),
                'source' => 'introduction',
                'status' => 'offered',
                'interview_date' => $today->copy()->subMonths(1)->addDays(7)->toDateString(),
                'result_date' => $today->copy()->subWeeks(2)->toDateString(),
                'result_notes' => '内定獲得。週 30 時間勤務、配慮事項として体調変化時の早退許可あり。',
            ],
        ];
        foreach ($apps as $a) {
            JobApplication::firstOrCreate(
                ['student_id' => $student->id, 'company_name' => $a['company_name']],
                array_merge($a, ['classroom_id' => $classroom->id, 'created_by' => $staff->id])
            );
        }

        // 2. 企業実習 (1 件)
        CompanyInternship::firstOrCreate(
            ['student_id' => $student->id, 'company_name' => 'DEF商事'],
            [
                'classroom_id' => $classroom->id,
                'contact_person' => '山本 課長',
                'contact_phone' => '03-1234-5678',
                'start_date' => $today->copy()->subMonths(3)->toDateString(),
                'end_date' => $today->copy()->subMonths(3)->addDays(5)->toDateString(),
                'total_days' => 5,
                'internship_type' => 'hands_on',
                'purpose' => '一般事務職の業務体験を通じて適性を確認する。',
                'plan_content' => "1日目: 職場見学・OJT\n2-3日目: データ入力作業\n4日目: 電話応対練習\n5日目: 振り返り・面談",
                'company_evaluation' => '指示理解と業務遂行は問題なし。報連相も期待値以上。',
                'attitude_score' => 4,
                'skill_score' => 3,
                'communication_score' => 4,
                'staff_evaluation' => '集中力に課題はあるが、休憩を適切に挟めば長時間作業も可能。',
                'outcome' => 'led_to_employment',
                'created_by' => $staff->id,
            ]
        );

        // 3. 就職後定着 (1 件) — 最初の利用者だけ就職済み
        if ($student->id % 2 === 0) {
            $placement = JobPlacement::firstOrCreate(
                ['student_id' => $student->id, 'company_name' => 'GHI製造'],
                [
                    'classroom_id' => $classroom->id,
                    'job_title' => '組立補助',
                    'start_date' => $today->copy()->subMonths(4)->toDateString(),
                    'employment_type' => 'part_time',
                    'monthly_salary' => 145000,
                    'weekly_hours' => 30,
                    'status' => 'active',
                    'reasonable_accommodations' => "・体調不良時の早退\n・週 1 回の事業所スタッフ訪問の許可\n・指示は文書/写真で",
                    'next_followup_date' => $today->copy()->addWeeks(2)->toDateString(),
                    'created_by' => $staff->id,
                ]
            );

            // 月次の定着支援連絡 (4 件)
            for ($i = 4; $i >= 1; $i--) {
                JobPlacementContact::firstOrCreate(
                    [
                        'job_placement_id' => $placement->id,
                        'contact_date' => $today->copy()->subMonths($i)->toDateString(),
                    ],
                    [
                        'contact_type' => $i % 2 === 0 ? 'visit' : 'phone',
                        'contact_with' => $i === 1 ? '上司・本人' : '本人',
                        'content' => "{$i} ヶ月目の定着確認。出勤状況は安定し、業務にも慣れてきた。",
                        'issues_raised' => $i === 2 ? '同僚との会話に少し疲れを感じることがある。' : null,
                        'actions_taken' => $i === 2 ? '休憩時の過ごし方をスタッフと整理。気分転換の工夫を提案。' : null,
                        'satisfaction_score' => 3 + ($i % 2),
                        'attendance_rate' => 95 - ($i * 2),
                        'created_by' => $staff->id,
                    ]
                );
            }
        }
    }

    private function dailyActivityName(string $serviceType, int $weekIndex): string
    {
        if ($serviceType === ServiceTypeRegistry::EMPLOYMENT_A) {
            $names = ['通常作業日', '受注作業強化日', '研修・OJT 日', '清掃・整理日'];
        } elseif ($serviceType === ServiceTypeRegistry::EMPLOYMENT_B) {
            $names = ['軽作業日', '創作活動日', '体験プログラム', '個別支援日'];
        } else {
            $names = ['基礎訓練日', '応用訓練日', '面接対策日', '企業実習準備'];
        }
        return $names[$weekIndex % count($names)];
    }
}
