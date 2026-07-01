export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* リード */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        現場でよくいただく質問と、その解決手順をまとめました。「ログインできない」「保存できない」「保護者に届かない」といった困りごとは、多くの場合ここに書かれた手順で解決できます。上から順に、状況に近い項目を探してお読みください。それでも解決しないときは、ページ下部の「不具合を報告したいとき」の手順で管理者・システム管理者にご連絡ください。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 画面上のメニュー名・ボタン名はこのマニュアルの表記と同じです。もし画面に見当たらないときは、左メニューが折りたたまれている（左上のメニューボタンで開閉できます）か、担当していない教室に切り替わっている可能性があります。
        </p>
      </div>

      {/* ===== ログインできない ===== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">ログインできないとき</h3>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        <strong>Q. ログイン画面で「ログイン」を押してもエラーが出て入れません。</strong>
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        ログイン画面には「ログインID」と「パスワード」の2つの入力欄があります。次の順で確認してください。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「ログインID」の欄に、管理者から伝えられたIDを正確に入力します。前後に空白が入っていないか、全角・半角が違っていないかを確認します。</li>
        <li>「パスワード」の欄に、パスワードを入力します。大文字・小文字は区別されます。「Caps Lock（大文字固定）」がオンになっていないか確認してください。</li>
        <li>「ログイン」ボタンを押します。うまくいけば、そのまま最初の画面（活動管理など）に移動します。</li>
        <li>入力欄の上に赤いエラーメッセージが出た場合は、その内容を確認します。IDまたはパスワードの誤りが最もよくある原因です。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> ログイン画面の下には「ログインID・パスワードが不明な場合は管理者にお問い合わせください」と表示されています。IDやパスワードを忘れた場合、この画面から自分でパスワードを再設定する仕組みはありません。教室の管理者に依頼して、パスワードを再発行してもらってください。
        </p>
      </div>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        <strong>Q. ID・パスワードを入れた後、「認証コード」を求められました。</strong>
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        そのアカウントは、より安全にログインするための「2要素認証」が有効になっています。ID・パスワードに加えて、認証アプリに表示される6桁のコードが必要です。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>ID・パスワードを入力して「ログイン」を押すと、「認証コード」の入力欄が表示されます。</li>
        <li>お使いの認証アプリを開き、このシステム用に表示されている6桁の数字を確認します。</li>
        <li>その6桁のコードを「認証コード」欄に入力し、「認証して続行」ボタンを押します。</li>
        <li>コードは一定時間で切り替わります。時間切れで入らないときは、新しいコードに変わるのを待ってから入力し直してください。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 認証アプリが使えない場合は、設定時に控えた「リカバリコード」を認証コード欄に入力してもログインできます。認証アプリを機種変更などで失った場合は、管理者に2要素認証の設定リセットを依頼してください。
        </p>
      </div>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        <strong>Q. 画面が真っ白になる／うまく表示されず操作できません。</strong>
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>まずキーボードの「F5」キー（またはブラウザの再読み込みボタン）でページを読み込み直します。</li>
        <li>直らない場合は、一度「ログアウト」してから、もう一度ログインし直します。ログアウトは、画面右上のログアウトアイコン、または左メニュー下部の「ログアウト」から行えます。</li>
        <li>それでも直らないときは、ブラウザをすべて閉じてから開き直し、再度ログインを試してください。</li>
        <li>Chrome・Edge・Safari など、最新のブラウザでのご利用をおすすめします。古いブラウザでは正しく表示されないことがあります。</li>
      </ol>

      {/* ===== 保存できない ===== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">保存できない・保存されないとき</h3>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        <strong>Q. 入力したのに保存できません（「保存」ボタンが押せない／エラーになる）。</strong>
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        保存できないときは、多くの場合「必須の入力欄が空になっている」か「通信が一時的に途切れている」ことが原因です。次の順で確認してください。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>入力欄の近くに赤い文字のメッセージが出ていないか確認します。必須項目が空だと、その欄の下に注意が表示され、保存ボタンが押せないことがあります。</li>
        <li>必須項目（多くは「＊」印が付いています）をすべて埋めてから、もう一度「保存」を押します。</li>
        <li>それでも保存できないときは、通信が途切れている可能性があります。画面をF5キーで読み込み直し、もう一度入力・保存してください。</li>
        <li>保存に成功すると、画面の隅に「保存しました」などのメッセージが短く表示されます。この表示が出れば保存は完了しています。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 保存できていない状態でページを移動したり、ブラウザを閉じたりすると、入力した内容が失われることがあります。エラーが出たときは、その画面のまま原因を解決するか、大切な内容は別の場所に控えてから操作してください。
        </p>
      </div>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        <strong>Q. パスワードを変更しようとすると「パスワード変更に失敗しました」と出ます。</strong>
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        パスワードの変更は、左メニューの「プロフィール」を開き、「パスワード変更」のカードから行います。失敗するときは次を確認してください。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「現在のパスワード」の欄に、今ログインに使っているパスワードを正しく入力しているか確認します。ここが違うと変更に失敗します。</li>
        <li>「新しいパスワード」は8文字以上で入力します。短すぎると変更できません。</li>
        <li>「新しいパスワード（確認）」に、上と同じパスワードをもう一度入力します。2つが一致していないと「パスワードが一致しません」と表示され、「パスワードを変更」ボタンが押せません。</li>
        <li>3つの欄がすべて正しく入力できると「パスワードを変更」ボタンが押せるようになります。押すと「パスワードを変更しました」と表示されます。</li>
      </ol>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        <strong>Q. 連絡帳の下書きが消えていないか心配です。保存はどうなっていますか。</strong>
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「連絡帳入力」の統合内容の編集画面では、まだ送信しない内容を「途中保存」ボタンで下書きとして保存できます。加えて、自動保存も働いています。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>下書きは約5分ごとに自動で保存されます。手動で今すぐ保存したいときは「途中保存」ボタン、またはキーボードの「Ctrl＋S」でも保存できます。</li>
        <li>「途中保存」はあくまで下書きです。保護者にはまだ届きません。届けるには「保護者に送信」を押す必要があります（下の項目もあわせてご確認ください）。</li>
      </ul>

      {/* ===== 保護者に届かない ===== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">保護者に届かないとき</h3>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        <strong>Q. 連絡帳を入力したのに、保護者が「届いていない」と言っています。</strong>
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        連絡帳は、記録を入力しただけでは保護者に届きません。最後に「保護者に送信」を押して、はじめて保護者の画面に表示されます。次の順で確認してください。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「連絡帳入力」を開き、対象の日付・活動を選びます。</li>
        <li>活動カードの「統合」ボタン（紙飛行機マーク）から「統合内容の編集」画面を開き、その児童の連絡帳の文章が入力されているか確認します。</li>
        <li>内容が正しければ、右下の「保護者に送信」ボタンを押します。確認ダイアログで「OK」を押すと送信されます。</li>
        <li>送信済みかどうかは、活動カードの目のマーク（送信済み内容を閲覧）から確認できます。ここで各連絡帳が「確認済み」か「未確認」かも分かります。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「送信したのに保護者がまだ読んでいない」連絡帳は、左メニューの「未確認連絡帳」でまとめて確認できます。経過日数ごとに件数が表示されるため、確認が遅れている保護者への声かけに役立ちます。「未確認」は「届いていない」ではなく「保護者がまだ開いていない」という意味です。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 「保護者に送信」を押した連絡帳は、後から取り消せません。「途中保存」（下書き）と「保護者に送信」は役割が違います。送信前に必ず内容を確認してください。
        </p>
      </div>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        <strong>Q. 「到着」「帰宅」を押したのに保護者に伝わっていないようです。</strong>
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「活動管理」の「本日の参加予定者」やチャット画面にある「到着」「帰宅」ボタンは、押すと保護者チャットへ自動でメッセージが送られます。次を確認してください。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>ボタンを押した後に確認ダイアログが出るので、児童名とメッセージ内容を確認し、「OK」を押したか思い出してください。「OK」を押すまでは送信されません。</li>
        <li>送信が完了すると、ボタンの表示が「到着済」「帰宅済」に変わります。この表示になっていれば送信済みです。</li>
        <li>送信できたかどうかは、その児童の保護者チャットを開くと、送られたメッセージとして確認できます。</li>
      </ol>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        <strong>Q. 保護者から「アプリの通知（お知らせ）が来ない」と相談されました。</strong>
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        端末の画面に届くプッシュ通知は、利用する人ごとに端末側で「有効」にする設定が必要です。保護者ご自身の端末での設定になりますが、ご案内できるよう手順を把握しておくと安心です。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「プロフィール」画面の「通知設定」で、「通知を有効にする」ボタンを押すと、その端末で通知を受け取れるようになります。</li>
        <li>iPhone・iPad の場合は、Safari の共有ボタンから「ホーム画面に追加」でアプリとして開いてから、通知を有効にする必要があります（iOS 16.4 以降で対応）。</li>
        <li>ブラウザで通知が「ブロック（拒否）」になっていると有効にできません。その場合はブラウザの設定でこのサイトの通知を「許可」に変えてから、ページを読み込み直します。</li>
        <li>正しく通知が届くかは、「通知設定」内の「テスト通知を送る」ボタンで確認できます。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> スタッフ・管理者の方も、同じ「プロフィール」画面の「通知設定」から、チャット・お知らせ・連絡帳・書類依頼などの通知を端末で受け取れます。通知が来ない場合は、まずこの設定が「有効」になっているか確認してください。
        </p>
      </div>

      {/* ===== 通知ベル ===== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">画面上の通知・未読の見方</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        画面右上の通知ベル（ベルのアイコン）には、対応が必要な項目のお知らせがたまります。未読があると、ベルの右上に赤い数字が表示されます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>ベルのアイコンを押すと、最近の通知の一覧が開きます。</li>
        <li>各通知を押すと、内容が既読になり、関係する画面へ移動できます。</li>
        <li>まとめて既読にしたいときは、一覧の右上「すべて既読にする」を押します。</li>
      </ol>

      {/* ===== 教室切り替え ===== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">別の教室の情報が見えない・違う教室が表示されるとき</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        複数の教室を担当している場合、今見ているのは「切り替えで選んでいる教室」の情報です。目的の児童や記録が見当たらないときは、教室が別のものに切り替わっていないか確認してください。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>パソコンでは画面上部（ヘッダー）、スマートフォンでは左メニューを開いた上部に、教室を選ぶプルダウンが表示されます。</li>
        <li>プルダウンから、見たい教室を選びます。切り替えると「教室を切り替えました」と表示され、画面がその教室の情報に更新されます。</li>
        <li>教室のプルダウンが表示されない場合は、担当教室が1つだけのため切り替えの必要がない状態です。</li>
      </ol>

      {/* ===== 不具合報告 ===== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">解決しないとき・不具合を報告したいとき</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        ここまでの手順で解決しない不具合は、左メニュー下部の「バグ報告」からシステム管理者に報告できます。状況が伝わるように報告すると、早く解決につながります。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>不具合が起きたら、まずその画面のアドレス（URL）を控えておくとスムーズです（アドレスバーを選択して Ctrl＋C でコピー）。</li>
        <li>左メニューの「バグ報告」を開き、右上の「新しい報告」ボタンを押します。</li>
        <li>「発生したページのURL」に、不具合が起きた画面のURLを入力（貼り付け）します。</li>
        <li>「エラー内容」に、「どんな操作をしたら、何が起きたか」をできるだけ具体的に書きます。ここは必須です。</li>
        <li>必要に応じて「エラー画面のスクリーンショット」を添付します。文章で説明しづらいときは、画面を撮って添付するだけでも助かります。</li>
        <li>「重要度」を選びます（低・中・高・緊急）。業務が止まって困っている場合は「高」や「緊急」を選びます。</li>
        <li>「送信」ボタンを押すと報告が届きます。「バグ報告を送信しました」と表示されれば完了です。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 報告後、対応が進むと状態が「未対応」→「対応済み確認依頼中」→「解決済み」と変わります。「対応済み確認依頼中」になったら、実際に直っているかを確認し、直っていれば「解決済みにする」、まだのときは「未対応に戻す」を押してください。報告の詳細画面では、担当者とのやり取り（返信）もできます。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 添付できるスクリーンショットは1枚あたり5MBまでです。大きすぎると送信できないことがあります。慣れている方は、キーボードの「F12」キーで開く画面（コンソール）に赤いエラーが出ていれば、その内容を「コンソールログ」欄に貼り付けると、原因の特定がさらに早くなります。
        </p>
      </div>
    </div>
  );
}
