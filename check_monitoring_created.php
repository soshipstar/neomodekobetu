<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>モニタリングシート作成確認</h1>";

// モニタリング記録を取得
$stmt = $pdo->query("
    SELECT
        mr.id,
        mr.student_id,
        mr.student_name,
        mr.monitoring_date,
        mr.plan_id,
        isp.created_date as plan_created_date,
        (SELECT COUNT(*) FROM monitoring_details WHERE monitoring_id = mr.id) as detail_count
    FROM monitoring_records mr
    LEFT JOIN individual_support_plans isp ON mr.plan_id = isp.id
    ORDER BY mr.id DESC
    LIMIT 20
");
$monitorings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($monitorings)) {
    echo "<p style='color: red;'>モニタリングシートが作成されていません。</p>";
} else {
    echo "<p style='color: green;'>モニタリングシート: " . count($monitorings) . " 件</p>";

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>生徒ID</th>";
    echo "<th>生徒名</th>";
    echo "<th>モニタリング日</th>";
    echo "<th>参照計画ID</th>";
    echo "<th>計画作成日</th>";
    echo "<th>明細数</th>";
    echo "</tr>";

    foreach ($monitorings as $m) {
        echo "<tr>";
        echo "<td>{$m['id']}</td>";
        echo "<td>{$m['student_id']}</td>";
        echo "<td>" . htmlspecialchars($m['student_name']) . "</td>";
        echo "<td>{$m['monitoring_date']}</td>";
        echo "<td>{$m['plan_id']}</td>";
        echo "<td>{$m['plan_created_date']}</td>";
        echo "<td>{$m['detail_count']}</td>";
        echo "</tr>";
    }

    echo "</table>";

    // サンプルで1件目の詳細を表示
    if (!empty($monitorings)) {
        $firstId = $monitorings[0]['id'];

        echo "<h2>サンプル: モニタリングID {$firstId} の明細</h2>";

        $stmt = $pdo->prepare("
            SELECT
                md.id,
                md.achievement_status,
                md.monitoring_comment,
                ispd.category,
                ispd.sub_category,
                ispd.support_goal,
                ispd.support_content
            FROM monitoring_details md
            INNER JOIN individual_support_plan_details ispd ON md.plan_detail_id = ispd.id
            WHERE md.monitoring_id = ?
            ORDER BY ispd.row_order
        ");
        $stmt->execute([$firstId]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($details)) {
            echo "<p style='color: red;'>明細がありません</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse; font-size: 12px;'>";
            echo "<tr>";
            echo "<th>明細ID</th>";
            echo "<th>項目</th>";
            echo "<th>サブ項目</th>";
            echo "<th>支援目標</th>";
            echo "<th>支援内容</th>";
            echo "<th>達成状況</th>";
            echo "<th>コメント</th>";
            echo "</tr>";

            foreach ($details as $d) {
                echo "<tr>";
                echo "<td>{$d['id']}</td>";
                echo "<td>" . htmlspecialchars($d['category'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($d['sub_category'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars(substr($d['support_goal'] ?? '', 0, 50)) . "...</td>";
                echo "<td>" . htmlspecialchars(substr($d['support_content'] ?? '', 0, 50)) . "...</td>";
                echo "<td style='background: #fff3cd;'>" . htmlspecialchars($d['achievement_status'] ?? '未入力') . "</td>";
                echo "<td style='background: #fff3cd;'>" . htmlspecialchars($d['monitoring_comment'] ?? '未入力') . "</td>";
                echo "</tr>";
            }

            echo "</table>";
            echo "<p><small>※ 黄色の欄が評価欄（編集可能）</small></p>";
        }
    }
}
