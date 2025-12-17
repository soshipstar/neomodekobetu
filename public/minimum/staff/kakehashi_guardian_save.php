<?php
/**
 * スタッフ用 保護者かけはしデータ保存処理
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /minimum/staff/kakehashi_guardian_view.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];
$classroomId = $_SESSION['classroom_id'] ?? null;

$studentId = $_POST['student_id'] ?? null;
$periodId = $_POST['period_id'] ?? null;
$redirectShow = $_POST['redirect_show'] ?? 'visible';

// 非表示処理
if (isset($_POST['hide_guardian_kakehashi'])) {
    try {
        // かけはしの存在確認
        $stmt = $pdo->prepare("
            SELECT id FROM kakehashi_guardian
            WHERE student_id = ? AND period_id = ?
        ");
        $stmt->execute([$studentId, $periodId]);
        if ($stmt->fetch()) {
            // 非表示に設定
            $stmt = $pdo->prepare("
                UPDATE kakehashi_guardian SET is_hidden = 1, updated_at = NOW()
                WHERE student_id = ? AND period_id = ?
            ");
            $stmt->execute([$studentId, $periodId]);
            $_SESSION['success'] = '保護者用かけはしを非表示にしました。';
        } else {
            $_SESSION['error'] = 'かけはしが見つかりませんでした。';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    }
    header("Location: kakehashi_guardian_view.php?student_id=$studentId&period_id=$periodId&show=$redirectShow");
    exit;
}

// 再表示処理
if (isset($_POST['unhide_guardian_kakehashi'])) {
    try {
        // かけはしの存在確認
        $stmt = $pdo->prepare("
            SELECT id FROM kakehashi_guardian
            WHERE student_id = ? AND period_id = ?
        ");
        $stmt->execute([$studentId, $periodId]);
        if ($stmt->fetch()) {
            // 再表示に設定
            $stmt = $pdo->prepare("
                UPDATE kakehashi_guardian SET is_hidden = 0, updated_at = NOW()
                WHERE student_id = ? AND period_id = ?
            ");
            $stmt->execute([$studentId, $periodId]);
            $_SESSION['success'] = '保護者用かけはしを再表示しました。';
        } else {
            $_SESSION['error'] = 'かけはしが見つかりませんでした。';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    }
    header("Location: kakehashi_guardian_view.php?student_id=$studentId&period_id=$periodId&show=$redirectShow");
    exit;
}

// 入力データ
$studentWish = $_POST['student_wish'] ?? '';
$homeChallenges = $_POST['home_challenges'] ?? '';
$shortTermGoal = $_POST['short_term_goal'] ?? '';
$longTermGoal = $_POST['long_term_goal'] ?? '';
$domainHealthLife = $_POST['domain_health_life'] ?? '';
$domainMotorSensory = $_POST['domain_motor_sensory'] ?? '';
$domainCognitiveBehavior = $_POST['domain_cognitive_behavior'] ?? '';
$domainLanguageCommunication = $_POST['domain_language_communication'] ?? '';
$domainSocialRelations = $_POST['domain_social_relations'] ?? '';
$otherChallenges = $_POST['other_challenges'] ?? '';

// バリデーション
if (!$studentId || !$periodId) {
    $_SESSION['error'] = '生徒または期間が選択されていません。';
    header('Location: /minimum/staff/kakehashi_guardian_view.php');
    exit;
}

// 生徒がスタッフの教室に所属していることを確認
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.id = ? AND u.classroom_id = ?
    ");
    $stmt->execute([$studentId, $classroomId]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = '不正なアクセスです。';
        header('Location: /minimum/staff/kakehashi_guardian_view.php');
        exit;
    }
} else {
    // 教室IDがない場合は、生徒の存在だけ確認
    $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = '不正なアクセスです。';
        header('Location: /minimum/staff/kakehashi_guardian_view.php');
        exit;
    }
}

try {
    // 期間が有効であることを確認
    $stmt = $pdo->prepare("
        SELECT id FROM kakehashi_periods
        WHERE id = ? AND student_id = ? AND is_active = 1
    ");
    $stmt->execute([$periodId, $studentId]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'この期間は編集できません。';
        header("Location: kakehashi_guardian_view.php?student_id=$studentId&period_id=$periodId");
        exit;
    }

    // 既存データの確認
    $stmt = $pdo->prepare("
        SELECT id, is_submitted, is_hidden FROM kakehashi_guardian
        WHERE student_id = ? AND period_id = ?
    ");
    $stmt->execute([$studentId, $periodId]);
    $existing = $stmt->fetch();

    // スタッフは提出済みでも編集可能（加筆のため）
    // データが存在しない場合はエラー
    if (!$existing) {
        $_SESSION['error'] = 'かけはしデータが存在しません。保護者が先に入力する必要があります。';
        header("Location: kakehashi_guardian_view.php?student_id=$studentId&period_id=$periodId");
        exit;
    }

    // 更新（is_submittedとsubmitted_atは変更しない）
    $stmt = $pdo->prepare("
        UPDATE kakehashi_guardian SET
            student_wish = ?,
            home_challenges = ?,
            short_term_goal = ?,
            long_term_goal = ?,
            domain_health_life = ?,
            domain_motor_sensory = ?,
            domain_cognitive_behavior = ?,
            domain_language_communication = ?,
            domain_social_relations = ?,
            other_challenges = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $studentWish,
        $homeChallenges,
        $shortTermGoal,
        $longTermGoal,
        $domainHealthLife,
        $domainMotorSensory,
        $domainCognitiveBehavior,
        $domainLanguageCommunication,
        $domainSocialRelations,
        $otherChallenges,
        $existing['id']
    ]);

    $_SESSION['success'] = 'かけはしを保存しました。';

} catch (PDOException $e) {
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
}

header("Location: kakehashi_guardian_view.php?student_id=$studentId&period_id=$periodId&show=$redirectShow");
exit;
