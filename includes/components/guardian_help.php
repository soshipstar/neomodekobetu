<?php
/**
 * 保護者向けヘルプサポートコンポーネント（選択式）
 * 保護者画面に組み込む操作サポート
 */

// 現在のページからカテゴリを判定
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$pageToCategory = [
    // ダッシュボード
    'dashboard' => 'dashboard',

    // 連絡帳
    'communication_logs' => 'communication',

    // チャット
    'chat' => 'chat',

    // 週間計画
    'weekly_plan' => 'weekly',

    // かけはし
    'kakehashi' => 'kakehashi',
    'kakehashi_history' => 'kakehashi',

    // 施設通信
    'newsletters' => 'newsletter',

    // 個別支援計画・モニタリング
    'support_plans' => 'plan',
    'monitoring' => 'plan',

    // マニュアル
    'manual' => 'manual',

    // プロフィール
    'profile' => 'profile',
    'change_password' => 'profile',
];

$defaultCategoryId = $pageToCategory[$currentPage] ?? null;

// ヘルプデータ
$helpData = [
    'categories' => [
        [
            'id' => 'dashboard',
            'icon' => '<span class="material-symbols-outlined">home</span>',
            'title' => 'ダッシュボード',
            'items' => [
                [
                    'question' => 'ダッシュボードでは何が確認できますか？',
                    'answer' => "【ダッシュボードについて】\n\nダッシュボードでは、お子様に関する最新情報をまとめて確認できます。\n\n■ 確認できる内容\n・未読の連絡帳\n・かけはしの入力状況\n・個別支援計画書の確認依頼\n・施設からのお知らせ\n\n新しい情報がある場合は、バッジ（数字）で表示されます。"
                ],
                [
                    'question' => 'バッジの数字は何を表していますか？',
                    'answer' => "【バッジについて】\n\n各メニューに表示される数字は、未対応の項目数を表しています。\n\n■ 例\n・連絡帳: 未読の連絡帳の数\n・かけはし: 入力が必要なかけはしの数\n・個別支援計画書: 確認が必要な計画書の数\n\nバッジをタップして、詳細を確認してください。"
                ]
            ]
        ],
        [
            'id' => 'communication',
            'icon' => '<span class="material-symbols-outlined">library_books</span>',
            'title' => '連絡帳',
            'items' => [
                [
                    'question' => '連絡帳はどこで確認できますか？',
                    'answer' => "【連絡帳の確認方法】\n\n1. メニューから「連絡帳一覧」を開く\n2. 日付ごとに連絡帳が表示されます\n3. 各連絡帳をタップして詳細を確認\n\n未読の連絡帳には「NEW」バッジが表示されます。"
                ],
                [
                    'question' => '連絡帳に返信できますか？',
                    'answer' => "【連絡帳への返信】\n\n連絡帳への直接返信はできませんが、チャット機能を使ってスタッフにメッセージを送ることができます。\n\n連絡帳の内容についてご質問やご意見がある場合は、チャットでお気軽にお問い合わせください。"
                ]
            ]
        ],
        [
            'id' => 'chat',
            'icon' => '<span class="material-symbols-outlined">chat</span>',
            'title' => 'チャット',
            'items' => [
                [
                    'question' => 'スタッフにメッセージを送るには？',
                    'answer' => "【メッセージの送信方法】\n\n1. メニューから「チャット」を開く\n2. メッセージ入力欄にテキストを入力\n3. 送信ボタン（紙飛行機アイコン）をタップ\n\nスタッフからの返信があると、プッシュ通知でお知らせします。"
                ],
                [
                    'question' => '欠席連絡はどうすればいいですか？',
                    'answer' => "【欠席連絡の方法】\n\n1. チャット画面を開く\n2. 「欠席連絡」ボタンをタップ\n3. 欠席日と理由を入力して送信\n\n欠席連絡は当日の朝までにお願いします。急な体調不良の場合は、できるだけ早くご連絡ください。"
                ],
                [
                    'question' => '振替希望を出すには？',
                    'answer' => "【振替希望の出し方】\n\n1. チャット画面を開く\n2. 「振替希望」ボタンをタップ\n3. 希望日を選択して送信\n\n振替の可否はスタッフが確認後、チャットでお知らせします。定員の関係でご希望に添えない場合もございます。"
                ]
            ]
        ],
        [
            'id' => 'weekly',
            'icon' => '<span class="material-symbols-outlined">edit_note</span>',
            'title' => '週間計画表',
            'items' => [
                [
                    'question' => '週間計画表とは何ですか？',
                    'answer' => "【週間計画表について】\n\nお子様の週ごとの目標と達成状況を確認できます。\n\n■ 確認できる内容\n・今週の目標\n・各曜日の活動予定\n・目標の達成状況\n・スタッフからのコメント\n\n週末に更新されることが多いので、定期的にご確認ください。"
                ]
            ]
        ],
        [
            'id' => 'kakehashi',
            'icon' => '<span class="material-symbols-outlined">handshake</span>',
            'title' => 'かけはし',
            'items' => [
                [
                    'question' => 'かけはしとは何ですか？',
                    'answer' => "【かけはしについて】\n\n「かけはし」は、ご家庭での様子やお子様への願いを施設に伝えるための大切な書類です。\n\n6ヶ月ごとに入力をお願いしています。この情報をもとに、個別支援計画を作成します。\n\n■ 入力する内容\n・家庭での様子\n・最近の成長や変化\n・今後の希望や要望"
                ],
                [
                    'question' => 'かけはしの入力期限はいつですか？',
                    'answer' => "【かけはしの入力期限】\n\n入力期限が近づくと、ダッシュボードとチャットでお知らせします。\n\n期限は個別支援計画書の作成期限の約1ヶ月前です。\n\n期限内にご入力いただけない場合、計画書作成に影響が出る場合がありますので、お早めにご対応ください。"
                ],
                [
                    'question' => 'かけはしの入力方法は？',
                    'answer' => "【かけはしの入力方法】\n\n1. メニューから「かけはし入力」を開く\n2. 各項目に記入\n   ・本人の願い\n   ・家庭での様子\n   ・今後の要望など\n3. 「下書き保存」または「提出」ボタンで保存\n\n■ 下書きと提出の違い\n・下書き: 途中保存（後で編集可能）\n・提出: スタッフに送信（編集不可）"
                ]
            ]
        ],
        [
            'id' => 'newsletter',
            'icon' => '<span class="material-symbols-outlined">newspaper</span>',
            'title' => '施設通信',
            'items' => [
                [
                    'question' => '施設通信はどこで見られますか？',
                    'answer' => "【施設通信の確認方法】\n\n1. メニューから「施設通信」を開く\n2. 月ごとの施設通信が一覧表示されます\n3. 読みたい月をタップして詳細を確認\n\n新しい施設通信が公開されると、ダッシュボードでお知らせします。"
                ]
            ]
        ],
        [
            'id' => 'plan',
            'icon' => '<span class="material-symbols-outlined">assignment</span>',
            'title' => '個別支援計画書・モニタリング',
            'items' => [
                [
                    'question' => '個別支援計画書の確認方法は？',
                    'answer' => "【個別支援計画書の確認】\n\n1. メニューから「個別支援計画書」を開く\n2. お子様を選択\n3. 計画書の一覧が表示されます\n4. 確認したい計画書をタップ\n\n新しい計画書が作成されると、確認のお願いが届きます。"
                ],
                [
                    'question' => '計画書案の確認・コメントの送り方は？',
                    'answer' => "【計画書案の確認方法】\n\n1. 「個別支援計画書」画面で該当の計画書を開く\n2. 内容を確認\n3. 以下のいずれかを選択:\n   ・「確認」: 内容に問題がない場合\n   ・「コメントを送信」: 変更希望がある場合\n\n■ コメントを送信する場合\nコメント欄に具体的な変更希望を記入してください。スタッフが確認後、修正した計画書を改めてお送りします。"
                ],
                [
                    'question' => '電子署名はどうやってするの？',
                    'answer' => "【電子署名の方法】\n\n電子署名は、スタッフとの面談時に行います。\n\n1. スタッフが署名画面を表示\n2. 署名欄に指（スマホ・タブレット）またはマウス（PC）で署名\n3. 書き直したい場合は「クリア」ボタンで消せます\n4. 署名後、スタッフが保存\n\n署名済みの計画書はPDFでダウンロードできます。"
                ],
                [
                    'question' => 'モニタリング表とは何ですか？',
                    'answer' => "【モニタリング表について】\n\nモニタリング表は、個別支援計画の目標がどれくらい達成できたかを評価する書類です。\n\n■ 確認できる内容\n・各目標の達成状況（A/B/C評価など）\n・次期に向けた課題や改善点\n・スタッフからのコメント\n\n計画期間の終わりに作成されます。"
                ]
            ]
        ],
        [
            'id' => 'profile',
            'icon' => '<span class="material-symbols-outlined">person</span>',
            'title' => 'プロフィール・設定',
            'items' => [
                [
                    'question' => 'パスワードを変更するには？',
                    'answer' => "【パスワードの変更方法】\n\n1. メニューから「パスワード変更」を開く\n2. 現在のパスワードを入力\n3. 新しいパスワードを入力（2回）\n4. 「変更する」ボタンをタップ\n\n■ パスワードの条件\n・8文字以上\n・英数字を含む\n\nパスワードを忘れた場合は、施設にお問い合わせください。"
                ],
                [
                    'question' => 'プロフィール情報を変更するには？',
                    'answer' => "【プロフィールの変更】\n\n1. メニューから「プロフィール」を開く\n2. 変更したい項目を編集\n3. 「保存」ボタンをタップ\n\n■ 変更できる項目\n・表示名\n・連絡先（メールアドレス・電話番号）\n\n※ 氏名などの変更は施設にお問い合わせください。"
                ]
            ]
        ],
        [
            'id' => 'manual',
            'icon' => '<span class="material-symbols-outlined">help</span>',
            'title' => 'ご利用ガイド',
            'items' => [
                [
                    'question' => 'このシステムの使い方がわかりません',
                    'answer' => "【ご利用ガイドについて】\n\nこのページでは、システムの使い方を詳しく説明しています。\n\n■ 目次から探す\n画面上部の目次から、知りたい項目を選んでタップしてください。\n\n■ それでもわからない場合\nチャットでスタッフにお問い合わせいただくか、施設にお電話ください。"
                ]
            ]
        ]
    ]
];
?>

