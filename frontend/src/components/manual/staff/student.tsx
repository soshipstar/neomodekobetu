export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 概要 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        生徒本人が自分のログインID・パスワードでシステムにログインすると、専用の「生徒画面（マイページ）」を使えるようになります。生徒はマイページから、スケジュールの確認、スタッフとのチャット、週間計画表の作成、提出物の管理、パスワードの変更ができます。この章では、スタッフが生徒のログインアカウントを発行・管理する手順と、生徒がログイン後にできることを説明します。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 生徒アカウントの発行は任意です。ログインID・パスワードを設定しない生徒は、システムにログインできません（生徒本人がアプリを使わない運用でも問題ありません）。保護者アカウントとは別のものですので、生徒に使わせたい場合のみ設定してください。
        </p>
      </div>

      {/* ============ 1. アカウント発行 ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">生徒のログインアカウントを発行する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        生徒のログイン設定は「生徒管理」画面から行います。ログインID（ユーザー名）とパスワードは、生徒を新規登録した後に、詳細画面または編集画面で設定します。
      </p>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">方法A：生徒詳細画面の「アカウント」タブから設定する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューから「生徒管理」を開きます。</li>
        <li>一覧から対象の生徒名をクリックし、生徒の詳細画面を開きます。</li>
        <li>画面上部のタブから「アカウント」を選びます。</li>
        <li>「ログインID（ユーザー名）」欄に、生徒が使うログインIDを入力します（例：<strong>tanaka_taro</strong> のように半角英数字で入力します）。</li>
        <li>「新しいパスワード」欄に、初回のパスワードを入力します（6文字以上）。</li>
        <li>「アカウント情報を保存」ボタンを押します。「アカウント情報を更新しました」と表示されれば設定完了です。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> ログインIDを変更したい場合は同じ「アカウント」タブでID欄を書き換えて保存します。パスワードを変えたいときだけパスワード欄に新しい値を入力してください。パスワード欄を空欄のまま保存すると、パスワードは変更されません。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">方法B：生徒の編集画面から設定する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        生徒管理の一覧からもログイン設定を行えます。基本情報や通所曜日をまとめて直すついでに設定したいときに便利です。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「生徒管理」画面で、対象の生徒の行にある「編集」ボタンを押します。</li>
        <li>編集画面を下へスクロールし、「生徒用ログイン設定」の項目まで進みます。</li>
        <li>「ユーザー名（半角英数字）」欄にログインID、「パスワード」欄に初回パスワードを入力します。</li>
        <li>「更新する」ボタンを押して保存します。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「生徒用ログイン設定」の項目は、すでに登録済みの生徒を編集するときにだけ表示されます。新規生徒の登録画面には表示されません。まず生徒を登録し、その後で編集または「アカウント」タブから設定してください。
        </p>
      </div>

      {/* ============ 2. ログイン情報の配布（印刷） ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">生徒用のログイン情報を印刷して渡す</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        設定したログインID・パスワードは、印刷用の資料として出力し、生徒や保護者に渡すことができます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「生徒管理」画面で対象の生徒の「編集」ボタンを押します。</li>
        <li>ログインID・パスワードを設定して保存済みであることを確認します。</li>
        <li>画面下部の「生徒用資料を印刷」ボタンを押します。別ウィンドウで印刷用ページが開きます。</li>
        <li>開いたページで「印刷する」ボタンを押し、紙に印刷するかPDFとして保存します。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        印刷される資料には、生徒氏名・事業所名・ログインID・パスワード・ログイン用のURL・お問い合わせ先が記載されます。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 印刷資料にはログインID・パスワードがそのまま記載されます。第三者に見られないよう取り扱いには十分ご注意ください。なお、生徒本人または保護者がパスワードを変更した後は、印刷資料のパスワード欄は「（保護者・本人により変更済み）」と表示され、実際のパスワードは表示されません。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 「生徒用資料を印刷」を押しても資料が開かない場合は、ログイン情報がまだ設定されていない可能性があります。先にユーザー名とパスワードを設定して保存してください。
        </p>
      </div>

      {/* ============ 3. 生徒のログイン方法 ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">生徒がログインする手順（生徒に案内する内容）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>ログイン画面を開きます（印刷資料に記載されたURL、または事業所で案内しているURL）。</li>
        <li>「ログインID」欄に、発行したログインIDを入力します。</li>
        <li>「パスワード」欄に、発行したパスワードを入力します。</li>
        <li>「ログイン」ボタンを押します。ログインに成功すると、生徒用の「マイページ」が表示されます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> ログイン画面はスタッフ・保護者・生徒で共通です。入力したログインIDとパスワードによって、自動的に生徒用のマイページに切り替わります。ログインID・パスワードが分からなくなった場合は、スタッフが「アカウント」タブから確認・再設定できます。
        </p>
      </div>

      {/* ============ 4. 生徒画面（マイページ）でできること ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">生徒画面（マイページ）でできること</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        ログインした生徒には「マイページ」が表示され、次のメニューが並びます。各メニューをタップ（クリック）すると、それぞれの画面に移動します。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>スケジュール</strong> - 自分の通所日・イベント・休日をカレンダーで確認できます。</li>
        <li><strong>チャット</strong> - スタッフとメッセージのやり取りができます。未読メッセージがある場合は件数のバッジが表示されます。</li>
        <li><strong>週間計画表</strong> - 今週の目標や日ごとの計画を自分で立てたり、確認したりできます。</li>
        <li><strong>提出物</strong> - 提出物の一覧を確認し、完了・未完了を管理できます。未提出がある場合は件数のバッジが表示されます。</li>
        <li><strong>パスワード変更</strong> - 自分のログインパスワードを変更できます。</li>
      </ul>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        マイページには、その日が通所日のときの案内や、未提出の提出物があるときの注意も表示されます。
      </p>

      {/* --- スケジュール --- */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">スケジュール（生徒側）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>マイページの「スケジュール」を開くと、当月のカレンダーが表示されます。</li>
        <li>上部の「＜」「＞」で前月・翌月に移動できます。「今月」ボタンで当月に戻れます。</li>
        <li>日付のマス目の下に色付きの点で、通所日・イベント・休日が示されます（凡例はカレンダー下部に表示されます）。</li>
        <li>日付をタップすると、その日の詳細（通所日かどうか、イベント内容、休日名）が下に表示されます。</li>
      </ol>

      {/* --- チャット --- */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">スタッフとのチャット（生徒側）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>マイページの「チャット」を開きます。過去のメッセージが表示されます。</li>
        <li>下部の入力欄にメッセージを入力します。</li>
        <li>クリップ（添付）ボタンから、画像やPDFなどのファイルを添付できます（1ファイル3MBまで。画像は自動で縮小されます）。</li>
        <li>送信ボタン（紙飛行機のマーク）を押すと送信されます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 誤送信を防ぐため、Enterキーや改行では送信されません。必ず送信ボタンを押す必要があります。生徒が送ったメッセージをスタッフが読むと「既読」と表示されます。スタッフ側は「生徒チャット」機能でこのやり取りを確認・返信します。
        </p>
      </div>

      {/* --- 週間計画表 --- */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">週間計画表（生徒側）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>マイページの「週間計画表」を開きます。上部の「＜」「＞」で週を切り替えられます（「今週」ボタンで当週に戻れます）。</li>
        <li>まだ計画がない週は「計画を作成する」ボタンから作成します。すでに計画がある週は「編集する」ボタンで内容を修正できます。</li>
        <li>編集画面では、「今週の個人目標」「やらなければならないこと」「やった方がいいこと」「やりたいこと」を入力します。</li>
        <li>その下で、月曜から日曜まで日ごとの計画を入力します。</li>
        <li>「保存」ボタンを押すと保存されます。やめる場合は「キャンセル」を押します。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        スタッフが設定した「みんなのめあて」や、「先生からのコメント」がある場合は、生徒の計画画面に表示されます。
      </p>

      {/* --- 提出物 --- */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">提出物の管理（生徒側）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        提出物には、週間計画表から連携されたもの、保護者チャット由来のもの、生徒が自分で登録したものがあります。画面上部には「期限間近」「未提出」「提出済み」の件数が表示されます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>マイページの「提出物」を開きます。未提出・提出済みに分かれて一覧表示されます。</li>
        <li>提出が終わったものは「完了にする」ボタンを押すと、提出済みに移動します（「未完了に戻す」で戻せます）。</li>
        <li>自分で提出物を追加するには、右上の「提出物を追加」ボタンを押し、提出物名・詳細説明・提出期限を入力して「保存」します。</li>
        <li>自分で登録した提出物は「編集」「削除」ができます（他の由来の提出物は完了・未完了の切り替えのみ行えます）。</li>
      </ol>

      {/* --- プロフィール・パスワード変更 --- */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">プロフィール・パスワード変更（生徒側）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        生徒はプロフィール画面で、自分のユーザー名・学年区分・事業所・保護者・通所曜日などの基本情報を確認できます。あわせて通知の設定と、パスワードの変更ができます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>マイページから「パスワード変更」を開きます（またはプロフィール画面内の「パスワード変更」欄を使います）。</li>
        <li>「いまのパスワード」に現在のパスワードを入力します。</li>
        <li>「あたらしいパスワード」に新しいパスワードを入力します（8文字以上）。</li>
        <li>「あたらしいパスワード（もういちど）」に同じものをもう一度入力します。</li>
        <li>「パスワードをかえる」ボタンを押すと変更が完了します。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 生徒が自分でパスワードを変更した後は、スタッフの印刷資料には変更後のパスワードは表示されません（「変更済み」と表示されます）。生徒がパスワードを忘れた場合は、スタッフが「アカウント」タブから新しいパスワードを再設定してください。
        </p>
      </div>
    </div>
  );
}
