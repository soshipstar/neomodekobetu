export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* リード段落: サービスの目的 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「きづり（軌綴）」（CAREBRIDGE）は、放課後等デイサービスの個別支援に特化した統合管理システムです。
        日々の活動記録から個別支援計画、保護者や生徒とのやりとり、職員間の情報共有までを一つの画面でまとめて扱えます。
        紙やバラバラのツールで管理していた業務を一元化し、子ども一人ひとりの成長の軌跡を丁寧に記録していくことを目的としています。
      </p>

      {/* 名前の由来 */}
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>名前の由来:</strong> 「軌（き）」は成長の軌跡を、「綴（つづり）」は記録を綴ることを表します。
          子どもたちの成長の軌跡を、支援者と保護者が一緒に綴っていく——そんな想いを込めたサービス名です。
        </p>
      </div>

      {/* このシステムでできること */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">このシステムでできること</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        指導員・管理者の方は、左側のメニューから次のような業務を行えます。ここでは代表的なものを紹介します。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          <strong>日々の活動と出欠の管理</strong> - 「活動管理」で当日の参加予定者やカレンダー、活動一覧を確認できます。
        </li>
        <li>
          <strong>連絡帳の作成と送信</strong> - 「連絡帳」で活動内容や5領域（健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性）の記録を作成し、保護者へ届けられます。
        </li>
        <li>
          <strong>保護者・生徒・職員とのやりとり</strong> - 「保護者チャット」「生徒チャット」「スタッフ間チャット」でメッセージのやりとりができます。
        </li>
        <li>
          <strong>個別支援計画とモニタリング</strong> - 「個別支援計画」で計画書を作成し、「モニタリング」で進捗の評価や見直しを行えます。
        </li>
        <li>
          <strong>アセスメント</strong> - 「アセスメント（職員）」「アセスメント（保護者）」で、支援者と保護者の双方から半期ごとの振り返りを記録できます。
        </li>
        <li>
          <strong>各種お知らせ・書類</strong> - 「お知らせ」「施設通信」「業務日誌」「提出物管理」「事業所評価」など、日常的な情報発信や書類業務もまとめて管理できます。
        </li>
      </ul>

      {/* 特徴 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">「きづり」の特徴</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          <strong>個別支援に特化</strong> - 放課後等デイサービスの現場に合わせて、5領域の視点や個別支援計画・モニタリングの流れを想定した作りになっています。
        </li>
        <li>
          <strong>一元管理</strong> - 記録・計画・連絡・書類を一つのシステムで扱うため、情報の探し直しや二重入力の手間を減らせます。
        </li>
        <li>
          <strong>保護者との情報共有</strong> - 連絡帳やチャット、計画書の共有を通じて、保護者と支援の状況を分かりやすく共有できます。
        </li>
        <li>
          <strong>AIによる下書き支援</strong> - 個別支援計画やアセスメント、施設通信などでは、AIによる下書き生成を利用できる機能があります。作成した下書きは必ず内容を確認・修正してからご利用ください。
        </li>
      </ul>

      {/* 利用する人 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">どんな人が使うか</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「きづり」は、立場ごとに専用の画面が用意されています。それぞれ見える情報や使える機能が異なります。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>管理者</strong> - 教室やアカウントの管理、システム全体の設定を行います。</li>
        <li><strong>スタッフ（指導員）</strong> - 日々の記録入力、計画作成、保護者・生徒対応など、現場の中心となる業務を行います。</li>
        <li><strong>保護者</strong> - 連絡帳やお知らせの確認、チャット、アセスメントの入力などを行います。</li>
        <li><strong>生徒</strong> - 週間計画やチャット、提出物のやりとりに利用します。</li>
      </ul>

      {/* このマニュアルの読み方 */}
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> このマニュアルは項目ごとに分かれています。まず全体像をつかみたいときは、この「「きづり」とは」と「システム概要」から読み進めると分かりやすくなります。
          具体的な操作方法は、それぞれの機能の項目に詳しく記載しています。
        </p>
      </div>

      {/* 端末利用に関する注意 */}
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 本システムは子どもや保護者の個人情報を扱います。業務での利用は、必ず事業所が貸与・管理する端末で行い、私有物（個人所有のスマートフォン・PC・タブレット等）では操作しないでください。
        </p>
      </div>
    </div>
  );
}
