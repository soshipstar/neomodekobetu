<?php
/**
 * 学年表示のデバッグページ
 * ブラウザで確認できる
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_helper.php';

// ログインチェック
requireLogin();
checkUserType(['admin', 'staff']);

$pdo = getDbConnection();

// 諏訪芳穏とルパート祥を含む全生徒を取得
$stmt = $pdo->query("
    SELECT
        id,
        student_name,
        birth_date,
        grade_level,
        grade_adjustment
    FROM students
    ORDER BY student_name
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 現在の日付情報
$now = new DateTime();
$currentYear = (int)$now->format('Y');
$currentMonth = (int)$now->format('n');
$fiscalYear = ($currentMonth >= 4) ? $currentYear : $currentYear - 1;

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学年表示デバッグ</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .highlight {
            background: #fff3cd !important;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
            font-weight: bold;
        }
        .badge-elementary {
            background: #28a745;
        }
        .badge-junior {
            background: #007bff;
        }
        .badge-high {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 学年表示デバッグページ</h1>

        <div class="info">
            <strong>現在の日付:</strong> <?php echo $now->format('Y年m月d日'); ?><br>
            <strong>現在の年度:</strong> <?php echo $fiscalYear; ?>年度
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>生徒名</th>
                    <th>生年月日</th>
                    <th>現在の<br>年齢</th>
                    <th>誕生<br>年度</th>
                    <th>年度差</th>
                    <th>学年<br>調整</th>
                    <th>調整後<br>年度差</th>
                    <th>計算された<br>学年レベル</th>
                    <th>DB保存の<br>grade_level</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <?php
                    // 生年月日から詳細を計算
                    $birth = new DateTime($student['birth_date']);
                    $birthYear = (int)$birth->format('Y');
                    $birthMonth = (int)$birth->format('n');
                    $birthDay = (int)$birth->format('j');

                    // 誕生年度を計算
                    if ($birthMonth < 4 || ($birthMonth == 4 && $birthDay == 1)) {
                        $birthFiscalYear = $birthYear - 1;
                    } else {
                        $birthFiscalYear = $birthYear;
                    }

                    // 年度差を計算
                    $gradeYear = $fiscalYear - $birthFiscalYear;

                    // 調整を適用
                    $gradeAdjustment = $student['grade_adjustment'] ?? 0;
                    $adjustedGradeYear = $gradeYear + $gradeAdjustment;

                    // 学年レベルを計算
                    $calculatedGrade = calculateGradeLevel($student['birth_date'], null, $gradeAdjustment);

                    // 現在の年齢を計算
                    $age = calculateAge($student['birth_date']);

                    // ラベルとバッジクラス
                    $gradeLabels = [
                        'elementary' => '小学部',
                        'junior_high' => '中学部',
                        'high_school' => '高等部'
                    ];
                    $badgeClasses = [
                        'elementary' => 'badge-elementary',
                        'junior_high' => 'badge-junior',
                        'high_school' => 'badge-high'
                    ];

                    $gradeLabel = $gradeLabels[$calculatedGrade] ?? '不明';
                    $badgeClass = $badgeClasses[$calculatedGrade] ?? '';

                    // 諏訪芳穏とルパート祥をハイライト
                    $rowClass = (strpos($student['student_name'], '諏訪') !== false || strpos($student['student_name'], 'ルパート') !== false) ? 'highlight' : '';
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td><?php echo $student['id']; ?></td>
                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                        <td><?php echo $student['birth_date'] ? date('Y/m/d', strtotime($student['birth_date'])) : '-'; ?></td>
                        <td><?php echo $age; ?>歳</td>
                        <td><?php echo $birthFiscalYear; ?>年度</td>
                        <td><?php echo $gradeYear; ?></td>
                        <td><?php echo $gradeAdjustment; ?></td>
                        <td><?php echo $adjustedGradeYear; ?></td>
                        <td>
                            <span class="badge <?php echo $badgeClass; ?>">
                                <?php echo $gradeLabel; ?>
                            </span>
                        </td>
                        <td><?php echo getGradeLevelLabel($student['grade_level']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h3>📝 学年判定の仕組み</h3>
            <p style="margin-bottom: 10px;"><strong>日本の学年制度（4月1日始まり）に基づいて計算しています：</strong></p>
            <ul style="margin-bottom: 15px;">
                <li><strong>誕生年度：</strong> 4月2日～翌年4月1日生まれが同じ年度</li>
                <li><strong>年度差：</strong> 現在の年度 - 誕生年度</li>
                <li><strong>学年判定：</strong>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        <li>小学部: 年度差 7～12（小1～小6）</li>
                        <li>中学部: 年度差 13～15（中1～中3）</li>
                        <li>高等部: 年度差 16～18（高1～高3）</li>
                    </ul>
                </li>
                <li><strong>学年調整：</strong> grade_adjustmentで±2学年まで調整可能</li>
            </ul>
            <p style="background: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107;">
                <strong>例：</strong> 2010年12月29日生まれの場合<br>
                → 誕生年度: 2010年度<br>
                → 2025年度では年度差: 2025 - 2010 = 15<br>
                → 判定: 中学3年生（中学部）<br>
                → 学年調整 -1 を設定すると: 年度差14 → 中学2年生（中学部）
            </p>
        </div>
    </div>
</body>
</html>
