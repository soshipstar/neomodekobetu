<?php
/**
 * 保護者かけはしデータ保存処理
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kakehashi.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

$studentId = $_POST['student_id'] ?? null;
$periodId = $_POST['period_id'] ?? null;
$action = $_POST['action'] ?? 'save'; // save or submit

// 入力データ
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
    header('Location: kakehashi.php');
    exit;
}

// 生徒が保護者の子供であることを確認
$stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND guardian_id = ?");
$stmt->execute([$studentId, $guardianId]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = '不正なアクセスです。';
    header('Location: kakehashi.php');
    exit;
}

try {
    // 既存データの確認
    $stmt = $pdo->prepare("
        SELECT id, is_submitted FROM kakehashi_guardian
        WHERE student_id = ? AND period_id = ?
    ");
    $stmt->execute([$studentId, $periodId]);
    $existing = $stmt->fetch();

    // 提出済みの場合は更新不可
    if ($existing && $existing['is_submitted']) {
        $_SESSION['error'] = '既に提出済みのため、変更できません。';
        header("Location: kakehashi.php?student_id=$studentId&period_id=$periodId");
        exit;
    }

    $isSubmitted = ($action === 'submit') ? 1 : 0;
    $submittedAt = $isSubmitted ? date('Y-m-d H:i:s') : null;

    if ($existing) {
        // 更新
        $stmt = $pdo->prepare("
            UPDATE kakehashi_guardian SET
                home_challenges = ?,
                short_term_goal = ?,
                long_term_goal = ?,
                domain_health_life = ?,
                domain_motor_sensory = ?,
                domain_cognitive_behavior = ?,
                domain_language_communication = ?,
                domain_social_relations = ?,
                other_challenges = ?,
                is_submitted = ?,
                submitted_at = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $homeChallenges,
            $shortTermGoal,
            $longTermGoal,
            $domainHealthLife,
            $domainMotorSensory,
            $domainCognitiveBehavior,
            $domainLanguageCommunication,
            $domainSocialRelations,
            $otherChallenges,
            $isSubmitted,
            $submittedAt,
            $existing['id']
        ]);
    } else {
        // 新規作成
        $stmt = $pdo->prepare("
            INSERT INTO kakehashi_guardian (
                period_id,
                student_id,
                home_challenges,
                short_term_goal,
                long_term_goal,
                domain_health_life,
                domain_motor_sensory,
                domain_cognitive_behavior,
                domain_language_communication,
                domain_social_relations,
                other_challenges,
                is_submitted,
                submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $periodId,
            $studentId,
            $homeChallenges,
            $shortTermGoal,
            $longTermGoal,
            $domainHealthLife,
            $domainMotorSensory,
            $domainCognitiveBehavior,
            $domainLanguageCommunication,
            $domainSocialRelations,
            $otherChallenges,
            $isSubmitted,
            $submittedAt
        ]);
    }

    if ($isSubmitted) {
        $_SESSION['success'] = 'かけはしを提出しました。';
    } else {
        $_SESSION['success'] = '下書きを保存しました。';
    }

} catch (PDOException $e) {
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
}

header("Location: kakehashi.php?student_id=$studentId&period_id=$periodId");
exit;
