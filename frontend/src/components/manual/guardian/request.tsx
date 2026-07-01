export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        お子様のより良い支援のために、保護者の皆様には次の3つのご協力をお願いしています。どれもこのシステムの画面から数分で行えます。ここでは、それぞれ「どの画面を開き、どのボタンを押し、何を入力するか」を順を追ってご案内します。専門的な知識は必要ありません。安心して進めてください。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>連絡帳の確認</strong> … 教室での様子をご確認いただき、「確認しました」を押していただきます。</li>
        <li><strong>アセスメントの記入</strong> … 家庭での様子やご要望を入力し、提出していただきます（半年に一度）。</li>
        <li><strong>個別支援計画書の確認・同意</strong> … 計画の内容をご確認いただき、承認または署名、あるいはご意見の送信をしていただきます。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> 対応が必要なものは、ログイン後の<strong>「ダッシュボード」</strong>にまとめて表示されます。まずはダッシュボードを開き、数字（バッジ）が付いている項目から確認すると迷いません。</p>
      </div>

      {/* 1. 連絡帳の確認 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. 連絡帳を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        お子様が教室で活動した日には、スタッフがその日の様子を「連絡帳」としてお届けします。内容を読んだら「確認しました」を押してください。スタッフに「保護者の方が読んでくれた」ことが伝わります。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左のメニュー（スマートフォンでは下部メニューや「メニュー」）から<strong>「連絡帳一覧」</strong>を開きます。</li>
        <li>日付の切り替えは、上部の日付欄を直接選ぶか、左右の矢印（&lt; &gt;）で前日・翌日に移動します。今日に戻りたいときは<strong>「今日」</strong>を押します。</li>
        <li>その日の連絡帳が表示されます。活動名・スタッフからのコメント・写真（ある場合）をご確認ください。写真は押すと大きく表示されます。</li>
        <li>まだ確認していない連絡帳には、オレンジ色の<strong>「未確認」</strong>という印が付いています。内容を読み終えたら、右下の<strong>「確認しました」</strong>ボタンを押します。</li>
        <li>押すと印が緑色の<strong>「確認済み」</strong>に変わり、確認した日時が記録されます。これで完了です。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        過去の連絡帳をまとめて探したいときは、メニューの<strong>「連絡帳検索」</strong>が便利です。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから<strong>「連絡帳検索」</strong>を開きます。最初は直近1か月分が表示されています。</li>
        <li>上の<strong>「検索・フィルター」</strong>で、期間（開始・終了）、領域、キーワードなどを指定します。お子様が複数いる場合は「お子様」も選べます。</li>
        <li><strong>「検索」</strong>ボタンを押すと、条件に合う連絡帳の一覧と件数が表示されます。条件をやり直すときは<strong>「クリア」</strong>を押します。</li>
        <li>この一覧からも、未確認の連絡帳は<strong>「確認しました」</strong>で確認できます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>補足:</strong> 「確認しました」は、内容を読んだ印です。気になることや質問がある場合は、確認したうえで<strong>「チャット」</strong>からお気軽にスタッフへお伝えください。</p>
      </div>

      {/* 2. アセスメントの記入 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. アセスメントを記入して提出する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「アセスメント」は、家庭でのお子様の様子やご要望をスタッフと共有するための書類です。ここで書いていただいた内容は、次の個別支援計画（目標づくり）の大切なもとになります。半年に一度、提出期限が近づくとお願いのお知らせが届きます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから<strong>「アセスメント入力」</strong>を開きます。</li>
        <li>お子様が複数いる場合は、まず<strong>「お子様を選択」</strong>で対象のお子様を選びます。</li>
        <li><strong>「アセスメント提出期限を選択」</strong>の欄から、記入する回（提出期限の日付）を選びます。「下書き」「提出済み」「未入力」の状態も表示されます。</li>
        <li>入力欄が開きます。次の項目を、書ける範囲で記入してください（すべて埋める必要はありません）。
          <ul className="ml-6 mt-2 list-disc space-y-1">
            <li><strong>本人の願い</strong>（お子様がなりたい姿・望んでいること）</li>
            <li><strong>家庭での願い</strong>（家庭で気になること・取り組みたいこと）</li>
            <li><strong>目標設定</strong>（短期目標＝6か月／長期目標＝1年以上）</li>
            <li><strong>五領域の課題</strong>（健康・生活／運動・感覚／認知・行動／言語・コミュニケーション／人間関係・社会性）</li>
            <li><strong>その他の課題</strong>（そのほかお伝えしたいこと）</li>
          </ul>
        </li>
        <li>途中でやめるときは<strong>「下書き保存」</strong>を押します。内容が保存され、あとから続きを入力できます。</li>
        <li>すべて記入し終えたら<strong>「提出する」</strong>を押します。確認のメッセージが出るので、内容でよければ進めてください。</li>
        <li>提出すると状態が緑色の<strong>「提出済み」</strong>に変わり、提出した日時が記録されます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>注意:</strong> 一度<strong>「提出する」</strong>を押すと、その後は内容を変更できません（「提出すると変更できなくなります」と確認が表示されます）。まだ迷っている項目があるときは、<strong>「下書き保存」</strong>で保存しておき、まとまってから提出してください。提出後に直したいことができた場合は、スタッフへご連絡ください。</p>
      </div>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>補足:</strong> 過去に提出したアセスメントは、画面右上の<strong>「アセスメント履歴」</strong>からいつでも見返せます。</p>
      </div>

      {/* 3. 個別支援計画書の確認・同意 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3. 個別支援計画書を確認して同意する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        個別支援計画書は、お子様一人ひとりの目標と支援内容を定めた大切な書類です。スタッフが計画案を作成すると、保護者の皆様に確認のお願いが届きます。内容をご確認いただき、次のいずれかで意思表示をしてください。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>変更なし（承認）</strong> … 内容に問題がない場合。署名なしで承認します。</li>
        <li><strong>署名して確認</strong> … 内容に問題がなく、あわせて電子署名も行う場合。</li>
        <li><strong>コメントを送る</strong> … 変更してほしい点やご意見がある場合。スタッフへ内容が伝わります。</li>
      </ul>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから<strong>「個別支援計画書」</strong>を開きます。確認が必要な計画があるときは、上部に確認のお願いが表示され、その計画には<strong>「確認待ち」</strong>の印が付いています。</li>
        <li>対象の計画の右上にある<strong>「内容を確認する」</strong>ボタンを押します。<strong>「個別支援計画書の確認」</strong>という画面（別ウィンドウ）が開きます。</li>
        <li>本人の意向・援助の方針・長期目標・短期目標・支援内容などをご確認ください。上下にスクロールすると全体を読めます。</li>
        <li>内容に応じて、次のいずれかの操作を行います（下の手順を参照）。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">内容に問題がないとき（承認・署名）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>署名をしない場合は、そのまま<strong>「変更なし（承認）」</strong>を押します。「承認しました」と表示され、計画に同意したことが記録されます。</li>
        <li>電子署名もする場合は、画面内の白い<strong>署名欄</strong>に署名してから<strong>「署名して確認」</strong>を押します。「確認・署名が完了しました」と表示されます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> 署名は、スマートフォンやタブレットでは<strong>指で直接</strong>、パソコンでは<strong>マウス</strong>で白い枠の中になぞって書きます。書き直したいときは、消してからもう一度書き直せます。署名は任意ですので、署名なしで「変更なし（承認）」を選んでも同意として扱われます。</p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">変更してほしい点があるとき（コメント）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>確認画面で<strong>「コメントを送る」</strong>を押します。コメントの入力欄に切り替わります。</li>
        <li><strong>「変更希望コメント」</strong>の欄に、変更してほしい箇所やご意見を入力します。</li>
        <li><strong>「コメントを送信」</strong>を押すと、「コメントを送信しました」と表示され、内容がスタッフに届きます。</li>
        <li>入力をやめて前の画面に戻りたいときは<strong>「戻る」</strong>を、確認そのものをやめるときは<strong>「キャンセル」</strong>を押します。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        承認・署名・コメントのいずれかを行うと、計画に<strong>「署名済み」「レビュー済み」</strong>などの印が付き、行った日付が記録されます。送信したコメントも計画の画面で確認できます。
      </p>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>補足:</strong> モニタリング表（計画の途中経過を確認する書類）についても、保護者署名をお願いすることがあります。メニューの<strong>「モニタリング表」</strong>から、同じように画面上で署名できます。</p>
      </div>

      {/* 困ったとき */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">困ったとき・ご相談</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        操作でわからないことや、お子様のことで気になることがあれば、いつでもご相談ください。保護者の皆様とスタッフが一緒に、お子様を中心とした支援チームとして歩んでいくためのシステムです。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>ちょっとした相談や質問は、メニューの<strong>「チャット」</strong>からスタッフへ送れます。写真やファイルの添付もできます。</li>
        <li>じっくり話したいときは、メニューの<strong>「面談予約」</strong>から面談を申し込めます。</li>
        <li>急ぎのご連絡や欠席の連絡は、チャットのほか、お電話でも承ります。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> 迷ったら、まず<strong>「ダッシュボード」</strong>に戻ってください。未確認の連絡帳・提出期限が近いアセスメント・確認待ちの計画書などが一覧で表示されるので、そこから必要な画面へ進むと確実です。</p>
      </div>
    </div>
  );
}
