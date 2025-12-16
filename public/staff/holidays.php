<?php
/**
 * 繧ｹ繧ｿ繝・ヵ逕ｨ - 莨第律邂｡逅・・繝ｼ繧ｸ
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 繝ｭ繧ｰ繧､繝ｳ繝√ぉ繝・け
requireLogin();
checkUserType(['staff', 'admin']);

$pdo = getDbConnection();

// 謨吝ｮ､ID繧貞叙蠕・$classroomId = $_SESSION['classroom_id'] ?? null;

// 讀懃ｴ｢繝代Λ繝｡繝ｼ繧ｿ繧貞叙蠕・$searchKeyword = $_GET['keyword'] ?? '';
$searchStartDate = $_GET['start_date'] ?? '';
$searchEndDate = $_GET['end_date'] ?? '';

// 莨第律荳隕ｧ繧貞叙蠕暦ｼ域､懃ｴ｢讖溯・莉倥″縲∵蕗螳､縺ｧ繝輔ぅ繝ｫ繧ｿ繝ｪ繝ｳ繧ｰ・・$sql = "
    SELECT
        h.id,
        h.holiday_date,
        h.holiday_name,
        h.holiday_type,
        h.created_at,
        u.full_name as created_by_name
    FROM holidays h
    LEFT JOIN users u ON h.created_by = u.id
    WHERE h.classroom_id = ?
";

$params = [$classroomId];

// 繧ｭ繝ｼ繝ｯ繝ｼ繝画､懃ｴ｢
if (!empty($searchKeyword)) {
    $sql .= " AND h.holiday_name LIKE ?";
    $params[] = '%' . $searchKeyword . '%';
}

// 譛滄俣讀懃ｴ｢・磯幕蟋区律・・if (!empty($searchStartDate)) {
    $sql .= " AND h.holiday_date >= ?";
    $params[] = $searchStartDate;
}

// 譛滄俣讀懃ｴ｢・育ｵゆｺ・律・・if (!empty($searchEndDate)) {
    $sql .= " AND h.holiday_date <= ?";
    $params[] = $searchEndDate;
}

$sql .= " ORDER BY h.holiday_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$holidays = $stmt->fetchAll();

// 繝壹・繧ｸ髢句ｧ・$currentPage = 'holidays';
renderPageStart('staff', $currentPage, '莨第律邂｡逅・);
?>

<style>
.type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: var(--radius-md);
    font-size: var(--text-caption-1);
    font-weight: bold;
}
.type-regular {
    background: rgba(0, 122, 255, 0.15);
    color: var(--apple-blue);
}
.type-special {
    background: rgba(255, 149, 0, 0.15);
    color: var(--apple-orange);
}
</style>

<!-- 繝壹・繧ｸ繝倥ャ繝繝ｼ -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">莨第律邂｡逅・/h1>
        <p class="page-subtitle">莨第律繝ｻ逾晄律縺ｮ逋ｻ骭ｲ縺ｨ邂｡逅・/p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        switch ($_GET['success']) {
            case 'created':
                if (isset($_GET['count'])) {
                    echo '螳壽悄莨第律縺ｨ縺励※' . (int)$_GET['count'] . '莉ｶ縺ｮ莨第律繧堤匳骭ｲ縺励∪縺励◆縲・;
                } else {
                    echo '莨第律繧堤匳骭ｲ縺励∪縺励◆縲・;
                }
                break;
            case 'deleted':
                echo '莨第律繧貞炎髯､縺励∪縺励◆縲・;
                break;
            default:
                echo '蜃ｦ逅・′螳御ｺ・＠縺ｾ縺励◆縲・;
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        繧ｨ繝ｩ繝ｼ: <?= htmlspecialchars($_GET['error']) ?>
    </div>
<?php endif; ?>

<!-- 譁ｰ隕冗匳骭ｲ繝輔か繝ｼ繝 -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-blue);">譁ｰ隕丈ｼ第律逋ｻ骭ｲ</h2>

        <!-- 繧ｿ繝門・繧頑崛縺・-->
        <div class="holiday-tabs" style="display: flex; gap: 10px; margin-bottom: 20px;">
            <button type="button" class="tab-btn active" data-tab="regular" style="flex: 1; padding: 12px; border: 2px solid var(--apple-blue); background: var(--apple-blue); color: white; border-radius: 8px; cursor: pointer; font-weight: 600;">
                螳壽悄莨第律・域ｯ朱ｱ縺ｮ莨代∩・・            </button>
            <button type="button" class="tab-btn" data-tab="special" style="flex: 1; padding: 12px; border: 2px solid #ddd; background: white; color: #333; border-radius: 8px; cursor: pointer; font-weight: 600;">
                迚ｹ蛻･莨第律・育･晄律繝ｻ繧､繝吶Φ繝育ｭ会ｼ・            </button>
        </div>

        <!-- 螳壽悄莨第律繝輔か繝ｼ繝 -->
        <div id="regularForm" class="holiday-form">
            <form action="holidays_save.php" method="POST">
                <input type="hidden" name="action" value="create_regular">

                <div class="form-group">
                    <label class="form-label">莨第律蜷・*</label>
                    <input type="text" name="holiday_name" class="form-control" required placeholder="萓・ 螳壻ｼ第律縲∵律譖應ｼ代∩">
                    <small style="color: var(--text-secondary);">繧ｫ繝ｬ繝ｳ繝繝ｼ縺ｫ陦ｨ遉ｺ縺輔ｌ繧句錐蜑阪〒縺・/small>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">莨第･ｭ譌･縺ｮ譖懈律 *</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                        <?php
                        $dayNames = ['譌･譖懈律', '譛域屆譌･', '轣ｫ譖懈律', '豌ｴ譖懈律', '譛ｨ譖懈律', '驥第屆譌･', '蝨滓屆譌･'];
                        $dayColors = ['#dc3545', '#333', '#333', '#333', '#333', '#333', '#007bff'];
                        foreach ($dayNames as $i => $dayName):
                        ?>
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                            <input type="checkbox" name="days_of_week[]" value="<?= $i ?>" style="width: 18px; height: 18px; cursor: pointer;">
                            <span style="color: <?= $dayColors[$i] ?>; font-weight: 500;"><?= $dayName ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <small style="color: var(--text-secondary); display: block; margin-top: 8px;">驕ｸ謚槭＠縺滓屆譌･縺悟ｹｴ蠎ｦ譛ｫ・・譛域忰・峨∪縺ｧ逋ｻ骭ｲ縺輔ｌ縺ｾ縺・/small>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">逋ｻ骭ｲ髢句ｧ区律</label>
                    <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    <small style="color: var(--text-secondary);">縺薙・譌･莉･髯阪・隧ｲ蠖捺屆譌･縺檎匳骭ｲ縺輔ｌ縺ｾ縺呻ｼ医ョ繝輔か繝ｫ繝・ 莉頑律・・/small>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">螳壽悄莨第律繧堤匳骭ｲ</button>
                </div>
            </form>
        </div>

        <!-- 迚ｹ蛻･莨第律繝輔か繝ｼ繝 -->
        <div id="specialForm" class="holiday-form" style="display: none;">
            <form action="holidays_save.php" method="POST">
                <input type="hidden" name="action" value="create_special">

                <div class="form-group">
                    <label class="form-label">譌･莉・*</label>
                    <input type="date" name="holiday_date" class="form-control" required>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">莨第律蜷・*</label>
                    <input type="text" name="holiday_name" class="form-control" required placeholder="萓・ 蜈・律縲∝､丞ｭ｣莨第･ｭ縲∝ｹｴ譛ｫ蟷ｴ蟋・>
                    <small style="color: var(--text-secondary);">繧ｫ繝ｬ繝ｳ繝繝ｼ縺ｫ陦ｨ遉ｺ縺輔ｌ繧句錐蜑阪〒縺・/small>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">迚ｹ蛻･莨第律繧堤匳骭ｲ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 繧ｿ繝門・繧頑崛縺・document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        // 繧ｿ繝悶・繧ｿ繝ｳ縺ｮ繧ｹ繧ｿ繧､繝ｫ蛻・ｊ譖ｿ縺・        document.querySelectorAll('.tab-btn').forEach(b => {
            b.style.background = 'white';
            b.style.color = '#333';
            b.style.borderColor = '#ddd';
            b.classList.remove('active');
        });
        btn.style.background = 'var(--apple-blue)';
        btn.style.color = 'white';
        btn.style.borderColor = 'var(--apple-blue)';
        btn.classList.add('active');

        // 繝輔か繝ｼ繝蛻・ｊ譖ｿ縺・        const tab = btn.dataset.tab;
        document.querySelectorAll('.holiday-form').forEach(form => {
            form.style.display = 'none';
        });
        document.getElementById(tab + 'Form').style.display = 'block';
    });
});

// 繝√ぉ繝・け繝懊ャ繧ｯ繧ｹ縺ｮ驕ｸ謚樊凾縺ｫ繧ｹ繧ｿ繧､繝ｫ螟画峩
document.querySelectorAll('input[name="days_of_week[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const label = this.closest('label');
        if (this.checked) {
            label.style.background = '#e3f2fd';
            label.style.borderColor = 'var(--apple-blue)';
        } else {
            label.style.background = 'white';
            label.style.borderColor = '#ddd';
        }
    });
});
</script>

<!-- 讀懃ｴ｢繝輔か繝ｼ繝 -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-blue);">莨第律讀懃ｴ｢</h2>
        <form method="GET" action="holidays.php">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">譛滄俣・磯幕蟋区律・・/label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($searchStartDate) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">譛滄俣・育ｵゆｺ・律・・/label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($searchEndDate) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">繧ｭ繝ｼ繝ｯ繝ｼ繝・/label>
                <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="莨第律蜷阪〒讀懃ｴ｢">
            </div>
            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <a href="holidays.php" class="btn btn-secondary">繧ｯ繝ｪ繧｢</a>
                <button type="submit" class="btn btn-primary">讀懃ｴ｢</button>
            </div>
        </form>
    </div>
</div>

<!-- 莨第律荳隕ｧ -->
<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-blue);">逋ｻ骭ｲ貂医∩莨第律荳隕ｧ</h2>

        <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate)): ?>
            <div class="alert alert-info">
                <strong>讀懃ｴ｢邨先棡:</strong> <?= count($holidays) ?>莉ｶ縺ｮ莨第律縺瑚ｦ九▽縺九ｊ縺ｾ縺励◆
            </div>
        <?php endif; ?>

        <table class="table">
            <thead>
                <tr>
                    <th>譌･莉・/th>
                    <th>莨第律蜷・/th>
                    <th>繧ｿ繧､繝・/th>
                    <th>逋ｻ骭ｲ閠・/th>
                    <th>逋ｻ骭ｲ譌･</th>
                    <th>謫堺ｽ・/th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($holidays)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                            <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate)): ?>
                                讀懃ｴ｢譚｡莉ｶ縺ｫ荳閾ｴ縺吶ｋ莨第律縺瑚ｦ九▽縺九ｊ縺ｾ縺帙ｓ縺ｧ縺励◆
                            <?php else: ?>
                                逋ｻ骭ｲ縺輔ｌ縺ｦ縺・ｋ莨第律縺後≠繧翫∪縺帙ｓ
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($holidays as $holiday): ?>
                        <tr>
                            <td><?= date('Y蟷ｴn譛・譌･・・ . ['譌･', '譛・, '轣ｫ', '豌ｴ', '譛ｨ', '驥・, '蝨・][date('w', strtotime($holiday['holiday_date']))] . '・・, strtotime($holiday['holiday_date'])) ?></td>
                            <td><?= htmlspecialchars($holiday['holiday_name']) ?></td>
                            <td>
                                <span class="type-badge <?= $holiday['holiday_type'] === 'regular' ? 'type-regular' : 'type-special' ?>">
                                    <?= $holiday['holiday_type'] === 'regular' ? '螳壽悄莨第律' : '迚ｹ蛻･莨第律' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($holiday['created_by_name'] ?? '-') ?></td>
                            <td><?= date('Y/m/d', strtotime($holiday['created_at'])) ?></td>
                            <td>
                                <form method="POST" action="holidays_save.php" style="display: inline;" onsubmit="return confirm('縺薙・莨第律繧貞炎髯､縺励∪縺吶°・・);">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="holiday_id" value="<?= $holiday['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">蜑企勁</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderPageEnd(); ?>
