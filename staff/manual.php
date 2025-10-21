<?php
/**
 * スタッフ向けマニュアルページ
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スタッフマニュアル - 個別支援連絡帳システム</title>
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
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .back-btn {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
            font-weight: 600;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .content {
            padding: 40px;
        }

        .section {
            margin-bottom: 40px;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .section h2 {
            color: #667eea;
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section h3 {
            color: #333;
            font-size: 18px;
            margin: 20px 0 10px 0;
            padding-left: 15px;
            border-left: 3px solid #667eea;
        }

        .section p {
            color: #555;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .section ul {
            margin-left: 30px;
            margin-bottom: 15px;
        }

        .section li {
            color: #555;
            line-height: 1.8;
            margin-bottom: 8px;
        }

        .feature-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .feature-title {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .step-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 3px solid #28a745;
        }

        .step-number {
            display: inline-block;
            background: #28a745;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            margin-right: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .note-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .note-box strong {
            color: #856404;
        }

        .tip-box {
            background: #d1ecf1;
            border: 1px solid #17a2b8;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .tip-box strong {
            color: #0c5460;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                border-radius: 0;
            }

            .header {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .back-btn {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .content {
                padding: 20px;
            }

            .section {
                padding: 20px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📖 スタッフマニュアル</h1>
            <a href="renrakucho_activities.php" class="back-btn">← 活動管理へ戻る</a>
        </div>

        <div class="content">
            <!-- システム概要 -->
            <div class="section">
                <h2>🏠 システム概要</h2>
                <p>
                    個別支援連絡帳システムは、特別支援教育における日々の活動記録と保護者とのコミュニケーションを効率化するためのシステムです。
                </p>
                <ul>
                    <li>活動記録の作成と管理</li>
                    <li>支援案（事前計画）の作成</li>
                    <li>複数スタッフの記録を統合して保護者向けメッセージを自動生成</li>
                    <li>保護者とのチャット機能</li>
                    <li>個別支援計画・モニタリング表の作成</li>
                    <li>かけはし（引継ぎ記録）の管理</li>
                </ul>
            </div>

            <!-- 基本的な使い方 -->
            <div class="section">
                <h2>📝 基本的な使い方</h2>

                <h3>1. 支援案の作成</h3>
                <p>活動を実施する前に、支援案（事前計画）を作成します。</p>

                <div class="step-box">
                    <span class="step-number">1</span>
                    活動管理ページで「📝 支援案を管理」ボタンをクリック
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
                    <strong>💡 ヒント:</strong> 過去の支援案を引用して編集することもできます。「📋 過去の支援案を引用する」ボタンから検索できます。
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

                <h3>3. 記録の統合と送信</h3>
                <p>複数のスタッフが記録した内容を統合し、保護者向けメッセージを生成します。</p>

                <div class="step-box">
                    <span class="step-number">1</span>
                    活動管理ページで該当の活動を探す
                </div>
                <div class="step-box">
                    <span class="step-number">2</span>
                    「🔄 統合する」ボタンをクリック（初めて統合する場合）<br>
                    または「✏️ 統合内容を編集」ボタンをクリック（既に統合済みの場合）
                </div>
                <div class="step-box">
                    <span class="step-number">3</span>
                    AIが自動生成した保護者向けメッセージを確認・編集
                </div>
                <div class="step-box">
                    <span class="step-number">4</span>
                    「💾 統合内容を保存（下書き）」で下書き保存、または「📤 保護者に送信」で送信
                </div>

                <div class="note-box">
                    <strong>⚠️ 注意:</strong>
                    <ul style="margin-top: 8px;">
                        <li>「🔄 統合する」は未送信の統合内容を削除して1から作り直します</li>
                        <li>「✏️ 統合内容を編集」は最後に保存した内容を編集できます</li>
                        <li>統合内容は5分ごとに自動保存されます（Ctrl+Sで手動保存も可能）</li>
                    </ul>
                </div>
            </div>

            <!-- チャット機能 -->
            <div class="section">
                <h2>💬 チャット機能</h2>
                <p>保護者と1対1でチャットができます。</p>

                <div class="feature-box">
                    <div class="feature-title">基本機能</div>
                    <ul>
                        <li>テキストメッセージの送受信</li>
                        <li>ファイル添付（最大3MB、1ヶ月間保存）</li>
                        <li>欠席連絡の受信</li>
                        <li>イベント参加申し込みの受信</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">提出期限の設定</div>
                    <p>保護者に提出物の期限を通知できます。</p>
                    <div class="step-box">
                        <span class="step-number">1</span>
                        チャット画面で「📅 提出期限を設定」ボタンをクリック
                    </div>
                    <div class="step-box">
                        <span class="step-number">2</span>
                        件名、説明、期限日を入力（ファイル添付も可能）
                    </div>
                    <div class="step-box">
                        <span class="step-number">3</span>
                        保護者のダッシュボードに期限アラートが表示されます
                    </div>
                    <div class="step-box">
                        <span class="step-number">4</span>
                        「📮 提出期限管理」ページで完了/未完了を管理できます
                    </div>
                </div>
            </div>

            <!-- かけはし機能 -->
            <div class="section">
                <h2>🌉 かけはし（引継ぎ記録）</h2>
                <p>スタッフと保護者の双方向の引継ぎ記録です。</p>

                <div class="feature-box">
                    <div class="feature-title">スタッフかけはし入力</div>
                    <p>施設での様子を保護者に伝えます。</p>
                    <ul>
                        <li>生徒を選択して記録を入力</li>
                        <li>期間を設定して一括入力も可能</li>
                        <li>過去の記録の閲覧・編集</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">保護者かけはし確認</div>
                    <p>保護者が入力した家庭での様子を確認できます。</p>
                    <ul>
                        <li>生徒ごとに保護者の記録を閲覧</li>
                        <li>期間を指定して検索</li>
                        <li>コメント機能で返信も可能</li>
                    </ul>
                </div>
            </div>

            <!-- 個別支援計画 -->
            <div class="section">
                <h2>📄 個別支援計画・モニタリング</h2>

                <div class="feature-box">
                    <div class="feature-title">個別支援計画書作成</div>
                    <p>生徒ごとの支援計画をAIの支援を受けながら作成できます。</p>
                    <div class="step-box">
                        <span class="step-number">1</span>
                        「🌉 かけはし管理」→「📄 個別支援計画書作成」を選択
                    </div>
                    <div class="step-box">
                        <span class="step-number">2</span>
                        生徒と対象期間を選択
                    </div>
                    <div class="step-box">
                        <span class="step-number">3</span>
                        AIが過去の記録から素案を生成
                    </div>
                    <div class="step-box">
                        <span class="step-number">4</span>
                        内容を確認・編集して保存
                    </div>
                    <div class="step-box">
                        <span class="step-number">5</span>
                        PDF出力も可能
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title">モニタリング表作成</div>
                    <p>支援計画の実施状況を記録・評価します。</p>
                    <ul>
                        <li>個別支援計画に基づいた評価</li>
                        <li>AIによる評価文の生成支援</li>
                        <li>保護者への共有・確認依頼</li>
                    </ul>
                </div>
            </div>

            <!-- マスタ管理 -->
            <div class="section">
                <h2>⚙️ マスタ管理</h2>

                <div class="feature-box">
                    <div class="feature-title">生徒管理</div>
                    <ul>
                        <li>生徒情報の登録・編集</li>
                        <li>保護者との紐付け</li>
                        <li>在籍状況の管理</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">保護者管理</div>
                    <ul>
                        <li>保護者アカウントの作成・編集</li>
                        <li>ログイン情報の管理</li>
                        <li>複数の子どもの紐付け</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">休日・イベント管理</div>
                    <ul>
                        <li>施設の休日を登録（保護者カレンダーに表示）</li>
                        <li>イベント情報を登録（保護者が参加申し込み可能）</li>
                    </ul>
                </div>
            </div>

            <!-- よくある質問 -->
            <div class="section">
                <h2>❓ よくある質問</h2>

                <div class="feature-box">
                    <div class="feature-title">Q: 支援案は必ず作成する必要がありますか？</div>
                    <p>
                        A: いいえ、必須ではありません。ただし、支援案を作成しておくと：
                    </p>
                    <ul>
                        <li>連絡帳の「本日の活動」に内容が自動入力されます</li>
                        <li>AIによる統合時に支援案の情報も考慮されます</li>
                        <li>活動の質と一貫性が向上します</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">Q: 他のスタッフが作成した活動記録を編集できますか？</div>
                    <p>
                        A: はい、同じ教室のスタッフが作成した記録は閲覧・編集できます。
                    </p>
                </div>

                <div class="feature-box">
                    <div class="feature-title">Q: 統合内容を送信した後に修正できますか？</div>
                    <p>
                        A: 送信後の修正は基本的にできません。送信前に必ず内容を確認してください。下書き保存を活用することをお勧めします。
                    </p>
                </div>

                <div class="feature-box">
                    <div class="feature-title">Q: チャットの添付ファイルはいつまで保存されますか？</div>
                    <p>
                        A: 添付ファイルは1ヶ月間保存されます。それ以降は自動的に削除されます。
                    </p>
                </div>
            </div>

            <!-- ヒントとコツ -->
            <div class="section">
                <h2>💡 ヒントとコツ</h2>

                <div class="tip-box">
                    <strong>支援案の活用</strong>
                    <p style="margin-top: 8px;">
                        過去に作成した似た活動の支援案を引用すると、作成時間を大幅に短縮できます。検索機能や期間指定を活用しましょう。
                    </p>
                </div>

                <div class="tip-box">
                    <strong>統合内容の下書き保存</strong>
                    <p style="margin-top: 8px;">
                        統合内容は自動保存されますが、Ctrl+Sで手動保存も可能です。他のスタッフと相談しながら編集する際は、こまめに保存しましょう。
                    </p>
                </div>

                <div class="tip-box">
                    <strong>チャット機能の活用</strong>
                    <p style="margin-top: 8px;">
                        急ぎの連絡や簡単な質問はチャットが便利です。提出期限機能を使えば、保護者への依頼事項を忘れずに管理できます。
                    </p>
                </div>

                <div class="tip-box">
                    <strong>記録の一貫性</strong>
                    <p style="margin-top: 8px;">
                        支援案で設定した「五領域への配慮」を意識して記録を書くと、より質の高い統合メッセージが生成されます。
                    </p>
                </div>
            </div>

            <!-- お問い合わせ -->
            <div class="section">
                <h2>📞 お問い合わせ</h2>
                <p>
                    システムの使い方で不明な点がある場合は、施設の管理者にお問い合わせください。
                </p>
                <p style="margin-top: 15px;">
                    <strong>ログイン中のユーザー:</strong> <?php echo htmlspecialchars($currentUser['full_name']); ?>さん
                </p>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>
