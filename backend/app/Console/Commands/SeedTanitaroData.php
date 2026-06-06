<?php

namespace App\Console\Commands;

use App\Models\AssessmentPeriod;
use App\Models\AssessmentStaff;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 「谷太郎」の第一回スタッフアセスメント + 2025-11-01 〜 2026-05-15 の連絡帳記録を作成する。
 *
 * 個別支援計画 / アセスメント / モニタリング表の AI 生成材料として参照されることを想定。
 *
 * 使い方:
 *   php artisan seed:tanitaro              # dry-run
 *   php artisan seed:tanitaro --apply      # 実際に DB に書き込み
 *   php artisan seed:tanitaro --apply --force-update  # 既存レコードも上書き
 *
 * 冪等性: firstOrCreate ベースで動作するため、複数回実行しても重複作成しない。
 */
class SeedTanitaroData extends Command
{
    protected $signature = 'seed:tanitaro
                            {--apply : 実際に DB を更新する}
                            {--force-update : 既存の student_record を上書きする (既定は新規のみ作成)}
                            {--from=2025-11-01 : 連絡帳の開始日}
                            {--to=2026-05-15 : 連絡帳の終了日}';

    protected $description = '谷太郎の初回スタッフアセスメント + 半年分の連絡帳を seed';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $forceUpdate = (bool) $this->option('force-update');
        $from = Carbon::parse((string) $this->option('from'))->startOfDay();
        $to = Carbon::parse((string) $this->option('to'))->endOfDay();

        $this->info($apply ? '=== APPLY ===' : '=== DRY-RUN (--apply で実行) ===');