<!-- ヘルプサポート -->
<div id="guardian-help-container">
    <!-- ヘルプ開始ボタン -->
    <button id="guardian-help-toggle" onclick="toggleGuardianHelp()">
        <span class="help-icon-btn"><span class="material-symbols-outlined" style="font-size: 20px;">help</span></span>
        <span class="help-label">ヘルプ</span>
    </button>

    <!-- ヘルプウィンドウ -->
    <div id="guardian-help-window" class="help-hidden">
        <div class="help-header">
            <div class="help-header-title">
                <span class="material-symbols-outlined" style="font-size: 20px;">menu_book</span>
                <span>操作ヘルプ</span>
            </div>
            <button class="help-close" onclick="toggleGuardianHelp()">×</button>
        </div>

        <div id="guardian-help-content" class="help-content">
            <!-- カテゴリ一覧（初期表示） -->
            <div id="guardian-help-categories" class="help-categories">
                <p class="help-intro">知りたい項目を選んでください</p>
                <?php foreach ($helpData['categories'] as $category): ?>
                <button class="help-category-btn" onclick="showGuardianCategory('<?= $category['id'] ?>')">
                    <span class="category-icon"><?= $category['icon'] ?></span>
                    <span class="category-title"><?= htmlspecialchars($category['title']) ?></span>
                    <span class="category-arrow">›</span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- 質問一覧（カテゴリ選択後） -->
            <?php foreach ($helpData['categories'] as $category): ?>
            <div id="guardian-help-category-<?= $category['id'] ?>" class="help-items" style="display: none;">
                <button class="help-back-btn" onclick="showGuardianCategories()">
                    ← カテゴリに戻る
                </button>
                <h3 class="help-category-title"><?= $category['icon'] ?> <?= htmlspecialchars($category['title']) ?></h3>
                <?php foreach ($category['items'] as $index => $item): ?>
                <button class="help-item-btn" onclick="showGuardianAnswer('<?= $category['id'] ?>', <?= $index ?>)">
                    <span class="item-question"><?= htmlspecialchars($item['question']) ?></span>
                    <span class="item-arrow">›</span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <!-- 回答表示 -->
            <div id="guardian-help-answer" class="help-answer" style="display: none;">
                <button class="help-back-btn" onclick="backToGuardianItems()">
                    ← 質問一覧に戻る
                </button>
                <div id="guardian-help-answer-content" class="help-answer-content"></div>
            </div>
        </div>
    </div>
