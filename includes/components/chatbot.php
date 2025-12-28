<?php
/**
 * ヘルプサポートコンポーネント（選択式）
 * スタッフ画面に組み込む操作サポート
 */

// 現在のページからカテゴリを判定
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$pageToCategory = [
    // 活動管理・連絡帳
    'renrakucho_activities' => 'activity',
    'renrakucho' => 'activity',
    'renrakucho_form' => 'activity',
    'renrakucho_edit' => 'activity',

    // 支援案
    'support_plans' => 'support_plan',
    'support_plan_form' => 'support_plan',
    'daily_routines_settings' => 'support_plan',
    'tag_settings' => 'support_plan',

    // かけはし
    'kakehashi_staff' => 'kakehashi',
    'kakehashi_guardian_view' => 'kakehashi',
    'kakehashi_staff_edit' => 'kakehashi',

    // 個別支援計画・モニタリング
    'kobetsu_plan' => 'plan',
    'kobetsu_monitoring' => 'plan',
    'student_weekly_plans' => 'plan',

    // 生徒・保護者管理
    'students' => 'user',
    'guardians' => 'user',
    'bulk_register' => 'user',
    'bulk_register_confirm' => 'user',
    'bulk_register_ai_confirm' => 'user',

    // チャット
    'chat' => 'chat',
    'student_chats' => 'chat',

    // その他
    'newsletter_create' => 'other',
    'holidays' => 'other',
    'makeup_requests' => 'other',
    'events' => 'other',
];

$defaultCategoryId = $pageToCategory[$currentPage] ?? null;

