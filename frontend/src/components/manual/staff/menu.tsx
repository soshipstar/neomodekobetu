export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        画面の左側にある縦長のメニュー（サイドメニュー）から、システムのすべての機能へ移動できます。メニューは目的ごとにグループ分けされており、上から順に「日常業務」「チャット」「利用者情報」「アセスメント」「計画・支援」「提出物」「情報発信」「記録・日誌」「管理・設定」「サポート」の見出し（区切り線付き）が並びます。ここでは、それぞれの項目がどんな画面を開くのかを説明します。
      </p>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">メニューの基本操作</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>使いたい機能の名前（例:「活動管理」）を、左のメニューから探します。</li>
        <li>項目名をクリック（タップ）すると、その画面が右側に開きます。</li>
        <li>いま開いている項目は、色が付いて強調表示されます。</li>
        <li>パソコンでは、メニュー下部の「&lt;」「&gt;」ボタンでメニューの幅を細く（アイコンのみ）／広く切り替えられます。</li>
        <li>スマートフォンでは、画面上部のメニューボタンから開き、項目を選ぶと自動で閉じます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> メニューの一番上には、所属する教室のロゴと名前、ログイン中の氏名・区分（スタッフ）が表示されます。複数の教室を担当している場合は、氏名の下に教室を切り替えるための選択欄が表示されます。一番下には「ログアウト」ボタンがあります。
        </p>
      </div>

      {/* 日常業務 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">日常業務</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">毎日の出欠・活動確認や、対応が必要な案件を扱うグループです。</p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>活動管理</strong>: ログイン後の最初の画面（ホーム）です。上部に対応が必要な通知（未読チャット、承認待ちの振替、期限が近い計画など）が並び、その下にカレンダーと、選んだ日の活動一覧・参加予定者が表示されます。生徒名の横の「到着」「帰宅」ボタンから保護者へ連絡できます（押すと確認画面が出ます）。</li>
        <li><strong>振替管理</strong>: 保護者から届いた振替（欠席の埋め合わせ）の依頼を確認し、「承認」または「却下」する画面です。保護者が入力した体調連絡の確認や、保護者へのアドバイス入力もここで行います。</li>
        <li><strong>保留タスク</strong>: 個別支援計画・モニタリング・アセスメントで、まだ作成できていないもの（未作成タスク）を一覧で確認する画面です。</li>
        <li><strong>未送信日誌一覧</strong>: 記録は作成したものの、まだ保護者へ送信していない連絡帳（日誌）をまとめて確認する画面です。</li>
        <li><strong>欠席時対応加算</strong>: 欠席時対応加算の記録を一覧で確認し、CSV（表計算ソフトで開ける形式）でダウンロードする画面です。</li>
      </ul>

      {/* チャット */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">チャット</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">メッセージのやり取りをするグループです。いずれも、左側に相手（ルーム）の一覧、右側に会話が表示されます。</p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>保護者チャット</strong>: 保護者とメッセージをやり取りする画面です。左のリストから相手を選び、右側でメッセージを送受信します。</li>
        <li><strong>生徒チャット</strong>: 生徒本人とメッセージをやり取りする画面です。</li>
        <li><strong>スタッフ間チャット</strong>: 職員どうしで連絡する画面です。1対1の個人チャットのほか、複数人のグループチャットも作成できます。</li>
      </ul>

      {/* 利用者情報 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">利用者情報</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>生徒情報</strong>: 生徒（利用児童）の一覧です。ここから生徒の登録・編集や、個々の生徒の詳細画面へ移動できます。</li>
        <li><strong>保護者情報</strong>: 保護者の一覧です。保護者の登録・編集を行います。</li>
      </ul>

      {/* アセスメント */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">アセスメント</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>アセスメント（職員）</strong>: 職員が行うアセスメント（実態把握）を入力・確認する画面です。</li>
        <li><strong>アセスメント（保護者）</strong>: 保護者が入力したアセスメントの内容を確認する画面です。</li>
      </ul>

      {/* 計画・支援 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">計画・支援</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">支援の計画づくりや面談に関するグループです。</p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>支援案</strong>: 活動で行う支援のアイデア（支援案）を一覧で管理する画面です。</li>
        <li><strong>週間計画</strong>: 各生徒の週間計画表を確認する画面です。生徒を選ぶと、その生徒の週間計画表が開きます。</li>
        <li><strong>生徒面談記録</strong>: 生徒との面談の記録を作成・確認する画面です。</li>
        <li><strong>面談管理</strong>: 保護者との面談の予定を管理する画面です。「新規作成」から面談を登録します。</li>
        <li><strong>個別支援計画</strong>: 生徒ごとの個別支援計画を作成・管理する画面です。</li>
        <li><strong>モニタリング</strong>: 個別支援計画に対するモニタリング表を作成する画面です。</li>
      </ul>

      {/* 提出物 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">提出物</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>生徒提出物</strong>: 生徒（保護者）に提出をお願いする書類を管理する画面です。「新規作成」から追加できます。</li>
        <li><strong>提出物管理</strong>: 提出状況（未提出・期限超過・提出済み）を集計して確認・管理する画面です。</li>
        <li><strong>非表示書類</strong>: 表示・非表示を切り替えている書類をまとめて管理する画面（書類表示管理）です。</li>
      </ul>

      {/* 情報発信 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">情報発信</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">保護者や利用者へ情報を届けるグループです。</p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>お知らせ</strong>: 保護者向けのお知らせを作成・管理する画面です。</li>
        <li><strong>施設通信</strong>: 施設通信（お便り）を作成・管理する画面です。「新規作成」から作れます。</li>
        <li><strong>施設通信設定</strong>: 施設通信の各種設定を行う画面です。</li>
        <li><strong>イベント</strong>: イベントを登録・管理する画面です（イベント管理）。カレンダーにも反映されます。</li>
      </ul>

      {/* 記録・日誌 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">記録・日誌</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>連絡帳</strong>: 日常活動の記録を入力し、保護者へ連絡する画面（連絡帳入力）です。活動管理の活動一覧から開くこともできます。</li>
        <li><strong>未確認連絡帳</strong>: 送信したものの、保護者がまだ確認していない連絡帳を一覧で確認する画面です。</li>
        <li><strong>フリースクール用報告書</strong>: フリースクール用の報告書を作成する画面です。</li>
        <li><strong>業務日誌</strong>: 日々の業務記録（業務日誌）を作成・確認する画面です。活動管理の右側からも作成・履歴に移動できます。</li>
      </ul>

      {/* 管理・設定 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">管理・設定</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">教室運営に関わる設定や、その他の管理機能のグループです。</p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>待機児童管理</strong>: 空き状況の確認と、待機児童の管理を行う画面です。</li>
        <li><strong>ヒヤリハット</strong>: ヒヤリハット（事故につながりかねなかった出来事）の記録を一覧・管理する画面です。</li>
        <li><strong>写真ライブラリ</strong>: 教室の写真をまとめて管理する画面です。</li>
        <li><strong>チャット添付ファイル</strong>: チャットでやり取りした添付ファイルを管理する画面です。</li>
        <li><strong>利用日一括変更</strong>: 利用日の追加・キャンセルをまとめて行う画面（利用日変更）です。</li>
        <li><strong>学校休業日活動設定</strong>: 夏休み・春休みなど、学校が休みの日に活動する日を設定する画面です。</li>
        <li><strong>休日設定</strong>: 教室の休日・祝日を登録・管理する画面（休日管理）です。</li>
        <li><strong>日課設定</strong>: 毎日の支援（日課）の内容を設定する画面（毎日の支援設定）です。並び替えや削除ができます。</li>
        <li><strong>タグ設定</strong>: 支援案などで使うタグをカスタマイズする画面です。</li>
        <li><strong>事業所評価</strong>: 事業所の自己評価を入力・集計する画面です。</li>
        <li><strong>利用者一括登録</strong>: CSVファイルをアップロードして、保護者と生徒をまとめて登録する画面です。</li>
        <li><strong>マニュアル</strong>: このスタッフマニュアルを開く画面です。</li>
        <li><strong>プロフィール</strong>: 自分のプロフィールを確認・編集する画面です。</li>
      </ul>

      {/* サポート */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">サポート</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>バグ報告</strong>: 不具合や気づいた点を運営へ報告する画面です。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 迷ったら、まず「活動管理」に戻ると全体の状況を把握しやすくなります。上部の「通知・アラート」に表示されている項目をクリックすると、対応が必要な画面（振替管理・未確認連絡帳・保留タスクなど）へ直接移動できます。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> メニューに表示される項目は、教室の設定やご利用中のプランによって一部異なる場合があります。ここに書かれた項目が見当たらないときは、管理者にご確認ください。
        </p>
      </div>
    </div>
  );
}