        // ----------------------------------------------------------------
        // 1) 谷太郎を検索
        // ----------------------------------------------------------------
        $candidates = Student::where('student_name', 'like', '%谷%太郎%')
            ->orWhere('student_name', 'like', '%たに%たろう%')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->error('谷太郎 が見つかりません。');
            return self::FAILURE;
        }

        if ($candidates->count() > 1) {
            $this->warn('複数候補:');
            foreach ($candidates as $c) {
                $this->line(sprintf('  id=%d  classroom=%d  name=%s', $c->id, $c->classroom_id, $c->student_name));
            }
            $this->warn('→ 最も古い id のレコードを採用します');
        }

        $student = $candidates->first();
        $this->info(sprintf('対象: id=%d, name=%s, classroom_id=%d, grade=%s',
            $student->id, $student->student_name, $student->classroom_id, $student->grade_level ?? '-'
        ));

        // ----------------------------------------------------------------
        // 2) 教室 + スタッフ ID を解決
        // ----------------------------------------------------------------
        $staff = User::where('classroom_id', $student->classroom_id)
            ->where('user_type', 'staff')
            ->where('is_active', true)
            ->first()
            ?? User::where('classroom_id', $student->classroom_id)
                ->where('is_active', true)
                ->first();

        if (!$staff) {
            $this->error("classroom_id={$student->classroom_id} に有効なスタッフがいません。");
            return self::FAILURE;
        }
        $this->info(sprintf('使用スタッフ: id=%d, name=%s', $staff->id, $staff->full_name));

        // ----------------------------------------------------------------
        // 3) 通所曜日を決定 (scheduled_* が無ければ月水金)
        // ----------------------------------------------------------------
        $weekdayKeys = [
            1 => 'scheduled_monday',
            2 => 'scheduled_tuesday',
            3 => 'scheduled_wednesday',
            4 => 'scheduled_thursday',
            5 => 'scheduled_friday',
            6 => 'scheduled_saturday',
            0 => 'scheduled_sunday',
        ];
        $scheduledDays = [];
        foreach ($weekdayKeys as $dow => $col) {
            if ($student->{$col}) {
                $scheduledDays[] = $dow;
            }
        }
        if (empty($scheduledDays)) {
            $scheduledDays = [1, 3, 5]; // 月水金
            $this->warn('scheduled_* が未設定のため、月水金で生成します');
        }

        // ----------------------------------------------------------------
        // 4) 初回アセスメント (Period + StaffEntry)
        // ----------------------------------------------------------------
        $this->newLine();
        $this->info('--- 初回スタッフアセスメント ---');

        $periodData = [
            'student_id'          => $student->id,
            'period_name'         => '初回アセスメント',
            'start_date'          => $from->toDateString(),
            'end_date'            => $to->toDateString(),
            'submission_deadline' => $to->copy()->addDays(7)->toDateString(),
            'is_active'           => true,
            'is_auto_generated'   => false,
        ];

        $staffEntryData = [
            'student_wish'           => "・支援者や保護者と一緒に活動する中で、安心して自分の気持ちを言葉や行動で表現したい。\n・好きな工作や運動を通じて達成感を味わいたい。\n・友だちと関わる場面を増やし、楽しいと感じられる体験を積み重ねたい。",
            'short_term_goal'        => "・登所後の支度を、声かけを 1〜2回に減らして自分で進められる (6ヶ月後)\n・集団活動の場で 5分以上集中して取り組める (6ヶ月後)\n・友だちへの関わりを 1日 3回以上自発的に行える (6ヶ月後)",
            'long_term_goal'         => "・身辺自立 (着替え・整理整頓・持ち物管理) を、家庭・施設の両方で安定して継続できる (1年後)\n・グループ活動で自分の役割を理解し、他児と協力して目標を達成できる (1年後)\n・気持ちが乱れた時に深呼吸やクールダウンなど自分で調整する方法を 2 つ以上使える (1年後)",
            'health_life'            => "【現状】登所時の挨拶や着替えは概ね自立しているが、持ち物の片付けは声かけが必要な日が多い。食事は偏食傾向があり、新しい食材に抵抗感を示すことがある。トイレ・手洗いは自立。\n【支援方針】視覚的な手順表を用意し、最後まで自分で確認できるルーティンを定着させる。食事は無理強いせず、少量からチャレンジできる雰囲気を作る。",
            'motor_sensory'          => "【現状】走る・跳ぶなどの粗大運動は同年齢水準。鉛筆操作・ハサミ操作はやや苦手で、線からはみ出すことが多い。聴覚過敏傾向があり、騒がしい環境では耳をふさぐ仕草が見られる。\n【支援方針】細かい運動は段階課題 (なぞり書き → 自由模写) で練習。聴覚刺激には耳栓やクールダウンスペースを提示し、自分で選んで使える環境を整える。",
            'cognitive_behavior'     => "【現状】興味のある活動 (工作・絵本) には 15〜20分集中できるが、苦手な課題は 3〜5分で気が逸れる。順序立てて取り組むことが難しく、途中で手順が抜けることがある。指示は口頭より視覚提示で理解しやすい。\n【支援方針】活動を細かいステップに分割し、各ステップ完了をチェックリストで視覚化。集中が途切れる前に短い休憩を挟む。",
            'language_communication' => "【現状】2〜3 語文での会話は可能。要求は身振りや短い単語で伝えることが多く、感情表現の語彙が限られる。質問に答えるよりも、関連のない発言を返すことがある。\n【支援方針】感情カードを使って「嬉しい」「悲しい」「困った」などのラベリングを支援。質問の前に注意喚起を行い、応答のターンを意識できるよう関わる。",
            'social_relations'       => "【現状】特定の友だち (A くん) とは穏やかに関われるが、新しい友だちや年上児との関わりは緊張が強く、距離をとる傾向。順番待ちは理解できているが、待ち時間が長いと不機嫌になる。\n【支援方針】小集団 (2〜3 人) での協同活動を中心に、安心感のある関係から段階的に拡大。順番待ちは具体的な時間 (タイマー) で見える化する。",
            'other_challenges'       => "・送迎時の切り替えに時間がかかる日があり、家庭と連携して事前予告を徹底したい。\n・季節の変化 (特に寒暖差) で疲れやすく、運動量を調整する必要がある。\n・スマートフォン動画への没頭が長引くと、活動への切り替えが難しい。家庭・施設で利用ルールを共有する。",
            'is_submitted'   => true,
            'submitted_at'   => Carbon::now()->subDays(120),
            'is_hidden'      => false,
        ];

        if ($apply) {
            $period = AssessmentPeriod::firstOrCreate(
                ['student_id' => $student->id, 'period_name' => '初回アセスメント'],
                $periodData,
            );
            $entry = AssessmentStaff::firstOrNew([
                'period_id'  => $period->id,
                'student_id' => $student->id,
            ]);
            if (!$entry->exists || $forceUpdate) {
                $entry->fill(array_merge($staffEntryData, ['staff_id' => $staff->id]));
                $entry->save();
                $this->info($entry->wasRecentlyCreated ? '  → AssessmentStaff CREATED' : '  → AssessmentStaff UPDATED');
            } else {
                $this->line('  → 既存の AssessmentStaff を保持 (--force-update で上書き可)');
            }
        } else {
            $this->line('  (dry-run) AssessmentPeriod + AssessmentStaff を作成予定');
            $this->line('    student_wish chars: ' . mb_strlen($staffEntryData['student_wish']));
            $this->line('    health_life chars:  ' . mb_strlen($staffEntryData['health_life']));
        }

        // ----------------------------------------------------------------
        // 5) 連絡帳: DailyRecord + StudentRecord を期間内の通所曜日に作成
        // ----------------------------------------------------------------
        $this->newLine();
        $this->info('--- 連絡帳 (' . $from->toDateString() . ' 〜 ' . $to->toDateString() . ') ---');

        $activities = $this->activitiesByWeekday();
        $domainTemplates = $this->domainTemplates();
        $strengthBase = $this->strengthBaseline();

        $createdDR = 0; $createdSR = 0; $updatedSR = 0; $skippedSR = 0;

        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dow = $cursor->dayOfWeek; // 0=Sun ... 6=Sat
            if (!in_array($dow, $scheduledDays, true)) {
                $cursor->addDay();
                continue;
            }

            // Carbon の diffInDays は符号が向きで変わる場合があるので、
            // $from -> $cursor の絶対日数を使う。abs() で念のため非負化。
            $weekIndex = (int) floor(abs($from->diffInDays($cursor)) / 7);
            $month = (int) $cursor->month;
            $activityList = $activities[$dow] ?? $activities[1];
            $activity = $activityList[$weekIndex % count($activityList)];

            $drData = [
                'classroom_id'   => $student->classroom_id,
                'record_date'    => $cursor->toDateString(),
                'activity_name'  => $activity['name'],
                'common_activity'=> $activity['common'],
                'staff_id'       => $staff->id,
            ];

            $srContent = $this->buildStudentRecordContent($cursor, $month, $weekIndex, $domainTemplates, $strengthBase);

            if ($apply) {
                $dr = DailyRecord::firstOrCreate(
                    [
                        'classroom_id'  => $student->classroom_id,
                        'record_date'   => $cursor->toDateString(),
                        'activity_name' => $activity['name'],
                    ],
                    $drData,
                );
                if ($dr->wasRecentlyCreated) $createdDR++;

                $sr = StudentRecord::firstOrNew([
                    'daily_record_id' => $dr->id,
                    'student_id'      => $student->id,
                ]);
                if (!$sr->exists) {
                    $sr->fill($srContent);
                    $sr->save();
                    $createdSR++;
                } elseif ($forceUpdate) {
                    $sr->fill($srContent);
                    $sr->save();
                    $updatedSR++;
                } else {
                    $skippedSR++;
                }
            } else {
                $createdDR++;
                $createdSR++;
            }

            $cursor->addDay();
        }

        $this->newLine();
        $this->info('=== 集計 ===');
        $this->line(sprintf('DailyRecord 作成: %d 件 (該当日数)', $createdDR));
        if ($apply) {
            $this->line(sprintf('StudentRecord 作成: %d 件 / 更新: %d 件 / スキップ: %d 件', $createdSR, $updatedSR, $skippedSR));
        } else {
            $this->line(sprintf('StudentRecord 作成見込み: %d 件 (dry-run)', $createdSR));
        }

        if (!$apply) {
            $this->warn('--apply で実行してください。');
        }

        return self::SUCCESS;
    }

    /**
     * 曜日別の活動候補。週ごとにローテーションする。
     * 0=日 ... 6=土
     */
    private function activitiesByWeekday(): array
    {
        return [
            1 => [ // 月
                ['name' => '外遊び',       'common' => '元気に外で遊びます'],
                ['name' => '公園散策',     'common' => '近隣の公園を散歩します'],
                ['name' => '集団ゲーム',   'common' => '体を動かす集団ゲームを楽しみます'],
                ['name' => 'ボール運動',   'common' => 'キャッチボールやドッジボール'],
            ],
            2 => [ // 火
                ['name' => '工作活動',     'common' => '紙工作や粘土で創造性を育みます'],
                ['name' => '絵画制作',     'common' => 'クレヨンや絵の具で自由制作'],
                ['name' => '折り紙',       'common' => '季節の折り紙に挑戦します'],
                ['name' => '創作工作',     'common' => '廃材を活用した創作活動'],
            ],
            3 => [ // 水
                ['name' => '学習タイム',   'common' => 'プリント学習や読み書きの練習'],
                ['name' => 'ボードゲーム', 'common' => 'ボードゲームで楽しみながらルールを学ぶ'],
                ['name' => 'パズル',       'common' => '空間認識と思考力を育てるパズル'],
                ['name' => 'カードゲーム', 'common' => 'カードゲームで戦略的思考を育てる'],
            ],
            4 => [ // 木
                ['name' => 'クッキング',   'common' => '簡単な調理体験で生活力を育みます'],
                ['name' => 'おやつ作り',   'common' => '一緒におやつ作りを楽しみます'],
                ['name' => '実験あそび',   'common' => '簡単な科学実験で好奇心を引き出します'],
                ['name' => '感触あそび',   'common' => '砂や小麦粉で感触を楽しみます'],
            ],
            5 => [ // 金
                ['name' => '音楽活動',     'common' => 'リズム遊びや楽器演奏'],
                ['name' => '読み聞かせ',   'common' => '絵本の読み聞かせと感想シェア'],
                ['name' => 'ダンス',       'common' => '簡単な振り付けで体を動かします'],
                ['name' => 'お話の時間',   'common' => '紙芝居や絵本を楽しみます'],
            ],
            6 => [ // 土
                ['name' => 'お出かけ',     'common' => '近隣施設に外出します'],
                ['name' => '休日活動',     'common' => '長時間の創作・スポーツ活動'],
            ],
            0 => [ // 日
                ['name' => '休日活動',     'common' => '休日のリラックスタイム'],
            ],
        ];
    }

    /**
     * 5領域それぞれの観察記録テンプレ。週ごとのバリエーション。
     */
    private function domainTemplates(): array
    {
        return [
            'health_life' => [
                "登所後すぐに自分でかばんを片付け、手洗いまでスムーズにできました。",
                "着替えの順序に少し迷いがありましたが、声かけ 1 回で最後まで自立できました。",
                "おやつの後の片付けを最後まで丁寧に行っていました。",
                "持ち物確認を自発的に行い、忘れ物なく帰る準備ができました。",
                "活動の合間に水分補給を自分で行う姿が見られました。",
            ],
            'motor_sensory' => [
                "ハサミで直線を切る課題に取り組み、線からはみ出さずに切れる回数が増えてきました。",
                "鉄棒の前回りを補助なしで成功させ、自信に満ちた表情を見せていました。",
                "音楽に合わせて両手両足を動かすリズム運動を 5分間続けられました。",
                "粘土を細長く伸ばすなど指先の細かい動きが上達しています。",
                "聴覚刺激が強い時間帯にイヤーマフを自分で取りに行く判断ができました。",
            ],
            'cognitive_behavior' => [
                "活動の手順表を見ながら最後まで取り組むことができました。途中で迷うこともありませんでした。",
                "1 時間の活動の中で 2 回ほど集中が途切れましたが、声かけで戻ってこられました。",
                "好きな課題には 20分以上集中し、難しい部分も諦めず取り組めました。",
                "新しい遊びのルールを 1 回の説明で理解し、実行に移すことができました。",
                "順序を考えながら課題を進める姿勢が見られ、計画力の成長を感じます。",
            ],
            'language_communication' => [
                "「やってみたい」「もっと」など自分の気持ちを言葉で伝える場面が複数ありました。",
                "友だちからの誘いに「いいよ」と短くも明確に返事ができていました。",
                "活動の感想を 2 文で伝えられ、語彙が少しずつ広がっています。",
                "困った時に「先生、助けて」と援助を求めることができました。",
                "感情カードを参照しながら今日の気持ちを「嬉しい」と表現できました。",
            ],
            'social_relations' => [
                "仲の良い友だちと協力して 1 つの作品を完成させることができました。",
                "順番待ちで少し焦れる場面はありましたが、タイマーを確認して落ち着けました。",
                "新しい職員にも自分から名前を伝え、関わりの広がりが見られます。",
                "他児が困っている時に「大丈夫?」と声をかける優しい場面がありました。",
                "グループ活動で自分の役割 (材料配り) を最後まで果たしました。",
            ],
        ];
    }

    /**
     * 強み (才能) チェックのベースラインスコア。
     */
    private function strengthBaseline(): array
    {
        return [
            '集中力'                  => 5,
            '持続力'                  => 4,
            '丁寧さ'                  => 6,
            '発想力'                  => 7,
            '観察力'                  => 6,
            '思いやり'                => 7,
            '情報処理の速さ'          => 4,
            '手先の器用さ'            => 4,
            '自分で選ぶ力'            => 5,
            'コミュニケーションの工夫' => 5,
        ];
    }

    /**
     * 1 日分の student_record コンテンツを組み立てる。
     */
    private function buildStudentRecordContent(Carbon $date, int $month, int $weekIndex, array $templates, array $strengthBase): array
    {
        $idx = $weekIndex % count($templates['health_life']);
        $monthSuffix = $this->monthlySuffix($month);

        $strengths = [];
        foreach ($strengthBase as $k => $base) {
            // 月の経過で 0〜2 ポイント上昇するベース成長 + 日ごとの揺らぎ
            $monthOffset = max(0, min(2, intval($month / 3))); // 月数で 0..2
            $jitter = (($weekIndex + crc32($k)) % 3) - 1;       // -1, 0, +1
            $score = max(0, min(10, $base + $monthOffset + $jitter));
            $strengths[$k] = $score;
        }

        return [
            'health_life'            => $templates['health_life'][$idx] . $monthSuffix['health'],
            'motor_sensory'          => $templates['motor_sensory'][$idx] . $monthSuffix['motor'],
            'cognitive_behavior'     => $templates['cognitive_behavior'][$idx] . $monthSuffix['cog'],
            'language_communication' => $templates['language_communication'][$idx] . $monthSuffix['lang'],
            'social_relations'       => $templates['social_relations'][$idx] . $monthSuffix['social'],
            'notes'                  => sprintf('(%s) %s', $date->format('Y/m/d'), '体調・気分とも安定。家庭での出来事 (家族と外出した話など) を共有してくれました。'),
            'strengths'              => $strengths,
            'service_type_data'      => null,
        ];
    }

    /**
     * 季節感を持たせるための後置テキスト。
     */
    private function monthlySuffix(int $month): array
    {
        if (in_array($month, [11, 12])) {
            return [
                'health'  => "寒さで体調を崩しがちな時期ですが、上着の着脱を自分で行えていました。",
                'motor'   => "寒い日でも外遊びを楽しみ、体を温める運動を選んで取り組めました。",
                'cog'     => "年末のイベント (クリスマス会) に向けた準備に楽しんで参加できました。",
                'lang'    => "「もうすぐクリスマスだね」など季節の話題で会話を広げられました。",
                'social'  => "年末のお別れ会で友だち同士の関わりが深まる場面がありました。",
            ];
        }
        if (in_array($month, [1, 2])) {
            return [
                'health'  => "新年の生活リズムを意識し、登所時間が安定してきました。",
                'motor'   => "寒さに負けず、室内でのストレッチ運動にも前向きに取り組めました。",
                'cog'     => "節分の鬼の工作など、季節行事への興味から集中力が高まりました。",
                'lang'    => "節分や立春など、季節の言葉に興味を持ち質問する姿が増えました。",
                'social'  => "豆まき行事で役割を引き受け、友だちと協力する姿が見られました。",
            ];
        }
        if (in_array($month, [3, 4])) {
            return [
                'health'  => "春の陽気で薄着になる練習をし、衣替えにも対応できました。",
                'motor'   => "外遊びの時間が伸び、運動量が増えて持久力が育っています。",
                'cog'     => "進級・進学のテーマで「次は何年生?」など先を見通す発言がありました。",
                'lang'    => "卒業・進級のテーマで友だちへの手紙を書く活動に取り組めました。",
                'social'  => "新しい友だちを迎える準備に、自分から名前を伝える練習をしていました。",
            ];
        }
        return [
            'health'  => "気候変動に対応し、活動量に応じて衣服を調整できていました。",
            'motor'   => "汗をかいた後の着替えを自分で行い、清潔を保つ意識が育っています。",
            'cog'     => "新しい活動に対しても物怖じせず、最後まで取り組めました。",
            'lang'    => "活動の感想を複数の語彙で伝えようとする姿勢が見られました。",
            'social'  => "友だちと気持ちを共有する場面が増え、関わりの幅が広がっています。",
        ];
    }
}
