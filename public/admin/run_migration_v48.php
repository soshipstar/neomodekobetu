<?php
/**
 * Migration v48: 事業所評価シートシステム用テーブル作成
 * Created: 2026-02-11
 */

require_once __DIR__ . '/../../config/database.php';

$pdo = getDbConnection();

$migrations = [
    // 評価期間（年度）テーブル
    "CREATE TABLE IF NOT EXISTS facility_evaluation_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fiscal_year INT NOT NULL COMMENT '年度（例：2024）',
        title VARCHAR(100) NOT NULL COMMENT '評価期間タイトル（例：2024年度事業所評価）',
        status ENUM('draft', 'collecting', 'aggregating', 'published') DEFAULT 'draft' COMMENT '状態',
        guardian_deadline DATE DEFAULT NULL COMMENT '保護者回答期限',
        staff_deadline DATE DEFAULT NULL COMMENT 'スタッフ回答期限',
        created_by INT DEFAULT NULL COMMENT '作成者',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_fiscal_year (fiscal_year),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 評価質問マスタ
    "CREATE TABLE IF NOT EXISTS facility_evaluation_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_type ENUM('guardian', 'staff') NOT NULL COMMENT '質問タイプ',
        category VARCHAR(100) NOT NULL COMMENT 'カテゴリ',
        question_number INT NOT NULL COMMENT '質問番号',
        question_text TEXT NOT NULL COMMENT '質問文',
        sort_order INT DEFAULT 0 COMMENT '表示順',
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_question (question_type, question_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 保護者評価回答
    "CREATE TABLE IF NOT EXISTS facility_guardian_evaluations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL COMMENT '評価期間ID',
        guardian_id INT NOT NULL COMMENT '保護者ID',
        student_id INT DEFAULT NULL COMMENT '生徒ID（任意）',
        is_submitted TINYINT(1) DEFAULT 0 COMMENT '提出済みフラグ',
        submitted_at DATETIME DEFAULT NULL COMMENT '提出日時',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (period_id) REFERENCES facility_evaluation_periods(id) ON DELETE CASCADE,
        FOREIGN KEY (guardian_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
        UNIQUE KEY unique_guardian_period (period_id, guardian_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 保護者評価回答詳細
    "CREATE TABLE IF NOT EXISTS facility_guardian_evaluation_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evaluation_id INT NOT NULL COMMENT '評価ID',
        question_id INT NOT NULL COMMENT '質問ID',
        answer ENUM('yes', 'neutral', 'no', 'unknown') DEFAULT NULL COMMENT '回答',
        comment TEXT DEFAULT NULL COMMENT 'ご意見',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (evaluation_id) REFERENCES facility_guardian_evaluations(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES facility_evaluation_questions(id) ON DELETE CASCADE,
        UNIQUE KEY unique_answer (evaluation_id, question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // スタッフ自己評価回答
    "CREATE TABLE IF NOT EXISTS facility_staff_evaluations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL COMMENT '評価期間ID',
        staff_id INT NOT NULL COMMENT 'スタッフID',
        is_submitted TINYINT(1) DEFAULT 0 COMMENT '提出済みフラグ',
        submitted_at DATETIME DEFAULT NULL COMMENT '提出日時',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (period_id) REFERENCES facility_evaluation_periods(id) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_staff_period (period_id, staff_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // スタッフ自己評価回答詳細
    "CREATE TABLE IF NOT EXISTS facility_staff_evaluation_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evaluation_id INT NOT NULL COMMENT '評価ID',
        question_id INT NOT NULL COMMENT '質問ID',
        answer ENUM('yes', 'neutral', 'no', 'unknown') DEFAULT NULL COMMENT '回答',
        comment TEXT DEFAULT NULL COMMENT 'コメント',
        improvement_plan TEXT DEFAULT NULL COMMENT '改善計画',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (evaluation_id) REFERENCES facility_staff_evaluations(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES facility_evaluation_questions(id) ON DELETE CASCADE,
        UNIQUE KEY unique_answer (evaluation_id, question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 集計結果（公表用）
    "CREATE TABLE IF NOT EXISTS facility_evaluation_summaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL COMMENT '評価期間ID',
        question_id INT NOT NULL COMMENT '質問ID',
        yes_count INT DEFAULT 0 COMMENT 'はいの数',
        neutral_count INT DEFAULT 0 COMMENT 'どちらともいえないの数',
        no_count INT DEFAULT 0 COMMENT 'いいえの数',
        unknown_count INT DEFAULT 0 COMMENT 'わからないの数',
        yes_percentage DECIMAL(5,2) DEFAULT 0 COMMENT 'はいの割合',
        comment_summary TEXT DEFAULT NULL COMMENT 'コメント要約（AI生成）',
        facility_comment TEXT DEFAULT NULL COMMENT '事業所コメント',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (period_id) REFERENCES facility_evaluation_periods(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES facility_evaluation_questions(id) ON DELETE CASCADE,
        UNIQUE KEY unique_summary (period_id, question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 自己評価総括表
    "CREATE TABLE IF NOT EXISTS facility_self_evaluation_summary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL COMMENT '評価期間ID',
        category VARCHAR(100) NOT NULL COMMENT 'カテゴリ',
        current_status TEXT DEFAULT NULL COMMENT '現状の取組',
        issues TEXT DEFAULT NULL COMMENT '課題',
        improvement_plan TEXT DEFAULT NULL COMMENT '改善計画',
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (period_id) REFERENCES facility_evaluation_periods(id) ON DELETE CASCADE,
        UNIQUE KEY unique_category (period_id, category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$results = [];
foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        // テーブル名を抽出
        preg_match('/(?:CREATE TABLE IF NOT EXISTS|ALTER TABLE)\s+(\w+)/i', $sql, $matches);
        $tableName = $matches[1] ?? 'unknown';
        $results[] = "✓ 成功: {$tableName}";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            preg_match('/CREATE TABLE IF NOT EXISTS\s+(\w+)/i', $sql, $matches);
            $tableName = $matches[1] ?? 'unknown';
            $results[] = "- スキップ（既存）: {$tableName}";
        } else {
            $results[] = "✗ エラー: " . $e->getMessage();
        }
    }
}

// 質問マスタデータを挿入
echo "<h3>質問マスタデータを挿入中...</h3>";

// 保護者向け質問（hyouka.xlsxより）
$guardianQuestions = [
    ['環境・体制整備', 1, '子どもの活動等のスペースが十分に確保されているか'],
    ['環境・体制整備', 2, '職員の配置数や専門性は適切であるか'],
    ['環境・体制整備', 3, '生活空間は、本人にわかりやすい構造化された環境になっているか。また、障害の特性に応じ、事業所の設備等は、バリアフリー化や情報伝達等への配慮が適切になされているか'],
    ['環境・体制整備', 4, '生活空間は、清潔で、心地よく過ごせる環境になっているか。また、子ども達の活動に合わせた空間となっているか'],
    ['適切な支援の提供', 5, '子どもと保護者のニーズや課題が客観的に分析された上で、児童発達支援計画が作成されているか'],
    ['適切な支援の提供', 6, '児童発達支援計画には、児童発達支援ガイドラインの「児童発達支援の提供すべき支援」の「発達支援（本人支援及び移行支援）」、「家族支援」、「地域支援」で示す支援内容から子どもの支援に必要な項目が適切に選択され、その上で、具体的な支援内容が設定されているか'],
    ['適切な支援の提供', 7, '児童発達支援計画に沿った支援が行われているか'],
    ['適切な支援の提供', 8, '活動プログラムが固定化しないよう工夫されているか'],
    ['適切な支援の提供', 9, '保育所や認定こども園、幼稚園等との交流や、障害のない子どもと活動する機会があるか'],
    ['適切な支援の提供', 10, '運営規程、利用者負担等について丁寧な説明がなされたか'],
    ['適切な支援の提供', 11, '児童発達支援ガイドラインの「児童発達支援の提供すべき支援」のねらい及び支援内容と、これに基づき作成された「児童発達支援計画」を示しながら、支援内容の説明がなされたか'],
    ['適切な支援の提供', 12, '保護者に対して家族支援プログラム（ペアレント・トレーニング等）が行われているか'],
    ['保護者への説明責任・連携支援', 13, '日頃から子どもの状況を保護者と伝え合い、子どもの健康や発達の状況、課題について共通理解ができているか'],
    ['保護者への説明責任・連携支援', 14, '定期的に、保護者に対して面談や、育児に関する助言等の支援が行われているか'],
    ['保護者への説明責任・連携支援', 15, '父母の会の活動の支援や、保護者会等の開催等により保護者同士の連携が支援されているか'],
    ['保護者への説明責任・連携支援', 16, '子どもや保護者からの相談や申入れについて、対応の体制が整備されているとともに、子どもや保護者に周知・説明され、相談や申入れをした際に迅速かつ適切に対応されているか'],
    ['保護者への説明責任・連携支援', 17, '子どもや保護者との意思の疎通や情報伝達のための配慮がなされているか'],
    ['保護者への説明責任・連携支援', 18, '定期的に会報やホームページ等で、活動概要や行事予定、連絡体制等の情報や業務に関する自己評価の結果を子どもや保護者に対して発信されているか'],
    ['保護者への説明責任・連携支援', 19, '個人情報の取扱いに十分注意されているか'],
    ['非常時等の対応', 20, '緊急時対応マニュアル、防犯マニュアル、感染症対応マニュアル等を策定し、保護者に周知・説明されているか。また、発生を想定した訓練が実施されているか'],
    ['非常時等の対応', 21, '非常災害の発生に備え、定期的に避難、救出、その他必要な訓練が行われているか'],
    ['満足度', 22, '子どもは通所を楽しみにしているか'],
    ['満足度', 23, '事業所の支援に満足しているか'],
];

// スタッフ向け質問（hyouka.xlsxより）
$staffQuestions = [
    ['環境・体制整備', 1, '利用定員が指導訓練室等スペースとの関係で適切であるか'],
    ['環境・体制整備', 2, '職員の配置数は適切であるか'],
    ['環境・体制整備', 3, '生活空間は、本人にわかりやすく構造化された環境になっているか。また、障害の特性に応じ、事業所の設備等は、バリアフリー化や情報伝達等への配慮が適切になされているか'],
    ['環境・体制整備', 4, '生活空間は、清潔で、心地よく過ごせる環境になっているか。また、子ども達の活動に合わせた空間となっているか'],
    ['業務改善', 5, '業務改善を進めるためのPDCAサイクル（目標設定と振り返り）に、広く職員が参画しているか'],
    ['業務改善', 6, '保護者等向け評価表により、保護者等に対して事業所の評価を実施するとともに、保護者等の意向等を把握し、業務改善につなげているか'],
    ['業務改善', 7, '事業所向け自己評価表及び保護者向け評価表の結果を踏まえ、事業所として自己評価を行うとともに、その結果による支援の質の向上、業務改善をしているか'],
    ['業務改善', 8, '職員の資質の向上を行うために、研修の機会を確保しているか'],
    ['適切な支援の提供', 9, 'アセスメントを適切に行い、子どもと保護者のニーズや課題を客観的に分析した上で、児童発達支援計画を作成しているか'],
    ['適切な支援の提供', 10, '子どもの適応行動の状況を図るために、標準化されたアセスメントツールを使用しているか'],
    ['適切な支援の提供', 11, '活動プログラムの立案をチームで行っているか'],
    ['適切な支援の提供', 12, '活動プログラムが固定化しないよう工夫しているか'],
    ['適切な支援の提供', 13, '子どもの状況に応じて、個別活動と集団活動を適宜組み合わせて児童発達支援計画を作成しているか'],
    ['適切な支援の提供', 14, '支援開始前には職員間で必ず打合せをし、その日行われる支援の内容や役割分担について確認しているか'],
    ['適切な支援の提供', 15, '支援終了後には、職員間で必ず打合せをし、その日行われた支援の振り返りを行い、気付いた点等を共有しているか'],
    ['適切な支援の提供', 16, '日々の支援に関して記録を取ることを徹底し、支援の検証・改善につなげているか'],
    ['適切な支援の提供', 17, '定期的にモニタリングを行い、児童発達支援計画の見直しの必要性を判断しているか'],
    ['関係機関や保護者との連携', 18, '障害児相談支援事業所のサービス担当者会議にその子どもの状況に精通した最もふさわしい者が参画しているか'],
    ['関係機関や保護者との連携', 19, '保育所や認定こども園、幼稚園等との交流や、障害のない子どもと活動する機会があるか'],
    ['関係機関や保護者との連携', 20, '（自立支援）協議会子ども部会や地域の子ども・子育て会議等へ積極的に参加しているか'],
    ['関係機関や保護者との連携', 21, '日頃から子どもの状況を保護者と伝え合い、子どもの発達の状況や課題について共通理解をしているか'],
    ['関係機関や保護者との連携', 22, '保護者の対応力の向上を図る観点から、保護者に対して家族支援プログラム（ペアレント・トレーニング等）の支援を行っているか'],
    ['保護者への説明責任', 23, '運営規程、利用者負担等について丁寧な説明を行っているか'],
    ['保護者への説明責任', 24, '児童発達支援ガイドラインの「児童発達支援の提供すべき支援」のねらい及び支援内容と、これに基づき作成された「児童発達支援計画」を示しながら支援内容の説明を行い、保護者から児童発達支援計画の同意を得ているか'],
    ['保護者への説明責任', 25, '定期的に、保護者からの子育ての悩み等に対する相談に適切に応じ、必要な助言と支援を行っているか'],
    ['保護者への説明責任', 26, '父母の会の活動を支援したり、保護者会等を開催する等により、保護者同士の連携を支援しているか'],
    ['保護者への説明責任', 27, '子どもや保護者からの相談や申入れについて、対応の体制を整備するとともに、子どもや保護者に周知し、相談や申入れがあった場合に迅速かつ適切に対応しているか'],
    ['保護者への説明責任', 28, '定期的に会報等を発行し、活動概要や行事予定、連絡体制等の情報を子どもや保護者に対して発信しているか'],
    ['保護者への説明責任', 29, '個人情報の取扱いに十分注意しているか'],
    ['保護者への説明責任', 30, '障害のある子どもや保護者との意思の疎通や情報伝達のための配慮をしているか'],
    ['保護者への説明責任', 31, '事業所の行事に地域住民を招待する等地域に開かれた事業運営を図っているか'],
    ['非常時等の対応', 32, '緊急時対応マニュアル、防犯マニュアル、感染症対応マニュアル等を策定し、職員や保護者に周知しているか'],
    ['非常時等の対応', 33, '非常災害の発生に備え、定期的に避難、救出その他必要な訓練を行っているか'],
    ['非常時等の対応', 34, '事前に、服薬やストーマ等についてのこまやかな連絡体制を整備しているか'],
    ['非常時等の対応', 35, '虐待を防止するため、職員の研修機会を確保する等、適切な対応をしているか'],
    ['非常時等の対応', 36, 'どのような場合にやむを得ず身体拘束を行うかについて、組織的に決定し、子どもや保護者に事前に十分に説明し了解を得た上で、児童発達支援計画に記載しているか'],
    ['非常時等の対応', 37, '食物アレルギーのある子どもについて、医師の指示書に基づく対応がされているか'],
    ['非常時等の対応', 38, 'ヒヤリハット事例集を作成して事業所内で共有しているか'],
    ['非常時等の対応', 39, '虐待を発見した場合や子どもからの訴えがあった場合の対応が定められ、連絡体制が整えられているか'],
];

try {
    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO facility_evaluation_questions
        (question_type, category, question_number, question_text, sort_order)
        VALUES (?, ?, ?, ?, ?)
    ");

    $inserted = 0;
    foreach ($guardianQuestions as $i => $q) {
        $insertStmt->execute(['guardian', $q[0], $q[1], $q[2], $i + 1]);
        $inserted++;
    }
    foreach ($staffQuestions as $i => $q) {
        $insertStmt->execute(['staff', $q[0], $q[1], $q[2], $i + 1]);
        $inserted++;
    }
    $results[] = "✓ 質問マスタ: {$inserted}件挿入";
} catch (Exception $e) {
    $results[] = "✗ 質問マスタエラー: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Migration v48</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .success { color: green; }
        .skip { color: gray; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>Migration v48: 事業所評価シートシステム用テーブル作成</h2>
    <ul>
    <?php foreach ($results as $result): ?>
        <li class="<?php
            echo strpos($result, '✓') !== false ? 'success' :
                 (strpos($result, '-') === 0 ? 'skip' : 'error');
        ?>"><?php echo htmlspecialchars($result); ?></li>
    <?php endforeach; ?>
    </ul>
    <p><a href="../staff/">スタッフページへ</a></p>
</body>
</html>