</div>

<style>
#guardian-help-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    cursor: move;
    touch-action: none;
    user-select: none;
}

#guardian-help-container.dragging {
    opacity: 0.8;
}

#guardian-help-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    transition: box-shadow 0.3s ease, transform 0.1s ease;
    font-size: 14px;
    font-weight: 600;
}

#guardian-help-toggle:hover {
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
}

#guardian-help-toggle .help-icon-btn {
    font-size: 18px;
}

#guardian-help-window {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 380px;
    max-width: calc(100vw - 40px);
    height: 500px;
    max-height: calc(100vh - 120px);
    background: var(--md-bg-primary, #ffffff);
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: all 0.3s ease;
}

#guardian-help-window.help-hidden {
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.95);
}

#guardian-help-container .help-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

#guardian-help-container .help-header-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 16px;
}

#guardian-help-container .help-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

#guardian-help-container .help-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

#guardian-help-container .help-content {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: var(--md-bg-secondary, #f5f5f7);
}

#guardian-help-container .help-intro {
    text-align: center;
    color: var(--text-secondary, #86868b);
    margin-bottom: 16px;
    font-size: 14px;
}

#guardian-help-container .help-category-btn {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 14px 16px;
    margin-bottom: 8px;
    background: var(--md-bg-primary, #ffffff);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

#guardian-help-container .help-category-btn:hover {
    background: var(--md-gray-5, #e5e5ea);
    transform: translateX(4px);
}

#guardian-help-container .category-icon {
    font-size: 24px;
    margin-right: 12px;
    display: flex;
    align-items: center;
}

