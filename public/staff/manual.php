<?php
/**
 * スタッフ向けマニュアルページ
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$currentUser = getCurrentUser();

// ページ開始
$currentPage = 'manual';
renderPageStart('staff', $currentPage, 'スタッフマニュアル');
?>

<style>
.manual-container {
    max-width: 1000px;
    margin: 0 auto;
}

.section {
    margin-bottom: 40px;
    padding: var(--spacing-2xl);
    background: var(--md-gray-6);
    border-radius: var(--radius-md);
    border-left: 4px solid var(--md-blue);
}

.section h2 {
    color: var(--md-blue);
    font-size: var(--text-title-2);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    gap: 10px;
}

.section h3 {
    color: var(--text-primary);
    font-size: 18px;
    margin: var(--spacing-lg) 0 10px 0;
    padding-left: 15px;
    border-left: 3px solid var(--md-blue);
}

.section p {
    color: var(--text-primary);
    line-height: 1.8;
    margin-bottom: 15px;
}

.section ul {
    margin-left: 30px;
    margin-bottom: 15px;
}

.section li {
    color: var(--text-primary);
    line-height: 1.8;
    margin-bottom: 8px;
}

.feature-box {
    background: var(--md-bg-primary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-sm);
    margin: 15px 0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.feature-title {
    font-weight: 600;
    color: var(--md-blue);
    margin-bottom: var(--spacing-md);
    font-size: var(--text-callout);
}

.step-box {
    background: var(--md-bg-primary);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin: var(--spacing-md) 0;
    border-left: 3px solid var(--md-green);
}

.step-number {
    display: inline-block;
    background: var(--md-green);
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    text-align: center;
    line-height: 24px;
    margin-right: 8px;
    font-size: var(--text-subhead);
    font-weight: 600;
}

.note-box {
    background: rgba(255, 149, 0, 0.15);
    border: 1px solid var(--md-orange);
    border-radius: var(--radius-sm);
    padding: 15px;
    margin: 15px 0;
    color: var(--text-primary);
}

.note-box strong { color: var(--md-orange); }

.tip-box {
    background: rgba(23, 162, 184, 0.15);
    border: 1px solid var(--md-teal);
    border-radius: var(--radius-sm);
    padding: 15px;
    margin: 15px 0;
    color: var(--text-primary);
}

.tip-box strong { color: var(--md-teal); }

.schedule-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 14px;
}

.schedule-table th, .schedule-table td {
    padding: 12px;
    border: 1px solid var(--border-primary);
    color: var(--text-primary);
}

.schedule-table thead tr { background: var(--md-blue); color: white; }
.schedule-table thead th { color: white; }
.schedule-table tbody tr { background: var(--md-bg-tertiary); }
.schedule-table tbody tr:nth-child(even) { background: var(--md-bg-secondary); }

.color-box {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    color: var(--text-primary);
}

.color-box.green { background: rgba(52, 199, 89, 0.15); border: 1px solid var(--md-green); }
.color-box.orange { background: rgba(255, 149, 0, 0.15); border: 1px solid var(--md-orange); }
.color-box.pink { background: rgba(255, 45, 85, 0.15); border: 1px solid var(--md-pink); }
.color-box.blue { background: rgba(0, 122, 255, 0.15); border: 1px solid var(--md-blue); }
.color-box.yellow { background: rgba(255, 204, 0, 0.15); border: 1px solid var(--md-yellow); }
.color-box p, .color-box li, .color-box strong { color: var(--text-primary); }

.timeline-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 700px;
}

.timeline-table th, .timeline-table td {
    padding: 10px;
    border: 1px solid var(--border-primary);
    color: var(--text-primary);
}

.timeline-table thead tr { background: var(--md-bg-tertiary); }
.timeline-table thead th { color: var(--text-primary); font-weight: 600; }
.timeline-table tbody td { background: var(--md-bg-secondary); }
.timeline-table .highlight-row { background: rgba(255, 149, 0, 0.1); }
.timeline-table .success-row { background: rgba(52, 199, 89, 0.1); }

.step-card {
    flex: 1;
    min-width: 280px;
    padding: 15px;
    border-radius: 8px;
    color: var(--text-primary);
}

.step-card.orange { background: rgba(255, 149, 0, 0.15); border-left: 4px solid var(--md-orange); }
.step-card.pink { background: rgba(255, 45, 85, 0.15); border-left: 4px solid var(--md-pink); }
.step-card.green { background: rgba(52, 199, 89, 0.15); border-left: 4px solid var(--md-green); }
.step-card h4 { margin-bottom: 10px; }
.step-card.orange h4 { color: var(--md-orange); }
.step-card.pink h4 { color: var(--md-pink); }
.step-card.green h4 { color: var(--md-green); }
.step-card p, .step-card li { color: var(--text-primary); font-size: 13px; }

.alert-red { color: var(--md-red); }
.alert-orange { color: var(--md-orange); }
.alert-blue { color: var(--md-blue); }
.alert-green { color: var(--md-green); }
.alert-pink { color: var(--md-pink); }

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
    display: inline-block;
    margin-bottom: var(--spacing-lg);
}
.quick-link:hover { background: var(--md-gray-5); }

@media (max-width: 768px) {
    .section { padding: var(--spacing-lg); }
}

@media print {
    .sidebar, .mobile-header, .quick-link, .manual-nav { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
}

/* マニュアルレイアウト */
.manual-layout {
    display: flex;
    gap: var(--spacing-xl);
    max-width: 1400px;
    margin: 0 auto;
}

/* 左サイドバー目次 */
.manual-nav {
    position: sticky;
    top: 20px;
    width: 200px;
    min-width: 200px;
    height: fit-content;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    background: var(--md-bg-primary);
    border-radius: var(--radius-md);
    padding: var(--spacing-md);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.manual-nav-title {
    font-size: var(--text-footnote);
    font-weight: 600;
    color: var(--md-blue);
    margin-bottom: var(--spacing-sm);
    padding-bottom: var(--spacing-sm);
    border-bottom: 1px solid var(--border-primary);
}

.manual-nav-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.manual-nav-link {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    background: transparent;
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 500;
    transition: all var(--duration-fast);
    border-left: 3px solid transparent;
}

.manual-nav-link:hover {
    background: var(--md-gray-6);
    border-left-color: var(--md-blue);
    color: var(--md-blue);
}

.manual-nav-link.active {
    background: rgba(0, 122, 255, 0.1);
    border-left-color: var(--md-blue);
    color: var(--md-blue);
}

.manual-nav-link .nav-icon {
    font-size: 14px;
    width: 18px;
    text-align: center;
}

/* メインコンテンツ */
.manual-main {
    flex: 1;
    min-width: 0;
}

/* モバイル用トグルボタン */
.manual-nav-toggle {
    display: none;
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 50px;
    height: 50px;
    background: var(--md-blue);
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 122, 255, 0.4);
    z-index: 1000;
}

.manual-nav-toggle:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 122, 255, 0.5);
}

/* モバイル用オーバーレイ */
.manual-nav-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 998;
}

.manual-nav-overlay.show {
    display: block;
}

@media (max-width: 900px) {
    .manual-layout {
        display: block;
    }

    .manual-nav {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 280px;
        max-height: 70vh;
        z-index: 999;
    }

    .manual-nav.show {
        display: block;
    }

    .manual-nav-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

/* セクションへのスクロールオフセット */
.section[id] {
    scroll-margin-top: 20px;
}

/* トップに戻るボタン */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: var(--md-blue);
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 122, 255, 0.4);
    opacity: 0;
    visibility: hidden;
    transition: all var(--duration-fast);
    z-index: 1000;
}

.back-to-top.show {
    opacity: 1;
    visibility: visible;
}

.back-to-top:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0, 122, 255, 0.5);
}

.manual-logo-header {
    text-align: center;
    margin-bottom: var(--spacing-xl);
}

