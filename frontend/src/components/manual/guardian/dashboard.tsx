export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        ログインすると、最初に「<strong>連絡帳ダッシュボード</strong>」という画面が表示されます。この画面は、いわば「今日のお知らせ掲示板」です。お子様の連絡帳の未確認や、園・教室からの通知、面談やアンケートのお願いなど、<strong>あなたに確認していただきたいことがひと目でわかる</strong>ようになっています。まずはこの画面を開く習慣をつけると安心です。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> ダッシュボードには、あなたに登録されている<strong>すべてのお子様の情報がまとめて表示</strong>されます。お子様が複数いる場合でも、切り替え操作をしなくても、この1画面で全員分を確認できます。
        </p>
      </div>

      {/* 画面の開き方 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">ダッシュボードを開く</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>ログインすると、自動的に「連絡帳ダッシュボード」が開きます。</li>
        <li>ほかの画面を見たあとにダッシュボードへ戻りたいときは、画面下のメニュー(スマートフォン)の「<strong>ホーム</strong>」を押すか、左側のメニュー一覧から「<strong>ダッシュボード</strong>」を選びます。</li>
        <li>スマートフォンでメニューが見当たらないときは、画面左上の三本線のボタン(メニューボタン)を押すと、メニュー一覧が開きます。</li>
      </ol>

      {/* 画面の構成 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">画面に表示されるもの</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        ダッシュボードは、上から順に次のようなまとまり(セクション)で構成されています。確認が必要なことがない場合は、表示されないまとまりもあります。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>確認が必要な項目</strong> … あなたに対応をお願いしたいことの一覧です。何もない場合は表示されません。</li>
        <li><strong>カレンダー</strong> … その月の活動予定日・欠席・連絡帳・面談・イベントなどが日付ごとに表示されます。</li>
        <li><strong>お子様ごとの連絡帳</strong> … お子様ごとに、まだ確認していない連絡帳が表示されます。</li>
        <li><strong>クイックリンク</strong> … よく使うページ(チャット・面談・連絡帳など)へすぐに移動できるボタンです。</li>
      </ul>

      {/* 通知ベル */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">通知(ベルのマーク)を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        画面の右上に、ベルのマークがあります。新しいお知らせがあると、ベルの右上に赤い丸で件数が表示されます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>画面右上の<strong>ベルのマーク</strong>を押します。</li>
        <li>最近の通知が一覧で表示されます。読みたい通知を押すと、その内容のページへ移動できます。</li>
        <li>すべてまとめて既読にしたいときは、一覧の右上にある「<strong>すべて既読にする</strong>」を押します。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> ベルの赤い数字は「まだ見ていない通知の数」です。通知を押して開くと、その通知は自動的に「既読(確認済み)」になり、数字が減ります。
        </p>
      </div>

      {/* 確認が必要な項目 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">「確認が必要な項目」の見方</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        対応をお願いしたいことがあるとき、画面の上のほうに「確認が必要な項目」というまとまりが表示されます。それぞれの項目には、内容と件数、そして移動用のリンク(青い文字)が表示されています。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「確認が必要な項目」の中から、対応したい内容を探します。</li>
        <li>その項目の下にある青い文字のリンク(例:「チャットを開く」「確認する」「回答する」など)を押します。</li>
        <li>該当するページが開きますので、内容を確認して手続きを進めてください。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        ここに表示されることがある主な項目は、次のとおりです。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>未読チャットメッセージ</strong> … 教室からのまだ読んでいないメッセージがあります。</li>
        <li><strong>個別支援計画書</strong> … 計画書案の確認や、正式版への署名のお願いがあります。</li>
        <li><strong>モニタリング表</strong> … 支援の振り返り記録の確認や署名のお願いがあります。</li>
        <li><strong>スタッフからのアセスメント</strong> … 職員が作成した記録の確認待ちがあります。</li>
        <li><strong>アセスメントの提出</strong> … あなたに入力・提出をお願いしているアセスメントがあります。期限も表示されます。</li>
        <li><strong>提出物</strong> … 提出をお願いしている書類などがあります。</li>
        <li><strong>面談予約</strong> … 面談の日程についての回答待ちがあります。</li>
        <li><strong>確定済み面談</strong> … 日時が決まっている面談の予定です。</li>
        <li><strong>事業所評価アンケート</strong> … アンケートへの回答のお願いがあります。</li>
        <li><strong>未確認連絡帳</strong> … まだ「確認しました」を押していない連絡帳があります。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> アセスメントや提出物、アンケートなどには<strong>提出期限</strong>があります。期限が近い項目や過ぎている項目は、赤やオレンジの文字で強調して表示されます。早めのご対応をお願いします。
        </p>
      </div>

      {/* お子様ごとの連絡帳 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">お子様の連絡帳を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        画面の下のほうに、お子様ごとのカードが表示されます。名前の頭文字と学年(小学生・中学生・高校生)が表示され、その下に、まだ確認していない連絡帳が並びます。確認が必要な連絡帳がない場合は「確認が必要な連絡帳はありません」と表示されます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>読みたい連絡帳のカード(活動名・日付・内容が書かれた四角い枠)を押します。</li>
        <li>連絡帳の詳しい内容が、画面の中央に大きく開きます(写真がある場合は写真も表示されます)。</li>
        <li>写真を押すと、大きく拡大して見ることができます。</li>
        <li>内容を確認したら、右下の「<strong>確認しました</strong>」ボタンを押してください。</li>
        <li>「確認しました」を押すと、その連絡帳は「確認済み」になり、確認した日時が記録されます。</li>
        <li>開いた画面を閉じるときは、右上の「×(バツ)」を押すか、画面の外側(暗くなっている部分)を押します。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 過去の連絡帳をすべて見たいときは、お子様のカードの右上にある「<strong>すべての連絡帳を見る</strong>」を押すと、連絡帳の一覧ページへ移動できます。
        </p>
      </div>

      {/* カレンダー */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">カレンダーで予定を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        カレンダーには、その月のお子様の活動予定日や欠席、連絡帳の有無、面談、イベントなどが日付ごとに表示されます。今日の日付は緑色の枠で囲まれています。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>別の月を見たいときは、カレンダー右上の左向き(<strong>&lt;</strong>)・右向き(<strong>&gt;</strong>)の矢印ボタンを押します。</li>
        <li>今月に戻したいときは、「<strong>今月</strong>」ボタンを押します。</li>
        <li>カレンダー内の<strong>イベント</strong>や<strong>面談</strong>の表示を押すと、詳しい内容が開きます。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        カレンダーの下には、色やマークの意味を説明した凡例(はんれい)が表示されています。主なマークの意味は次のとおりです。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>活動予定日</strong> … お子様がこれから通う予定の日です。</li>
        <li><strong>連絡帳(未確認)</strong> … 連絡帳が届いていて、まだ確認していない日です(赤色で表示)。</li>
        <li><strong>連絡帳(確認済み)</strong> … すでに確認した連絡帳がある日です(緑色で表示)。</li>
        <li><strong>欠席日</strong> … 欠席として登録されている日です。</li>
        <li><strong>振替活動日 / 追加利用</strong> … 通常とは別に利用が予定・登録されている日です。</li>
        <li><strong>面談予定</strong> … 面談が予定されている日です。</li>
      </ul>

      {/* クイックリンク */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">クイックリンクからすぐ移動する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        画面のいちばん下に、よく使うページへのボタン(クイックリンク)が並んでいます。押すだけで、その画面へすぐに移動できます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>チャット</strong> … 教室とのメッセージのやりとり</li>
        <li><strong>個別支援計画</strong> … お子様の支援計画書の確認</li>
        <li><strong>面談</strong> … 面談の予約・確認</li>
        <li><strong>事業所評価</strong> … アンケートへの回答</li>
        <li><strong>アセスメント</strong> … アセスメントの入力・提出</li>
        <li><strong>連絡帳</strong> … 連絡帳の一覧</li>
        <li><strong>モニタリング</strong> … モニタリング表の確認</li>
        <li><strong>欠席連絡</strong> … お休みの連絡</li>
      </ul>

      {/* うまく表示されないとき */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">うまく表示されないとき</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「ダッシュボードの読み込みに失敗しました」と表示された場合は、画面を<strong>再読み込み(更新)</strong>してみてください。それでも直らないときは、しばらく待ってから開き直してください。</li>
        <li>「お子様の情報が登録されていません」と表示された場合は、まだお子様の登録が済んでいない可能性があります。教室(事業所)にお問い合わせください。</li>
      </ul>
    </div>
  );
}