// ヘルプデータ
$helpData = [
    'categories' => [
        [
            'id' => 'activity',
            'icon' => '📝',
            'title' => '活動管理・連絡帳',
            'items' => [
                [
                    'question' => '活動を登録するには？',
                    'answer' => "【活動の登録方法】\n\n1. メニューから「活動管理」を開く\n2. 「新しい活動を追加」ボタンをクリック\n3. 活動名、日付、内容を入力\n4. 参加者を選択\n5. 「保存」ボタンで登録完了"
                ],
                [
                    'question' => '連絡帳を保護者に送るには？',
                    'answer' => "【連絡帳の送信方法】\n\n1. 活動管理で活動を登録\n2. 「統合する」ボタンをクリック（AIが連絡帳を自動生成）\n3. 「統合内容を編集」で内容を確認・修正\n4. 「連絡帳を送信」ボタンで保護者に配信\n\n※統合には数秒かかります"
                ],
                [
                    'question' => '活動を編集・削除するには？',
                    'answer' => "【活動の編集・削除】\n\n■ 編集\n活動一覧から該当の活動の「編集」ボタンをクリック\n\n■ 削除\n「削除」ボタンをクリック（確認ダイアログが表示されます）\n\n※送信済みの連絡帳は削除できません"
                ],
                [
                    'question' => '統合とは何ですか？',
                    'answer' => "【統合機能について】\n\n「統合」は、登録した活動内容をもとに、AIが参加者ごとの連絡帳を自動生成する機能です。\n\n■ 統合する：AIが連絡帳を生成\n■ 統合内容を編集：生成された内容を確認・修正\n■ 送信済み内容を閲覧：送信後の内容を確認"
                ]
            ]
        ],
        [
            'id' => 'support_plan',
            'icon' => '📋',
            'title' => '支援案',
            'items' => [
                [
                    'question' => '支援案を作成するには？',
                    'answer' => "【支援案の作成方法】\n\n1. メニューから「支援案」を開く\n2. 「新しい支援案を作成」ボタンをクリック\n3. 活動名、目的、内容、五領域への配慮を入力\n4. 「保存」で登録完了\n\n作成した支援案は活動登録時に選択して使用できます"
                ],
                [
                    'question' => '毎日の支援とは？',
                    'answer' => "【毎日の支援について】\n\n「朝の会」「帰りの会」など、毎日行う定例活動を設定できます。\n\n1. 支援案画面で「毎日の支援を設定」をクリック\n2. 活動名と内容を入力\n3. 保存\n\n設定した活動は、活動登録画面で簡単に追加できます"
                ],
                [
                    'question' => 'タグの使い方は？',
                    'answer' => "【タグについて】\n\n支援案を分類するためのラベルです。\n\n■ タグの設定\n支援案画面で「タグを設定」から管理\n\n■ タグの活用\n・支援案作成時にタグを付ける\n・検索時にタグでフィルター\n\nデフォルト：動画、食、学習、イベント、その他"
                ]
            ]
        ],
        [
            'id' => 'kakehashi',
            'icon' => '🌉',
            'title' => 'かけはし',
            'items' => [
                [
                    'question' => 'かけはしとは？',
                    'answer' => "【かけはしについて】\n\n個別支援計画書を作成するための情報収集ツールです。\n\n■ かけはし（職員）\nスタッフが生徒の様子や目標を記入\n\n■ かけはし（保護者）\n保護者が家庭での様子や希望を記入\n\n両方の情報をもとに個別支援計画を作成します"
                ],
                [
                    'question' => 'かけはしの記入方法は？',
                    'answer' => "【かけはしの記入】\n\n1. メニューから「かけはし（職員）」を開く\n2. 生徒を選択\n3. 提出期限を選択\n4. 各項目を入力（本人の願い、目標、五領域など）\n5. 「下書き保存」または「提出」\n\n※提出期限は個別支援計画の期間に連動しています"
                ],
                [
                    'question' => '保護者のかけはしを確認するには？',
                    'answer' => "【保護者かけはしの確認】\n\n1. メニューから「かけはし（保護者）」を開く\n2. 生徒を選択\n3. 提出期限を選択\n4. 保護者が入力した内容を確認\n\n※保護者が未入力の場合は「まだ作成されていません」と表示されます"
                ]
            ]
        ],
        [
            'id' => 'plan',
            'icon' => '📊',
            'title' => '個別支援計画・モニタリング',
            'items' => [
                [
                    'question' => '個別支援計画を作成するには？',
                    'answer' => "【個別支援計画の作成】\n\n1. メニューから「個別支援計画」を開く\n2. 生徒を選択\n3. 「新規作成」または「AI生成」ボタン\n4. かけはしの情報をもとに計画を入力\n5. 保存して完了\n\n※かけはしを事前に記入しておくとスムーズです"
                ],
                [
                    'question' => 'モニタリングとは？',
                    'answer' => "【モニタリングについて】\n\n個別支援計画の進捗を確認する表です。\n\n■ 作成タイミング\n次の個別支援計画作成の1ヶ月前までに作成\n\n■ 記入内容\n・目標の達成状況\n・課題と改善点\n・次期への引き継ぎ事項"
                ],
                [
                    'question' => '週間計画の使い方は？',
                    'answer' => "【週間計画について】\n\n生徒ごとの週間目標・計画を設定できます。\n\n1. メニューから「週間計画」を開く\n2. 生徒と週を選択\n3. 各曜日の目標や活動を入力\n4. 保存\n\n保護者も週間計画を確認できます"
                ]
            ]
        ],
        [
            'id' => 'user',
            'icon' => '👥',
            'title' => '生徒・保護者管理',
            'items' => [
                [
                    'question' => '生徒を登録するには？',
                    'answer' => "【生徒の登録方法】\n\n1. メニューから「生徒管理」を開く\n2. 「新規登録」ボタンをクリック\n3. 氏名、生年月日、支援開始日を入力\n4. 通所曜日を選択\n5. 保護者を選択（先に保護者登録が必要）\n6. 保存で完了"
                ],
                [
                    'question' => '保護者を登録するには？',
                    'answer' => "【保護者の登録方法】\n\n1. メニューから「保護者管理」を開く\n2. 「新規登録」ボタンをクリック\n3. 氏名を入力\n4. 保存で完了\n\n※ログインID・パスワードは自動生成されます\n※編集画面でパスワードを確認できます"
                ],
                [
                    'question' => '一括登録するには？',
                    'answer' => "【利用者の一括登録】\n\n1. メニューから「利用者一括登録」を開く\n2. CSVファイルをアップロード、またはテキストを貼り付けてAI解析\n3. 確認画面で内容をチェック\n4. 「登録する」で一括登録\n5. ID/パスワード一覧をPDFでダウンロード可能"
                ],
                [
                    'question' => 'パスワードを確認・変更するには？',
                    'answer' => "【パスワードの確認・変更】\n\n1. 「保護者管理」を開く\n2. 該当の保護者の「編集」ボタン\n3. ログイン情報セクションでパスワードを確認\n4. 「コピー」でクリップボードにコピー\n5. 「自動生成」で新しいパスワードを発行可能"
                ]
            ]
        ],
        [
            'id' => 'chat',
            'icon' => '💬',
            'title' => 'チャット',
            'items' => [
                [
                    'question' => '保護者とチャットするには？',
                    'answer' => "【保護者チャット】\n\n1. メニューから「保護者チャット」を開く\n2. チャット相手を選択\n3. メッセージを入力して送信\n\n■ ピン留め機能\n重要なチャットを上部に固定できます\n\n■ 一斉送信\n「一斉送信」ボタンで全保護者にメッセージ送信"
                ],
                [
                    'question' => '生徒とチャットするには？',
                    'answer' => "【生徒チャット】\n\n1. メニューから「生徒チャット」を開く\n2. チャット相手を選択\n3. メッセージを入力して送信\n\n※生徒がログインしている場合のみ利用可能です"
                ]
            ]
        ],
        [
            'id' => 'other',
            'icon' => '⚙️',
            'title' => 'その他',
            'items' => [
                [
                    'question' => '施設通信を作成するには？',
                    'answer' => "【施設通信の作成】\n\n1. メニューから「施設通信」を開く\n2. 「新規作成」ボタン\n3. タイトル、期間を設定\n4. 「AI生成」で下書きを自動作成\n5. 内容を編集して保存\n6. 「公開」で保護者に配信"
                ],
                [
                    'question' => '休日を設定するには？',
                    'answer' => "【休日設定】\n\n1. メニューから「休日設定」を開く\n2. カレンダーで日付を選択\n3. 休日名を入力（例：年末年始、お盆休み）\n4. 保存で完了\n\n設定した休日は出欠管理に反映されます"
                ],
                [
                    'question' => '振替管理について',
                    'answer' => "【振替管理】\n\n保護者からの振替リクエストを管理します。\n\n1. メニューから「振替管理」を開く\n2. リクエスト一覧を確認\n3. 「承認」または「却下」を選択\n\n保護者は専用画面から振替を申請できます"
                ],
                [
                    'question' => 'イベントを登録するには？',
                    'answer' => "【イベント登録】\n\n1. メニューから「イベント」を開く\n2. 「新規作成」ボタン\n3. イベント名、日時、内容を入力\n4. 保存で完了\n\n登録したイベントは保護者にも表示されます"
                ]
            ]
        ]
    ]
];
?>

