export default function ManualSection() {
  return (
    <div className="space-y-4">
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        提出物管理では、連絡帳の返却や書類・持ち物などの提出依頼を作成し、保護者・生徒へ配信して、提出状況を一覧で確認・回収できます。左のメニューの「提出物」グループには、用途の異なる2つのページがあります。目的に合わせて使い分けてください。
      </p>

      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>生徒提出物</strong>（メニュー「生徒提出物」）… 1件の依頼を教室の生徒全体に配信し、誰が提出したかを進捗バーで確認するページです。提出ファイルの回収に向いています。</li>
        <li><strong>提出物管理</strong>（メニュー「提出物管理」）… 生徒を1人ずつ指定して依頼を作り、期限超過を色分けで管理するページです。個別の書類依頼や期限管理に向いています。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> どちらのページでも、依頼を作成すると保護者・生徒の画面に自動で表示されます。あらためて送信ボタンを押す必要はありません。保護者・生徒が提出（完了操作）を行うと、その状況が指導員側の一覧に反映されます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. 生徒提出物（教室全体へ配信・ファイル回収）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        1つの依頼を教室の生徒全員に一斉に配信し、提出状況をまとめて確認したいときに使います。
      </p>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">提出依頼を作成する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左のメニューから「生徒提出物」を開きます。</li>
        <li>画面右上の「新規依頼作成」ボタンを押します。「提出依頼を作成」というウィンドウが開きます。</li>
        <li>「タイトル」に依頼の名前（例: 検温票の提出）を入力します。ここは必須です。</li>
        <li>「説明」に依頼の内容や注意点を入力します（任意）。空欄のままでも作成できます。</li>
        <li>「期限（任意）」で提出期限の日付を選びます。期限を設けない場合は空欄のままにします。</li>
        <li>「作成」ボタンを押します。「提出依頼を作成しました」と表示され、一覧に追加されます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 作成した依頼は自動的に教室の全生徒へ配信されます。やり直したいときは、入力ウィンドウ下部の「キャンセル」を押すと作成せずに閉じられます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">提出状況を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        一覧はタブで切り替えられます。まだ受付中の依頼は「受付中」タブ、締め切った依頼は「締切済み」タブに表示されます。各タブの見出し横の数字は、その状態の依頼件数です。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>一覧の「提出状況」欄で、緑色のバーと「提出人数／対象人数」の数字を確認します（例: 3/12 は12人中3人が提出済み）。</li>
        <li>「期限」欄で提出期限を確認します。期限を設定していない依頼は「期限なし」と表示されます。</li>
        <li>「ステータス」欄では、受付中の依頼に「受付中」、締め切った依頼に「締切」のラベルが付きます。</li>
        <li>誰が提出したかを確認するには、一覧の「タイトル」（青い文字）をクリックします。生徒ごとの提出一覧ウィンドウが開きます。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">提出ファイルを回収する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>一覧でタイトルをクリックし、生徒ごとの提出一覧ウィンドウを開きます。</li>
        <li>各行で「生徒名」「ステータス」（未提出／提出済み／確認済み）「提出日時」を確認します。</li>
        <li>「ファイル」欄にファイル名が表示されている場合は、その名前をクリックするとファイルを開いて内容を確認・保存できます。</li>
        <li>保護者・生徒がコメントを添えている場合は「コメント」欄に表示されます。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">依頼を締め切る・再開する・削除する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>受付を終える場合は、対象の依頼の「操作」欄にある「締切る」ボタンを押します。依頼は「締切済み」タブへ移り、ステータスが「締切」になります。</li>
        <li>締め切った依頼を再び受け付けたい場合は、「締切済み」タブでその依頼の「再開」ボタンを押します。</li>
        <li>依頼そのものを消す場合は、「操作」欄のごみ箱アイコンを押します。確認メッセージで「OK」を選ぶと削除されます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 削除した依頼と、それに紐づく提出データは元に戻せません。回収済みのファイルが不要か確認してから削除してください。受付を一時的に止めたいだけであれば、削除ではなく「締切る」を使ってください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. 提出物管理（生徒を指定した個別依頼・期限管理）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        特定の生徒に書類の提出を依頼し、期限や進み具合を1人ずつ管理したいときに使います。画面上部には「全体」「未提出」「期限超過」「提出済み」の4つの件数カードが並び、状況をひと目で把握できます。
      </p>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">個別の提出依頼を作成する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左のメニューから「提出物管理」を開きます。</li>
        <li>画面右上の「新規依頼作成」ボタンを押します。「提出依頼を作成」ウィンドウが開きます。</li>
        <li>「生徒」の欄で、依頼する生徒を一覧から選びます。ここは必須です。</li>
        <li>「タイトル」に依頼の名前を入力します（必須）。</li>
        <li>「説明」に内容や注意点を入力します（任意）。</li>
        <li>「期限（任意）」で提出期限の日付を選びます。</li>
        <li>「作成」ボタンを押します。「提出依頼を作成しました」と表示され、その生徒への依頼として一覧に追加されます。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">提出状況と期限を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        一覧は「未提出」「提出済み」「すべて」の3つのタブで切り替えられます。各タブの見出し横の数字は件数です。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「生徒名」欄には生徒名と保護者名が表示されます。</li>
        <li>「期限」欄は、期限を過ぎた依頼が赤色で「◯日超過」と表示され、期限まで3日以内の依頼が「残り◯日」と強調表示されます。</li>
        <li>「ステータス」欄は、未提出の依頼に「未提出」、提出済みの依頼に「提出済み」（提出日時・完了メモ付き）のラベルが付きます。</li>
        <li>「添付」欄にファイル名が表示されている場合は、名前をクリックすると添付ファイルを開けます。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 上部の「期限超過」カードの数字を見れば、対応が必要な依頼がどれだけあるかを毎回のログイン時にすぐ確認できます。数字がある日は「未提出」タブで期限が赤い依頼から声かけを進めると漏れがありません。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">提出完了として処理する（回収）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        書類を受け取ったら、指導員側で完了処理を行い記録に残します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>対象の依頼の「操作」欄にある「完了」ボタンを押します。「提出完了の確認」ウィンドウが開きます。</li>
        <li>必要に応じて「完了メモ（任意）」に、受け取った状況や補足を入力します。</li>
        <li>「完了にする」ボタンを押します。「提出完了にしました」と表示され、その依頼は「提出済み」に変わります。</li>
        <li>間違えて完了にした場合は、その依頼の「未提出に戻す」ボタンを押し、確認メッセージで「OK」を選ぶと未提出の状態へ戻せます。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">個別依頼を削除する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>対象の依頼の「操作」欄にあるごみ箱アイコンを押します。</li>
        <li>確認メッセージで「OK」を選ぶと、その依頼が削除されます。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">保護者・生徒側での見え方</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        作成した依頼は、保護者・生徒の「提出物」画面に自動で表示されます。相手側では次のように見え、操作できます。指導員が案内する際の参考にしてください。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>期限が近い依頼は「期限間近」、過ぎた依頼は「期限切れ」として色分けで目立つように表示されます。</li>
        <li>提出が済んだら、相手が「完了にする」ボタンを押します。押し間違えたときは「未完了に戻す」で戻せます。</li>
        <li>相手側では、依頼が「週間計画表」「保護者チャット」「自分で登録」のどこから来たものかがラベルで分かるようになっています。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 保護者・生徒が「完了にする」を押しても、書類そのものが自動で届くとは限りません。ファイルの回収が必要な依頼は、「生徒提出物」ページの提出一覧でファイルの有無を確認するか、「提出物管理」ページで受け取り後に「完了」処理を行ってください。
        </p>
      </div>
    </div>
  );
}
