export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入の段落 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「きづり（CAREBRIDGE）」は、放課後等デイサービスの日々の業務を一つにまとめた管理システムです。活動の記録、連絡帳、個別支援計画、モニタリング、アセスメント、チャット、おたより（施設通信）などを、パソコン・タブレット・スマートフォンのいずれからでも利用できます。このセクションでは、システム全体の仕組みと、指導員・管理者が最初に知っておきたい全体像を説明します。
      </p>

      {/* 4つの利用者ロール */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">利用者の種類（ロール）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        システムは、ログインする人ごとに見える画面とできる操作が変わります。役割は大きく次の4つに分かれます。それぞれログイン後の画面（メニュー）が異なります。
      </p>
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="bg-[var(--brand-80)] text-white">
              <th className="p-3 text-left">ロール</th>
              <th className="p-3 text-left">主な利用者</th>
              <th className="p-3 text-left">できること</th>
            </tr>
          </thead>
          <tbody>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3 font-medium">管理者</td>
              <td className="p-3">施設長・管理責任者</td>
              <td className="p-3">教室や職員・利用者アカウントの管理、各種基本設定、請求・契約の確認など、システム全体の設定を行います。</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
              <td className="p-3 font-medium">スタッフ（職員）</td>
              <td className="p-3">指導員・支援員</td>
              <td className="p-3">活動の記録、連絡帳の作成・送信、支援計画やモニタリングの作成、保護者・生徒とのチャットなど、日々の支援業務を行います。</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3 font-medium">保護者</td>
              <td className="p-3">利用児童の保護者</td>
              <td className="p-3">連絡帳の確認、チャットでの相談、アセスメントの入力、支援計画やモニタリング表の確認、事業所評価の回答などを行います。</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
              <td className="p-3 font-medium">生徒（お子さま）</td>
              <td className="p-3">利用児童本人</td>
              <td className="p-3">週間計画の記入、提出物の確認、スタッフとのチャット、スケジュールの確認ができます。</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 上記4つのほかに、活動記録の入力に使う「タブレット」用の画面があります。教室に据え置いた端末から、その日の活動記録・出欠・写真などをまとめて入力するための専用画面です。指導員・管理者は、これら5つの画面が連携して1つのシステムを構成していると理解しておくと全体像がつかみやすくなります。
        </p>
      </div>

      {/* 画面の基本構成 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">画面の基本構成</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        ログインすると、どのロールでも同じレイアウトの画面が表示されます。各部の役割は次のとおりです。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>左サイドバー（メニュー）</strong> — 各機能へ移動するための一覧です。パソコンでは常に左側に表示され、スマートフォンでは左上のメニューボタン（三本線のアイコン）を押すと開きます。</li>
        <li><strong>上部ヘッダー</strong> — 左上にメニューの開閉ボタン、右上に通知ベル、ログイン中のお名前、ログアウトボタンが並びます。複数の教室を担当している場合は、教室の切り替えもここ（またはメニュー内）から行えます。</li>
        <li><strong>通知ベル</strong> — 未読のチャットや新しい連絡など、対応が必要な項目のお知らせが届きます。</li>
        <li><strong>メイン画面</strong> — 選んだメニューの内容が中央に表示されます。</li>
      </ul>

      {/* 主要機能の俯瞰 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">主要な機能の全体像</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        スタッフ画面の左メニューは、業務の種類ごとにグループ分けされています。代表的な機能は次のとおりです。詳しい操作方法は、それぞれの専用セクションで説明します。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>活動管理</strong> — ログイン後最初に開く画面です。当日の活動や出欠の状況を確認できます。</li>
        <li><strong>連絡帳</strong> — その日の活動やお子さまの様子を記録し、保護者へ送信します。保護者はスマートフォンなどで内容を確認できます。</li>
        <li><strong>チャット</strong> — 保護者チャット・生徒チャット・スタッフ間チャットに分かれており、それぞれ相手とメッセージやファイルをやり取りできます。</li>
        <li><strong>個別支援計画</strong> — お子さまごとの支援計画を作成・更新します。AIによる下書き生成や、保護者への確認送信・電子署名にも対応しています。</li>
        <li><strong>モニタリング</strong> — 個別支援計画の進み具合を定期的に評価・記録します。</li>
        <li><strong>アセスメント</strong> — 保護者向け・職員向けに分かれており、半期ごとの振り返りを双方で記入して共有します。</li>
        <li><strong>週間計画・面談記録</strong> — 週ごとの計画や、生徒・保護者との面談内容を記録します。</li>
        <li><strong>提出物</strong> — 保護者や生徒への提出依頼を作成し、提出状況を管理します。</li>
        <li><strong>お知らせ・施設通信（おたより）・イベント</strong> — 保護者へのお知らせ配信や、月ごとのおたより作成、行事の案内を行います。</li>
        <li><strong>各種管理・設定</strong> — 生徒情報・保護者情報の管理、待機児童管理、ヒヤリハット、写真ライブラリ、休日・日課設定、事業所評価など、運営に必要な機能がまとまっています。</li>
      </ul>

      {/* 業務の大まかな流れ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">日々の業務の大まかな流れ</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        はじめて使う方は、まず次の流れをイメージすると全体がつかみやすくなります。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>ログインして「活動管理」画面で、その日の出欠や活動の状況を確認します。</li>
        <li>タブレットまたはパソコンから、その日の活動やお子さまの様子を記録します。</li>
        <li>記録をもとに「連絡帳」を作成し、保護者へ送信します。</li>
        <li>「チャット」で保護者からの連絡を確認し、必要に応じて返信します。</li>
        <li>一定期間ごとに、個別支援計画・モニタリング・アセスメントなどの書類を作成・更新します。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> どのメニューを開けばよいか迷ったときは、まず左サイドバーの見出し（「日常業務」「チャット」「計画・支援」など）を目印にしてください。やりたいことに近いグループを開くと、目的の機能を見つけやすくなります。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 本システムはお子さまや保護者の個人情報を扱います。管理者・スタッフは、個人所有のスマートフォン・パソコン・タブレットではなく、事業所が貸与・管理する端末から利用してください。離席する際は、他の人に操作されないよう画面をロックするか、ログアウトすることをおすすめします。
        </p>
      </div>
    </div>
  );
}