.manual-logo {
    width: 100px;
    height: auto;
    margin-bottom: var(--spacing-md);
}
</style>

<!-- ロゴ -->
<div class="manual-logo-header">
    <img src="/uploads/kiduri.png" alt="きづり" class="manual-logo">
</div>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">スタッフマニュアル</h1>
        <p class="page-subtitle">システムの使い方ガイド</p>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">← 活動管理へ戻る</a>

<!-- モバイル用オーバーレイ -->
<div class="manual-nav-overlay" id="manualNavOverlay" onclick="toggleManualNav()"></div>

<!-- モバイル用目次ボタン -->
<button class="manual-nav-toggle" onclick="toggleManualNav()" title="目次"><span class="material-symbols-outlined">menu_book</span></button>

<div class="manual-layout" id="manualLayout">
    <!-- 左サイドバー目次 -->
    <nav class="manual-nav" id="manualNav">
        <div class="manual-nav-title"><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">menu_book</span> 目次</div>
        <div class="manual-nav-list">
            <a href="#about" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">menu_book</span>きづりとは</a>
            <a href="#overview" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">home</span>システム概要</a>
            <a href="#menu" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">assignment</span>メニュー構成</a>
            <a href="#daily" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">today</span>毎日行うこと</a>
            <a href="#periodic" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">calendar_month</span>一定期間ごと</a>
            <a href="#basic" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">edit_note</span>基本的な使い方</a>
            <a href="#guardian" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">family_restroom</span>保護者機能</a>
            <a href="#student" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">school</span>生徒機能</a>
            <a href="#submission" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">upload_file</span>提出物管理</a>
            <a href="#kakehashi" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">handshake</span>かけはし管理</a>
            <a href="#schedule" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">event</span>書類スケジュール</a>
            <a href="#master" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">settings</span>マスタ管理</a>
            <a href="#faq" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">help</span>よくある質問</a>
            <a href="#tips" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">lightbulb</span>ヒントとコツ</a>
            <a href="#contact" class="manual-nav-link"><span class="material-symbols-outlined nav-icon">contact_support</span>お問い合わせ</a>
        </div>
    </nav>

    <!-- メインコンテンツ -->
    <div class="manual-main">
        <div class="manual-container">
            <!-- きづりとは -->
            <div class="section" id="about" style="border-left-color: var(--md-purple); background: linear-gradient(135deg, rgba(175, 82, 222, 0.08), rgba(0, 122, 255, 0.08));">
                <h2 style="color: var(--md-purple);"><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">menu_book</span> 「きづり（軌綴）」とは</h2>
                <p>
                    「きづり」は、放課後等デイサービス・児童発達支援向けの個別支援連絡帳システムです。
                    「<strong>軌</strong>」は子どもたちの成長の軌跡、「<strong>綴</strong>」はそれを記録し紡いでいくという意味を込めています。
                </p>
                <p>
                    日々の活動記録から個別支援計画の作成まで、AIを活用して業務を効率化し、より質の高い支援に集中できる環境を提供します。
                </p>

                <h3 style="border-left-color: var(--md-purple); color: var(--md-purple);"><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">handshake</span> 「かけはし」とは</h3>
                <p>
                    「かけはし」は、施設と家庭をつなぐ情報共有機能です。
                </p>
                <div class="feature-box">
                    <ul>
                        <li><strong>スタッフ</strong>：施設での様子や成長の記録を保護者に共有</li>
                        <li><strong>保護者</strong>：家庭での様子や願いをスタッフに共有</li>
                    </ul>
                </div>
                <p>
                    双方からの情報を元に、個別支援計画書をAIが分析・提案。お子様一人ひとりに最適な支援計画の作成をサポートします。
                </p>
            </div>

            <!-- システム概要 -->
            <div class="section" id="overview">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">home</span> システム概要</h2>
                <p>
                    個別支援連絡帳システムは、放課後等デイサービスにおける日々の活動記録、保護者・生徒とのコミュニケーション、
                    個別支援計画の作成を効率化するためのシステムです。
                </p>

                <div class="feature-box">
                    <div class="feature-title">システムでできること</div>
                    <ul>
                        <li><strong>活動管理</strong> - 支援案の作成、日々の活動記録、保護者への連絡帳送信</li>
                        <li><strong>チャット</strong> - 保護者・生徒との個別連絡、欠席連絡の受付</li>
                        <li><strong>かけはし</strong> - 施設・家庭での様子の記録と共有</li>
                        <li><strong>計画・支援</strong> - 個別支援計画書、モニタリング表の作成（AI支援機能付き）</li>
                        <li><strong>情報発信</strong> - 施設通信の作成、イベント管理</li>
                        <li><strong>管理</strong> - 生徒・保護者情報、休日・活動日の管理</li>
                    </ul>
                </div>
            </div>

            <!-- メニュー構成 -->
            <div class="section" id="menu">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">assignment</span> メニュー構成</h2>
                <p>
                    左側のサイドバー（PCの場合）または上部のメニュー（スマホの場合）から各機能にアクセスできます。
                </p>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">home</span> 活動管理</div>
                    <p>日々の活動記録と連絡帳管理のメイン画面です。ここから支援案の作成、活動記録、保護者への送信を行います。</p>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">sync</span> 振替管理</div>
                    <p>保護者からの振替希望を確認し、承認・却下を行います。承認した振替は参加予定者リストに自動反映されます。</p>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span> チャット</div>
                    <ul>
                        <li><strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">family_restroom</span> 保護者チャット</strong> - 保護者との1対1チャット。欠席連絡・イベント申込の受付も可能</li>
                        <li><strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">child_care</span> 生徒チャット</strong> - 生徒との個別チャット。複数生徒への一斉送信も可能</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">handshake</span> かけはし</div>
                    <ul>
                        <li><strong>かけはし（職員）</strong> - 施設での様子を記録し保護者と共有</li>
                        <li><strong>かけはし（保護者）</strong> - 保護者が入力した家庭での様子を確認</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 計画・支援</div>
                    <ul>
                        <li><strong>支援案</strong> - 活動前の計画を作成。五領域への配慮を記載</li>
                        <li><strong>週間計画</strong> - 生徒の週間目標と達成度を管理</li>
                        <li><strong>個別支援計画</strong> - 6ヶ月ごとの支援計画書を作成（AI支援機能付き）</li>
                        <li><strong>モニタリング</strong> - 支援計画の達成度評価を記録</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">upload_file</span> 提出物</div>
                    <ul>
                        <li><strong>生徒提出物</strong> - 生徒への提出依頼と進捗管理</li>
                        <li><strong>提出物管理</strong> - 保護者への提出依頼と進捗管理</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">newspaper</span> 情報発信</div>
                    <ul>
                        <li><strong>施設通信</strong> - 月間の施設通信を作成（AI支援機能付き）</li>
                        <li><strong>施設通信設定</strong> - 施設通信のテンプレート設定</li>
                        <li><strong>イベント</strong> - イベントの登録と参加者管理</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">settings</span> 管理・設定</div>
                    <ul>
                        <li><strong>生徒登録・変更</strong> - 生徒情報の登録・編集</li>
                        <li><strong>保護者登録・変更</strong> - 保護者アカウントの管理</li>
                        <li><strong>待機児童管理</strong> - 利用待ちの児童と曜日別定員を管理</li>
                        <li><strong>利用日一括変更</strong> - 生徒の利用日を一括で追加・キャンセル</li>
                        <li><strong>学校休業日設定</strong> - 夏休み等の活動日を設定</li>
                        <li><strong>休日設定</strong> - 施設の休日を登録</li>
                        <li><strong>マニュアル</strong> - このページ</li>
                        <li><strong>プロフィール</strong> - 自分のアカウント設定</li>
                    </ul>
                </div>
            </div>

            <!-- 毎日行うこと -->
            <div class="section" id="daily">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">event</span> 毎日行うこと</h2>
                <p>
                    以下は活動日に毎日行う業務の流れです。
                </p>

                <h3>1. 活動前（朝）</h3>
                <div class="step-box">
                    <span class="step-number">1</span>
                    <strong>支援案を確認</strong><br>
                    活動管理画面で本日の支援案を確認します。未作成の場合は「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">edit_note</span> 支援案を管理」から作成してください。
                </div>
                <div class="step-box">
                    <span class="step-number">2</span>
                    <strong>出席予定者を確認</strong><br>
                    活動管理画面に表示される出席予定者リストを確認します。欠席連絡がある場合はリストから除外されます。
                </div>
                <div class="step-box">
                    <span class="step-number">3</span>
                    <strong>チャットを確認</strong><br>
                    保護者チャットで欠席連絡や問い合わせがないか確認します。未読メッセージがある場合はバッジが表示されます。
                </div>

                <h3>2. 活動中</h3>
                <div class="step-box">
                    <span class="step-number">4</span>
                    <strong>活動記録を作成</strong><br>
                    活動管理画面で「新しい活動を追加」→支援案を選択→参加者ごとに「本日の様子」「気になったこと」を入力します。
                </div>

                <h3>3. 活動後（夕方）</h3>
                <div class="step-box">
                    <span class="step-number">5</span>
                    <strong>記録を統合</strong><br>
                    「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">sync</span> 統合する」ボタンでAIが保護者向けメッセージを生成します。内容を確認・編集してください。
                </div>
                <div class="step-box">
                    <span class="step-number">6</span>
                    <strong>保護者に送信</strong><br>
                    統合画面で「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">upload_file</span> 保護者に送信」ボタンを押すと、各保護者のダッシュボードに連絡帳が表示されます。
                </div>

                <div class="note-box">
                    <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> 送信前の確認ポイント:</strong>
                    <ul style="margin-top: 8px;">
                        <li>生徒の名前が正しいか</li>
                        <li>誤字脱字がないか</li>
                        <li>個人情報が含まれていないか（他の生徒の名前など）</li>
                        <li>保護者に適切な表現になっているか</li>
                    </ul>
                </div>

                <div class="tip-box">
                    <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 効率化のヒント:</strong>
                    <p style="margin-top: 8px;">
                        活動中にこまめに記録を入力しておくと、夕方の作業がスムーズになります。
                        複数スタッフで記録する場合も、それぞれが入力した内容が統合時に自動でまとめられます。
                    </p>
                </div>
            </div>

            <!-- 一定期間ごとにやるべきこと -->
            <div class="section" id="periodic">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">calendar_month</span> 一定期間ごとに行うこと</h2>

                <h3>週次（毎週）</h3>
                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 週間計画の確認</div>
                    <ul>
                        <li>生徒の週間計画を確認し、達成度を評価</li>
                        <li>必要に応じてコメントを追加</li>
                    </ul>
                </div>

                <h3>月次（毎月）</h3>
                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">newspaper</span> 施設通信の作成</div>
                    <ul>
                        <li>月末までに翌月の施設通信を作成</li>
                        <li>イベント予定、お知らせ、前月の活動報告を含める</li>
                        <li>AI生成機能で効率的に作成可能</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">calendar_month</span> 休日・イベントの登録</div>
                    <ul>
                        <li>翌月の休日を「休日設定」に登録</li>
                        <li>イベントがあれば「イベント」に登録</li>
                        <li>長期休暇期間は「学校休業日活動」で活動日を設定</li>
                    </ul>
                </div>

                <h3>6ヶ月ごと（生徒の初回利用日基準）</h3>
                <div class="color-box orange">
                    <p><strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> 重要:</strong> 以下の書類は生徒ごとに期限が異なります。「活動管理」画面の未作成タスクを定期的に確認してください。</p>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">handshake</span> かけはし作成（期限の1ヶ月前から）</div>
                    <ul>
                        <li><strong>保護者かけはし:</strong> 保護者に入力を依頼</li>
                        <li><strong>スタッフかけはし:</strong> 施設での様子・評価を記録</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span> モニタリング表作成（期限の1ヶ月前から）</div>
                    <ul>
                        <li>前回の個別支援計画の達成度を評価</li>
                        <li>AI支援機能で評価文を生成可能</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">description</span> 個別支援計画書作成（期限当月）</div>
                    <ul>
                        <li>かけはし・モニタリングを参考に作成</li>
                        <li>AI支援機能で素案を生成可能</li>
                        <li>保護者に確認依頼を送信</li>
                    </ul>
                </div>

                <div class="tip-box">
                    <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 計画的な作業のコツ:</strong>
                    <p style="margin-top: 8px;">
                        期限の1ヶ月前になったら、まず保護者にかけはし入力を依頼しましょう。
                        保護者の入力を待つ間に、スタッフかけはしとモニタリングを作成しておくと効率的です。
                    </p>
                </div>
            </div>

            <!-- 基本的な使い方 -->
            <div class="section" id="basic">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">edit_note</span> 基本的な使い方</h2>

                <h3>1. 支援案の作成</h3>
                <p>活動を実施する前に、支援案（事前計画）を作成します。</p>

                <div class="step-box">
                    <span class="step-number">1</span>
                    活動管理ページで「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">edit_note</span> 支援案を管理」ボタンをクリック
                </div>
                <div class="step-box">
                    <span class="step-number">2</span>
                    「+ 新しい支援案を作成」ボタンをクリック
                </div>
                <div class="step-box">
                    <span class="step-number">3</span>
                    以下の項目を入力：
                    <ul style="margin-top: 8px; margin-left: 20px;">
                        <li><strong>活動予定日</strong>: 活動を実施する日付</li>
                        <li><strong>活動名</strong>: 活動のタイトル</li>
                        <li><strong>活動の目的</strong>: この活動で目指すこと</li>
                        <li><strong>活動の内容</strong>: 具体的な活動内容（連絡帳の「本日の活動」に自動反映されます）</li>
                        <li><strong>五領域への配慮</strong>: 健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性</li>
                        <li><strong>その他</strong>: 特記事項や注意点</li>
                    </ul>
                </div>

                <div class="tip-box">
                    <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> ヒント:</strong> 過去の支援案を引用して編集することもできます。「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">assignment</span> 過去の支援案を引用する」ボタンから検索できます。
                </div>

                <h3>2. 活動の記録作成</h3>
                <p>活動実施後、各生徒の様子を記録します。</p>

                <div class="step-box">
                    <span class="step-number">1</span>
                    活動管理ページで日付を選択し「新しい活動を追加」ボタンをクリック
                </div>
                <div class="step-box">
                    <span class="step-number">2</span>
                    支援案を選択（その日の支援案が自動的に表示されます）
                </div>
                <div class="step-box">
                    <span class="step-number">3</span>
                    参加者を選択し、各生徒について記録：
                    <ul style="margin-top: 8px; margin-left: 20px;">
                        <li><strong>本日の活動（共通）</strong>: 支援案の内容が自動入力されます</li>
                        <li><strong>本日の様子</strong>: 生徒の様子や取り組みの様子</li>
                        <li><strong>気になったこと1</strong>: 特に注目した点や成長</li>
                        <li><strong>気になったこと2</strong>: さらなる気づきや課題</li>
                        <li><strong>五領域チェック</strong>: 関連する領域にチェック</li>
                    </ul>
                </div>
                <div class="step-box">
                    <span class="step-number">4</span>
                    最後に「確定して保存」または「全体をこの内容で保存」ボタンで保存
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 個別保存機能（編集時のみ）</div>
                    <p>既存の活動を編集する際、生徒ごとに個別に保存できます。</p>
                    <ul>
                        <li><strong>変更があった生徒のみ保存:</strong> 入力内容を変更すると、その生徒の入力欄に「この生徒の修正を保存」ボタンが表示されます</li>
                        <li><strong>画面遷移なし:</strong> ボタンを押すとその生徒のデータのみ保存され、入力画面にとどまります</li>
                        <li><strong>完了メッセージ:</strong> 保存が完了すると、ボタンが「修正が完了しました」と表示されます</li>
                        <li><strong>再編集可能:</strong> 3秒後にボタンが元に戻り、再度編集・保存が可能になります</li>
                    </ul>
                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 使い分けのヒント:</strong>
                        <p style="margin-top: 8px;">
                            複数の生徒を編集中に、一人ずつ確実に保存したい場合は「この生徒の修正を保存」を使い、全員分まとめて保存する場合は「全体をこの内容で保存」を使います。
                        </p>
                    </div>
                </div>

                <h3>3. 記録の統合と送信</h3>
                <p>複数のスタッフが記録した内容を統合し、保護者向けメッセージを生成します。</p>

                <div class="step-box">
                    <span class="step-number">1</span>
                    活動管理ページで該当の活動を探す
                </div>
                <div class="step-box">
                    <span class="step-number">2</span>
                    「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">sync</span> 統合する」ボタンをクリック（初めて統合する場合）<br>
                    または「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">edit</span> 統合内容を編集」ボタンをクリック（既に統合済みの場合）
                </div>
                <div class="step-box">
                    <span class="step-number">3</span>
                    AIが自動生成した保護者向けメッセージを確認・編集
                </div>
                <div class="step-box">
                    <span class="step-number">4</span>
                    「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">save</span> 統合内容を保存（下書き）」で下書き保存、または「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">upload_file</span> 保護者に送信」で送信
                </div>

                <div class="note-box">
                    <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> 注意:</strong>
                    <ul style="margin-top: 8px;">
                        <li>「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">sync</span> 統合する」は未送信の統合内容を削除して1から作り直します</li>
                        <li>「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">edit</span> 統合内容を編集」は最後に保存した内容を編集できます</li>
                        <li>統合内容は5分ごとに自動保存されます（Ctrl+Sで手動保存も可能）</li>
                    </ul>
                </div>
            </div>

            <!-- 保護者機能 -->
            <div class="section" id="guardian">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">family_restroom</span> 保護者機能</h2>
                <p>保護者とのコミュニケーションと提出物管理を行います。</p>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span> 保護者チャット</div>
                    <p>保護者と1対1でチャットができます。</p>
                    <ul>
                        <li>テキストメッセージの送受信</li>
                        <li>ファイル添付（最大3MB、1ヶ月間保存）</li>
                        <li>欠席連絡の受信（ピンク色で表示）</li>
                        <li>イベント参加申し込みの受信（青色で表示）</li>
                        <li>生徒を学部別（小学生・中学生・高校生）に分類して表示</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> チャットの使い方:</strong>
                        <p style="margin-top: 8px;">
                            左側のリストから生徒を選択するとチャット画面が表示されます。メッセージ入力欄にテキストを入力し、必要に応じてファイルを添付して送信できます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 提出期限管理</div>
                    <p>保護者に提出物の期限を通知し、進捗を管理できます。</p>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">提出期限の設定方法</h3>
                    <div class="step-box">
                        <span class="step-number">1</span>
                        保護者チャット画面で「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">event</span> 提出期限を設定」ボタンをクリック
                    </div>
                    <div class="step-box">
                        <span class="step-number">2</span>
                        件名、説明、期限日を入力（ファイル添付も可能）
                    </div>
                    <div class="step-box">
                        <span class="step-number">3</span>
                        自動的にチャットに通知メッセージが送信されます
                    </div>
                    <div class="step-box">
                        <span class="step-number">4</span>
                        保護者のダッシュボードに期限アラートが表示されます
                    </div>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">提出状況の管理</h3>
                    <ul style="margin-left: 20px;">
                        <li>「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">assignment</span> 提出期限管理」ページで全体の進捗を確認</li>
                        <li>保護者のダッシュボードには優先度別にアラート表示：
                            <ul style="margin-left: 20px; margin-top: 5px;">
                                <li><strong>期限超過</strong> - 赤/グレーバナー</li>
                                <li><strong>3日以内</strong> - 赤バナー（緊急）</li>
                                <li><strong>それ以降</strong> - 青バナー（通常）</li>
                            </ul>
                        </li>
                        <li>完了/未完了の切り替えが可能</li>
                        <li>完了したものは統計に集計されます</li>
                    </ul>
                </div>
            </div>

            <!-- 生徒機能 -->
            <div class="section" id="student">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">school</span> 生徒機能</h2>
                <p>生徒とのコミュニケーションと学習計画の管理を行います。</p>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span> 生徒チャット</div>
                    <p>生徒と個別にチャットができます。</p>
                    <ul>
                        <li>テキストメッセージの送受信</li>
                        <li>ファイル添付機能</li>
                        <li>生徒を学部別・在籍状況別に検索・フィルター</li>
                        <li>複数の生徒を選択して一斉送信が可能</li>
                    </ul>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">一斉送信機能</h3>
                    <div class="step-box">
                        <span class="step-number">1</span>
                        生徒一覧で右側のチェックボックスをクリックして生徒を選択
                    </div>
                    <div class="step-box">
                        <span class="step-number">2</span>
                        画面下部に表示される「一斉送信」バーをクリック
                    </div>
                    <div class="step-box">
                        <span class="step-number">3</span>
                        メッセージとファイル（任意）を入力して送信
                    </div>
                    <div class="step-box">
                        <span class="step-number">4</span>
                        選択したすべての生徒に同じメッセージが送信されます
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 週間計画表</div>
                    <p>生徒の週間計画を確認し、達成度を評価できます。</p>
                    <ul>
                        <li>生徒が設定した週間目標の確認</li>
                        <li>日々の達成状況のチェック</li>
                        <li>スタッフによる達成度評価とコメント</li>
                        <li>保護者との共有</li>
                    </ul>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">5段階達成度評価</h3>
                    <p>週の終わりに、スタッフが生徒の達成度を5段階で評価できます。</p>

                    <table class="schedule-table" style="font-size: 13px; margin-top: 10px;">
                        <tbody>
                            <tr>
                                <td style="text-align: center; width: 80px;"><strong>5</strong></td>
                                <td>とてもよくできた - 目標を大きく上回る達成</td>
                            </tr>
                            <tr>
                                <td style="text-align: center;"><strong>4</strong></td>
                                <td>よくできた - 目標を達成</td>
                            </tr>
                            <tr>
                                <td style="text-align: center;"><strong>3</strong></td>
                                <td>ふつう - 概ね達成</td>
                            </tr>
                            <tr>
                                <td style="text-align: center;"><strong>2</strong></td>
                                <td>もう少し - 一部未達成</td>
                            </tr>
                            <tr>
                                <td style="text-align: center;"><strong>1</strong></td>
                                <td>がんばろう - 達成できなかった</td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">評価項目</h3>
                    <ul>
                        <li><strong>今週の目標</strong> - 生徒が設定した週間目標の達成度</li>
                        <li><strong>いっしょに決めた目標</strong> - スタッフと一緒に設定した目標</li>
                        <li><strong>やるべきこと</strong> - 必須タスクの達成度</li>
                        <li><strong>やったほうがいいこと</strong> - 推奨タスクの達成度</li>
                        <li><strong>やりたいこと</strong> - 自主的な活動の達成度</li>
                        <li><strong>各曜日の計画</strong> - 曜日ごとの計画達成度</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 評価のタイミング:</strong>
                        <p style="margin-top: 8px;">
                            翌週の計画を見るときに前週の達成度評価を入力できます。評価は保護者と共有され、生徒の成長記録として活用できます。
                        </p>
                    </div>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> 注意:</strong> 週間計画表は生徒自身がログインして設定する必要があります。生徒用ログイン情報は「生徒登録・変更」ページで設定できます。
                    </div>
                </div>
            </div>

            <!-- 提出物管理 -->
            <div class="section" id="submission">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">upload_file</span> 提出物管理</h2>
                <p>生徒と保護者への提出物を一元管理します。</p>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 生徒提出物</div>
                    <p>生徒が自分で管理する提出物（宿題、レポート等）を一覧で確認できます。</p>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">確認できる情報</h3>
                    <ul>
                        <li><strong>週間計画の提出物</strong> - 週間計画表に登録された課題</li>
                        <li><strong>保護者経由の提出物</strong> - 保護者チャットで設定した提出期限</li>
                        <li><strong>生徒自身の登録</strong> - 生徒がマイページで登録した提出物</li>
                    </ul>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">ステータス表示</h3>
                    <table class="schedule-table" style="font-size: 13px;">
                        <tbody>
                            <tr>
                                <td style="background: rgba(255, 59, 48, 0.15); width: 100px;"><strong>期限超過</strong></td>
                                <td>期限を過ぎた未完了の提出物（赤色表示）</td>
                            </tr>
                            <tr>
                                <td style="background: rgba(255, 149, 0, 0.15);"><strong>期限間近</strong></td>
                                <td>3日以内に期限が迫っている提出物（オレンジ表示）</td>
                            </tr>
                            <tr>
                                <td style="background: rgba(52, 199, 89, 0.15);"><strong>完了</strong></td>
                                <td>提出済みの提出物（緑色表示）</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 活用のヒント:</strong>
                        <p style="margin-top: 8px;">
                            生徒提出物一覧で各生徒の提出状況を確認し、期限が近い・過ぎている場合は声かけをしましょう。生徒チャットで直接リマインドすることもできます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 提出物管理（保護者向け）</div>
                    <p>保護者への提出依頼を一括管理します。</p>
                    <ul>
                        <li>チャットで設定した全ての提出期限を一覧表示</li>
                        <li>完了/未完了のステータス管理</li>
                        <li>期限超過の自動アラート表示</li>
                        <li>生徒別・期限別でのソート</li>
                    </ul>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> 提出物の設定:</strong> 提出期限の設定は保護者チャット画面から行います。「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">event</span> 提出期限を設定」ボタンをクリックして作成してください。
                    </div>
                </div>
            </div>

            <!-- かけはし管理 -->
            <div class="section" id="kakehashi">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">handshake</span> かけはし管理</h2>
                <p>引継ぎ記録から支援計画まで、生徒の成長を総合的に管理します。</p>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit</span> スタッフかけはし入力</div>
                    <p>施設での様子を保護者に伝えます。</p>
                    <ul>
                        <li>生徒を選択して日々の記録を入力</li>
                        <li>期間を設定して一括入力も可能</li>
                        <li>過去の記録の閲覧・編集</li>
                        <li>保護者ダッシュボードに自動的に表示されます</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 効率的な入力方法:</strong>
                        <p style="margin-top: 8px;">
                            複数日分をまとめて入力する場合は、期間設定機能を使うと効率的です。一度の入力で複数の日付に同じ内容を記録できます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 保護者かけはし確認</div>
                    <p>保護者が入力した家庭での様子を確認できます。</p>
                    <ul>
                        <li>生徒ごとに保護者の記録を閲覧</li>
                        <li>期間を指定して検索</li>
                        <li>スタッフコメント機能で返信も可能</li>
                        <li>未確認の記録を確認済みにマーク</li>
                    </ul>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> 重要:</strong> 保護者からの記録は定期的に確認し、必要に応じてコメントで返信しましょう。保護者との信頼関係構築に役立ちます。
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">description</span> 個別支援計画書作成</div>
                    <p>生徒ごとの支援計画をAIの支援を受けながら作成できます。</p>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">作成手順</h3>
                    <div class="step-box">
                        <span class="step-number">1</span>
                        「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">handshake</span> かけはし管理」→「<span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">description</span> 個別支援計画書作成」を選択
                    </div>
                    <div class="step-box">
                        <span class="step-number">2</span>
                        生徒と対象期間（開始日・終了日）を選択
                    </div>
                    <div class="step-box">
                        <span class="step-number">3</span>
                        「AIで生成」ボタンをクリック（過去の記録から素案を自動生成）
                    </div>
                    <div class="step-box">
                        <span class="step-number">4</span>
                        生成された内容を確認・編集
                    </div>
                    <div class="step-box">
                        <span class="step-number">5</span>
                        保存してPDF出力も可能
                    </div>
                    <div class="step-box">
                        <span class="step-number">6</span>
                        保護者に確認依頼を送信
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span> モニタリング表作成</div>
                    <p>支援計画の実施状況を記録・評価します。</p>
                    <ul>
                        <li>個別支援計画に基づいた目標の評価</li>
                        <li>AIによる評価文の生成支援</li>
                        <li>達成度の記録（A/B/C評価など）</li>
                        <li>保護者への共有・確認依頼</li>
                        <li>PDF出力機能</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 効果的な評価のコツ:</strong>
                        <p style="margin-top: 8px;">
                            モニタリング期間中の「かけはし」記録や活動記録を参照しながら評価すると、より具体的で説得力のある評価文が作成できます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">newspaper</span> 施設通信を作成</div>
                    <p>保護者全員に向けた通信を作成・配信できます。</p>
                    <ul>
                        <li>季節のお便りやイベント案内を作成</li>
                        <li>画像やファイルの添付が可能</li>
                        <li>配信履歴の管理</li>
                        <li>保護者ダッシュボードに自動表示</li>
                    </ul>
                </div>
            </div>

            <!-- 書類作成スケジュール -->
            <div class="section" id="schedule">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">event</span> 書類作成スケジュールと期限ルール</h2>
                <p>
                    かけはし・個別支援計画書・モニタリング表は、決まったサイクルで作成する必要があります。
                    以下の表とルールを参考に、計画的に作成してください。
                </p>

                <!-- 基本サイクル説明 -->
                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">sync</span> 基本サイクル（6ヶ月周期）</div>
                    <p style="margin-bottom: 15px;">個別支援計画書は<strong>初回利用日から6ヶ月ごと</strong>に作成します。</p>

                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th style="text-align: left;">書類名</th>
                                <th style="text-align: center;">作成周期</th>
                                <th style="text-align: center;">期限の目安</th>
                                <th style="text-align: left;">備考</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>かけはし（保護者）</strong></td>
                                <td style="text-align: center;">6ヶ月ごと</td>
                                <td style="text-align: center;"><strong>個別支援計画期限の<br>1ヶ月前</strong></td>
                                <td>保護者が家庭での様子・願いを入力</td>
                            </tr>
                            <tr>
                                <td><strong>かけはし（スタッフ）</strong></td>
                                <td style="text-align: center;">6ヶ月ごと</td>
                                <td style="text-align: center;"><strong>個別支援計画期限の<br>1ヶ月前</strong></td>
                                <td>スタッフが施設での様子・評価を入力</td>
                            </tr>
                            <tr>
                                <td><strong>個別支援計画書</strong></td>
                                <td style="text-align: center;">6ヶ月ごと</td>
                                <td style="text-align: center;"><strong>初回利用日から<br>6ヶ月ごと</strong></td>
                                <td>かけはしを参照して作成</td>
                            </tr>
                            <tr>
                                <td><strong>モニタリング表</strong></td>
                                <td style="text-align: center;">6ヶ月ごと</td>
                                <td style="text-align: center;"><strong>個別支援計画期限の<br>1ヶ月前</strong></td>
                                <td>前回計画の評価・振り返り</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- タイムライン図 -->
                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span> 作成タイムライン（例：4月開始の場合）</div>
                    <p style="margin-bottom: 15px;">初回利用日が<strong>4月1日</strong>の生徒の場合のスケジュール例：</p>

                    <div style="overflow-x: auto;">
                        <table class="timeline-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">時期</th>
                                    <th style="width: 20%;">かけはし<br>（保護者・スタッフ）</th>
                                    <th style="width: 20%;">個別支援計画書</th>
                                    <th style="width: 20%;">モニタリング表</th>
                                    <th style="width: 25%;">備考</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>4月</strong></td>
                                    <td>初回作成</td>
                                    <td>初回作成</td>
                                    <td>-</td>
                                    <td>利用開始時</td>
                                </tr>
                                <tr>
                                    <td><strong>5月〜8月</strong></td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>日々の支援実施期間</td>
                                </tr>
                                <tr class="highlight-row">
                                    <td><strong>9月</strong><br><small class="alert-orange">（5ヶ月目）</small></td>
                                    <td><strong class="alert-orange">作成開始</strong><br><small>提出期限: 9月末</small></td>
                                    <td>-</td>
                                    <td><strong class="alert-pink">作成</strong><br><small>前回計画の評価</small></td>
                                    <td><strong class="alert-orange">10月の計画作成準備</strong></td>
                                </tr>
                                <tr class="success-row">
                                    <td><strong>10月</strong><br><small class="alert-green">（6ヶ月目）</small></td>
                                    <td>-</td>
                                    <td><strong class="alert-green">作成・提出</strong><br><small>期限: 10月</small></td>
                                    <td>-</td>
                                    <td><strong class="alert-green">2期目開始</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>11月〜2月</strong></td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>日々の支援実施期間</td>
                                </tr>
                                <tr class="highlight-row">
                                    <td><strong>3月</strong><br><small class="alert-orange">（11ヶ月目）</small></td>
                                    <td><strong class="alert-orange">作成開始</strong><br><small>提出期限: 3月末</small></td>
                                    <td>-</td>
                                    <td><strong class="alert-pink">作成</strong><br><small>前回計画の評価</small></td>
                                    <td><strong class="alert-orange">4月の計画作成準備</strong></td>
                                </tr>
                                <tr class="success-row">
                                    <td><strong>翌4月</strong><br><small class="alert-green">（12ヶ月目）</small></td>
                                    <td>-</td>
                                    <td><strong class="alert-green">作成・提出</strong><br><small>期限: 4月</small></td>
                                    <td>-</td>
                                    <td><strong class="alert-green">3期目開始</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 期限ルールの詳細 -->
                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">hourglass_empty</span> 期限ルール詳細</div>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">個別支援計画書の期限計算</h3>
                    <div class="color-box green">
                        <p><strong>基準日:</strong> 生徒の初回利用日（支援開始日）</p>
                        <p><strong>計算式:</strong> 初回利用日 + (6ヶ月 × n回目)</p>
                        <p style="margin-top: 10px;"><strong>例:</strong> 初回利用日が2024年4月15日の場合</p>
                        <ul style="margin-left: 20px; margin-top: 5px;">
                            <li>1回目の期限: 2024年10月15日</li>
                            <li>2回目の期限: 2025年4月15日</li>
                            <li>3回目の期限: 2025年10月15日</li>
                        </ul>
                    </div>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">かけはし・モニタリングの期限計算</h3>
                    <div class="color-box orange">
                        <p><strong>基準日:</strong> 次回個別支援計画書の期限</p>
                        <p><strong>計算式:</strong> 個別支援計画書期限 - 1ヶ月</p>
                        <p style="margin-top: 10px;"><strong>例:</strong> 個別支援計画書の期限が2024年10月15日の場合</p>
                        <ul style="margin-left: 20px; margin-top: 5px;">
                            <li>かけはし（保護者）提出期限: 2024年9月15日</li>
                            <li>かけはし（スタッフ）作成期限: 2024年9月15日</li>
                            <li>モニタリング表作成期限: 2024年9月15日</li>
                        </ul>
                    </div>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">システム上のアラート表示</h3>
                    <div class="color-box pink">
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                            <tr>
                                <td style="padding: 8px; width: 30%;"><strong class="alert-red">期限切れ</strong></td>
                                <td style="padding: 8px;">期限を過ぎたタスク（赤色で表示）</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px;"><strong class="alert-orange">1ヶ月以内</strong></td>
                                <td style="padding: 8px;">期限まで30日以内（オレンジ色で表示）</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px;"><strong class="alert-blue">通常</strong></td>
                                <td style="padding: 8px;">期限まで30日以上（青色で表示）</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 作成の流れ -->
                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 作成の流れ</div>

                    <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 15px;">
                        <div class="step-card orange">
                            <h4>STEP 1: かけはし作成</h4>
                            <p>期限の<strong>1ヶ月前</strong>から開始</p>
                            <ul style="margin-top: 8px; margin-left: 15px;">
                                <li>保護者にかけはし入力を依頼</li>
                                <li>スタッフは施設での様子を記録</li>
                                <li>両方が揃うまで待機</li>
                            </ul>
                        </div>

                        <div class="step-card pink">
                            <h4>STEP 2: モニタリング作成</h4>
                            <p>かけはしと<strong>並行して</strong>作成</p>
                            <ul style="margin-top: 8px; margin-left: 15px;">
                                <li>前回の個別支援計画を評価</li>
                                <li>目標の達成度を記録</li>
                                <li>次期計画への引継ぎ事項をまとめる</li>
                            </ul>
                        </div>

                        <div class="step-card green">
                            <h4>STEP 3: 個別支援計画書作成</h4>
                            <p>期限<strong>当月</strong>に作成</p>
                            <ul style="margin-top: 8px; margin-left: 15px;">
                                <li>かけはし（保護者・スタッフ）を参照</li>
                                <li>モニタリングの評価を反映</li>
                                <li>新しい目標を設定</li>
                                <li>保護者に確認・同意を依頼</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="note-box">
                    <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> 重要な注意点:</strong>
                    <ul style="margin-top: 8px;">
                        <li>かけはし（保護者・スタッフ）が揃わないと、質の高い個別支援計画書が作成できません</li>
                        <li>モニタリングは前回の計画の振り返りなので、初回利用時は不要です</li>
                        <li>期限を過ぎると「未作成タスク」ページに表示されます</li>
                        <li>生徒ごとに初回利用日が異なるため、期限も生徒ごとに異なります</li>
                    </ul>
                </div>

                <div class="tip-box">
                    <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 効率的な作成のコツ:</strong>
                    <p style="margin-top: 8px;">
                        「未作成・未提出タスク」ページ（活動管理 → 未作成タスク）を定期的に確認し、期限が近づいているタスクから優先的に対応しましょう。
                        システムが自動的に期限を計算して表示するので、漏れなく管理できます。
                    </p>
                </div>
            </div>

            <!-- マスタ管理 -->
            <div class="section" id="master">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">settings</span> マスタ管理</h2>
                <p>システムの基本情報を管理します。</p>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">group</span> 生徒管理</div>
                    <p>生徒情報の登録・編集を行います。</p>
                    <ul>
                        <li>基本情報の登録（氏名、生年月日、支援開始日）</li>
                        <li>保護者アカウントとの紐付け</li>
                        <li>在籍状況の管理（在籍/体験/短期利用/退所）</li>
                        <li>学年の自動計算と調整（-2～+2学年）</li>
                        <li>参加予定曜日の設定</li>
                        <li>生徒用ログイン情報の設定</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 学年調整機能:</strong>
                        <p style="margin-top: 8px;">
                            生年月日から自動計算される学年が実際と異なる場合、学年調整機能で-2～+2学年の範囲で調整できます。これにより、飛び級や留年などのケースにも対応できます。
                        </p>
                    </div>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> 生徒用ログイン:</strong> 生徒が週間計画表を使用する場合は、生徒用のユーザー名とパスワードを設定してください。保護者用ログインとは別に管理されます。
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">family_restroom</span> 保護者管理</div>
                    <p>保護者アカウントの作成・管理を行います。</p>
                    <ul>
                        <li>保護者アカウントの新規作成</li>
                        <li>ログイン情報（ユーザー名・パスワード）の管理</li>
                        <li>複数の子どもの紐付け</li>
                        <li>連絡先情報の管理</li>
                        <li>教室への所属設定</li>
                        <li>アカウントの削除</li>
                    </ul>

                    <div class="step-box">
                        <span class="step-number">1</span>
                        「新しい保護者を追加」ボタンをクリック
                    </div>
                    <div class="step-box">
                        <span class="step-number">2</span>
                        氏名、ユーザー名、パスワードを入力
                    </div>
                    <div class="step-box">
                        <span class="step-number">3</span>
                        保存後、生徒管理画面で生徒と紐付け
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">calendar_month</span> 休日管理</div>
                    <p>施設の休日を登録・管理します。</p>
                    <ul>
                        <li>休日の種類別登録（国民の祝日/施設休日/臨時休業）</li>
                        <li>休日名と日付の設定</li>
                        <li>保護者カレンダーに自動表示</li>
                        <li>参加予定者の自動計算から除外</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 休日の種類:</strong>
                        <p style="margin-top: 8px;">
                            休日の種類により、カレンダー上の表示色が変わります。国民の祝日は赤、施設休日はオレンジ、臨時休業はグレーで表示されます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> イベント管理</div>
                    <p>施設のイベント情報を登録・管理します。</p>
                    <ul>
                        <li>イベント名、日付、説明の登録</li>
                        <li>イベントカラーの設定（カレンダー表示用）</li>
                        <li>保護者がチャットから参加申し込み可能</li>
                        <li>参加者一覧の確認</li>
                        <li>活動管理画面の出席予定者リストに表示</li>
                    </ul>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> イベント参加申し込み:</strong> 保護者はチャット画面から「イベント参加」を選択してイベントに申し込みます。スタッフは活動管理画面で参加者を確認できます。
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">hourglass_empty</span> 待機児童管理</div>
                    <p>利用待ちの児童と教室の定員を管理します。</p>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">曜日別定員設定</h3>
                    <ul>
                        <li>各曜日の最大定員を設定</li>
                        <li>営業日/休業日を曜日ごとに設定</li>
                        <li>現在の利用者数と空き状況を一覧表示</li>
                    </ul>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">待機児童の登録・管理</h3>
                    <div class="step-box">
                        <span class="step-number">1</span>
                        生徒登録画面で「待機」ステータスを選択して登録
                    </div>
                    <div class="step-box">
                        <span class="step-number">2</span>
                        希望利用曜日を設定
                    </div>
                    <div class="step-box">
                        <span class="step-number">3</span>
                        空きが出たら「在籍」に変更して正式利用開始
                    </div>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">ダッシュボード表示</h3>
                    <table class="schedule-table" style="font-size: 13px;">
                        <tbody>
                            <tr>
                                <td style="background: rgba(52, 199, 89, 0.15);"><strong>空きあり</strong></td>
                                <td>定員に余裕がある曜日（緑色表示）</td>
                            </tr>
                            <tr>
                                <td style="background: rgba(255, 149, 0, 0.15);"><strong>残りわずか</strong></td>
                                <td>残り2〜3名の曜日（オレンジ表示）</td>
                            </tr>
                            <tr>
                                <td style="background: rgba(255, 59, 48, 0.15);"><strong>満員</strong></td>
                                <td>定員に達した曜日（赤色表示）</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 活用のヒント:</strong>
                        <p style="margin-top: 8px;">
                            待機児童の希望曜日と空き状況を照らし合わせることで、効率的な利用調整ができます。空きが出た曜日を希望している待機児童に優先的に連絡しましょう。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> 利用日一括変更</div>
                    <p>生徒の利用日を追加・キャンセルできます。</p>
                    <ul>
                        <li>生徒を選択してカレンダーを表示</li>
                        <li>チェックボックスで利用日のON/OFFを切り替え</li>
                        <li>通常利用日のキャンセル → 保護者に自動通知</li>
                        <li>追加利用日の登録 → 参加予定者に反映</li>
                    </ul>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">カレンダーの見方</h3>
                    <table class="schedule-table" style="font-size: 13px;">
                        <tbody>
                            <tr>
                                <td style="background: rgba(0, 122, 255, 0.1);"><strong>青背景</strong></td>
                                <td>通常利用日（生徒の登録曜日）</td>
                            </tr>
                            <tr>
                                <td><span style="background: rgba(52, 199, 89, 0.15); color: #059669; padding: 2px 6px; border-radius: 3px; font-size: 11px;">追加</span></td>
                                <td>追加利用日として登録済み</td>
                            </tr>
                            <tr>
                                <td><span style="background: rgba(255, 59, 48, 0.15); color: #dc2626; padding: 2px 6px; border-radius: 3px; font-size: 11px;">キャンセル</span></td>
                                <td>利用をキャンセル済み</td>
                            </tr>
                            <tr>
                                <td style="background: rgba(255, 59, 48, 0.05);"><strong>赤背景</strong></td>
                                <td>休日（操作不可）</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 使い方:</strong>
                        <p style="margin-top: 8px;">
                            チェックを入れると「利用する」、外すと「利用しない」になります。通常利用日をキャンセルすると、保護者チャットに自動で通知が送信されます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">sync</span> 振替管理</div>
                    <p>保護者からの振替希望を管理します。</p>
                    <ul>
                        <li>保護者が欠席連絡時に振替希望日を選択</li>
                        <li>スタッフが承認/却下を判断</li>
                        <li>承認した振替は参加予定者リストに自動反映</li>
                    </ul>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">振替の流れ</h3>
                    <div class="step-box">
                        <span class="step-number">1</span>
                        保護者がチャットで欠席連絡 + 振替希望日を選択
                    </div>
                    <div class="step-box">
                        <span class="step-number">2</span>
                        「振替管理」ページに承認待ちとして表示
                    </div>
                    <div class="step-box">
                        <span class="step-number">3</span>
                        スタッフが「承認」または「却下」をクリック
                    </div>
                    <div class="step-box">
                        <span class="step-number">4</span>
                        承認された振替は、当日の参加予定者に自動表示
                    </div>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">warning</span> 注意:</strong> 振替希望日が未設定の欠席連絡は、通常の欠席として処理されます。振替管理ページには表示されません。
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">school</span> 学校休業日活動設定</div>
                    <p>夏休み・春休み等の学校休業期間に活動する日を設定します。</p>
                    <ul>
                        <li>カレンダーで活動日をチェック</li>
                        <li>保護者・生徒カレンダーに「学休」ラベルで表示</li>
                        <li>チェックなしの日は「平日」として表示</li>
                    </ul>

                    <h3 style="font-size: var(--text-callout); margin: 15px 0 10px 0; color: var(--primary-purple);">カレンダー表示</h3>
                    <table class="schedule-table" style="font-size: 13px;">
                        <tbody>
                            <tr>
                                <td><span style="background: rgba(59, 130, 246, 0.25); color: #2563eb; padding: 2px 6px; border-radius: 3px; font-size: 11px;">学休</span></td>
                                <td>学校休業日活動（夏休み・春休み等）</td>
                            </tr>
                            <tr>
                                <td><span style="background: rgba(52, 199, 89, 0.25); color: #059669; padding: 2px 6px; border-radius: 3px; font-size: 11px;">平日</span></td>
                                <td>通常の平日活動</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong><span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">lightbulb</span> 設定のタイミング:</strong>
                        <p style="margin-top: 8px;">
                            長期休暇（夏休み・冬休み・春休み）の前に、活動を行う日を設定してください。保護者・生徒のカレンダーに反映され、活動日がわかりやすくなります。
                        </p>
                    </div>
                </div>
            </div>

            <!-- よくある質問 -->
            <div class="section" id="faq">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">help</span> よくある質問</h2>

                <div class="feature-box">
                    <div class="feature-title">Q: 各メニューはどこから アクセスできますか？</div>
                    <p>
                        A: 画面上部のドロップダウンメニューからアクセスできます。
                    </p>
                    <ul>
                        <li><strong>保護者</strong> - 保護者チャット、提出期限管理</li>
                        <li><strong>生徒</strong> - 生徒チャット、週間計画表</li>
                        <li><strong>かけはし管理</strong> - かけはし入力/確認、個別支援計画書、モニタリング表、施設通信</li>
                        <li><strong>マスタ管理</strong> - 生徒管理、保護者管理、休日管理、イベント管理</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">Q: 保護者チャットと生徒チャットの違いは何ですか？</div>
                    <p>
                        A: 以下のように用途が異なります：
                    </p>
                    <ul>
                        <li><strong>保護者チャット</strong> - 保護者と1対1でコミュニケーション。欠席連絡やイベント申し込みを受け付けます。</li>
                        <li><strong>生徒チャット</strong> - 生徒本人とチャット。複数の生徒への一斉送信が可能です。</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">Q: 提出期限を設定したら保護者にどう表示されますか？</div>
                    <p>
                        A: 保護者ダッシュボードに優先度別にアラート表示されます：
                    </p>
                    <ul>
                        <li><strong>期限超過</strong> - 赤/グレーバナーで表示</li>
                        <li><strong>3日以内</strong> - 赤バナー（緊急）</li>
                        <li><strong>それ以降</strong> - 青バナー（通常）</li>
                    </ul>
                    <p style="margin-top: 8px;">
                        また、チャットにも自動的に通知メッセージが送信されます。
                    </p>
                </div>

                <div class="feature-box">
                    <div class="feature-title">Q: 他のスタッフが作成した記録を編集できますか？</div>
                    <p>
                        A: はい、同じ教室のスタッフが作成した記録は閲覧・編集できます。教室が異なるスタッフの記録は表示されません。
                    </p>
                </div>

                <div class="feature-box">
                    <div class="feature-title">Q: チャットの添付ファイルはいつまで保存されますか？</div>
                    <p>
                        A: 添付ファイルは1ヶ月間保存されます。それ以降は自動的に削除されます。重要なファイルは別途保存してください。
                    </p>
                </div>

                <div class="feature-box">
                    <div class="feature-title">Q: 個別支援計画書はAIで自動生成できますか？</div>
                    <p>
                        A: はい、過去の「かけはし」記録や活動記録をもとに、AIが素案を生成します。生成された内容を確認・編集して完成させることができます。
                    </p>
                </div>

                <div class="feature-box">
                    <div class="feature-title">Q: 生徒の学年が生年月日と合わないのですが？</div>
                    <p>
                        A: 生徒管理画面で「学年調整」機能を使用してください。-2～+2学年の範囲で調整できます。飛び級や留年などのケースに対応しています。
                    </p>
                </div>
            </div>

            <!-- ヒントとコツ -->
            <div class="section" id="tips">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">lightbulb</span> ヒントとコツ</h2>

                <div class="tip-box">
                    <strong>ドロップダウンメニューの活用</strong>
                    <p style="margin-top: 8px;">
                        各ページの上部にあるドロップダウンメニューから、すべての機能に素早くアクセスできます。ページ移動がスムーズになり、作業効率が向上します。
                    </p>
                </div>

                <div class="tip-box">
                    <strong>保護者との効果的なコミュニケーション</strong>
                    <p style="margin-top: 8px;">
                        チャットでの日常的なやり取りと、かけはしでの定期的な記録共有を組み合わせることで、保護者との信頼関係を深めることができます。急ぎの連絡はチャット、詳しい様子はかけはしで伝えましょう。
                    </p>
                </div>

                <div class="tip-box">
                    <strong>提出期限管理の活用</strong>
                    <p style="margin-top: 8px;">
                        保護者への提出依頼は、提出期限機能を使うと漏れなく管理できます。期限を過ぎた依頼は自動的に赤色で表示されるため、フォローアップも簡単です。
                    </p>
                </div>

                <div class="tip-box">
                    <strong>生徒チャットの一斉送信</strong>
                    <p style="margin-top: 8px;">
                        イベント案内や共通のお知らせは、生徒チャットの一斉送信機能を使うと効率的です。学部別や在籍状況でフィルタリングしてから送信できます。
                    </p>
                </div>

                <div class="tip-box">
                    <strong>個別支援計画書のAI活用</strong>
                    <p style="margin-top: 8px;">
                        AI生成機能を使う際は、対象期間中の「かけはし」記録を充実させておくと、より具体的で質の高い支援計画書が生成されます。日々の記録が計画書作成の基礎となります。
                    </p>
                </div>

                <div class="tip-box">
                    <strong>学年調整機能の活用</strong>
                    <p style="margin-top: 8px;">
                        発達の状況や教育上の配慮で、実年齢と異なる学年に所属している生徒には、学年調整機能を使いましょう。適切な学部（小学生・中学生・高校生）に分類されます。
                    </p>
                </div>

                <div class="tip-box">
                    <strong>イベント参加者の管理</strong>
                    <p style="margin-top: 8px;">
                        イベントを登録すると、保護者がチャットから参加申し込みできます。活動管理画面の出席予定者リストにイベント参加者も表示されるため、当日の準備がスムーズです。
                    </p>
                </div>
            </div>

            <!-- お問い合わせ -->
            <div class="section" id="contact">
                <h2><span class="material-symbols-outlined" style="font-size: 24px; vertical-align: middle;">contact_support</span> お問い合わせ</h2>
                <p>
                    システムの使い方で不明な点がある場合は、施設の管理者にお問い合わせください。
                </p>
                <p style="margin-top: 15px;">
                    <strong>ログイン中のユーザー:</strong> <?php echo htmlspecialchars($currentUser['full_name']); ?>さん
                </p>
            </div>
        </div>
    </div><!-- /.manual-main -->
