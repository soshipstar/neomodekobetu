<?php
/**
 * 保護者向けマニュアルページ
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireLogin();
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /index.php');
    exit;
}

$currentUser = getCurrentUser();
$pdo = getDbConnection();

// 教室情報を取得
$classroom = null;
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$currentUser['id']]);
$classroom = $stmt->fetch();

// ページ開始
$currentPage = 'manual';
renderPageStart('guardian', $currentPage, 'ご利用ガイド', ['classroom' => $classroom]);
?>

<style>
.intro-section {
    background: rgba(102, 126, 234, 0.05);
    border-radius: 0;
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    border: 1px solid var(--cds-border-subtle-00);
}

.intro-section h2 {
    color: var(--md-purple);
    font-size: var(--text-body);
    margin-bottom: var(--spacing-md);
}

.intro-section p {
    color: var(--text-primary);
    line-height: 1.8;
    font-size: var(--text-subhead);
}

.section {
    margin-bottom: var(--spacing-xl);
    padding: var(--spacing-lg);
    background: var(--md-bg-tertiary);
    border-radius: 0;
    border-left: 4px solid var(--cds-purple-60);
}

.section.important {
    border-left-color: var(--cds-orange-50);
    background: rgba(255, 149, 0, 0.02);
}

.section h2 {
    color: var(--md-purple);
    font-size: var(--text-body);
    margin-bottom: var(--spacing-md);
}

.section.important h2 {
    color: var(--md-orange);
}

.section h3 {
    color: var(--text-primary);
    font-size: var(--text-callout);
    margin: var(--spacing-lg) 0 var(--spacing-sm) 0;
    padding-left: var(--spacing-sm);
    border-left: 3px solid var(--md-purple);
}

.section p, .section li {
    color: var(--text-primary);
    line-height: 1.8;
    font-size: var(--text-subhead);
}

.section ul, .section ol {
    margin-left: var(--spacing-lg);
    margin-top: var(--spacing-sm);
}

.section li {
    margin-bottom: var(--spacing-sm);
}

.highlight-box {
    background: var(--md-bg-secondary);
    border-radius: 0;
    padding: var(--spacing-md);
    margin: var(--spacing-md) 0;
}

.highlight-box.purple {
    border: 1px solid var(--cds-purple-60);
    background: rgba(102, 126, 234, 0.05);
}

.highlight-box.orange {
    border: 1px solid var(--cds-orange-50);
    background: rgba(255, 149, 0, 0.05);
}

.highlight-box.green {
    border: 1px solid var(--cds-support-success);
    background: rgba(36, 161, 72, 0.05);
}

.flow-diagram {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
    margin: var(--spacing-lg) 0;
}

.flow-step {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: 0;
}

.flow-number {
    width: 36px;
    height: 36px;
    background: var(--cds-purple-60);
    color: white;
    border-radius: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: var(--text-callout);
    flex-shrink: 0;
}

.flow-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.flow-desc {
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.flow-arrow {
    text-align: center;
    color: var(--cds-purple-60);
    font-size: var(--text-body);
}

.document-card {
    background: var(--md-bg-secondary);
    border-radius: 0;
    padding: var(--spacing-lg);
    margin: var(--spacing-md) 0;
    border: 1px solid var(--cds-border-subtle-00);
}

.document-card h4 {
    color: var(--md-purple);
    font-size: var(--text-callout);
    margin-bottom: var(--spacing-sm);
}

.document-card p {
    color: var(--text-secondary);
    font-size: var(--text-subhead);
    line-height: 1.7;
}

.check-list {
    list-style: none;
    margin-left: 0;
}

.check-list li {
    padding: var(--spacing-sm) 0;
    padding-left: 30px;
    position: relative;
    border-bottom: 1px solid var(--cds-border-subtle-00);
}

.check-list li:last-child {
    border-bottom: none;
}

.check-list li::before {
    content: "✓";
    position: absolute;
    left: 0;
    color: var(--md-green);
    font-weight: bold;
    font-size: var(--text-callout);
}

.toc {
    background: var(--md-bg-tertiary);
    border-radius: 0;
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

.toc h3 {
    color: var(--text-primary);
    font-size: var(--text-callout);
    margin-bottom: var(--spacing-md);
}

.toc ul {
    list-style: none;
    margin: 0;
}

.toc li {
    margin-bottom: var(--spacing-sm);
}

.toc a {
    color: var(--md-blue);
    text-decoration: none;
    font-size: var(--text-subhead);
}

.toc a:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .section { padding: var(--spacing-md); }
    .flow-step { flex-direction: column; text-align: center; }
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
        <h1 class="page-title">保護者マニュアル</h1>
        <p class="page-subtitle">システムのご利用方法をご案内します</p>
    </div>
</div>

<!-- イントロダクション -->
<div class="intro-section">
    <h2><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">menu_book</span> このシステムについて</h2>
    <p>
        この連絡帳システムは、お子様の日々の活動記録と成長を、保護者の皆様とスタッフが一緒に見守り、
        <strong>根拠に基づいた支援目標</strong>を設定するために開発されました。<br><br>
        日々の記録を積み重ねることで、お子様一人ひとりに合った支援計画を作成し、
        より良い成長をサポートしていきます。
    </p>
</div>

<!-- 目次 -->
<div class="toc">
    <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 目次</h3>
    <ul>
        <li><a href="#daily-records">1. 日々の活動記録について</a></li>
        <li><a href="#kakehashi">2. かけはしについて</a></li>
        <li><a href="#support-plan">3. 個別支援計画書について</a></li>
        <li><a href="#signature">4. 電子署名について</a></li>
        <li><a href="#flow">5. 書類作成の流れ</a></li>
        <li><a href="#request">6. 保護者の皆様へのお願い</a></li>
    </ul>
</div>

<!-- 日々の活動記録 -->
<div class="section" id="daily-records">
    <h2><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 1. 日々の活動記録について</h2>
    <p>お子様が教室で活動した日には、スタッフが活動内容を記録し、保護者の皆様にお届けしています。</p>

    <h3>活動記録に含まれる内容</h3>
    <ul>
        <li><strong>その日の活動内容</strong> - どんな活動をしたか</li>
        <li><strong>お子様の様子</strong> - 活動中の表情や反応、頑張ったこと</li>
        <li><strong>スタッフからのコメント</strong> - 気づいたことや成長のポイント</li>
    </ul>

    <div class="highlight-box green">
        <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">lightbulb</span> なぜ日々の記録が大切なのか</strong><br><br>
        日々の小さな変化や成長を記録することで、お子様の<strong>得意なこと・苦手なこと・興味のあること</strong>が見えてきます。
        この積み重ねが、次の支援目標を決める際の<strong>大切な根拠</strong>となります。
    </div>
</div>

<!-- かけはし -->
<div class="section" id="kakehashi">
    <h2><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">handshake</span> 2. かけはしについて</h2>
    <p>
        「かけはし」は、<strong>保護者とスタッフの情報共有</strong>のための大切な書類です。
        お子様の家庭での様子と教室での様子を共有し、一貫した支援を行うために作成します。
    </p>

    <div class="document-card">
        <h4><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 保護者かけはし（保護者の皆様が記入）</h4>
        <p>
            家庭でのお子様の様子、最近の変化、気になること、教室への要望などを記入していただきます。
            お子様のことを一番よく知っている保護者の皆様からの情報は、支援を行う上でとても重要です。
        </p>
    </div>

    <div class="document-card">
        <h4><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> スタッフかけはし（スタッフが作成）</h4>
        <p>
            日々の活動記録をもとに、教室でのお子様の様子、成長したポイント、
            今後の支援の方向性などをまとめます。
        </p>
    </div>

    <div class="highlight-box purple">
        <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">sync</span> かけはしの作成サイクル</strong><br><br>
        かけはしは<strong>6か月ごと</strong>に作成します。<br>
        期限が近づくと、システムから入力のお願いが届きますので、ご協力をお願いいたします。
    </div>
</div>

<!-- 個別支援計画書 -->
<div class="section" id="support-plan">
    <h2><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span> 3. 個別支援計画書について</h2>
    <p>
        個別支援計画書は、お子様一人ひとりに合わせた<strong>支援の目標と具体的な内容</strong>を定めた書類です。
        法律で定められた重要な書類であり、6か月ごとに見直しを行います。
    </p>

    <h3>個別支援計画書の内容</h3>
    <ul>
        <li><strong>長期目標</strong> - 1年後に目指す姿</li>
        <li><strong>短期目標</strong> - 6か月後に達成したい目標</li>
        <li><strong>具体的な支援内容</strong> - 目標達成のために行う支援</li>
    </ul>

    <div class="highlight-box orange">
        <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">warning</span> 根拠に基づいた目標設定</strong><br><br>
        個別支援計画の目標は、<strong>日々の活動記録</strong>と<strong>かけはし</strong>の内容を分析して設定します。<br>
        「なんとなく」ではなく、<strong>実際の様子や変化を根拠</strong>として、
        お子様に合った現実的で達成可能な目標を立てています。
    </div>

    <h3>モニタリング（経過観察）</h3>
    <p>
        支援計画の途中で、目標の達成状況を確認する「モニタリング」を行います。
        計画通りに進んでいるか、計画の見直しが必要かを確認し、必要に応じて調整します。
    </p>
</div>

<!-- 電子署名について -->
<div class="section" id="signature">
    <h2><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">draw</span> 4. 電子署名について</h2>
    <p>
        個別支援計画書とモニタリング表では、<strong>電子署名</strong>による確認をお願いしています。
        紙の書類に署名する代わりに、画面上で直接署名できます。
    </p>

    <h3>個別支援計画書の確認手順</h3>
    <div class="flow-diagram">
        <div class="flow-step">
            <div class="flow-number">1</div>
            <div class="flow-content">
                <div class="flow-title">計画書案を確認</div>
                <div class="flow-desc">スタッフが作成した計画書案がシステムに届きます。内容をご確認ください。</div>
            </div>
        </div>
        <div class="flow-arrow">↓</div>
        <div class="flow-step">
            <div class="flow-number">2</div>
            <div class="flow-content">
                <div class="flow-title">確認またはコメント送信</div>
                <div class="flow-desc">内容に問題がなければ「確認」、変更希望がある場合は「コメントを送信」を選択します。</div>
            </div>
        </div>
        <div class="flow-arrow">↓</div>
        <div class="flow-step">
            <div class="flow-number">3</div>
            <div class="flow-content">
                <div class="flow-title">面談で電子署名</div>
                <div class="flow-desc">スタッフとの面談時に、画面上で署名をお願いします。</div>
            </div>
        </div>
    </div>

    <div class="highlight-box green">
        <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">draw</span> 電子署名の方法</strong><br><br>
        スマートフォンやタブレットの場合は<strong>指で直接</strong>、パソコンの場合は<strong>マウス</strong>で署名欄に署名できます。<br>
        書き直したい場合は「クリア」ボタンで消してやり直せます。
    </div>

    <div class="highlight-box orange">
        <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">info</span> 変更希望がある場合</strong><br><br>
        計画書案の内容に変更を希望される場合は、コメント欄に具体的なご希望を記入してください。<br>
        スタッフが内容を確認し、修正した計画書を改めてお送りします。
    </div>
</div>

<!-- 書類作成の流れ -->
<div class="section" id="flow">
    <h2><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">sync</span> 5. 書類作成の流れ</h2>
    <p>以下の流れで、日々の記録から支援計画が作成されます。</p>

    <div class="flow-diagram">
        <div class="flow-step">
            <div class="flow-number">1</div>
            <div class="flow-content">
                <div class="flow-title">日々の活動記録</div>
                <div class="flow-desc">スタッフが毎回の活動を記録し、保護者へ送信</div>
            </div>
        </div>
        <div class="flow-arrow">↓</div>
        <div class="flow-step">
            <div class="flow-number">2</div>
            <div class="flow-content">
                <div class="flow-title">保護者かけはし作成</div>
                <div class="flow-desc">家庭での様子や要望を記入（6か月ごと）</div>
            </div>
        </div>
        <div class="flow-arrow">↓</div>
        <div class="flow-step">
            <div class="flow-number">3</div>
            <div class="flow-content">
                <div class="flow-title">スタッフかけはし作成</div>
                <div class="flow-desc">日々の記録をもとに、教室での様子をまとめる</div>
            </div>
        </div>
        <div class="flow-arrow">↓</div>
        <div class="flow-step">
            <div class="flow-number">4</div>
            <div class="flow-content">
                <div class="flow-title">個別支援計画書作成</div>
                <div class="flow-desc">かけはしの内容を踏まえて、次の目標を設定</div>
            </div>
        </div>
        <div class="flow-arrow">↓</div>
        <div class="flow-step">
            <div class="flow-number">5</div>
            <div class="flow-content">
                <div class="flow-title">保護者確認・同意</div>
                <div class="flow-desc">計画内容をご確認いただき、同意をいただく</div>
            </div>
        </div>
    </div>

    <div class="highlight-box green">
        <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">auto_awesome</span> このサイクルのポイント</strong><br><br>
        日々の記録 → かけはし → 支援計画 という流れにより、<br>
        <strong>「今のお子様の姿」に基づいた支援</strong>を行うことができます。
    </div>
</div>

<!-- 保護者へのお願い -->
<div class="section important" id="request">
    <h2><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">volunteer_activism</span> 6. 保護者の皆様へのお願い</h2>
    <p>お子様のより良い支援のために、以下のご協力をお願いいたします。</p>

    <ul class="check-list">
        <li>
            <strong>活動記録の確認</strong><br>
            <span style="color: var(--text-secondary);">
                送信された活動記録をご確認ください。お子様の教室での様子がわかります。
                気になることがあればお気軽にご連絡ください。
            </span>
        </li>
        <li>
            <strong>かけはしへの記入</strong><br>
            <span style="color: var(--text-secondary);">
                期限内に保護者かけはしの記入をお願いします。
                家庭での様子は、支援計画を立てる上で非常に重要な情報です。
            </span>
        </li>
        <li>
            <strong>個別支援計画書の確認・同意</strong><br>
            <span style="color: var(--text-secondary);">
                作成された計画書をご確認いただき、ご質問やご意見があればお知らせください。
                内容にご納得いただけましたら、同意の手続きをお願いします。
            </span>
        </li>
        <li>
            <strong>何でもご相談ください</strong><br>
            <span style="color: var(--text-secondary);">
                お子様のことで気になることがあれば、いつでもチャットやお電話でご相談ください。
                一緒にお子様の成長を支えていきましょう。
            </span>
        </li>
    </ul>

    <div class="highlight-box purple" style="margin-top: var(--spacing-lg);">
        <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span> コミュニケーションを大切に</strong><br><br>
        このシステムを通じて、保護者の皆様とスタッフが情報を共有し、
        <strong>お子様を中心とした支援チーム</strong>として一緒に歩んでいければと思います。<br><br>
        ご不明な点がございましたら、お気軽にお問い合わせください。
    </div>
</div>

<?php renderPageEnd(); ?>
