'use client';

import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Manual sections
// ---------------------------------------------------------------------------

interface ManualSection {
  id: string;
  icon: React.ReactNode;
  title: string;
  content: React.ReactNode;
}

const sections: ManualSection[] = [
  {
    id: 'about',
    icon: <MaterialIcon name="menu_book" size={20} />,
    title: '「きづり（軌綴）」とは',
    content: (
      <div className="space-y-3">
        <p>
          「きづり（軌綴）」は、放課後等デイサービスの個別支援に特化した統合管理システムです。
          日々の活動記録、個別支援計画、保護者連絡、職員間コミュニケーションを一元管理し、
          子どもたち一人ひとりの成長の軌跡を綴ります。
        </p>
        <p>
          名前の由来：「軌」は成長の軌跡、「綴」は記録を綴ること。
          子どもたちの成長の軌跡を丁寧に綴っていくという想いを込めています。
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
          <li><strong>保護者</strong> - 連絡帳確認、かけはし入力、チャット</li>
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
            <p className="font-medium text-[var(--neutral-foreground-1)]">かけはし</p>
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
                <td className="p-3">かけはしの作成</td>
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
          <li><strong>かけはし</strong> - 半期ごとの振り返り記入、職員記入分の確認</li>
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
    id: 'kakehashi',
    icon: <MaterialIcon name="handshake" size={20} />,
    title: 'かけはし管理',
    content: (
      <div className="space-y-3">
        <p>「かけはし」は保護者と職員をつなぐ半期ごとの振り返りドキュメントです。</p>
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
              <li>保護者から見た子どもの様子</li>
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
        <p>個別支援計画やモニタリング、かけはしには期限が設定されています。</p>
        <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
          <li><strong className="text-[var(--status-danger-fg)]">期限超過（赤）</strong> - 期限を過ぎたもの。早急に対応してください</li>
          <li><strong className="text-[var(--status-warning-fg)]">期限間近（黄）</strong> - 1ヶ月以内に期限が来るもの</li>
          <li>ダッシュボードに期限情報がまとめて表示されます</li>
          <li>かけはし期間は自動生成されます（期限1ヶ月前に次の期間を生成）</li>
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

export default function ManualPage() {
  const [openSections, setOpenSections] = useState<Set<string>>(new Set(['about']));

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
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">スタッフマニュアル</h1>
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
