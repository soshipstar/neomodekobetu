<?php
/**
 * 個別支援計画書 かけはし分析処理
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kakehashi_auto_generator.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /minimum/staff/kobetsu_plan.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

$studentId = $_POST['student_id'] ?? null;
$periodId = $_POST['period_id'] ?? null;

if (!$studentId || !$periodId) {
    $_SESSION['error'] = '生徒またはかけはし期間が選択されていません。';
    header("Location: /minimum/staff/kobetsu_plan.php?student_id=$studentId");
    exit;
}

try {
    error_log('=== 個別支援計画書 かけはし分析開始 ===');
    error_log('生徒ID: ' . $studentId);
    error_log('期間ID: ' . $periodId);

    // 生徒情報を取得
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception('生徒が見つかりません。');
    }

    error_log('生徒名: ' . $student['student_name']);

    // かけはし期間情報を取得（提出期限を取得するため）
    $stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ?");
    $stmt->execute([$periodId]);
    $period = $stmt->fetch();

    if (!$period) {
        throw new Exception('かけはし期間が見つかりません。');
    }

    // かけはし期間番号を取得
    $periodNumber = getKakehashiPeriodNumber($pdo, $studentId, $periodId);
    error_log('かけはし期間番号: ' . $periodNumber);

    // 個別支援計画書の提出期限を計算（ルールに基づく）
    // - 初回: かけはしの提出期限と同じ
    // - 2回目以降: かけはしの提出期限の1ヶ月後
    $kakehashiDeadline = new DateTime($period['submission_deadline']);
    $supportPlanDeadline = calculateSupportPlanDeadline($kakehashiDeadline, $periodNumber);

    // 作成日 = 個別支援計画書の提出期限（期限日に作成するため）
    $createdDate = clone $supportPlanDeadline;

    // 長期目標達成時期 = 作成日から1年後
    $longTermGoalDate = clone $createdDate;
    $longTermGoalDate->modify('+1 year');

    // 短期目標達成時期 = 作成日から6ヶ月後
    $shortTermGoalDate = clone $createdDate;
    $shortTermGoalDate->modify('+6 months');

    // 各領域の達成時期 = 作成日から6ヶ月後
    $achievementDate = clone $createdDate;
    $achievementDate->modify('+6 months');

    // 同意日 = 作成日と同一日
    $consentDate = clone $createdDate;

    error_log('かけはし提出期限: ' . $kakehashiDeadline->format('Y-m-d'));
    error_log('個別支援計画書提出期限/作成日: ' . $supportPlanDeadline->format('Y-m-d'));

    error_log('作成日: ' . $createdDate->format('Y-m-d'));
    error_log('長期目標達成時期: ' . $longTermGoalDate->format('Y-m-d'));
    error_log('短期目標達成時期: ' . $shortTermGoalDate->format('Y-m-d'));

    // 保護者のかけはしデータを取得
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_guardian
        WHERE student_id = ? AND period_id = ? AND is_submitted = 1
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $periodId]);
    $guardianKakehashi = $stmt->fetch();
    error_log('保護者かけはし: ' . ($guardianKakehashi ? '取得成功' : '未提出'));

    // スタッフのかけはしデータを取得
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_staff
        WHERE student_id = ? AND period_id = ? AND is_submitted = 1
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $periodId]);
    $staffKakehashi = $stmt->fetch();
    error_log('スタッフかけはし: ' . ($staffKakehashi ? '取得成功' : '未提出'));


    // 本人の願い、目標設定（短期目標、長期目標）の入力チェック
    $guardianHasRequiredFields = $guardianKakehashi &&
        !empty(trim($guardianKakehashi['student_wish'] ?? '')) &&
        !empty(trim($guardianKakehashi['short_term_goal'] ?? '')) &&
        !empty(trim($guardianKakehashi['long_term_goal'] ?? ''));

    $staffHasRequiredFields = $staffKakehashi &&
        !empty(trim($staffKakehashi['student_wish'] ?? '')) &&
        !empty(trim($staffKakehashi['short_term_goal'] ?? '')) &&
        !empty(trim($staffKakehashi['long_term_goal'] ?? ''));

    if (!$guardianHasRequiredFields && !$staffHasRequiredFields) {
        $_SESSION['error'] = '本人の願い、目標設定（短期目標、長期目標）が未入力だと個別支援計画書は作成できません。保護者かけはしまたはスタッフかけはしに必要項目を入力してください。';
        header("Location: /minimum/staff/kobetsu_plan.php?student_id=$studentId");
        exit;
    }
    // 直近のモニタリング表を取得
    $stmt = $pdo->prepare("
        SELECT mr.*, GROUP_CONCAT(
            CONCAT(
                COALESCE(ispd.category, ''), '|',
                COALESCE(ispd.sub_category, ''), '|',
                COALESCE(md.achievement_status, ''), '|',
                COALESCE(md.monitoring_comment, '')
            ) SEPARATOR '###'
        ) as monitoring_items
        FROM monitoring_records mr
        LEFT JOIN monitoring_details md ON mr.id = md.monitoring_id
        LEFT JOIN individual_support_plan_details ispd ON md.plan_detail_id = ispd.id
        WHERE mr.student_id = ?
        GROUP BY mr.id
        ORDER BY mr.monitoring_date DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $latestMonitoring = $stmt->fetch();

    // モニタリングデータを解析
    $monitoringData = [];
    if ($latestMonitoring && $latestMonitoring['monitoring_items']) {
        $items = explode('###', $latestMonitoring['monitoring_items']);
        foreach ($items as $item) {
            $parts = explode('|', $item);
            if (count($parts) >= 4) {
                $key = $parts[0] . '_' . $parts[1];
                $monitoringData[$key] = [
                    'status' => $parts[2],
                    'comment' => $parts[3]
                ];
            }
        }
    }

    // プロンプトの構築
    $prompt = "以下のかけはしデータとモニタリング情報をもとに、個別支援計画書を作成してください。\n\n";
    $prompt .= "【重要な指示】\n";
    $prompt .= "- 【最重要】個別支援計画の短期目標・長期目標は、保護者かけはしとスタッフかけはしの短期目標・長期目標の文言を最大限考慮し、それらとの整合性・連続性を保ちながら作成してください\n";
    $prompt .= "- かけはしで設定された目標を土台として、施設での具体的な支援場面に落とし込んだ目標を記述してください\n";
    $prompt .= "- 各項目について、具体的かつ詳細に記述してください\n";
    $prompt .= "- 支援目標は、観察可能で測定可能な行動として明確に記述してください\n";
    $prompt .= "- 支援内容は、具体的な支援方法、頻度、場面、使用する教材・環境設定などを詳しく記載してください\n";
    $prompt .= "- 各領域の支援内容は最低150文字以上、できれば200-300文字程度で詳しく記述してください\n";
    $prompt .= "- 留意事項には、配慮すべき点、成功のためのポイント、予想される困難と対処法などを記載してください\n\n";

    // 保護者かけはし
    if ($guardianKakehashi) {
        $prompt .= "【保護者からのかけはし】\n";
        $prompt .= "本人の願い: " . ($guardianKakehashi['student_wish'] ?? '') . "\n";
        $prompt .= "家庭での願い: " . ($guardianKakehashi['home_challenges'] ?? '') . "\n";
        $prompt .= "【重要】短期目標（保護者）: " . ($guardianKakehashi['short_term_goal'] ?? '') . "\n";
        $prompt .= "【重要】長期目標（保護者）: " . ($guardianKakehashi['long_term_goal'] ?? '') . "\n";
        $prompt .= "健康・生活: " . ($guardianKakehashi['domain_health_life'] ?? '') . "\n";
        $prompt .= "運動・感覚: " . ($guardianKakehashi['domain_motor_sensory'] ?? '') . "\n";
        $prompt .= "認知・行動: " . ($guardianKakehashi['domain_cognitive_behavior'] ?? '') . "\n";
        $prompt .= "言語・コミュニケーション: " . ($guardianKakehashi['domain_language_communication'] ?? '') . "\n";
        $prompt .= "人間関係・社会性: " . ($guardianKakehashi['domain_social_relations'] ?? '') . "\n";
        $prompt .= "その他: " . ($guardianKakehashi['other_challenges'] ?? '') . "\n\n";
    }

    // スタッフかけはし
    if ($staffKakehashi) {
        $prompt .= "【スタッフからのかけはし】\n";
        $prompt .= "本人の願い: " . ($staffKakehashi['student_wish'] ?? '') . "\n";
        $prompt .= "【重要】短期目標（スタッフ）: " . ($staffKakehashi['short_term_goal'] ?? '') . "\n";
        $prompt .= "【重要】長期目標（スタッフ）: " . ($staffKakehashi['long_term_goal'] ?? '') . "\n";
        $prompt .= "健康・生活: " . ($staffKakehashi['domain_health_life'] ?? '') . "\n";
        $prompt .= "運動・感覚: " . ($staffKakehashi['domain_motor_sensory'] ?? '') . "\n";
        $prompt .= "認知・行動: " . ($staffKakehashi['domain_cognitive_behavior'] ?? '') . "\n";
        $prompt .= "言語・コミュニケーション: " . ($staffKakehashi['domain_language_communication'] ?? '') . "\n";
        $prompt .= "人間関係・社会性: " . ($staffKakehashi['domain_social_relations'] ?? '') . "\n";
        $prompt .= "その他: " . ($staffKakehashi['other_challenges'] ?? '') . "\n\n";
    }

    // モニタリング情報
    if ($latestMonitoring) {
        $prompt .= "【直近のモニタリング情報】\n";
        $prompt .= "実施日: " . $latestMonitoring['monitoring_date'] . "\n";
        $prompt .= "総合所見: " . ($latestMonitoring['overall_comment'] ?? '') . "\n";
        foreach ($monitoringData as $key => $data) {
            $prompt .= "$key: 達成状況={$data['status']}, コメント={$data['comment']}\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "以下のJSON形式で出力してください：\n";
    $prompt .= "{\n";
    $prompt .= '  "life_intention": "利用児及び家族の生活に対する意向（保護者と本人の願いを踏まえた詳細な記述。100-200文字程度）",'."\n";
    $prompt .= '  "overall_policy": "総合的な支援の方針（本人・家族の意向を受けて、どのような方針で支援するか。強み・課題・環境を踏まえた総合的な方針を150-250文字程度で記述）",'."\n";
    $prompt .= '  "long_term_goal_text": "【最重要】長期目標の内容（上記の保護者かけはしとスタッフかけはしの長期目標の文言を最大限考慮し、それらの目標と整合性を保ちながら、施設での支援を通じて到達してほしい具体的な姿を記述してください。観察可能な行動として100-150文字程度で記述。施設内での活動・場面を想定した目標にすること。「1年後には」「○ヶ月後には」などの期間を含めた表現は使用しないこと）",'."\n";
    $prompt .= '  "short_term_goal_text": "【最重要】短期目標の内容（上記の保護者かけはしとスタッフかけはしの短期目標の文言を最大限考慮し、それらの目標と整合性を保ちながら、施設での支援を通じて到達してほしい具体的な姿を記述してください。観察可能な行動として100-150文字程度で記述。施設内での活動・場面を想定した目標にすること。「半年後には」「○ヶ月後には」などの期間を含めた表現は使用しないこと）",'."\n";
    $prompt .= '  "details": ['."\n";
    $prompt .= '    {'."\n";
    $prompt .= '      "category": "本人支援",'."\n";
    $prompt .= '      "sub_category": "生活習慣（健康・生活）",'."\n";
    $prompt .= '      "support_goal": "施設での支援を通じて到達する具体的な目標。どのような場面・状況で、どのような行動ができるようになるかを明確に記述。100-120文字程度で詳しく。例：「朝の活動開始時に、視覚支援ツール（手順表）を見ながら、ロッカーへの荷物の片付け、手洗い・うがい、出席カードへのシール貼りの一連の流れを、スタッフの声かけなしで自分で行うことができる」",'."\n";
    $prompt .= '      "support_content": "支援内容（施設内で実施する具体的な支援方法、頻度、場面、使用する教材・環境設定、段階的な支援の流れなど。200-300文字程度で詳細に記述。例：「毎日の活動開始時に、視覚支援ツール（絵カード）を使用して手洗いの手順を確認します。①蛇口をひねる ②石鹸をつける ③20秒間こする ④洗い流す ⑤タオルで拭く の5段階を、最初はスタッフが手を添えて一緒に行い、徐々に声かけのみ、最終的には自立を目指します。成功体験を積み重ねるため、できた時には具体的に褒め、シールなどで視覚的に成果を示します。」）",'."\n";
    $prompt .= '      "staff_organization": "保育士\\n児童指導員",'."\n";
    $prompt .= '      "notes": "留意事項（配慮すべき点、成功のポイント、予想される困難と対処法など。80-150文字程度）"'."\n";
    $prompt .= '    },'."\n";
    $prompt .= '    {'."\n";
    $prompt .= '      "category": "本人支援",'."\n";
    $prompt .= '      "sub_category": "コミュニケーション（言語・コミュニケーション）",'."\n";
    $prompt .= '      "support_goal": "施設での支援を通じて到達する具体的な目標。どのような場面・状況で、どのような言葉・コミュニケーションができるようになるかを明確に記述。100-120文字程度で詳しく",'."\n";
    $prompt .= '      "support_content": "支援内容（施設内で実施する具体的な支援。200-300文字程度で詳細に）",'."\n";
    $prompt .= '      "staff_organization": "保育士\\n児童指導員",'."\n";
    $prompt .= '      "notes": "留意事項"'."\n";
    $prompt .= '    },'."\n";
    $prompt .= '    {'."\n";
    $prompt .= '      "category": "本人支援",'."\n";
    $prompt .= '      "sub_category": "社会性（人間関係・社会性）",'."\n";
    $prompt .= '      "support_goal": "施設での支援を通じて到達する具体的な目標。どのような場面・状況で、どのような友達との関わり・社会的行動ができるようになるかを明確に記述。100-120文字程度で詳しく",'."\n";
    $prompt .= '      "support_content": "支援内容（施設内で実施する具体的な支援。200-300文字程度で詳細に）",'."\n";
    $prompt .= '      "staff_organization": "保育士\\n児童指導員",'."\n";
    $prompt .= '      "notes": "留意事項"'."\n";
    $prompt .= '    },'."\n";
    $prompt .= '    {'."\n";
    $prompt .= '      "category": "本人支援",'."\n";
    $prompt .= '      "sub_category": "運動・感覚（運動・感覚）",'."\n";
    $prompt .= '      "support_goal": "施設での支援を通じて到達する具体的な目標。どのような場面・状況で、どのような運動・感覚活動ができるようになるかを明確に記述。100-120文字程度で詳しく",'."\n";
    $prompt .= '      "support_content": "支援内容（施設内で実施する具体的な支援。200-300文字程度で詳細に）",'."\n";
    $prompt .= '      "staff_organization": "保育士\\n児童指導員",'."\n";
    $prompt .= '      "notes": "留意事項"'."\n";
    $prompt .= '    },'."\n";
    $prompt .= '    {'."\n";
    $prompt .= '      "category": "本人支援",'."\n";
    $prompt .= '      "sub_category": "学習（認知・行動）",'."\n";
    $prompt .= '      "support_goal": "施設での支援を通じて到達する具体的な目標。どのような場面・状況で、どのような学習・認知課題ができるようになるかを明確に記述。100-120文字程度で詳しく",'."\n";
    $prompt .= '      "support_content": "支援内容（施設内で実施する具体的な支援。200-300文字程度で詳細に）",'."\n";
    $prompt .= '      "staff_organization": "保育士\\n児童指導員",'."\n";
    $prompt .= '      "notes": "留意事項"'."\n";
    $prompt .= '    },'."\n";
    $prompt .= '    {'."\n";
    $prompt .= '      "category": "家族支援",'."\n";
    $prompt .= '      "sub_category": "保護者支援",'."\n";
    $prompt .= '      "support_goal": "具体的な到達目標（保護者への支援に関する目標）",'."\n";
    $prompt .= '      "support_content": "支援内容（家庭での関わり方のアドバイス、情報提供、相談支援など。200-300文字程度で詳細に）",'."\n";
    $prompt .= '      "staff_organization": "児童発達支援管理責任者\\n保育士",'."\n";
    $prompt .= '      "notes": "留意事項"'."\n";
    $prompt .= '    },'."\n";
    $prompt .= '    {'."\n";
    $prompt .= '      "category": "地域支援",'."\n";
    $prompt .= '      "sub_category": "関係機関連携",'."\n";
    $prompt .= '      "support_goal": "具体的な到達目標（連携に関する目標）",'."\n";
    $prompt .= '      "support_content": "支援内容（保育園・学校・医療機関などとの連携内容。情報共有の方法や頻度など。200-300文字程度で詳細に）",'."\n";
    $prompt .= '      "staff_organization": "児童発達支援管理責任者",'."\n";
    $prompt .= '      "notes": "留意事項"'."\n";
    $prompt .= '    }'."\n";
    $prompt .= '  ]'."\n";
    $prompt .= "}\n\n";
    $prompt .= "【注意事項】\n";
    $prompt .= "- 必ず有効なJSON形式で出力してください\n";
    $prompt .= "- 【最重要】個別支援計画の長期目標・短期目標は、保護者かけはしとスタッフかけはしで既に設定された長期目標・短期目標の内容を最大限尊重し、それらの表現や意図を引き継ぎながら、施設での具体的な支援場面に適した形で記述してください\n";
    $prompt .= "- かけはしの目標で使われているキーワードや表現をできるだけ活かしてください\n";
    $prompt .= "- 【重要】五領域（健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性）については、必ず施設内で実施できる支援内容を記述してください。家庭での取り組みは含めないでください\n";
    $prompt .= "- 支援内容は具体的な手順、頻度、使用する道具・環境、段階的なアプローチを含めてください\n";
    $prompt .= "- 抽象的な表現ではなく、実際に現場で実践できる具体的な内容を記述してください\n";
    $prompt .= "- 【重要】長期目標・短期目標・支援目標には「1年後には」「半年後には」「○ヶ月後」「いつまでに」などの期間を含めた表現は絶対に使用しないでください。期間は別途入力するため、目標文には到達してほしい具体的な行動のみを記述してください\n";

    // OpenAI GPT-4に送信
    $apiKey = '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';

    $data = [
        'model' => 'gpt-5.2',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'あなたは児童発達支援の専門家です。個別支援計画書を作成する際は、具体的で実践可能な支援内容を詳細に記述してください。抽象的な表現は避け、現場のスタッフが実際に使用できる具体的な手順、頻度、環境設定、段階的なアプローチを含めてください。'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.8,
        'max_completion_tokens' => 4000
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // デバッグ用：API応答をログに記録
    error_log('OpenAI APIレスポンスコード: ' . $httpCode);
    error_log('OpenAI APIレスポンス: ' . substr($response, 0, 500)); // 最初の500文字のみ

    if ($curlError) {
        throw new Exception('CURLエラー: ' . $curlError);
    }

    if ($httpCode !== 200) {
        throw new Exception('API呼び出しに失敗しました（HTTPコード: ' . $httpCode . '）: ' . $response);
    }

    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSONデコードエラー: ' . json_last_error_msg());
    }

    if (!isset($result['choices'][0]['message']['content'])) {
        error_log('APIレスポンス全体: ' . print_r($result, true));
        throw new Exception('APIレスポンスが不正です。レスポンス構造を確認してください。');
    }

    $generatedText = $result['choices'][0]['message']['content'];
    error_log('生成されたテキスト（最初の500文字）: ' . substr($generatedText, 0, 500));

    // JSON部分を抽出
    error_log('JSON抽出を試行中...');
    if (preg_match('/\{[\s\S]*\}/', $generatedText, $matches)) {
        $jsonText = $matches[0];
        error_log('JSON抽出成功。パース中...');

        $generatedData = json_decode($jsonText, true);

        if (!$generatedData) {
            error_log('JSONパースエラー: ' . json_last_error_msg());
            error_log('抽出されたJSON: ' . substr($jsonText, 0, 500));
            throw new Exception('生成されたJSONのパースに失敗しました: ' . json_last_error_msg());
        }

        error_log('JSONパース成功。項目数: ' . count($generatedData));
        error_log('details配列の要素数: ' . (isset($generatedData['details']) ? count($generatedData['details']) : 0));

        // デフォルト日付を追加
        $generatedData['created_date'] = $createdDate->format('Y-m-d');
        $generatedData['long_term_goal_date'] = $longTermGoalDate->format('Y-m-d');
        $generatedData['short_term_goal_date'] = $shortTermGoalDate->format('Y-m-d');
        $generatedData['consent_date'] = $consentDate->format('Y-m-d');

        // 各領域の達成時期にもデフォルト値を設定
        if (isset($generatedData['details']) && is_array($generatedData['details'])) {
            foreach ($generatedData['details'] as &$detail) {
                if (!isset($detail['achievement_date']) || empty($detail['achievement_date'])) {
                    $detail['achievement_date'] = $achievementDate->format('Y-m-d');
                }
            }
            unset($detail); // 参照を解除
        }

        // セッションに保存
        $_SESSION['generated_plan'] = $generatedData;
        $_SESSION['success'] = 'かけはしデータの分析が完了しました。個別支援計画書案が生成されました。内容を確認・編集して保存してください。';

        error_log('セッションに保存完了');

    } else {
        error_log('JSON抽出失敗。生成されたテキスト: ' . substr($generatedText, 0, 500));
        throw new Exception('生成されたテキストからJSONを抽出できませんでした。生成されたテキストの形式を確認してください。');
    }

} catch (Exception $e) {
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();

    // デバッグ用：詳細なエラー情報をログに記録
    error_log('個別支援計画書生成エラー: ' . $e->getMessage());
    error_log('スタックトレース: ' . $e->getTraceAsString());
}

// リダイレクト先を確認
$redirectUrl = "Location: /minimum/staff/kobetsu_plan.php?student_id=$studentId";
error_log('リダイレクト先: ' . $redirectUrl);

header($redirectUrl);
exit;
