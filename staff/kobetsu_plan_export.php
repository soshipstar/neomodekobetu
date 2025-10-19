<?php
/**
 * 個別支援計画書CSV出力
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$planId = $_GET['plan_id'] ?? null;

if (!$planId) {
    $_SESSION['error'] = '計画IDが指定されていません。';
    header('Location: kobetsu_plan.php');
    exit;
}

$pdo = getDbConnection();

// 計画データを取得
$stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = '計画が見つかりません。';
    header('Location: kobetsu_plan.php');
    exit;
}

// 明細データを取得
$stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
$stmt->execute([$planId]);
$details = $stmt->fetchAll();

// CSV出力準備
$filename = '個別支援計画書_' . $plan['student_name'] . '_' . date('Ymd', strtotime($plan['created_date'])) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM付加（Excel対応）
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// メタ情報部分
fputcsv($output, ['種別', '項目名', '値']);
fputcsv($output, ['タイトル', '個別支援計画書', '']);
fputcsv($output, ['対象者名', $plan['student_name'], '']);
fputcsv($output, ['作成年月日', date('Y年m月d日', strtotime($plan['created_date'])), '']);
fputcsv($output, ['']);

fputcsv($output, ['利用児及び家族の生活に対する意向', $plan['life_intention'] ?? '', '']);
fputcsv($output, ['']);

fputcsv($output, ['総合的な支援の方針', $plan['overall_policy'] ?? '', '']);
fputcsv($output, ['']);

if ($plan['long_term_goal_date']) {
    fputcsv($output, ['長期目標', date('Y年m月d日', strtotime($plan['long_term_goal_date'])), '']);
    fputcsv($output, ['長期目標内容', $plan['long_term_goal_text'] ?? '', '']);
} else {
    fputcsv($output, ['長期目標', '', '']);
    fputcsv($output, ['長期目標内容', $plan['long_term_goal_text'] ?? '', '']);
}
fputcsv($output, ['']);

if ($plan['short_term_goal_date']) {
    fputcsv($output, ['短期目標', date('Y年m月d日', strtotime($plan['short_term_goal_date'])), '']);
    fputcsv($output, ['短期目標内容', $plan['short_term_goal_text'] ?? '', '']);
} else {
    fputcsv($output, ['短期目標', '', '']);
    fputcsv($output, ['短期目標内容', $plan['short_term_goal_text'] ?? '', '']);
}
fputcsv($output, ['']);

// 明細テーブルのヘッダ
fputcsv($output, [
    '項目',
    '支援目標（具体的な到達目標）',
    '支援内容（内容・支援の提供上のポイント・5領域との関連性等）',
    '達成時期',
    '担当者／提供機関',
    '留意事項',
    '優先順位'
]);

// 明細データ
foreach ($details as $detail) {
    $category = $detail['category'];
    if ($detail['sub_category']) {
        $category .= "\n" . $detail['sub_category'];
    }

    $achievementDate = '';
    if ($detail['achievement_date']) {
        $achievementDate = date('Y/m/d', strtotime($detail['achievement_date']));
    }

    fputcsv($output, [
        $category,
        $detail['support_goal'] ?? '',
        $detail['support_content'] ?? '',
        $achievementDate,
        $detail['staff_organization'] ?? '',
        $detail['notes'] ?? '',
        $detail['priority'] ?? ''
    ]);
}

// 空行
fputcsv($output, ['']);

// 注記
fputcsv($output, [
    'Note',
    '※5領域の視点：「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」',
    ''
]);
fputcsv($output, ['']);

// 同意欄
fputcsv($output, ['ラベル', '値']);
fputcsv($output, ['管理責任者氏名', $plan['manager_name'] ?? '']);

$consentDateStr = '';
if ($plan['consent_date']) {
    $consentDateStr = date('Y年m月d日', strtotime($plan['consent_date']));
}
fputcsv($output, ['同意日', $consentDateStr]);
fputcsv($output, ['保護者署名', $plan['guardian_signature'] ?? '']);

fclose($output);
exit;