</div><!-- /.manual-layout -->

<?php
$inlineJs = <<<JS
// 印刷機能
function printManual() {
    window.print();
}

// ショートカット: Ctrl+P で印刷
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printManual();
    }
});

// モバイル用目次トグル
function toggleManualNav() {
    const nav = document.getElementById('manualNav');
    const overlay = document.getElementById('manualNavOverlay');
    nav.classList.toggle('show');
    overlay.classList.toggle('show');
}

// トップに戻るボタン
document.addEventListener('DOMContentLoaded', function() {
    // ボタンを作成
    const backToTop = document.createElement('button');
    backToTop.className = 'back-to-top';
    backToTop.innerHTML = '↑';
    backToTop.title = 'トップに戻る';
    backToTop.onclick = function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    document.body.appendChild(backToTop);

    // スクロール時の表示制御とアクティブリンク更新
    const sections = document.querySelectorAll('.section[id]');
    const navLinks = document.querySelectorAll('.manual-nav-link');

    window.addEventListener('scroll', function() {
        // トップに戻るボタン
        if (window.scrollY > 300) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }

        // 現在表示中のセクションをハイライト
        let current = '';
        sections.forEach(function(section) {
            const sectionTop = section.offsetTop;
            if (window.scrollY >= sectionTop - 100) {
                current = section.getAttribute('id');
            }
        });

        navLinks.forEach(function(link) {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    });
});

// 目次リンクのスムーススクロール
document.querySelectorAll('.manual-nav-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
        const targetId = this.getAttribute('href').substring(1);
        const target = document.getElementById(targetId);
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // モバイルでは目次を閉じる
            if (window.innerWidth <= 900) {
                document.getElementById('manualNav').classList.remove('show');
                document.getElementById('manualNavOverlay').classList.remove('show');
            }
        }
    });
});
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
