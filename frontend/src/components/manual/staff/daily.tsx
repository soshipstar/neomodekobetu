export default function ManualSection() {
  return (
    <div className="space-y-4">
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        このページでは、指導員・管理者の方が毎日行う基本の流れをまとめています。
        1日の作業は大きく「出席の確認」「活動記録の入力」「連絡帳の送信」「チャットの確認」の4つに分かれます。
        上から順に進めれば、迷わず1日の記録と保護者への連絡を完了できます。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>1日の流れ:</strong> ①ダッシュボード（活動管理）で出席を確認 → ②連絡帳入力で活動記録を入力 →
          ③統合内容を確認して保護者へ送信 → ④チャットで保護者からの連絡を確認、の順で進めます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. 出席を確認する（ダッシュボード）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        画面左のメニューから「活動管理」（ダッシュボード）を開きます。
        画面上部の「通知・アラート」で未読チャットや未確認連絡帳など、その日に対応が必要な項目がまとめて表示されます。
        画面右側の「本日の参加予定者」で、来所予定の児童を学年ごとに確認します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「活動管理」を開きます。</li>
        <li>右上の「今日」ボタンを押すと、当日の日付に戻ります。別の日を見たいときはカレンダーの日付をクリックします。</li>
        <li>右側の「本日の参加予定者」で、参加予定の児童一覧を確認します。学年（未就学・小学生・中学生・高校生）ごとに分かれて表示されます。</li>
        <li>欠席の児童は名前に取り消し線が付き、「欠席」の赤いラベルが表示されます。</li>
        <li>児童名をクリックすると、その児童の詳細ページを開けます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 参加予定者の一覧は約30秒ごとに自動で更新され、他のスタッフが送信した「到着」「帰宅」の状況も反映されます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">到着・帰宅を保護者へ知らせる（かんたん連絡）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「本日の参加予定者」の各児童名の右側には、「到着」「帰宅」のボタンがあります。
        ボタンを押すと、その児童の保護者チャットへ「到着しました」または「これから帰ります」のメッセージが送られます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>児童が来所したら、その児童の行にある「到着」ボタンを押します。</li>
        <li>「〇〇さんの保護者に『到着しました』を送信します。本当によろしいですか？」という確認が出るので、内容を確認して「OK」を押します。</li>
        <li>送信が完了すると、ボタンが「到着済」の表示に変わります。</li>
        <li>帰りの際は同じように「帰宅」ボタンを押し、確認後に送信します。送信後は「帰宅済」の表示に変わります。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 到着・帰宅の連絡は保護者にすぐ届きます。押し間違いを防ぐため確認ダイアログが出ますので、
          児童名とメッセージ内容を必ず確認してから「OK」を押してください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. 活動記録を入力する（連絡帳入力）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        左メニューの「連絡帳入力」を開きます。画面上部の日付ナビゲーションで対象日を選び、「活動一覧」タブでその日の活動を確認します。
        （ダッシュボードの活動一覧から活動をクリックしても、この画面へ移動できます。）
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「連絡帳入力」を開き、上部の日付バーで対象の日を選びます。左右の矢印、「今日」ボタン、または日付入力欄・週の並びから選べます。</li>
        <li>「活動一覧」タブに、その日の活動がカードで表示されます。活動がまだない場合は、右上の「活動を作成」ボタンから作成します。</li>
        <li>記録を入力したい活動カードの右側にある鉛筆マーク（記録を編集）のボタンを押します。</li>
        <li>下側に「生徒記録」の編集パネルが開きます。左の「生徒一覧」から、記録する児童を選びます。</li>
        <li>右側の入力欄に、5つの領域（健康・生活／運動・感覚／認知・行動／言語・コミュニケーション／人間関係・社会性）ごとにその日の様子を入力します。必要に応じて下の「個別メモ」も入力します。</li>
        <li>入力が終わったら「保存」ボタンを押します。記録が保存されると、生徒一覧の名前の横にチェックマークが付きます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 各領域の入力欄の下には、その児童の個別支援計画の目標が表示されます。
          「この目標を引用」ボタンを押すと、目標を観察記録欄に取り込めます。短期・長期目標に対するコメントも入力できます。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> この活動に児童を追加したいときは、生徒一覧の下の「生徒を追加」ボタンから選べます。
          記録を消したいときは、生徒名にカーソルを合わせると出る×ボタンで、その児童の記録を削除できます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3. 連絡帳を保護者に送信する（統合・送信）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        入力した記録を1つの連絡帳の文章にまとめて、保護者へ送信します。
        活動カードにある「統合」ボタン（紙飛行機マーク）を押すと、「統合内容の編集」画面が開きます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>活動カードの「統合」ボタンを押して「統合内容の編集」画面を開きます。</li>
        <li>児童ごとに、送信する文章の入力欄が表示されます。手入力するか、右上の「AI生成」ボタンで、入力済みの記録から文章を自動作成できます。</li>
        <li>文章の内容を確認し、必要に応じて直接書き直します。</li>
        <li>その日・その児童に一致する写真がある場合は、自動で添付候補として表示されます。不要な写真は写真右上の×で外せます。後からアップした写真は「写真を再取得」ボタンで取り込めます。</li>
        <li>まだ送信しないときは「途中保存」ボタンで下書きとして保存できます（自動保存は5分ごと、キーボードの Ctrl+S でも保存できます）。</li>
        <li>内容が確定したら、右下の「保護者に送信」ボタンを押します。</li>
        <li>「〇名の保護者にこの内容で連絡帳を送信します。送信後は取り消せません。よろしいですか？」という確認が出るので、内容を確認して「OK」を押します。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 「保護者に送信」を押すと連絡帳が保護者に届き、後から取り消すことはできません。
          「途中保存」と「保護者に送信」を押し間違えないよう、送信前に必ず内容を確認してください。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 送信済みの内容は、活動カードの目のマーク（送信済み内容を閲覧）から確認できます。
          この画面では、各連絡帳が「確認済み」か「未確認」か（保護者が読んだかどうか）も分かります。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">未確認の連絡帳を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        送信したのに保護者がまだ確認していない連絡帳は、「連絡帳入力」画面の「未確認連絡帳」タブでまとめて確認できます。
        経過日数（今日送信／1〜2日経過／3日以上経過）ごとに件数が表示されるため、確認が遅れている保護者への声かけに役立ちます。
      </p>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">4. チャットを確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        左メニューの「チャット」を開きます。左側に保護者とのチャットルームの一覧、右側に選んだルームのやり取りが表示されます。
        未読のメッセージがあるルームには、赤い数字のバッジが付きます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「チャット」を開きます。ルーム一覧は学年グループ（未就学児・小学生・中学生・高校生）ごとに折りたたまれています。見出しを押すと開閉できます。</li>
        <li>上部の検索欄に生徒名・保護者名を入力すると、目的のルームをすばやく探せます。</li>
        <li>未読バッジの付いたルームを押すと、右側にやり取りが表示され、未読は自動的に既読になります。</li>
        <li>返信するときは、下部の入力欄にメッセージを入力して送信します。ファイルの添付もできます。</li>
        <li>よく使うルームは、右上のピンのマークで上部に固定表示できます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> チャット画面の上部にある「これから帰ります」「到着しました」ボタンからは、
          複数の保護者を選んでまとめて連絡を送れます。送信内容は編集でき、よく使う文言は「テンプレ保存」で教室の既定として保存できます。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 全員へのお知らせは、ルーム一覧の右上「一斉送信」から行えます。送信先は既定で在籍中の児童のみが選択され、
          ファイルを1つ添付して全員に共有することもできます。チャット画面の上部アイコンからは、選択中の保護者への「面談予約」や「提出期限」の設定も行えます。
        </p>
      </div>
    </div>
  );
}
