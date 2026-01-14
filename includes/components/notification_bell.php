<?php
/**
 * 通知ベルコンポーネント
 *
 * ヘッダーに表示する通知ベル+ドロップダウンメニュー
 *
 * 使用方法:
 *   <?php include __DIR__ . '/notification_bell.php'; ?>
 *   <?php renderNotificationBell($notificationData); ?>
 *
 * 必要な変数:
 *   $notificationData - notification_helper.phpから取得したデータ
 */

/**
 * 通知ベルをレンダリング
 *
 * @param array $notificationData 通知データ（notifications, totalCount）
 * @param string $role ユーザーロール（staff, guardian, admin）
 */
function renderNotificationBell(array $notificationData, string $role = 'staff'): void
{
    $notifications = $notificationData['notifications'] ?? [];
    $totalCount = $notificationData['totalCount'] ?? 0;

    // カラーマップ
    $colorMap = [
        'blue' => 'var(--md-blue)',
        'purple' => 'var(--md-purple)',
        'green' => 'var(--md-green)',
        'orange' => 'var(--md-orange)',
        'red' => 'var(--md-red)',
        'teal' => 'var(--md-teal)',
    ];
?>
<div class="notification-bell-wrapper">
    <button class="notification-bell-btn" onclick="toggleNotificationDropdown(event)" aria-label="通知">
        <span class="material-symbols-outlined">notifications</span>
        <?php if ($totalCount > 0): ?>
            <span class="notification-badge"><?= $totalCount > 99 ? '99+' : $totalCount ?></span>
        <?php endif; ?>
    </button>
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-header">
            <span class="notification-dropdown-title">通知</span>
            <?php if ($totalCount > 0): ?>
                <span class="notification-dropdown-count"><?= $totalCount ?>件</span>
            <?php endif; ?>
        </div>
        <div class="notification-dropdown-body">
            <?php if (empty($notifications)): ?>
                <div class="notification-empty">
                    <span class="material-symbols-outlined">notifications_off</span>
                    <p>通知はありません</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $key => $notification):
                    $color = $colorMap[$notification['color']] ?? 'var(--md-blue)';
                ?>
                    <a href="<?= htmlspecialchars($notification['url']) ?>" class="notification-dropdown-item" style="--item-color: <?= $color ?>;">
                        <div class="notification-dropdown-icon" style="background: <?= $color ?>;">
                            <span class="material-symbols-outlined"><?= $notification['icon'] ?></span>
                        </div>
                        <div class="notification-dropdown-content">
                            <div class="notification-dropdown-item-title"><?= htmlspecialchars($notification['title']) ?>があります</div>
                            <div class="notification-dropdown-item-detail">
                                <?= $notification['count'] ?>件
                            </div>
                        </div>
                        <div class="notification-dropdown-badge" style="background: <?= $color ?>;">
                            <?= $notification['count'] ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($notifications)): ?>
            <div class="notification-dropdown-footer">
                <?php if ($role === 'staff' || $role === 'admin'): ?>
                    <a href="/staff/pending_tasks.php" class="notification-dropdown-footer-link">すべてのタスクを見る</a>
                <?php else: ?>
                    <a href="/guardian/dashboard.php" class="notification-dropdown-footer-link">ダッシュボードへ</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// 通知ドロップダウンの開閉
function toggleNotificationDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('notificationDropdown');
    const isOpen = dropdown.classList.contains('show');

    // 他のドロップダウンを閉じる
    document.querySelectorAll('.notification-dropdown.show').forEach(el => {
        if (el !== dropdown) el.classList.remove('show');
    });

    dropdown.classList.toggle('show');
}

// ドロップダウン外をクリックで閉じる
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notificationDropdown');
    const bellBtn = document.querySelector('.notification-bell-btn');
    if (dropdown && bellBtn && !dropdown.contains(event.target) && !bellBtn.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// ESCキーで閉じる
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) dropdown.classList.remove('show');
    }
});
</script>
<?php
}