#guardian-help-container .category-icon .material-symbols-outlined {
    font-size: 24px;
}

#guardian-help-container .category-title {
    flex: 1;
    font-size: 15px;
    font-weight: 500;
    color: var(--text-primary, #1d1d1f);
}

#guardian-help-container .category-arrow {
    font-size: 20px;
    color: var(--text-tertiary, #86868b);
}

#guardian-help-container .help-back-btn {
    display: inline-block;
    padding: 8px 12px;
    margin-bottom: 16px;
    background: transparent;
    border: none;
    color: #667eea;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.2s;
}

#guardian-help-container .help-back-btn:hover {
    color: #5a6fd6;
}

#guardian-help-container .help-category-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary, #1d1d1f);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #667eea;
    display: flex;
    align-items: center;
    gap: 8px;
}

#guardian-help-container .help-category-title .material-symbols-outlined {
    font-size: 22px;
}

#guardian-help-container .help-item-btn {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 14px 16px;
    margin-bottom: 8px;
    background: var(--md-bg-primary, #ffffff);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

#guardian-help-container .help-item-btn:hover {
    background: var(--md-gray-5, #e5e5ea);
    transform: translateX(4px);
}

#guardian-help-container .item-question {
    flex: 1;
    font-size: 14px;
    color: var(--text-primary, #1d1d1f);
}

#guardian-help-container .item-arrow {
    font-size: 18px;
    color: var(--text-tertiary, #86868b);
}

#guardian-help-container .help-answer-content {
    background: var(--md-bg-primary, #ffffff);
    padding: 20px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.8;
    color: var(--text-primary, #1d1d1f);
    white-space: pre-wrap;
}

/* モバイル対応 */
@media (max-width: 480px) {
    #guardian-help-window {
        width: calc(100vw - 20px);
        right: -10px;
        bottom: 60px;
        height: 70vh;
    }

    #guardian-help-toggle .help-label {
        display: none;
    }

    #guardian-help-toggle {
        width: 56px;
        height: 56px;
        padding: 0;
        justify-content: center;
        border-radius: 50%;
    }

    #guardian-help-toggle .help-icon-btn {
        font-size: 24px;
    }
}
</style>

<script>
// ヘルプデータ（PHPから渡す）
const guardianHelpData = <?= json_encode($helpData, JSON_UNESCAPED_UNICODE) ?>;
const guardianDefaultCategoryId = <?= json_encode($defaultCategoryId) ?>;

let guardianHelpOpen = false;
let guardianCurrentCategoryId = null;

// ヘルプの開閉
function toggleGuardianHelp() {
    const window = document.getElementById('guardian-help-window');
    guardianHelpOpen = !guardianHelpOpen;

    if (guardianHelpOpen) {
        window.classList.remove('help-hidden');
        // デフォルトカテゴリがあればそのカテゴリを表示
        if (guardianDefaultCategoryId) {
            showGuardianCategory(guardianDefaultCategoryId);
        } else {
            showGuardianCategories();
        }
    } else {
        window.classList.add('help-hidden');
    }
}

// カテゴリ一覧を表示
function showGuardianCategories() {
    document.getElementById('guardian-help-categories').style.display = 'block';
    document.getElementById('guardian-help-answer').style.display = 'none';

    // 全カテゴリ項目を非表示
    guardianHelpData.categories.forEach(cat => {
        document.getElementById('guardian-help-category-' + cat.id).style.display = 'none';
    });

    guardianCurrentCategoryId = null;
}

// カテゴリの質問一覧を表示
function showGuardianCategory(categoryId) {
    document.getElementById('guardian-help-categories').style.display = 'none';
    document.getElementById('guardian-help-answer').style.display = 'none';

    // 全カテゴリ項目を非表示
    guardianHelpData.categories.forEach(cat => {
        document.getElementById('guardian-help-category-' + cat.id).style.display = 'none';
    });

    // 選択したカテゴリを表示
    document.getElementById('guardian-help-category-' + categoryId).style.display = 'block';
    guardianCurrentCategoryId = categoryId;
}

