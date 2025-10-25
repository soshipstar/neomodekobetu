<?php
/**
 * スタッフ用 - イベント管理ページ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();

// スタッフまたは管理者のみ
if ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$pdo = getDbConnection();

// 検索パラメータを取得
$searchKeyword = $_GET['keyword'] ?? '';
$searchStartDate = $_GET['start_date'] ?? '';
$searchEndDate = $_GET['end_date'] ?? '';
$searchTargetAudience = $_GET['target_audience'] ?? '';

// イベント一覧を取得（検索機能付き）
$sql = "
    SELECT
        e.id,
        e.event_date,
        e.event_name,
        e.event_description,
        e.event_color,
        e.target_audience,
        e.created_at,
        u.full_name as created_by_name
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE 1=1
";

$params = [];

// キーワード検索
if (!empty($searchKeyword)) {
    $sql .= " AND (e.event_name LIKE ? OR e.event_description LIKE ?)";
    $params[] = '%' . $searchKeyword . '%';
    $params[] = '%' . $searchKeyword . '%';
}

// 期間検索（開始日）
if (!empty($searchStartDate)) {
    $sql .= " AND e.event_date >= ?";
    $params[] = $searchStartDate;
}

// 期間検索（終了日）
if (!empty($searchEndDate)) {
    $sql .= " AND e.event_date <= ?";
    $params[] = $searchEndDate;
}

// 対象者フィルター
if (!empty($searchTargetAudience)) {
    $sql .= " AND e.target_audience = ?";
    $params[] = $searchTargetAudience;
}

$sql .= " ORDER BY e.event_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>イベント管理 - スタッフページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .content-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .color-picker-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .color-picker-container input[type="color"] {
            width: 60px;
            height: 40px;
            border: none;
            cursor: pointer;
        }
        .color-preview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .color-option {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .color-option:hover {
            border-color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        table tr:hover {
            background: #f8f9fa;
        }
        .event-color-badge {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 3px;
            vertical-align: middle;
        }
        .user-info {
            color: #666;
            font-size: 14px;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* ドロップダウンメニュー */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            border: none;
            font-family: inherit;
            transition: all 0.3s;
        }

        .dropdown-toggle:hover {
            background: rgba(255,255,255,0.3);
        }

        .dropdown-arrow {
            font-size: 10px;
            transition: transform 0.3s;
        }

        .dropdown.open .dropdown-arrow {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 200px;
            margin-top: 5px;
            z-index: 1000;
            overflow: hidden;
        }

        .dropdown.open .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background: #f8f9fa;
        }

        .dropdown-menu a .menu-icon {
            margin-right: 8px;
        }

        .user-info {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 イベント管理</h1>
            <div class="user-info" id="userInfo">
                <!-- 保護者ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        👨‍👩‍👧 保護者
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="chat.php">
                            <span class="menu-icon">💬</span>保護者チャット
                        </a>
                        <a href="submission_management.php">
                            <span class="menu-icon">📮</span>提出期限管理
                        </a>
                    </div>
                </div>

                <!-- 生徒ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        🎓 生徒
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="student_chats.php">
                            <span class="menu-icon">💬</span>生徒チャット
                        </a>
                        <a href="student_weekly_plans.php">
                            <span class="menu-icon">📝</span>週間計画表
                        </a>
                    </div>
                </div>

                <!-- かけはし管理ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        🌉 かけはし管理
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="kakehashi_staff.php">
                            <span class="menu-icon">✏️</span>スタッフかけはし入力
                        </a>
                        <a href="kakehashi_guardian_view.php">
                            <span class="menu-icon">📋</span>保護者かけはし確認
                        </a>
                        <a href="kobetsu_plan.php">
                            <span class="menu-icon">📄</span>個別支援計画書作成
                        </a>
                        <a href="kobetsu_monitoring.php">
                            <span class="menu-icon">📊</span>モニタリング表作成
                        </a>
                        <a href="newsletter_create.php">
                            <span class="menu-icon">📰</span>施設通信を作成
                        </a>
                    </div>
                </div>

                <!-- マスタ管理ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        ⚙️ マスタ管理
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="students.php">
                            <span class="menu-icon">👥</span>生徒管理
                        </a>
                        <a href="guardians.php">
                            <span class="menu-icon">👨‍👩‍👧</span>保護者管理
                        </a>
                        <a href="holidays.php">
                            <span class="menu-icon">🗓️</span>休日管理
                        </a>
                        <a href="events.php">
                            <span class="menu-icon">🎉</span>イベント管理
                        </a>
                    </div>
                </div>

                <a href="renrakucho_activities.php" class="logout-btn">← 活動管理</a>
                <a href="/logout.php" class="logout-btn">ログアウト</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php
                switch ($_GET['success']) {
                    case 'created':
                        echo 'イベントを登録しました。';
                        break;
                    case 'updated':
                        echo 'イベントを更新しました。';
                        break;
                    case 'deleted':
                        echo 'イベントを削除しました。';
                        break;
                    default:
                        echo '処理が完了しました。';
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                エラー: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- 新規登録フォーム -->
        <div class="content-box">
            <h2 class="section-title">新規イベント登録</h2>
            <form action="events_save.php" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label>日付 *</label>
                        <input type="date" name="event_date" required>
                    </div>
                    <div class="form-group">
                        <label>イベント名 *</label>
                        <input type="text" name="event_name" required placeholder="例: 運動会、遠足、文化祭など">
                    </div>
                </div>
                <div class="form-group">
                    <label>説明</label>
                    <textarea name="event_description" placeholder="イベントの詳細説明（任意）"></textarea>
                    <div class="help-text">イベントの詳細を記入してください（省略可）</div>
                </div>
                <div class="form-group">
                    <label>対象者 *</label>
                    <select name="target_audience" required>
                        <option value="all">全体</option>
                        <option value="elementary">小学生</option>
                        <option value="junior_high_school">中高生向け</option>
                        <option value="guardian">保護者</option>
                        <option value="other">その他</option>
                    </select>
                    <div class="help-text">このイベントの対象者を選択してください</div>
                </div>
                <div class="form-group">
                    <label>カレンダー表示色</label>
                    <div class="color-picker-container">
                        <input type="color" name="event_color" id="event_color" value="#28a745">
                        <span>選択した色でカレンダーに表示されます</span>
                    </div>
                    <div class="color-preview" style="margin-top: 10px;">
                        <div class="color-option" style="background: #28a745;" onclick="document.getElementById('event_color').value='#28a745'"></div>
                        <div class="color-option" style="background: #007bff;" onclick="document.getElementById('event_color').value='#007bff'"></div>
                        <div class="color-option" style="background: #ffc107;" onclick="document.getElementById('event_color').value='#ffc107'"></div>
                        <div class="color-option" style="background: #dc3545;" onclick="document.getElementById('event_color').value='#dc3545'"></div>
                        <div class="color-option" style="background: #17a2b8;" onclick="document.getElementById('event_color').value='#17a2b8'"></div>
                        <div class="color-option" style="background: #6f42c1;" onclick="document.getElementById('event_color').value='#6f42c1'"></div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="btn btn-success">登録する</button>
                </div>
            </form>
        </div>

        <!-- 検索フォーム -->
        <div class="content-box">
            <h2 class="section-title">🔍 イベント検索</h2>
            <form method="GET" action="events.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>期間（開始日）</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($searchStartDate); ?>">
                    </div>
                    <div class="form-group">
                        <label>期間（終了日）</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($searchEndDate); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>対象者</label>
                        <select name="target_audience">
                            <option value="">すべて</option>
                            <option value="all" <?php echo $searchTargetAudience === 'all' ? 'selected' : ''; ?>>全体</option>
                            <option value="elementary" <?php echo $searchTargetAudience === 'elementary' ? 'selected' : ''; ?>>小学生</option>
                            <option value="junior_high_school" <?php echo $searchTargetAudience === 'junior_high_school' ? 'selected' : ''; ?>>中高生向け</option>
                            <option value="guardian" <?php echo $searchTargetAudience === 'guardian' ? 'selected' : ''; ?>>保護者</option>
                            <option value="other" <?php echo $searchTargetAudience === 'other' ? 'selected' : ''; ?>>その他</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>キーワード</label>
                        <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="イベント名または説明で検索">
                    </div>
                </div>
                <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                    <a href="events.php" class="btn btn-secondary">クリア</a>
                    <button type="submit" class="btn btn-primary">検索</button>
                </div>
            </form>
        </div>

        <!-- イベント一覧 -->
        <div class="content-box">
            <h2 class="section-title">登録済みイベント一覧</h2>
            <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($searchTargetAudience)): ?>
                <div style="margin-bottom: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2196F3; color: #1976D2;">
                    <strong>検索結果:</strong> <?php echo count($events); ?>件のイベントが見つかりました
                </div>
            <?php endif; ?>
            <table>
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>イベント名</th>
                        <th>対象者</th>
                        <th>説明</th>
                        <th>色</th>
                        <th>登録者</th>
                        <th>登録日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($searchTargetAudience)): ?>
                                    検索条件に一致するイベントが見つかりませんでした
                                <?php else: ?>
                                    登録されているイベントがありません
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $targetAudienceLabels = [
                            'all' => '全体',
                            'elementary' => '小学生',
                            'junior_high_school' => '中高生',
                            'guardian' => '保護者',
                            'other' => 'その他'
                        ];
                        ?>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($event['event_date']))] . '）', strtotime($event['event_date'])); ?></td>
                                <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                <td>
                                    <span style="display: inline-block; padding: 4px 8px; background: #e3f2fd; color: #1565c0; border-radius: 12px; font-size: 12px; font-weight: bold;">
                                        <?php echo $targetAudienceLabels[$event['target_audience']] ?? '全体'; ?>
                                    </span>
                                </td>
                                <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($event['event_description'] ?: '---'); ?>
                                </td>
                                <td>
                                    <span class="event-color-badge" style="background: <?php echo htmlspecialchars($event['event_color']); ?>;"></span>
                                </td>
                                <td><?php echo htmlspecialchars($event['created_by_name']); ?></td>
                                <td><?php echo date('Y/m/d', strtotime($event['created_at'])); ?></td>
                                <td>
                                    <form method="POST" action="events_save.php" style="display: inline;" onsubmit="return confirm('このイベントを削除しますか？');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">削除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // ドロップダウンメニューのトグル
        function toggleDropdown(event, button) {
            event.stopPropagation();
            const dropdown = button.closest('.dropdown');
            const isOpen = dropdown.classList.contains('open');

            // 他のドロップダウンを閉じる
            document.querySelectorAll('.dropdown.open').forEach(d => {
                d.classList.remove('open');
            });

            // このドロップダウンをトグル
            if (!isOpen) {
                dropdown.classList.add('open');
            }
        }

        // ドロップダウン外をクリックしたら閉じる
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown.open').forEach(d => {
                d.classList.remove('open');
            });
        });

        // ドロップダウン内のクリックで伝播を止める
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>
