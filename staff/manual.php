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
                <p style="margin-top: 15px;">
                    システムは以下の4つの主要機能で構成されています：
                </p>
                <ul>
                    <li><strong>👨‍👩‍👧 保護者</strong> - 保護者チャット、提出期限管理</li>
                    <li><strong>🎓 生徒</strong> - 生徒チャット、週間計画表</li>
                    <li><strong>🌉 かけはし管理</strong> - スタッフかけはし入力、保護者かけはし確認、個別支援計画書作成、モニタリング表作成、施設通信を作成</li>
                    <li><strong>⚙️ マスタ管理</strong> - 生徒管理、保護者管理、休日管理、イベント管理</li>
                </ul>
            </div>

            <!-- メニュー構造 -->
            <div class="section">
                <h2>📋 メニュー構成</h2>
                <p>
                    各ページの上部にドロップダウンメニューがあり、すべての機能に素早くアクセスできます。
                </p>

                <div class="feature-box">
                    <div class="feature-title">👨‍👩‍👧 保護者メニュー</div>
                    <ul>
                        <li><strong>💬 保護者チャット</strong> - 保護者と1対1のチャット、ファイル添付、欠席・イベント登録の受付</li>
                        <li><strong>📮 提出期限管理</strong> - 保護者への提出依頼の管理、完了/未完了の確認</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">🎓 生徒メニュー</div>
                    <ul>
                        <li><strong>💬 生徒チャット</strong> - 生徒との個別チャット、一斉送信機能</li>
                        <li><strong>📝 週間計画表</strong> - 生徒の週間計画の確認と達成度評価</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">🌉 かけはし管理メニュー</div>
                    <ul>
                        <li><strong>✏️ スタッフかけはし入力</strong> - 施設での様子を記録</li>
                        <li><strong>📋 保護者かけはし確認</strong> - 家庭での様子を確認</li>
                        <li><strong>📄 個別支援計画書作成</strong> - AIサポート付き支援計画書の作成</li>
                        <li><strong>📊 モニタリング表作成</strong> - 支援計画の実施状況評価</li>
                        <li><strong>📰 施設通信を作成</strong> - 保護者向け通信の作成</li>
                    </ul>
                </div>

                <div class="feature-box">
                    <div class="feature-title">⚙️ マスタ管理メニュー</div>
                    <ul>
                        <li><strong>👥 生徒管理</strong> - 生徒情報の登録・編集</li>
                        <li><strong>👨‍👩‍👧 保護者管理</strong> - 保護者アカウントの管理</li>
                        <li><strong>🗓️ 休日管理</strong> - 施設休日の登録</li>
                        <li><strong>🎉 イベント管理</strong> - イベント情報の登録</li>
                    </ul>
                </div>
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
                <div class="step-box">
                    <span class="step-number">4</span>
                    最後に「確定して保存」または「全体をこの内容で保存」ボタンで保存
                </div>

                <div class="feature-box">
                    <div class="feature-title">📝 個別保存機能（編集時のみ）</div>
                    <p>既存の活動を編集する際、生徒ごとに個別に保存できます。</p>
                    <ul>
                        <li><strong>変更があった生徒のみ保存:</strong> 入力内容を変更すると、その生徒の入力欄に「この生徒の修正を保存」ボタンが表示されます</li>
                        <li><strong>画面遷移なし:</strong> ボタンを押すとその生徒のデータのみ保存され、入力画面にとどまります</li>
                        <li><strong>完了メッセージ:</strong> 保存が完了すると、ボタンが「修正が完了しました」と表示されます</li>
                        <li><strong>再編集可能:</strong> 3秒後にボタンが元に戻り、再度編集・保存が可能になります</li>
                    </ul>
                    <div class="tip-box" style="margin-top: 10px;">
                        <strong>💡 使い分けのヒント:</strong>
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

            <!-- 保護者機能 -->
            <div class="section">
                <h2>👨‍👩‍👧 保護者機能</h2>
                <p>保護者とのコミュニケーションと提出物管理を行います。</p>

                <div class="feature-box">
                    <div class="feature-title">💬 保護者チャット</div>
                    <p>保護者と1対1でチャットができます。</p>
                    <ul>
                        <li>テキストメッセージの送受信</li>
                        <li>ファイル添付（最大3MB、1ヶ月間保存）</li>
                        <li>欠席連絡の受信（ピンク色で表示）</li>
                        <li>イベント参加申し込みの受信（青色で表示）</li>
                        <li>生徒を学部別（小学部・中等部・高等部）に分類して表示</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong>💡 チャットの使い方:</strong>
                        <p style="margin-top: 8px;">
                            左側のリストから生徒を選択するとチャット画面が表示されます。メッセージ入力欄にテキストを入力し、必要に応じてファイルを添付して送信できます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title">📮 提出期限管理</div>
                    <p>保護者に提出物の期限を通知し、進捗を管理できます。</p>

                    <h3 style="font-size: 16px; margin: 15px 0 10px 0; color: #667eea;">提出期限の設定方法</h3>
                    <div class="step-box">
                        <span class="step-number">1</span>
                        保護者チャット画面で「📅 提出期限を設定」ボタンをクリック
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

                    <h3 style="font-size: 16px; margin: 15px 0 10px 0; color: #667eea;">提出状況の管理</h3>
                    <ul style="margin-left: 20px;">
                        <li>「📮 提出期限管理」ページで全体の進捗を確認</li>
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
            <div class="section">
                <h2>🎓 生徒機能</h2>
                <p>生徒とのコミュニケーションと学習計画の管理を行います。</p>

                <div class="feature-box">
                    <div class="feature-title">💬 生徒チャット</div>
                    <p>生徒と個別にチャットができます。</p>
                    <ul>
                        <li>テキストメッセージの送受信</li>
                        <li>ファイル添付機能</li>
                        <li>生徒を学部別・在籍状況別に検索・フィルター</li>
                        <li>複数の生徒を選択して一斉送信が可能</li>
                    </ul>

                    <h3 style="font-size: 16px; margin: 15px 0 10px 0; color: #667eea;">一斉送信機能</h3>
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
                    <div class="feature-title">📝 週間計画表</div>
                    <p>生徒の週間計画を確認し、達成度を評価できます。</p>
                    <ul>
                        <li>生徒が設定した週間目標の確認</li>
                        <li>日々の達成状況のチェック</li>
                        <li>スタッフによる達成度評価とコメント</li>
                        <li>保護者との共有</li>
                    </ul>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong>⚠️ 注意:</strong> 週間計画表は生徒自身がログインして設定する必要があります。生徒用ログイン情報は「生徒管理」ページで設定できます。
                    </div>
                </div>
            </div>

            <!-- かけはし管理 -->
            <div class="section">
                <h2>🌉 かけはし管理</h2>
                <p>引継ぎ記録から支援計画まで、生徒の成長を総合的に管理します。</p>

                <div class="feature-box">
                    <div class="feature-title">✏️ スタッフかけはし入力</div>
                    <p>施設での様子を保護者に伝えます。</p>
                    <ul>
                        <li>生徒を選択して日々の記録を入力</li>
                        <li>期間を設定して一括入力も可能</li>
                        <li>過去の記録の閲覧・編集</li>
                        <li>保護者ダッシュボードに自動的に表示されます</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong>💡 効率的な入力方法:</strong>
                        <p style="margin-top: 8px;">
                            複数日分をまとめて入力する場合は、期間設定機能を使うと効率的です。一度の入力で複数の日付に同じ内容を記録できます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title">📋 保護者かけはし確認</div>
                    <p>保護者が入力した家庭での様子を確認できます。</p>
                    <ul>
                        <li>生徒ごとに保護者の記録を閲覧</li>
                        <li>期間を指定して検索</li>
                        <li>スタッフコメント機能で返信も可能</li>
                        <li>未確認の記録を確認済みにマーク</li>
                    </ul>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong>⚠️ 重要:</strong> 保護者からの記録は定期的に確認し、必要に応じてコメントで返信しましょう。保護者との信頼関係構築に役立ちます。
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title">📄 個別支援計画書作成</div>
                    <p>生徒ごとの支援計画をAIの支援を受けながら作成できます。</p>

                    <h3 style="font-size: 16px; margin: 15px 0 10px 0; color: #667eea;">作成手順</h3>
                    <div class="step-box">
                        <span class="step-number">1</span>
                        「🌉 かけはし管理」→「📄 個別支援計画書作成」を選択
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
                    <div class="feature-title">📊 モニタリング表作成</div>
                    <p>支援計画の実施状況を記録・評価します。</p>
                    <ul>
                        <li>個別支援計画に基づいた目標の評価</li>
                        <li>AIによる評価文の生成支援</li>
                        <li>達成度の記録（A/B/C評価など）</li>
                        <li>保護者への共有・確認依頼</li>
                        <li>PDF出力機能</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong>💡 効果的な評価のコツ:</strong>
                        <p style="margin-top: 8px;">
                            モニタリング期間中の「かけはし」記録や活動記録を参照しながら評価すると、より具体的で説得力のある評価文が作成できます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title">📰 施設通信を作成</div>
                    <p>保護者全員に向けた通信を作成・配信できます。</p>
                    <ul>
                        <li>季節のお便りやイベント案内を作成</li>
                        <li>画像やファイルの添付が可能</li>
                        <li>配信履歴の管理</li>
                        <li>保護者ダッシュボードに自動表示</li>
                    </ul>
                </div>
            </div>

            <!-- マスタ管理 -->
            <div class="section">
                <h2>⚙️ マスタ管理</h2>
                <p>システムの基本情報を管理します。</p>

                <div class="feature-box">
                    <div class="feature-title">👥 生徒管理</div>
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
                        <strong>💡 学年調整機能:</strong>
                        <p style="margin-top: 8px;">
                            生年月日から自動計算される学年が実際と異なる場合、学年調整機能で-2～+2学年の範囲で調整できます。これにより、飛び級や留年などのケースにも対応できます。
                        </p>
                    </div>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong>⚠️ 生徒用ログイン:</strong> 生徒が週間計画表を使用する場合は、生徒用のユーザー名とパスワードを設定してください。保護者用ログインとは別に管理されます。
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title">👨‍👩‍👧 保護者管理</div>
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
                    <div class="feature-title">🗓️ 休日管理</div>
                    <p>施設の休日を登録・管理します。</p>
                    <ul>
                        <li>休日の種類別登録（国民の祝日/施設休日/臨時休業）</li>
                        <li>休日名と日付の設定</li>
                        <li>保護者カレンダーに自動表示</li>
                        <li>参加予定者の自動計算から除外</li>
                    </ul>

                    <div class="tip-box" style="margin-top: 10px;">
                        <strong>💡 休日の種類:</strong>
                        <p style="margin-top: 8px;">
                            休日の種類により、カレンダー上の表示色が変わります。国民の祝日は赤、施設休日はオレンジ、臨時休業はグレーで表示されます。
                        </p>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="feature-title">🎉 イベント管理</div>
                    <p>施設のイベント情報を登録・管理します。</p>
                    <ul>
                        <li>イベント名、日付、説明の登録</li>
                        <li>イベントカラーの設定（カレンダー表示用）</li>
                        <li>保護者がチャットから参加申し込み可能</li>
                        <li>参加者一覧の確認</li>
                        <li>活動管理画面の出席予定者リストに表示</li>
                    </ul>

                    <div class="note-box" style="margin-top: 10px;">
                        <strong>⚠️ イベント参加申し込み:</strong> 保護者はチャット画面から「イベント参加」を選択してイベントに申し込みます。スタッフは活動管理画面で参加者を確認できます。
                    </div>
                </div>
            </div>

            <!-- よくある質問 -->
            <div class="section">
                <h2>❓ よくある質問</h2>

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
            <div class="section">
                <h2>💡 ヒントとコツ</h2>

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
                        発達の状況や教育上の配慮で、実年齢と異なる学年に所属している生徒には、学年調整機能を使いましょう。適切な学部（小学部・中等部・高等部）に分類されます。
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