// 回答を表示
function showGuardianAnswer(categoryId, itemIndex) {
    const category = guardianHelpData.categories.find(c => c.id === categoryId);
    if (!category) return;

    const item = category.items[itemIndex];
    if (!item) return;

    document.getElementById('guardian-help-categories').style.display = 'none';
    guardianHelpData.categories.forEach(cat => {
        document.getElementById('guardian-help-category-' + cat.id).style.display = 'none';
    });

    const answerContent = document.getElementById('guardian-help-answer-content');
    answerContent.textContent = item.answer;

    document.getElementById('guardian-help-answer').style.display = 'block';
    guardianCurrentCategoryId = categoryId;
}

// 質問一覧に戻る
function backToGuardianItems() {
    if (guardianCurrentCategoryId) {
        showGuardianCategory(guardianCurrentCategoryId);
    } else {
        showGuardianCategories();
    }
}

// ドラッグ機能
(function() {
    const container = document.getElementById('guardian-help-container');
    if (!container) return;

    let isDragging = false;
    let hasMoved = false;
    let startX, startY, startRight, startBottom;

    // 保存された位置を復元
    function restorePosition() {
        const saved = localStorage.getItem('guardianHelpButtonPosition');
        if (saved) {
            try {
                const pos = JSON.parse(saved);
                container.style.right = pos.right + 'px';
                container.style.bottom = pos.bottom + 'px';
            } catch(e) {}
        }
    }

    // 位置を保存
    function savePosition() {
        const rect = container.getBoundingClientRect();
        const pos = {
            right: window.innerWidth - rect.right,
            bottom: window.innerHeight - rect.bottom
        };
        localStorage.setItem('guardianHelpButtonPosition', JSON.stringify(pos));
    }

    // 位置を画面内に収める
    function clampPosition() {
        const rect = container.getBoundingClientRect();
        let right = window.innerWidth - rect.right;
        let bottom = window.innerHeight - rect.bottom;

        // 画面外に出ないように制限
        right = Math.max(10, Math.min(right, window.innerWidth - rect.width - 10));
        bottom = Math.max(10, Math.min(bottom, window.innerHeight - rect.height - 10));

        container.style.right = right + 'px';
        container.style.bottom = bottom + 'px';
    }

    // マウス/タッチ開始
    function onStart(e) {
        // ヘルプウィンドウ内のクリックは無視
        if (e.target.closest('#guardian-help-window')) return;

        isDragging = true;
        hasMoved = false;
        container.classList.add('dragging');

        const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;

        startX = clientX;
        startY = clientY;

        const rect = container.getBoundingClientRect();
        startRight = window.innerWidth - rect.right;
        startBottom = window.innerHeight - rect.bottom;

        e.preventDefault();
    }

    // マウス/タッチ移動
    function onMove(e) {
        if (!isDragging) return;

        const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;

        const deltaX = startX - clientX;
        const deltaY = startY - clientY;

        // 5px以上動いたら移動とみなす
        if (Math.abs(deltaX) > 5 || Math.abs(deltaY) > 5) {
            hasMoved = true;
        }

        if (hasMoved) {
            container.style.right = (startRight + deltaX) + 'px';
            container.style.bottom = (startBottom + deltaY) + 'px';
            clampPosition();
        }
    }

    // マウス/タッチ終了
    function onEnd(e) {
        if (!isDragging) return;

        isDragging = false;
        container.classList.remove('dragging');

        if (hasMoved) {
            savePosition();
            // ドラッグ終了時はクリックイベントをキャンセル
            e.preventDefault();
            e.stopPropagation();
        }
    }

    // イベントリスナー登録
    container.addEventListener('mousedown', onStart);
    container.addEventListener('touchstart', onStart, { passive: false });

    document.addEventListener('mousemove', onMove);
    document.addEventListener('touchmove', onMove, { passive: false });

    document.addEventListener('mouseup', onEnd);
    document.addEventListener('touchend', onEnd);

    // ヘルプボタンのクリックハンドラを修正
    const originalToggle = window.toggleGuardianHelp;
    window.toggleGuardianHelp = function() {
        if (!hasMoved) {
            originalToggle();
        }
        hasMoved = false;
    };

    // ウィンドウリサイズ時に位置を調整
    window.addEventListener('resize', clampPosition);

    // 初期化
    restorePosition();
})();
</script>
