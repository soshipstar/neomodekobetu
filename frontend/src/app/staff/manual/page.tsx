'use client';

import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useWorkspace } from '@/hooks/useWorkspace';
import { serviceTypeLabel } from '@/lib/serviceType';

// ---------------------------------------------------------------------------
// Manual sections
// ---------------------------------------------------------------------------

interface ManualSection {
  id: string;
  icon: React.ReactNode;
  title: string;
  content: React.ReactNode;
}

const afterSchoolSections: ManualSection[] = [
  {
    id: 'about',
    icon: <MaterialIcon name="menu_book" size={20} />,
    title: '「ケアブリッジ」とは',
    content: (
      <div className="space-y-3">
        <p>
          「ケアブリッジ」は、放課後等デイサービスの個別支援に特化した統合管理システムです。
          日々の活動記録、個別支援計画、保護者連絡、職員間コミュニケーションを一元管理し、
          本人たち一人ひとりの成長の軌跡を綴ります。
        </p>
        <p>
          名前の由来：「軌」は成長の軌跡、「綴」は記録を綴ること。
          本人たちの成長の軌跡を丁寧に綴っていくという想いを込めています。
        </p>
      </div>
    ),
  },
  {
    id: 'overview',
    icon: <MaterialIcon name="home" size={16} className="h-5 w-5" />,
    title: 'システム概要',
    content: (
      <div className="space-y-3">
        <p>本システムは以下の5種類のユーザーが利用します。</p>
        <ul className="ml-6 list-disc space-y-1">
          <li><strong>管理者</strong> - システム全体の設定、ユーザー管理、教室管理</li>
          <li><strong>スタッフ</strong> - 日常の記録入力、計画作成、保護者対応</li>
          <li><strong>保護者</strong> - 連絡帳確認、アセスメント入力、チャット</li>
          <li><strong>生徒</strong> - 週間計画、チャット、提出物</li>
          <li><strong>タブレット</strong> - 活動記録の入力端末</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'menu',
    icon: <MaterialIcon name="checklist" size={20} />,
    title: 'メニュー構成',
    content: (
      <div className="space-y-3">
        <p>左サイドバーから以下の機能にアクセスできます。</p>
        <div className="grid gap-3 md:grid-cols-2">
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
            <p className="font-medium text-[var(--neutral-foreground-1)]">ダッシュボード</p>
            <p className="text-sm text-[var(--neutral-foreground-3)]">未読チャット、期限情報、出席状況を一覧表示</p>
          </div>
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
            <p className="font-medium text-[var(--neutral-foreground-1)]">連絡帳（れんらくちょう）</p>
            <p className="text-sm text-[var(--neutral-foreground-3)]">日々の活動記録と5領域の記録</p>
          </div>
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
            <p className="font-medium text-[var(--neutral-foreground-1)]">チャット</p>
            <p className="text-sm text-[var(--neutral-foreground-3)]">保護者とのリアルタイムメッセージング</p>
          </div>
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
            <p className="font-medium text-[var(--neutral-foreground-1)]">個別支援計画</p>
            <p className="text-sm text-[var(--neutral-foreground-3)]">計画作成、AI生成、PDF出力</p>
          </div>
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
            <p className="font-medium text-[var(--neutral-foreground-1)]">モニタリング</p>
            <p className="text-sm text-[var(--neutral-foreground-3)]">計画の進捗評価と達成状況</p>
          </div>
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
            <p className="font-medium text-[var(--neutral-foreground-1)]">アセスメント</p>
            <p className="text-sm text-[var(--neutral-foreground-3)]">保護者・職員間の架け橋となるドキュメント</p>
          </div>
        </div>
      </div>
    ),
  },
  {
    id: 'daily',
    icon: <MaterialIcon name="calendar_month" size={20} />,
    title: '毎日行うこと',
    content: (
      <div className="space-y-3">
        <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
          <p className="mb-2 font-medium text-[var(--neutral-foreground-1)]">1. 出席確認</p>
          <p className="text-sm text-[var(--neutral-foreground-2)]">ダッシュボードで本日の出席状況を確認し、欠席連絡があれば対応します。</p>
        </div>
        <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
          <p className="mb-2 font-medium text-[var(--neutral-foreground-1)]">2. 活動記録の入力</p>
          <p className="text-sm text-[var(--neutral-foreground-2)]">タブレットまたはPCから当日の活動を記録します。5領域の視点で記入してください。</p>
        </div>
        <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
          <p className="mb-2 font-medium text-[var(--neutral-foreground-1)]">3. 連絡帳の送信</p>
          <p className="text-sm text-[var(--neutral-foreground-2)]">活動記録を保護者に送信します。個別コメントも追加できます。</p>
        </div>
        <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
          <p className="mb-2 font-medium text-[var(--neutral-foreground-1)]">4. チャット確認</p>
          <p className="text-sm text-[var(--neutral-foreground-2)]">保護者からのメッセージを確認し、必要に応じて返信します。</p>
        </div>
      </div>
    ),
  },
  {
    id: 'periodic',
    icon: <MaterialIcon name="schedule" size={20} />,
    title: '一定期間ごとに行うこと',
    content: (
      <div className="space-y-3">
        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="bg-[var(--brand-80)] text-white">
                <th className="p-3 text-left">時期</th>
                <th className="p-3 text-left">タスク</th>
                <th className="p-3 text-left">備考</th>
              </tr>
            </thead>
            <tbody>
              <tr className="border-b border-[var(--neutral-stroke-2)]">
                <td className="p-3 font-medium">毎月</td>
                <td className="p-3">おたより作成・配信</td>
                <td className="p-3">AI自動生成機能あり</td>
              </tr>
              <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
                <td className="p-3 font-medium">6ヶ月ごと</td>
                <td className="p-3">個別支援計画の作成・更新</td>
                <td className="p-3">AI支援で生成可能</td>
              </tr>
              <tr className="border-b border-[var(--neutral-stroke-2)]">
                <td className="p-3 font-medium">6ヶ月ごと</td>
                <td className="p-3">モニタリングの実施</td>
                <td className="p-3">計画の評価と見直し</td>
              </tr>
              <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
                <td className="p-3 font-medium">6ヶ月ごと</td>
                <td className="p-3">アセスメントの作成</td>
                <td className="p-3">保護者・職員の双方向記入</td>
              </tr>
              <tr className="border-b border-[var(--neutral-stroke-2)]">
                <td className="p-3 font-medium">年1回</td>
                <td className="p-3">事業所評価アンケート</td>
                <td className="p-3">保護者評価の集計</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    ),
  },
  {
    id: 'basic-usage',
    icon: <MaterialIcon name="edit_square" size={16} className="h-5 w-5" />,
    title: '基本的な使い方',
    content: (
      <div className="space-y-4">
        <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">連絡帳の記入</h3>
        <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li>左メニューから「連絡帳」を選択</li>
          <li>日付を選択し、活動内容を入力</li>
          <li>5領域（健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性）の視点で個別記録を入力</li>
          <li>「保存」で下書き保存、「送信」で保護者に配信</li>
        </ol>

        <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">個別支援計画の作成</h3>
        <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li>「個別支援計画」メニューから生徒を選択</li>
          <li>「新規作成」をクリック</li>
          <li>保護者の願い、本人の願い、長期・短期目標を入力</li>
          <li>各支援領域の目標と支援内容を入力（AI生成ボタンで自動生成可能）</li>
          <li>保存後、PDFダウンロードや保護者確認の送信ができます</li>
        </ol>

        <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">チャットの使い方</h3>
        <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li>「チャット」メニューから保護者のチャットルームを選択</li>
          <li>メッセージを入力してEnterキー（改行はShift+Enter）で送信</li>
          <li>ファイル添付も可能です（画像、PDF等）</li>
          <li>重要なメッセージはピン留めできます</li>
        </ol>
      </div>
    ),
  },
  {
    id: 'guardian',
    icon: <MaterialIcon name="group" size={20} />,
    title: '保護者機能',
    content: (
      <div className="space-y-3">
        <p>保護者向けの機能は以下の通りです。スタッフから保護者に操作方法を案内する際の参考にしてください。</p>
        <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li><strong>ダッシュボード</strong> - 未読の連絡帳や通知を確認</li>
          <li><strong>連絡帳</strong> - 日々の活動記録を確認、確認ボタンで既読通知</li>
          <li><strong>チャット</strong> - スタッフとのメッセージのやりとり</li>
          <li><strong>アセスメント</strong> - 半期ごとの振り返り記入、職員記入分の確認</li>
          <li><strong>個別支援計画</strong> - 計画内容の確認と署名</li>
          <li><strong>モニタリング</strong> - 評価結果の確認</li>
          <li><strong>事業所評価</strong> - アンケートの回答</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'student',
    icon: <MaterialIcon name="school" size={16} className="h-5 w-5" />,
    title: '生徒機能',
    content: (
      <div className="space-y-3">
        <p>生徒アカウントでログインすると以下の機能が利用できます。</p>
        <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li><strong>週間計画</strong> - 1週間の予定と目標を記入</li>
          <li><strong>チャット</strong> - スタッフとのメッセージ</li>
          <li><strong>提出物</strong> - 課題の提出と確認</li>
          <li><strong>スケジュール</strong> - 自分の通所スケジュール確認</li>
        </ul>
        <div className="rounded-lg border border-[var(--status-warning-fg)] bg-[rgba(var(--status-warning-rgb,255,149,0),0.1)] p-3">
          <p className="text-sm font-medium text-[var(--status-warning-fg)]">ポイント</p>
          <p className="text-sm text-[var(--neutral-foreground-2)]">
            生徒アカウントのログインIDとパスワードは「生徒管理」ページから印刷できます。
          </p>
        </div>
      </div>
    ),
  },
  {
    id: 'submissions',
    icon: <MaterialIcon name="upload" size={20} />,
    title: '提出物管理',
    content: (
      <div className="space-y-3">
        <p>保護者への提出依頼を管理します。</p>
        <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li>「提出物管理」メニューから「新規依頼作成」をクリック</li>
          <li>対象生徒、タイトル、説明、期限を入力</li>
          <li>保護者が提出すると一覧に反映されます</li>
          <li>期限超過の場合は赤く表示されます</li>
          <li>完了処理でアーカイブできます</li>
        </ol>
      </div>
    ),
  },
  {
    id: 'assessment',
    icon: <MaterialIcon name="handshake" size={20} />,
    title: 'アセスメント管理',
    content: (
      <div className="space-y-3">
        <p>「アセスメント」は保護者と職員をつなぐ半期ごとの振り返りドキュメントです。</p>
        <div className="grid gap-3 md:grid-cols-2">
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-4">
            <p className="mb-2 font-medium text-[var(--brand-80)]">職員側の記入</p>
            <ul className="ml-4 list-disc space-y-1 text-sm text-[var(--neutral-foreground-2)]">
              <li>本人の願い、短期・長期目標</li>
              <li>5領域の評価と所見</li>
              <li>AI自動生成で下書き作成可能</li>
              <li>PDFダウンロード対応</li>
            </ul>
          </div>
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-4">
            <p className="mb-2 font-medium text-[var(--brand-80)]">保護者側の記入</p>
            <ul className="ml-4 list-disc space-y-1 text-sm text-[var(--neutral-foreground-2)]">
              <li>保護者から見た本人の様子</li>
              <li>家庭での変化や困りごと</li>
              <li>スタッフが確認ボタンで確認処理</li>
            </ul>
          </div>
        </div>
      </div>
    ),
  },
  {
    id: 'evaluation',
    icon: <MaterialIcon name="assignment_turned_in" size={16} className="h-5 w-5" />,
    title: '事業所評価',
    content: (
      <div className="space-y-3">
        <p>年1回の事業所評価アンケートを実施・集計できます。</p>
        <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li>「事業所評価」メニューからアンケートを作成</li>
          <li>保護者に回答を依頼（ダッシュボードに通知が表示されます）</li>
          <li>回答結果は自動集計され、グラフで可視化</li>
          <li>PDFレポートとしてダウンロード可能</li>
        </ol>
      </div>
    ),
  },
  {
    id: 'schedule',
    icon: <MaterialIcon name="schedule" size={20} />,
    title: '書類作成スケジュールと期限ルール',
    content: (
      <div className="space-y-3">
        <p>個別支援計画やモニタリング、アセスメントには期限が設定されています。</p>
        <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li><strong className="text-[var(--status-danger-fg)]">期限超過（赤）</strong> - 期限を過ぎたもの。早急に対応してください</li>
          <li><strong className="text-[var(--status-warning-fg)]">期限間近（黄）</strong> - 1ヶ月以内に期限が来るもの</li>
          <li>ダッシュボードに期限情報がまとめて表示されます</li>
          <li>アセスメント期間は自動生成されます（期限1ヶ月前に次の期間を生成）</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'settings',
    icon: <MaterialIcon name="settings" size={20} />,
    title: 'マスタ管理',
    content: (
      <div className="space-y-3">
        <p>管理者メニューから以下の設定ができます。</p>
        <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li><strong>教室管理</strong> - 教室の追加・編集・ロゴ設定</li>
          <li><strong>スタッフ管理</strong> - スタッフアカウントの追加・権限設定</li>
          <li><strong>生徒管理</strong> - 生徒情報の登録・編集</li>
          <li><strong>保護者管理</strong> - 保護者アカウントの管理</li>
          <li><strong>祝日設定</strong> - 年間の祝日・休業日の設定</li>
          <li><strong>タグ設定</strong> - 活動記録のタグ管理</li>
          <li><strong>日課設定</strong> - デイリールーティンの設定</li>
          <li><strong>おたより設定</strong> - ニュースレターの表示設定</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'signature',
    icon: <MaterialIcon name="draw" size={20} />,
    title: '電子署名機能',
    content: (
      <div className="space-y-3">
        <p>個別支援計画には電子署名機能があります。</p>
        <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li>計画を保護者に送信すると、保護者のダッシュボードに署名依頼が表示されます</li>
          <li>保護者はタッチパネルまたはマウスで署名を手書き入力できます</li>
          <li>署名済みの計画は署名画像付きでPDF出力されます</li>
          <li>署名状態は計画一覧の「署名済み」バッジで確認できます</li>
        </ol>
      </div>
    ),
  },
  {
    id: 'faq',
    icon: <MaterialIcon name="help" size={16} className="h-5 w-5" />,
    title: 'よくある質問',
    content: (
      <div className="space-y-4">
        {[
          { q: 'パスワードを忘れた場合は？', a: '管理者にパスワードのリセットを依頼してください。管理者は「スタッフ管理」からパスワードを再設定できます。' },
          { q: '連絡帳を送信後に修正できますか？', a: '送信済みの連絡帳は編集可能です。修正すると保護者に通知が再送されます。' },
          { q: 'AI生成がうまくいかない場合は？', a: '過去のデータが少ない場合は精度が低くなります。手動で修正しながら使い続けることでデータが蓄積され、精度が向上します。' },
          { q: 'PDFが正しく表示されない場合は？', a: 'ブラウザを最新バージョンに更新してください。Chrome推奨です。' },
          { q: 'スマートフォンでも使えますか？', a: 'はい、レスポンシブ対応しています。PWA対応のため、ホーム画面に追加するとアプリのように使えます。' },
        ].map(({ q, a }, idx) => (
          <div key={idx} className="rounded-lg bg-[var(--neutral-background-2)] p-4">
            <p className="mb-1 font-medium text-[var(--neutral-foreground-1)]">Q: {q}</p>
            <p className="text-sm text-[var(--neutral-foreground-2)]">A: {a}</p>
          </div>
        ))}
      </div>
    ),
  },
  {
    id: 'tips',
    icon: <MaterialIcon name="lightbulb" size={16} className="h-5 w-5" />,
    title: 'ヒントとコツ',
    content: (
      <div className="space-y-3">
        <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li>ダッシュボードを毎日チェックすると、期限切れや未対応のタスクを見逃しません</li>
          <li>AI生成は下書きとして活用し、必ず内容を確認・修正してから保存してください</li>
          <li>チャットのピン留め機能を使うと、重要な連絡を見失いません</li>
          <li>連絡帳は「統合」機能でまとめて作成・送信できます</li>
          <li>キーボードショートカット: チャットでShift+Enterで改行できます</li>
          <li>おたよりのAI生成は設定ページでカスタマイズできます</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'contact',
    icon: <MaterialIcon name="phone" size={20} />,
    title: 'お問い合わせ',
    content: (
      <div className="space-y-3">
        <p>システムに関するお問い合わせは管理者までご連絡ください。</p>
        <div className="rounded-lg bg-[var(--neutral-background-2)] p-4">
          <p className="text-sm text-[var(--neutral-foreground-2)]">
            不具合や改善要望がございましたら、チャットボット（画面右下のアイコン）からもお問い合わせいただけます。
          </p>
        </div>
      </div>
    ),
  },
];

// ---------------------------------------------------------------------------
// 就労 A/B 用マニュアル
// ---------------------------------------------------------------------------

const employmentSections: ManualSection[] = [
  {
    id: 'about-employment',
    icon: <MaterialIcon name="work" size={16} />,
    title: 'ケアブリッジ (就労支援版) とは',
    content: (
      <div className="space-y-3">
        <p>
          ケアブリッジは <strong>就労継続支援 A型・B型</strong> 事業所向けの統合管理システムです。
          利用者の出退勤・工賃計算・個別支援計画・モニタリングをひとつのシステムで完結できます。
        </p>
        <ul className="ml-4 list-disc space-y-1 text-sm">
          <li><strong>工賃管理</strong> — 時給制 / 出来高制を自動計算し、月次工賃台帳を作成</li>
          <li><strong>個別支援計画</strong> — 6 領域 (健康/日常/対人/コミュニケーション/就労スキル/行動特性) で目標設定</li>
          <li><strong>連絡帳</strong> — 強み (才能) チェック 10 項目で利用者の成長を可視化</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'daily-employment',
    icon: <MaterialIcon name="today" size={16} />,
    title: '毎日行うこと',
    content: (
      <div className="space-y-3">
        <p>就労 A型・B型では、毎日の運営で以下を行ってください:</p>
        <ol className="ml-4 list-decimal space-y-2">
          <li>
            <strong>朝の出勤確認</strong> — 利用者の出勤を「連絡帳」または「日々の出退勤」画面で記録します。
            出勤時刻 / 退勤時刻 / 工賃対象時間 を入力すると、月末の工賃計算に自動反映されます。
          </li>
          <li>
            <strong>作業内容の記録</strong> — その日の作業内容 (袋詰め / 検品 / 清掃 等) と作業時間を連絡帳に記入します。
            B型では出来高制を選択している場合、作業数の入力も忘れずに。
          </li>
          <li>
            <strong>強み (才能) チェック</strong> — 利用者ごとに 10 項目 (集中力 / 正確性 / 協調性 / 報連相 等) を 1〜10 で評価します。
            6 ヶ月のモニタリング時に成長傾向が自動集計されます。
          </li>
          <li>
            <strong>ヒヤリハットの記録</strong> — 機器使用や搬送中の事故未遂、利用者間のトラブル等は即座に記録してください。
          </li>
        </ol>
      </div>
    ),
  },
  {
    id: 'wage-management',
    icon: <MaterialIcon name="payments" size={16} />,
    title: '工賃管理 (重要)',
    content: (
      <div className="space-y-3">
        <p>就労 A 型は <strong>最低賃金以上の時給</strong>、B型は <strong>平均工賃 3,000 円以上 / 月</strong> が指定基準です。</p>
        <h4 className="mt-3 font-semibold">月次の流れ</h4>
        <ol className="ml-4 list-decimal space-y-2">
          <li><strong>利用者ごとの設定</strong> — 利用者詳細画面で計算方式 (時給制 / 出来高制) と単価を設定します。A型は雇用契約上の時給を入力。</li>
          <li><strong>連絡帳に出退勤と工賃対象時間を記入</strong> — 自動的に月次集計に反映されます。</li>
          <li><strong>工賃管理画面 (/staff/wage-management)</strong> — 対象月を選んで「工賃を計算」を実行すると、利用者別の支給額が表示されます。</li>
          <li><strong>必要に応じて手動調整</strong> — 賞与 / 控除 / 時間外手当 (A型) を明細単位で追加できます。</li>
          <li><strong>確定 → 支払い済</strong> — 月末に「確定」、振込実施後に「支払い済」に状態を進めます。確定後は再計算されません。</li>
        </ol>
        <p className="mt-3 text-xs text-[var(--neutral-foreground-3)]">
          ※ A型の最低賃金は都道府県ごとに毎年 10 月改定されます。改定タイミングで利用者の時給を更新してください。
        </p>
      </div>
    ),
  },
  {
    id: 'plan-employment',
    icon: <MaterialIcon name="folder_special" size={16} />,
    title: '個別支援計画とモニタリング',
    content: (
      <div className="space-y-3">
        <p>
          就労継続支援では <strong>6 ヶ月ごとのモニタリング義務</strong> があります。計画書は最低 1 年ごとに見直しが必要です。
        </p>
        <h4 className="mt-3 font-semibold">作成の流れ</h4>
        <ol className="ml-4 list-decimal space-y-2">
          <li><strong>アセスメント実施</strong> — 利用開始時または計画更新時に、本人の希望・家族の希望をヒアリング</li>
          <li><strong>個別支援計画の作成</strong> — 6 領域で目標設定。就労固有事項として「工賃目標」「一般就労への移行目標」「定着支援計画」を記入</li>
          <li><strong>サービス管理責任者の署名</strong> — 確認後に「正式版」として確定</li>
          <li><strong>本人・家族の同意取得</strong> — 同意日と署名を記録</li>
          <li><strong>6 ヶ月後にモニタリング</strong> — 「保留タスク」に自動表示されます。連絡帳の強みチェック推移も自動集計</li>
          <li><strong>必要に応じて計画見直し</strong> — 1 年経過時、または目標達成時、生活状況変化時</li>
        </ol>
      </div>
    ),
  },
  {
    id: 'work-manuals',
    icon: <MaterialIcon name="menu_book" size={16} />,
    title: '作業マニュアル機能',
    content: (
      <div className="space-y-3">
        <p>「作業マニュアル」(/staff/work-manuals) は、現場で利用者と一緒に確認できる手順書です。</p>
        <h4 className="mt-3 font-semibold">活用方法</h4>
        <ul className="ml-4 list-disc space-y-2">
          <li><strong>写真・動画つきの手順登録</strong> — ステップごとに画像/動画を添付できます。スマホで撮影してアップロード</li>
          <li><strong>注意事項とチェックポイント</strong> — 各ステップで「これだけはやらない」「ここを完了確認」を明示</li>
          <li><strong>利用者個別の手順書</strong> — 合理的配慮として、利用者ごとに専用の手順書を作成できます (student_id 指定)</li>
          <li><strong>難易度設定</strong> — 初級 / 中級 / 上級 で分類して、利用者のスキルに応じて出題できます</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'operation-metrics',
    icon: <MaterialIcon name="insights" size={16} />,
    title: '運営指標ダッシュボード',
    content: (
      <div className="space-y-3">
        <p>「運営指標」(/staff/operation-metrics) で以下を確認できます:</p>
        <ul className="ml-4 list-disc space-y-2">
          <li><strong>稼働率</strong> — 1 日平均利用者数 ÷ 定員。70% 以上が目安、90% 超は加算条件</li>
          <li><strong>開所日数</strong> — 月の営業日数</li>
          <li><strong>延べ利用日数</strong> — 利用者 × 出勤日の合計</li>
          <li><strong>6 ヶ月稼働率推移</strong> — 経営判断や行政指導前の参考に</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'signature-employment',
    icon: <MaterialIcon name="draw" size={16} />,
    title: '電子署名',
    content: (
      <div className="space-y-3">
        <p>個別支援計画書・モニタリング表は <strong>本人 / 家族</strong> の同意署名と、<strong>サービス管理責任者</strong> の確認署名が必要です。</p>
        <ul className="ml-4 list-disc space-y-2">
          <li>本人 / 家族署名 — ガーディアン (家族) アカウントから自署可能</li>
          <li>事業所側署名 — サ管 (サービス管理責任者) アカウントから自署可能</li>
          <li>PDF 出力時に署名が反映されます。実地指導でも紙印刷で提出できます</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'faq-employment',
    icon: <MaterialIcon name="help" size={16} />,
    title: 'よくある質問',
    content: (
      <div className="space-y-3">
        <h4 className="font-semibold">Q. 工賃計算で「未集計」と出る</h4>
        <p>利用者詳細画面で「計算方式」(時給/出来高/固定) と「時給」または「出来高単価」が設定されているか確認してください。</p>
        <h4 className="font-semibold">Q. A型と B型の違いは何ですか?</h4>
        <p>A型は <strong>雇用契約あり / 最低賃金保証</strong>、B型は <strong>雇用契約なし / 工賃のみ</strong> です。本システムでは事業所のサービス種別設定で自動的に画面表記が切り替わります。</p>
        <h4 className="font-semibold">Q. 平均工賃を確認したい</h4>
        <p>工賃管理画面の月次一覧に「平均工賃」列があります。年間集計は今後のバージョンで対応予定です。</p>
      </div>
    ),
  },
];

// ---------------------------------------------------------------------------
// 就労移行支援 用マニュアル
// ---------------------------------------------------------------------------

const transitionSections: ManualSection[] = [
  {
    id: 'about-transition',
    icon: <MaterialIcon name="trending_up" size={16} />,
    title: 'ケアブリッジ (就労移行支援版) とは',
    content: (
      <div className="space-y-3">
        <p>
          ケアブリッジは <strong>就労移行支援事業所</strong> 向けの統合管理システムです。
          利用者の訓練記録・求職活動・企業実習・就職後の定着支援まで、2 年間 (原則) の利用期間中の全てを管理できます。
        </p>
        <ul className="ml-4 list-disc space-y-1 text-sm">
          <li><strong>個別支援計画</strong> — 5 領域 (就職準備 / 作業スキル / 対人関係 / 生活基盤 / 自己理解) で目標設定</li>
          <li><strong>連絡帳</strong> — 訓練内容・ビジネスマナー評価・実習記録を 1 日 1 件で記録</li>
          <li><strong>求職活動管理</strong> — 応募 / 面接 / 内定 / 入社の状況を一元管理</li>
          <li><strong>企業実習管理</strong> — 実習先・評価 (就労意欲 / 技能 / 対人) を 5 段階で記録</li>
          <li><strong>就職後の定着支援</strong> — 在籍中 OB/OG の月次連絡・出勤率・本人満足度を追跡</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'daily-transition',
    icon: <MaterialIcon name="today" size={16} />,
    title: '毎日行うこと',
    content: (
      <div className="space-y-3">
        <ol className="ml-4 list-decimal space-y-2">
          <li><strong>朝の SHR (ショートホームルーム)</strong> — 出席・体調確認、本日のスケジュール共有</li>
          <li><strong>訓練科目の実施</strong> — ビジネスマナー / PC スキル / グループワーク / 個別面談</li>
          <li><strong>連絡帳記入</strong> — 訓練内容 / ビジネスマナー評価 (1-5) / 求職活動の進捗 を記録</li>
          <li><strong>強み (才能) チェック</strong> — 自己理解 / ビジネスマナー / 報連相 / 時間管理 等 10 項目を 1〜10 で評価</li>
          <li><strong>振り返り日報の作成支援</strong> — 利用者本人が記入する日報をスタッフが確認・コメント</li>
        </ol>
      </div>
    ),
  },
  {
    id: 'transition-phases',
    icon: <MaterialIcon name="route" size={16} />,
    title: '訓練段階と利用期間',
    content: (
      <div className="space-y-3">
        <p>就労移行支援は <strong>原則 2 年間</strong> の利用が前提です。訓練段階を 3 期に分けて運営します:</p>
        <ul className="ml-4 list-disc space-y-2">
          <li><strong>基礎訓練期 (0〜6 ヶ月)</strong> — 生活リズム確立、ビジネスマナー、基礎スキル習得</li>
          <li><strong>応用訓練期 (7〜12 ヶ月)</strong> — 専門スキル、対人スキル、職場見学・短期実習</li>
          <li><strong>求職・実習期 (13 ヶ月〜)</strong> — 履歴書作成、企業見学、長期実習、就職活動</li>
        </ul>
        <p className="mt-3 text-xs text-[var(--neutral-foreground-3)]">
          ※ 利用者詳細画面で「利用期限」を必ず設定してください。期限近くになると「保留タスク」に表示されます。
        </p>
      </div>
    ),
  },
  {
    id: 'plan-transition',
    icon: <MaterialIcon name="folder_special" size={16} />,
    title: '個別支援計画とモニタリング',
    content: (
      <div className="space-y-3">
        <h4 className="mt-3 font-semibold">就労移行特有の項目</h4>
        <ul className="ml-4 list-disc space-y-2">
          <li><strong>目標とする就労形態 / 業界</strong> — 正社員 / パート / 特例子会社 など本人の希望</li>
          <li><strong>求職活動計画</strong> — 月のハローワーク訪問回数 / 企業見学回数 等</li>
          <li><strong>企業実習計画</strong> — 短期実習 (5 日) / 長期実習 (1 ヶ月) のタイミング</li>
          <li><strong>就職後の定着支援方針</strong> — 就職後 6 ヶ月の支援頻度</li>
        </ul>
        <p className="mt-3">6 ヶ月ごとのモニタリング義務は就労継続と同じ。連絡帳で記録した強みチェック / 訓練評価 / ビジネスマナースコアの推移を自動集計します。</p>
      </div>
    ),
  },
  {
    id: 'job-search',
    icon: <MaterialIcon name="work_history" size={16} />,
    title: '求職活動の記録 (就労移行支援画面)',
    content: (
      <div className="space-y-3">
        <p>「就労移行支援」画面 (/staff/transition-support) の「求職活動」タブで管理します。</p>
        <ul className="ml-4 list-disc space-y-2">
          <li><strong>応募記録</strong> — 会社名 / 職種 / 雇用形態 / 応募日 / 経路 (ハローワーク / 直接応募 / 紹介 等)</li>
          <li><strong>状態管理</strong> — 応募済 → 書類選考 → 面接予定 → 面接済 → 内定 / 不採用</li>
          <li><strong>面接フィードバック</strong> — 面接後の感想・改善点を記録</li>
          <li><strong>就職実績の集計</strong> — 年次の就職実績は事業所評価の重要指標</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'company-internship',
    icon: <MaterialIcon name="business_center" size={16} />,
    title: '企業実習の管理',
    content: (
      <div className="space-y-3">
        <p>「就労移行支援」画面の「企業実習」タブで管理します。</p>
        <h4 className="mt-3 font-semibold">実習記録の項目</h4>
        <ul className="ml-4 list-disc space-y-2">
          <li><strong>実習先企業</strong> — 会社名 / 連絡担当者 / 連絡先</li>
          <li><strong>期間と日数</strong> — 開始日 / 終了日 / 総実習日数</li>
          <li><strong>実習種別</strong> — 見学 / 体験 / 採用試行 (トライアル雇用)</li>
          <li><strong>実習計画</strong> — 日ごとの予定 (1 日目: 職場見学、2 日目: 軽作業、等)</li>
          <li><strong>企業評価</strong> — 3 軸を 1-5 で記録 ( 就労意欲 / 技能 / 対人 )</li>
          <li><strong>事業所評価</strong> — スタッフ側の所見、合理的配慮の提案</li>
          <li><strong>結果</strong> — 就職に結びついた / 訓練継続 / 中止</li>
        </ul>
      </div>
    ),
  },
  {
    id: 'job-placement',
    icon: <MaterialIcon name="emoji_events" size={16} />,
    title: '就職後の定着支援',
    content: (
      <div className="space-y-3">
        <p>「就労移行支援」画面の「就職後定着」タブで OB/OG を管理します。</p>
        <h4 className="mt-3 font-semibold">登録項目</h4>
        <ul className="ml-4 list-disc space-y-2">
          <li>就職先 / 職種 / 就職開始日 / 雇用形態 / 月収 / 週労働時間</li>
          <li><strong>合理的配慮の内容</strong> — 職場で適用されている配慮事項</li>
          <li><strong>次回フォロー期日</strong> — 通常 1 ヶ月後の訪問・電話日</li>
        </ul>
        <h4 className="mt-3 font-semibold">定着支援の記録</h4>
        <ul className="ml-4 list-disc space-y-2">
          <li>連絡履歴 (訪問 / 電話 / メール) — 連絡相手 (本人 / 上司 / 家族)</li>
          <li>本人満足度 1-5 / 出勤率 %</li>
          <li>課題・対応内容のメモ</li>
        </ul>
        <p className="mt-3 text-xs text-[var(--neutral-foreground-3)]">
          ※ 6 ヶ月以上の定着支援が必要な場合は就労定着支援事業 (別サービス) への引継ぎを検討してください。
        </p>
      </div>
    ),
  },
  {
    id: 'faq-transition',
    icon: <MaterialIcon name="help" size={16} />,
    title: 'よくある質問',
    content: (
      <div className="space-y-3">
        <h4 className="font-semibold">Q. 利用期限が近い利用者を一覧で確認したい</h4>
        <p>「保留タスク」(/staff/pending-tasks) で確認できます。期限 30 日以内の利用者がアラート表示されます。</p>
        <h4 className="font-semibold">Q. 求職活動の月次集計を出したい</h4>
        <p>現バージョンでは月別集計は未対応。応募一覧から手動でカウントしてください (次回改修予定)。</p>
        <h4 className="font-semibold">Q. 就職後の定着率を計算したい</h4>
        <p>「就労移行支援」画面の「就職後定着」タブで「在籍中」「離職」を確認できます。比率は年次レポートで自動算出予定。</p>
        <h4 className="font-semibold">Q. 利用期限を超えても利用継続するには?</h4>
        <p>市町村の支給決定で 1 年延長可能 (最大 3 年)。</p>
      </div>
    ),
  },
];

// ---------------------------------------------------------------------------
// サービス種別ごとにマニュアルを切り替える
// ---------------------------------------------------------------------------

function getSectionsByServiceType(serviceType: string): ManualSection[] {
  if (serviceType === 'employment_a' || serviceType === 'employment_b') {
    return employmentSections;
  }
  if (serviceType === 'transition') {
    return transitionSections;
  }
  return afterSchoolSections;
}

export default function ManualPage() {
  const { serviceType } = useWorkspace();
  const sections = getSectionsByServiceType(serviceType);
  const [openSections, setOpenSections] = useState<Set<string>>(new Set([sections[0]?.id ?? '']));

  const toggleSection = (id: string) => {
    setOpenSections((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const expandAll = () => {
    setOpenSections(new Set(sections.map((s) => s.id)));
  };

  const collapseAll = () => {
    setOpenSections(new Set());
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">スタッフマニュアル</h1>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            <strong>{serviceTypeLabel(serviceType)}</strong> 用のマニュアルを表示しています。
          </p>
        </div>
        <div className="flex gap-2">
          <button
            className="text-sm text-[var(--brand-80)] hover:text-[var(--brand-70)]"
            onClick={expandAll}
          >
            すべて開く
          </button>
          <span className="text-[var(--neutral-foreground-3)]">|</span>
          <button
            className="text-sm text-[var(--brand-80)] hover:text-[var(--brand-70)]"
            onClick={collapseAll}
          >
            すべて閉じる
          </button>
        </div>
      </div>

      {/* Table of contents */}
      <Card>
        <CardBody>
          <p className="mb-3 text-sm font-medium text-[var(--neutral-foreground-2)]">目次</p>
          <div className="flex flex-wrap gap-2">
            {sections.map((section) => (
              <button
                key={section.id}
                className="rounded-lg bg-[var(--neutral-background-2)] px-3 py-1.5 text-xs font-medium text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-3)] transition-colors"
                onClick={() => {
                  setOpenSections((prev) => new Set(prev).add(section.id));
                  document.getElementById(`section-${section.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }}
              >
                {section.title}
              </button>
            ))}
          </div>
        </CardBody>
      </Card>

      {/* Sections */}
      <div className="space-y-3">
        {sections.map((section) => {
          const isOpen = openSections.has(section.id);
          return (
            <Card key={section.id} id={`section-${section.id}`}>
              <button
                className="flex w-full items-center justify-between px-6 py-4 text-left"
                onClick={() => toggleSection(section.id)}
              >
                <div className="flex items-center gap-3">
                  <span className="text-[var(--brand-80)]">{section.icon}</span>
                  <span className="text-base font-semibold text-[var(--neutral-foreground-1)]">{section.title}</span>
                </div>
                {isOpen ? (
                  <MaterialIcon name="expand_more" size={20} className="text-[var(--neutral-foreground-3)]" />
                ) : (
                  <MaterialIcon name="chevron_right" size={20} className="text-[var(--neutral-foreground-3)]" />
                )}
              </button>
              {isOpen && (
                <CardBody>
                  <div className="text-sm text-[var(--neutral-foreground-2)] leading-relaxed">
                    {section.content}
                  </div>
                </CardBody>
              )}
            </Card>
          );
        })}
      </div>
    </div>
  );
}