<!-- ヘルプサポート -->
<div id="help-container">
    <!-- ヘルプ開始ボタン -->
    <button id="help-toggle" onclick="toggleHelp()">
        <span class="help-icon-btn">❓</span>
        <span class="help-label">ヘルプ</span>
    </button>

    <!-- ヘルプウィンドウ -->
    <div id="help-window" class="help-hidden">
        <div class="help-header">
            <div class="help-header-title">
                <span>📖</span>
                <span>操作ヘルプ</span>
            </div>
            <button class="help-close" onclick="toggleHelp()">×</button>
        </div>

        <div id="help-content" class="help-content">
            <!-- カテゴリ一覧（初期表示） -->
            <div id="help-categories" class="help-categories">
                <p class="help-intro">知りたい項目を選んでください</p>
                <?php foreach ($helpData['categories'] as $category): ?>
                <button class="help-category-btn" onclick="showCategory('<?= $category['id'] ?>')">
                    <span class="category-icon"><?= $category['icon'] ?></span>
                    <span class="category-title"><?= htmlspecialchars($category['title']) ?></span>
                    <span class="category-arrow">›</span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- 質問一覧（カテゴリ選択後） -->
            <?php foreach ($helpData['categories'] as $category): ?>
            <div id="help-category-<?= $category['id'] ?>" class="help-items" style="display: none;">
                <button class="help-back-btn" onclick="showCategories()">
                    ← カテゴリに戻る
                </button>
                <h3 class="help-category-title"><?= $category['icon'] ?> <?= htmlspecialchars($category['title']) ?></h3>
                <?php foreach ($category['items'] as $index => $item): ?>
                <button class="help-item-btn" onclick="showAnswer('<?= $category['id'] ?>', <?= $index ?>)">
                    <span class="item-question"><?= htmlspecialchars($item['question']) ?></span>
                    <span class="item-arrow">›</span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <!-- 回答表示 -->
            <div id="help-answer" class="help-answer" style="display: none;">
                <button class="help-back-btn" onclick="backToItems()">
                    ← 質問一覧に戻る
                </button>
                <div id="help-answer-content" class="help-answer-content"></div>
            </div>
        </div>
    </div>
</div>

<style>
#help-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

#help-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #34c759 0%, #30d158 100%);
    color: white;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(52, 199, 89, 0.4);
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 600;
}

#help-toggle:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 199, 89, 0.5);
}

#help-toggle .help-icon-btn {
    font-size: 18px;
}

#help-window {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 380px;
    max-width: calc(100vw - 40px);
    height: 500px;
    max-height: calc(100vh - 120px);
    background: var(--apple-bg-primary, #ffffff);
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: all 0.3s ease;
}

#help-window.help-hidden {
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.95);
}

.help-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, #34c759 0%, #30d158 100%);
    color: white;
}

.help-header-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 16px;
}

