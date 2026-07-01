export default function ManualSection() {
  return (
    <div className="space-y-4">
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        このセクションでは、放課後等デイサービスの指導員・管理者が毎日の業務でよく使う3つの機能、
        <strong>「連絡帳の記入・送信」</strong>・<strong>「個別支援計画の作成(AI生成を含む)」</strong>・
        <strong>「チャット」</strong>の操作手順をひとつずつ詳しく説明します。画面に表示されるボタン名やラベルに沿って
        書いていますので、はじめての方も上から順に進めれば操作できます。
      </p>

      {/* ===================================================================== */}
      {/* 1. 連絡帳 */}
      {/* ===================================================================== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. 連絡帳の記入と送信</h3>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        連絡帳は「日々の活動を記録し、その内容をひとつの文章にまとめて保護者に送る」という流れで使います。
        大きく分けて次の3ステップです。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>活動を作成する(その日に行った活動の枠を用意する)</li>
        <li>児童ごとに5領域の観察記録を入力する</li>
        <li>記録をまとめた「統合内容」を確認・編集して、保護者に送信する</li>
      </ol>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">1-1. 日付を選ぶ</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから<strong>「連絡帳入力」</strong>の画面を開きます。</li>
        <li>
          画面上部に日付の切り替え欄があります。<strong>「今日」</strong>ボタンで当日に戻れるほか、
          左右の矢印ボタンで前日・翌日に移動できます。週の帯に並んだ曜日をタップして日付を選ぶことも、
          カレンダー入力欄から直接日付を指定することもできます。
        </li>
        <li>選んだ日付の下に「◯年◯月◯日(曜日)」が表示され、その日の活動一覧に切り替わります。</li>
      </ol>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">1-2. 活動を作成する</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          画面右上の<strong>「活動を作成」</strong>ボタンを押します。まだその日の活動が1件も無いときは、
          一覧の中央に表示される<strong>「活動を作成する」</strong>ボタンからも作成できます。
        </li>
        <li>活動作成の画面が開くので、活動名などを入力して保存します。</li>
        <li>保存すると、連絡帳入力の「活動一覧」タブにその活動のカードが表示されます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 活動カードには、記録した児童の人数、「送信済」「未送信」の件数が表示されます。
          一覧の上には<strong>「活動一覧」</strong>と<strong>「未確認連絡帳」</strong>の2つのタブがあり、
          「未確認連絡帳」タブでは、送信したのに保護者がまだ確認していない連絡帳を、経過日数(今日送信/1〜2日経過/3日以上経過)ごとに確認できます。
        </p>
      </div>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">1-3. 児童ごとの記録を入力する</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          活動カードの右側にある<strong>鉛筆(編集)アイコン</strong>のボタンを押します。画面の下に「◯◯(活動名) - 生徒記録」パネルが開きます。
        </li>
        <li>
          左側の<strong>「生徒一覧」</strong>から記録したい児童の名前を選びます。名前の右のアイコンで、
          入力済み(チェックマーク)かまだ未入力かが分かります。
        </li>
        <li>
          対象の児童がまだ一覧に居ないときは、一覧の下の<strong>「生徒を追加」</strong>ボタンを押し、
          表示された名前から選んで追加します。
        </li>
        <li>
          右側の入力欄に、5つの領域ごとに観察したことを記入します。領域は
          <strong>「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」</strong>の5つです。
        </li>
        <li>
          個別支援計画が登録されている児童では、各領域の入力欄の下にその領域の目標が青い枠で表示されます。
          <strong>「この目標を引用」</strong>ボタンを押すと、その目標を観察記録欄に取り込めます(引用すると「引用済み」と表示されます)。
        </li>
        <li>
          短期目標・長期目標が登録されている場合は、入力欄の下にそれぞれの目標が表示され、
          それに対するコメントを書き込む欄があります。必要に応じて記入します。
        </li>
        <li>その他に伝えたいことは、一番下の<strong>「個別メモ」</strong>欄に記入します。</li>
        <li>入力が終わったら、右下の<strong>「保存」</strong>ボタンを押します。「保存しました」と表示されれば完了です。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 生徒一覧の名前にマウスを合わせると小さな<strong>×</strong>ボタンが出ます。
          これを押すとその児童の記録を削除できます(関連する統合ノートも一緒に削除される点にご注意ください)。
        </p>
      </div>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">1-4. 統合内容を作って保護者に送信する</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          活動カードの<strong>「統合」</strong>ボタン(紙飛行機アイコン)を押します。「統合内容の編集」画面が開きます。
        </li>
        <li>
          児童ごとにカードが並びます。各カードには、入力済みの5領域の記録をつなげた文章の下書きが自動で入っています。
          必要に応じて文章を直接編集できます。
        </li>
        <li>
          文章をAIで整えたい場合は、児童カード右上の<strong>「AI生成」</strong>ボタンを押します。
          AIが5領域の記録を保護者向けの読みやすい文章にまとめ直します。
        </li>
        <li>
          その日・その児童の写真が見つかると、文章の下に<strong>自動添付された写真</strong>のサムネイルが表示されます。
          不要な写真は各画像の<strong>×</strong>で外せます。写真がまだ見つからないときや後から写真が追加されたときは、
          <strong>「写真を再取得」</strong>ボタンで取り込めます。
        </li>
        <li>
          途中で作業を中断するときは、下部の<strong>「途中保存」</strong>ボタンで下書きを保存します。
          保存した内容は次に開いたときに続きから編集できます。
        </li>
        <li>
          内容が整ったら、右下の<strong>「保護者に送信」</strong>ボタンを押します。確認のメッセージが出るので、
          内容と送信人数を確かめて<strong>「OK」</strong>を押すと保護者に配信されます。
        </li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> <strong>「保護者に送信」</strong>を押して送信した連絡帳は<strong>取り消せません</strong>。
          <strong>「途中保存」</strong>との押し間違いに注意し、送信前の確認メッセージで内容と人数を必ず確認してください。
          送信済みの児童のカードは「送信済み」と表示され、文章の編集はできなくなります。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「統合内容の編集」画面は<strong>5分ごとに自動保存</strong>され、
          <strong>Ctrl+S(Macは⌘+S)</strong>でもいつでも途中保存できます。画面に「最終保存: ◯◯」と保存時刻が表示されます。
          未送信の内容をすべて作り直したいときは、左下の<strong>「統合をリセット」</strong>ボタンを使います。
          送信済みの内容をあとから見返すときは、活動カードの<strong>目(閲覧)アイコン</strong>から
          「送信済み内容の閲覧」を開きます。ここでは各児童の送信日時や、保護者が確認したかどうか(確認済み/未確認)も分かります。
        </p>
      </div>

      {/* ===================================================================== */}
      {/* 2. 個別支援計画 */}
      {/* ===================================================================== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. 個別支援計画の作成(AI生成を含む)</h3>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        個別支援計画は、児童ごとの目標と具体的な支援内容をまとめる書類です。
        児童の詳細画面から<strong>「個別支援計画」</strong>を開いて作成します。作り方は「手入力で新規作成する方法」と
        「AIに素案を作らせてから仕上げる方法」の2通りがあります。
      </p>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">2-1. 手入力で新規作成する</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>対象の児童の<strong>「個別支援計画」</strong>画面を開きます。</li>
        <li>画面右上の<strong>「新規作成」</strong>ボタンを押すと、作成フォームが開きます。</li>
        <li>
          上から順に、<strong>「作成年月日」</strong>、<strong>「利用児及び家族の生活に対する意向」</strong>、
          <strong>「総合的な支援の方針」</strong>を入力します。
        </li>
        <li>
          <strong>「長期目標」</strong>と<strong>「短期目標」</strong>のカードで、それぞれ達成時期(日付)と目標の文章を入力します。
        </li>
        <li>
          <strong>「支援目標及び具体的な支援内容等」</strong>の表に、支援の項目ごとに
          「支援目標」「支援内容」「達成時期」「担当者/提供機関」「留意事項」「優先」を入力します。
          新規作成では「本人支援(5領域)」「家族支援」「移行支援」の行があらかじめ用意されています。
          行が足りないときは<strong>「行を追加」</strong>ボタンで増やし、不要な行はゴミ箱アイコンで削除できます。
        </li>
        <li>最後に<strong>「同意」</strong>のカードで「管理責任者氏名」と「同意日」を入力します。</li>
        <li>入力が終わったら、右下の<strong>「作成する」</strong>ボタンを押して保存します。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 表の入力は5領域(健康・生活/運動・感覚/認知・行動/言語・コミュニケーション/人間関係・社会性)の
          視点で記入するのが基本です。フォームの下にもこの5領域の目安が表示されています。
        </p>
      </div>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">2-2. AIで素案を生成する</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>個別支援計画の画面右上にある<strong>「AI生成」</strong>ボタンを押します。</li>
        <li>
          確認画面が開きます。「AIが過去のデータに基づいて素案を生成する」旨と注意書きが表示されるので、
          内容を確認して<strong>「生成を開始」</strong>ボタンを押します。
        </li>
        <li>
          生成には数分かかる場合があります。処理が始まると<strong>「AI生成ジョブを開始しました。完了後に通知されます。」</strong>
          というメッセージが表示されます。しばらく待ってから画面を確認してください。
        </li>
        <li>
          生成された計画は<strong>下書き</strong>として一覧に追加されます。カードの<strong>「編集」</strong>ボタンから内容を開き、
          手入力の場合と同じフォームで確認・修正して仕上げます。
        </li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> AIが生成した内容は<strong>あくまで参考</strong>です。そのまま使わず、
          必ず担当者・専門スタッフが内容を確認し、児童の実態に合わせて修正してから確定してください。
        </p>
      </div>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">2-3. 計画の確認・編集・署名要求</h4>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          作成した計画は一覧にカードで並びます。カードには作成日と、状態を表すラベル
          (<strong>「下書き」「提出済」「正式」</strong>)が表示されます。
        </li>
        <li>
          カードの<strong>「プレビュー」</strong>ボタンで、実際の書類の形で内容を確認できます。
        </li>
        <li>
          状態が「下書き」または「提出済」の計画は、<strong>「編集」</strong>ボタンから内容を修正できます。
        </li>
        <li>
          職員署名や保護者署名がまだの計画には<strong>「職員署名なし」「保護者署名なし」</strong>のラベルが付きます。
          プレビュー画面から保護者への<strong>署名要求</strong>を送ることができます。
        </li>
      </ul>

      {/* ===================================================================== */}
      {/* 3. チャット */}
      {/* ===================================================================== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3. チャットの使い方</h3>

      <p className="text-sm text-[var(--neutral-foreground-2)]">
        チャットでは、保護者と1対1でメッセージのやり取りができます。個別のやり取りに加えて、
        面談予約・提出期限の設定・一斉送信・送迎の定型連絡といった機能もそろっています。
      </p>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">3-1. 相手を選んでメッセージを送る</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから<strong>「チャット」</strong>を開きます。左側にチャット相手の一覧が表示されます。</li>
        <li>
          一覧は<strong>「ピン留め」</strong>と、<strong>「未就学児」「小学生」「中学生」「高校生」</strong>の学年グループに
          分かれています。グループ名を押すと開閉できます。上部の検索欄に生徒名・保護者名を入れて探すこともできます。
        </li>
        <li>やり取りしたい児童の行を選ぶと、右側にその相手とのメッセージ画面が開きます。</li>
        <li>
          画面下の入力欄にメッセージを入力し、右の<strong>送信ボタン(紙飛行機アイコン)</strong>を押して送ります。
          誤送信を防ぐため、Enterキーでは送信されません。改行はShift+Enterで入力します。
        </li>
        <li>
          ファイルや写真を送るときは、入力欄の左の<strong>クリップ(添付)アイコン</strong>からファイルを選びます。
          画像は自動で300KB以下に圧縮され、画像以外のファイルは3MBまで送れます。
        </li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> よくやり取りする相手はチャット画面右上の<strong>ピン(押しピン)アイコン</strong>で
          ピン留めしておくと、一覧の一番上にまとまって表示され、探しやすくなります。未読のメッセージがある相手には、
          一覧に赤い未読件数のバッジが付きます。
        </p>
      </div>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">3-2. 面談を予約する</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>面談したい保護者とのチャットを開き、画面右上の<strong>カレンダー(面談予約)アイコン</strong>を押します。</li>
        <li>「面談予約」画面で、<strong>「目的」</strong>(必須)を入力し、必要なら「詳細」も記入します。</li>
        <li><strong>「候補日時」</strong>を最大3つまで指定します(1つ以上は必須です)。</li>
        <li><strong>「送信」</strong>ボタンを押すと、保護者に候補日時が送られます。保護者が希望日を選ぶと予約が進みます。</li>
      </ol>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">3-3. 提出期限を設定する</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>対象の保護者とのチャットを開き、画面右上の<strong>チェックリスト(提出期限)アイコン</strong>を押します。</li>
        <li>「提出期限の設定」画面で、<strong>「提出物名」</strong>(必須)を入力し、必要なら「説明」も記入します。</li>
        <li><strong>「提出期限」</strong>の日付を指定します(必須)。</li>
        <li><strong>「設定」</strong>ボタンを押すと、提出期限が登録されます。</li>
      </ol>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">3-4. 複数の保護者に一斉送信する</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左パネル上部の<strong>「一斉送信」</strong>(メガホンアイコン)を押します。</li>
        <li>
          「保護者に一斉送信」画面で送信先を選びます。初期状態では<strong>在籍中の児童のみ</strong>が選ばれています。
          <strong>「全員」「在籍中のみ」「全解除」</strong>のボタンや、各行のチェックで送信先を調整できます。
        </li>
        <li>メッセージを入力します。必要なら<strong>「ファイルを選択」</strong>から3MB以下のファイルを1つ添付できます。</li>
        <li>
          右下の<strong>「◯件に送信」</strong>ボタンを押すと、選んだ保護者全員に同じ内容が送られます。
        </li>
      </ol>

      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">3-5. 送迎の定型連絡(これから帰ります/到着しました)</h4>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          左パネル上部の<strong>「これから帰ります」</strong>または<strong>「到着しました」</strong>ボタンを押します。
        </li>
        <li>
          送信画面が開き、<strong>「送信内容(編集可能)」</strong>にあらかじめ定型文が入っています。必要ならその場で文面を書き換えられます。
        </li>
        <li>
          送信先を選びます。その日の参加予定者は<strong>「本日の参加予定者」</strong>としてまとめて表示され、
          <strong>「本日の予定者」「全選択」「全解除」</strong>のボタンで手早く選べます。
        </li>
        <li>右下の<strong>「◯件に送信」</strong>ボタンを押すと、選んだ保護者に定型連絡が送られます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 送信画面の文面を書き換えたあと<strong>「テンプレ保存」</strong>を押すと、
          その文面がこの教室の既定の定型文として保存され、次回からその内容が最初に表示されます。
          システム標準の文面に戻したいときは<strong>「既定に戻す」</strong>を押します。
        </p>
      </div>
    </div>
  );
}
