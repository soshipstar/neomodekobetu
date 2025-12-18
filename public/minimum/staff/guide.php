<?php
/**
 * 業務フローガイド
 * ミニマム版 - スタッフ用説明資料
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
requireUserType(['staff', 'admin']);

// ページ開始
$currentPage = 'guide';
renderPageStart('staff', $currentPage, '業務フローガイド');
?>

<style>
.guide-container {
    max-width: 900px;
    margin: 0 auto;
}

.guide-section {
    background: var(--apple-bg-primary);
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-md);
}

.guide-section h2 {
    color: var(--primary-purple);
    font-size: var(--text-title-3);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-sm);
    border-bottom: 2px solid var(--primary-purple);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.guide-section h3 {
    color: var(--text-primary);
    font-size: var(--text-headline);
    margin: var(--spacing-lg) 0 var(--spacing-md);
}

.guide-section p {
    color: var(--text-primary);
    line-height: 1.8;
    margin-bottom: var(--spacing-md);
}

.guide-section ul, .guide-section ol {
    color: var(--text-primary);
    line-height: 1.8;
    margin-bottom: var(--spacing-md);
    padding-left: var(--spacing-xl);
}

.guide-section li {
    margin-bottom: var(--spacing-sm);
}

.flow-diagram {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
    margin: var(--spacing-lg) 0;
}

.flow-step {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--apple-bg-secondary);
    border-radius: var(--radius-md);
    position: relative;
}

.flow-step:not(:last-child)::after {
    content: '↓';
    position: absolute;
    bottom: -24px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 20px;
    color: var(--primary-purple);
}

.flow-number {
    width: 36px;
    height: 36px;
    background: var(--primary-purple);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}

.flow-content {
    flex: 1;
}

.flow-content h4 {
    color: var(--text-primary);
    font-size: var(--text-body);
    font-weight: 600;
    margin-bottom: 4px;
}

.flow-content p {
    color: var(--text-secondary);
    font-size: var(--text-subhead);
    margin: 0;
}

.role-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}

.role-badge.staff {
    background: #dbeafe;
    color: #1e40af;
}

.role-badge.guardian {
    background: #d1fae5;
    color: #065f46;
}

.highlight-box {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    border-left: 4px solid var(--primary-purple);
    padding: var(--spacing-md);
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    margin: var(--spacing-md) 0;
}

.highlight-box p {
    margin: 0;
    color: var(--text-primary);
}

.timeline {
    position: relative;
    padding-left: 30px;
    margin: var(--spacing-lg) 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--apple-gray-4);
}

.timeline-item {
    position: relative;
    padding-bottom: var(--spacing-lg);
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -26px;
    top: 4px;
    width: 12px;
    height: 12px;
    background: var(--primary-purple);
    border-radius: 50%;
}

.timeline-item h4 {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 4px;
}

.timeline-item p {
    color: var(--text-secondary);
    margin: 0;
    font-size: var(--text-subhead);
}

.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-md);
    margin-top: var(--spacing-lg);
}

.quick-link {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    background: var(--apple-bg-secondary);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s;
}

.quick-link:hover {
    background: var(--primary-purple);
    color: white;
    transform: translateY(-2px);
}

.quick-link-icon {
    font-size: 24px;
}

@media (max-width: 768px) {
    .flow-step {
        flex-direction: column;
        text-align: center;
    }

    .flow-step:not(:last-child)::after {
        left: 50%;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">業務フローガイド</h1>
        <p class="page-subtitle">かけはしシステムの使い方と業務の流れ</p>
    </div>
</div>

<div class="guide-container">

    <!-- 概要 -->
    <div class="guide-section">
        <h2>📌 システム概要</h2>
        <p>このシステムは、放課後等デイサービスにおける<strong>個別支援計画の作成・管理</strong>を効率化するためのツールです。</p>

        <div class="highlight-box">
            <p><strong>主な機能：</strong>かけはし、個別支援計画書作成、モニタリング、保護者チャット</p>
        </div>

        <div class="highlight-box" style="border-left-color: #10b981;">
            <p><strong>安心ポイント：</strong>各生徒の提出期限が近づくと<strong>アラートでお知らせ</strong>するので、作り忘れの心配がありません。</p>
        </div>

        <h3>基本的な業務サイクル（6ヶ月周期）</h3>
        <div class="flow-diagram">
            <div class="flow-step">
                <div class="flow-number">1</div>
                <div class="flow-content">
                    <h4>かけはし入力</h4>
                    <p>個別支援計画の提出<strong>1ヶ月前まで</strong>に保護者・職員が記録を完了</p>
                </div>
            </div>
            <div class="flow-step">
                <div class="flow-number">2</div>
                <div class="flow-content">
                    <h4>個別支援計画作成（半年ごと）</h4>
                    <p>かけはしの内容を基に支援計画を作成（初回はかけはしと同時）</p>
                </div>
            </div>
            <div class="flow-step">
                <div class="flow-number">3</div>
                <div class="flow-content">
                    <h4>支援の実施</h4>
                    <p>計画に基づいた支援を6ヶ月間実施</p>
                </div>
            </div>
            <div class="flow-step">
                <div class="flow-number">4</div>
                <div class="flow-content">
                    <h4>モニタリング</h4>
                    <p>次の個別支援計画の<strong>1ヶ月前まで</strong>に進捗を評価・記録</p>
                </div>
            </div>
        </div>

        <div class="highlight-box">
            <p><strong>提出期限の関係：</strong><br>
            かけはし → 個別支援計画（1ヶ月後）→ モニタリング（5ヶ月後＝次の計画の1ヶ月前）</p>
        </div>
    </div>

    <!-- かけはし -->
    <div class="guide-section">
        <h2>🌉 かけはしについて</h2>
        <p>「かけはし」は保護者と施設をつなぎ、<strong>現状の課題と目標を共有するための資料</strong>です。個別支援計画を作成する前に必ず作成します。</p>

        <div class="highlight-box" style="border-left-color: #f59e0b;">
            <p><strong>重要：</strong>かけはしは個別支援計画作成の<strong>前提資料</strong>です。保護者・職員双方が入力しますが、保護者の入力が間に合わない場合は<strong>職員のかけはしだけでも個別支援計画を作成できます</strong>。</p>
        </div>

        <h3>かけはしの流れ</h3>
        <div class="timeline">
            <div class="timeline-item">
                <h4>1. 期間の自動作成</h4>
                <p>生徒の支援開始日を基準に、<strong>6ヶ月間</strong>のかけはし期間が自動作成されます。</p>
            </div>
            <div class="timeline-item">
                <h4>2. 保護者入力 <span class="role-badge guardian">保護者</span></h4>
                <p>保護者が家庭での様子、現状の課題、要望などを入力します。</p>
            </div>
            <div class="timeline-item">
                <h4>3. 職員入力 <span class="role-badge staff">スタッフ</span></h4>
                <p>施設でのお子様の様子、課題、支援の方向性などを入力します。</p>
            </div>
            <div class="timeline-item">
                <h4>4. 提出期限までに完了</h4>
                <p><strong>個別支援計画の提出1ヶ月前まで</strong>に入力を完了させます。</p>
            </div>
            <div class="timeline-item">
                <h4>5. PDF出力・確認</h4>
                <p>入力が完了したらPDFとして出力し、内容を確認します。</p>
            </div>
        </div>

        <div class="highlight-box">
            <p><strong>初回の場合：</strong>支援開始日の1日前が提出期限となり、個別支援計画と同時に作成します。</p>
        </div>

        <h3>かけはしメニュー</h3>
        <div class="quick-links">
            <a href="kakehashi_staff.php" class="quick-link">
                <span class="quick-link-icon">🌉</span>
                <span>かけはし（職員）入力</span>
            </a>
            <a href="kakehashi_guardian_view.php" class="quick-link">
                <span class="quick-link-icon">📖</span>
                <span>保護者入力の確認</span>
            </a>
        </div>
    </div>

    <!-- 個別支援計画 -->
    <div class="guide-section">
        <h2>📋 個別支援計画について</h2>
        <p>個別支援計画は、お子様一人ひとりの支援目標と具体的な支援内容を定めた計画書です。<strong>半年ごと</strong>に作成・更新します。</p>

        <div class="highlight-box" style="border-left-color: #ef4444;">
            <p><strong>前提条件：</strong>個別支援計画を作成する前に、<strong>かけはしの作成が必要</strong>です。かけはしで共有された課題と目標を基に計画を立てます。</p>
        </div>

        <h3>作成の流れ</h3>
        <div class="flow-diagram">
            <div class="flow-step">
                <div class="flow-number">1</div>
                <div class="flow-content">
                    <h4>かけはし確認</h4>
                    <p>保護者・職員のかけはしから課題と要望を把握します</p>
                </div>
            </div>
            <div class="flow-step">
                <div class="flow-number">2</div>
                <div class="flow-content">
                    <h4>計画作成 <span class="role-badge staff">スタッフ</span></h4>
                    <p>支援目標、支援内容、達成基準を設定します。AIによる生成支援も利用できます。</p>
                </div>
            </div>
            <div class="flow-step">
                <div class="flow-number">3</div>
                <div class="flow-content">
                    <h4>保護者確認 <span class="role-badge guardian">保護者</span></h4>
                    <p>作成した計画を保護者に確認・同意してもらいます</p>
                </div>
            </div>
            <div class="flow-step">
                <div class="flow-number">4</div>
                <div class="flow-content">
                    <h4>計画開始</h4>
                    <p>確認が完了したら計画に基づいた支援を開始します</p>
                </div>
            </div>
        </div>

        <div class="highlight-box">
            <p><strong>AI生成機能：</strong>かけはしの記録を基に、AIが計画の下書きを自動生成できます。生成された内容は必ず確認・修正してください。</p>
        </div>

        <h3>計画メニュー</h3>
        <div class="quick-links">
            <a href="kobetsu_plan.php" class="quick-link">
                <span class="quick-link-icon">📋</span>
                <span>個別支援計画の作成</span>
            </a>
        </div>
    </div>

    <!-- モニタリング -->
    <div class="guide-section">
        <h2>📊 モニタリングについて</h2>
        <p>モニタリングは、個別支援計画の進捗状況を評価・記録するものです。<strong>次の個別支援計画の1ヶ月前まで</strong>（＝現在の計画期限の5ヶ月後）に実施します。</p>

        <h3>モニタリングの目的</h3>
        <ul>
            <li>支援目標の達成度を確認する</li>
            <li>支援内容が適切かどうかを評価する</li>
            <li>新たな課題や成長を記録する</li>
            <li>次回の計画見直しに活かす</li>
        </ul>

        <div class="highlight-box">
            <p><strong>タイミング：</strong>モニタリングは次のかけはし・個別支援計画を作成する前に完了させます。前回の計画の成果を評価し、次の計画に反映させるためです。</p>
        </div>

        <h3>評価の流れ</h3>
        <div class="flow-diagram">
            <div class="flow-step">
                <div class="flow-number">1</div>
                <div class="flow-content">
                    <h4>モニタリング作成</h4>
                    <p>個別支援計画を基にモニタリング表が自動生成されます</p>
                </div>
            </div>
            <div class="flow-step">
                <div class="flow-number">2</div>
                <div class="flow-content">
                    <h4>達成度評価 <span class="role-badge staff">スタッフ</span></h4>
                    <p>各目標の達成度（◎○△×）と所見を入力します</p>
                </div>
            </div>
            <div class="flow-step">
                <div class="flow-number">3</div>
                <div class="flow-content">
                    <h4>総合所見の記入</h4>
                    <p>期間全体の評価と今後の方針を記入します</p>
                </div>
            </div>
            <div class="flow-step">
                <div class="flow-number">4</div>
                <div class="flow-content">
                    <h4>保護者共有 <span class="role-badge guardian">保護者</span></h4>
                    <p>モニタリング結果を保護者に共有・確認してもらいます</p>
                </div>
            </div>
        </div>

        <h3>達成度の目安</h3>
        <ul>
            <li><strong>◎（十分達成）</strong>：目標を十分に達成できている</li>
            <li><strong>○（概ね達成）</strong>：おおむね目標を達成できている</li>
            <li><strong>△（一部達成）</strong>：一部達成しているが課題が残る</li>
            <li><strong>×（未達成）</strong>：達成に至っていない、継続的な支援が必要</li>
        </ul>

        <h3>モニタリングメニュー</h3>
        <div class="quick-links">
            <a href="kobetsu_monitoring.php" class="quick-link">
                <span class="quick-link-icon">📊</span>
                <span>モニタリングの作成・評価</span>
            </a>
        </div>
    </div>

    <!-- 年間スケジュール -->
    <div class="guide-section">
        <h2>📅 年間スケジュール例</h2>
        <p>以下は4月入所の生徒を例にした業務サイクルです。<strong>生徒ごとに支援開始日が異なるため、提出期限は個別に管理されます。</strong></p>

        <h3>6ヶ月サイクルの例（4月入所の場合）</h3>
        <table class="table" style="margin-top: var(--spacing-md);">
            <thead>
                <tr>
                    <th>時期</th>
                    <th>主な業務</th>
                    <th>備考</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>3月末</strong></td>
                    <td>初回かけはし・個別支援計画</td>
                    <td>支援開始日（4/1）の1日前まで</td>
                </tr>
                <tr>
                    <td><strong>4月〜9月</strong></td>
                    <td>第1期 支援実施</td>
                    <td>計画に基づいた支援</td>
                </tr>
                <tr>
                    <td><strong>8月</strong></td>
                    <td>第1回モニタリング</td>
                    <td>次の計画（10月）の1ヶ月前</td>
                </tr>
                <tr>
                    <td><strong>9月</strong></td>
                    <td>2回目かけはし入力</td>
                    <td>第2期計画の1ヶ月前まで</td>
                </tr>
                <tr>
                    <td><strong>10月</strong></td>
                    <td>第2期 個別支援計画</td>
                    <td>かけはし提出の1ヶ月後</td>
                </tr>
                <tr>
                    <td><strong>10月〜3月</strong></td>
                    <td>第2期 支援実施</td>
                    <td>計画に基づいた支援</td>
                </tr>
                <tr>
                    <td><strong>2月</strong></td>
                    <td>第2回モニタリング</td>
                    <td>次の計画（4月）の1ヶ月前</td>
                </tr>
                <tr>
                    <td><strong>3月</strong></td>
                    <td>3回目かけはし入力</td>
                    <td>第3期計画の1ヶ月前まで</td>
                </tr>
            </tbody>
        </table>

        <div class="highlight-box">
            <p><strong>ポイント：</strong>システムが提出期限を自動計算します。ダッシュボードで各生徒の期限を確認してください。</p>
        </div>
    </div>

    <!-- よくある質問 -->
    <div class="guide-section">
        <h2>❓ よくある質問</h2>

        <h3>Q. かけはしとは何ですか？</h3>
        <p>かけはしは保護者と施設をつなぎ、<strong>現状の課題と目標を共有するための資料</strong>です。日常的な連絡帳ではなく、個別支援計画を作成するための前提資料として位置づけられています。</p>

        <h3>Q. かけはしの期間はどのように決まりますか？</h3>
        <p>生徒の支援開始日を基準に、<strong>6ヶ月間</strong>の期間が自動作成されます。例えば、4月1日に支援開始した場合、4/1〜9/30が第1期、10/1〜3/31が第2期となります。</p>

        <h3>Q. 保護者がかけはしを入力しない場合はどうなりますか？</h3>
        <p>保護者の入力が間に合わない場合でも、<strong>職員のかけはしだけで個別支援計画を作成できます</strong>。ただし、保護者の意見も反映できるよう、可能な限り入力を促してください。</p>

        <h3>Q. 提出期限はどのように計算されますか？</h3>
        <p><strong>初回：</strong>支援開始日の1日前（かけはしと個別支援計画は同時）<br>
        <strong>2回目以降：</strong>かけはしは対象期間開始の1ヶ月前、個別支援計画はかけはしの1ヶ月後、モニタリングは個別支援計画の5ヶ月後（＝次の計画の1ヶ月前）</p>

        <h3>Q. 提出期限を忘れそうで心配です</h3>
        <p>ご安心ください。各生徒のかけはし・個別支援計画・モニタリングの<strong>作成期限が近づくとアラートでお知らせ</strong>します。ダッシュボードで期限が近い項目を確認できます。</p>

        <h3>Q. 個別支援計画のAI生成機能とは？</h3>
        <p>かけはしの記録を分析し、支援目標や支援内容の下書きを自動生成する機能です。生成された内容はあくまで参考であり、必ずスタッフが確認・修正してから使用してください。</p>

        <h3>Q. モニタリングはいつ作成すればいいですか？</h3>
        <p>個別支援計画の期限の5ヶ月後（＝次の計画の1ヶ月前）までに作成します。前回の計画期間の成果を評価し、次の計画に反映させるためです。</p>

        <h3>Q. 保護者への確認依頼はどうすればいいですか？</h3>
        <p>チャット機能を使って保護者に連絡し、システム上で計画やモニタリングを確認してもらうよう依頼してください。保護者はログイン後、自分のお子様の計画・モニタリングを閲覧できます。</p>
    </div>

</div>

<?php renderPageEnd(); ?>