.help-close {
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

.help-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.help-content {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: var(--apple-bg-secondary, #f5f5f7);
}

.help-intro {
    text-align: center;
    color: var(--text-secondary, #86868b);
    margin-bottom: 16px;
    font-size: 14px;
}

.help-category-btn {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 14px 16px;
    margin-bottom: 8px;
    background: var(--apple-bg-primary, #ffffff);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.help-category-btn:hover {
    background: var(--apple-gray-5, #e5e5ea);
    transform: translateX(4px);
}

.category-icon {
    font-size: 24px;
    margin-right: 12px;
}

.category-title {
    flex: 1;
    font-size: 15px;
    font-weight: 500;
    color: var(--text-primary, #1d1d1f);
}

.category-arrow {
    font-size: 20px;
    color: var(--text-tertiary, #86868b);
}

.help-back-btn {
    display: inline-block;
    padding: 8px 12px;
    margin-bottom: 16px;
    background: transparent;
    border: none;
    color: #34c759;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.2s;
}

.help-back-btn:hover {
    color: #248a3d;
}

.help-category-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary, #1d1d1f);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #34c759;
}

.help-item-btn {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 14px 16px;
    margin-bottom: 8px;
    background: var(--apple-bg-primary, #ffffff);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.help-item-btn:hover {
    background: var(--apple-gray-5, #e5e5ea);
    transform: translateX(4px);
}

.item-question {
    flex: 1;
    font-size: 14px;
    color: var(--text-primary, #1d1d1f);
}

.item-arrow {
    font-size: 18px;
    color: var(--text-tertiary, #86868b);
}

.help-answer-content {
    background: var(--apple-bg-primary, #ffffff);
    padding: 20px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.8;
    color: var(--text-primary, #1d1d1f);
    white-space: pre-wrap;
}

/* モバイル対応 */
@media (max-width: 480px) {
    #help-window {
        width: calc(100vw - 20px);
        right: -10px;
        bottom: 60px;
        height: 70vh;
    }

    #help-toggle .help-label {
        display: none;
    }

    #help-toggle {
        width: 56px;
        height: 56px;
        padding: 0;
        justify-content: center;
        border-radius: 50%;
    }

    #help-toggle .help-icon-btn {
        font-size: 24px;
    }
}

/* ダークモード対応 */
@media (prefers-color-scheme: dark) {
    .help-content {
        background: #1c1c1e;
    }

    .help-category-btn,
    .help-item-btn,
    .help-answer-content {
        background: #2c2c2e;
    }

    .help-category-btn:hover,
    .help-item-btn:hover {
        background: #3a3a3c;
    }

    .category-title,
    .item-question,
    .help-answer-content,
    .help-category-title {
        color: #f5f5f7;
    }
}
</style>

<script>
// ヘルプデータ（PHPから渡す）
const helpData = <?= json_encode($helpData, JSON_UNESCAPED_UNICODE) ?>;
const defaultCategoryId = <?= json_encode($defaultCategoryId) ?>;

let helpOpen = false;
let currentCategoryId = null;

// ヘルプの開閉
function toggleHelp() {
    const window = document.getElementById('help-window');
    helpOpen = !helpOpen;

    if (helpOpen) {
        window.classList.remove('help-hidden');
        // デフォルトカテゴリがあればそのカテゴリを表示
        if (defaultCategoryId) {
            showCategory(defaultCategoryId);
        } else {
            showCategories();
        }
    } else {
        window.classList.add('help-hidden');
    }
}

// カテゴリ一覧を表示
function showCategories() {
    document.getElementById('help-categories').style.display = 'block';
    document.getElementById('help-answer').style.display = 'none';

    // 全カテゴリ項目を非表示
    helpData.categories.forEach(cat => {
        document.getElementById('help-category-' + cat.id).style.display = 'none';
    });

    currentCategoryId = null;
}

// カテゴリの質問一覧を表示
function showCategory(categoryId) {
    document.getElementById('help-categories').style.display = 'none';
    document.getElementById('help-answer').style.display = 'none';

    // 全カテゴリ項目を非表示
    helpData.categories.forEach(cat => {
        document.getElementById('help-category-' + cat.id).style.display = 'none';
    });

    // 選択したカテゴリを表示
    document.getElementById('help-category-' + categoryId).style.display = 'block';
    currentCategoryId = categoryId;
}

// 回答を表示
function showAnswer(categoryId, itemIndex) {
    const category = helpData.categories.find(c => c.id === categoryId);
    if (!category) return;

    const item = category.items[itemIndex];
    if (!item) return;

    document.getElementById('help-categories').style.display = 'none';
    helpData.categories.forEach(cat => {
        document.getElementById('help-category-' + cat.id).style.display = 'none';
    });

    const answerContent = document.getElementById('help-answer-content');
    answerContent.textContent = item.answer;

    document.getElementById('help-answer').style.display = 'block';
    currentCategoryId = categoryId;
}

// 質問一覧に戻る
function backToItems() {
    if (currentCategoryId) {
        showCategory(currentCategoryId);
    } else {
        showCategories();
    }
}
</script>
