export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        システムの使い方で困ったときや、不具合を見つけたときの連絡方法を説明します。困りごとの種類によって、次の3つの窓口を使い分けてください。まず自分で調べたいときは画面右下の「操作ヘルプ」、不具合を運営に伝えたいときは「バグ報告」、教室内の運用ルールに関することは管理者へ、というのが基本です。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 個人情報保護のため、私有物（個人所有のスマートフォン・PC・タブレット）ではシステムを操作しないでください。お問い合わせやバグ報告も、必ず事業所が貸与・管理する端末から行ってください。
        </p>
      </div>

      {/* 窓口の使い分け */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">相談先の使い分け</h3>
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="bg-[var(--brand-80)] text-white">
              <th className="p-3 text-left">こんなとき</th>
              <th className="p-3 text-left">連絡先</th>
            </tr>
          </thead>
          <tbody>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3">操作方法が分からない・使い方を調べたい</td>
              <td className="p-3">画面右下の「操作ヘルプ」（後述）</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
              <td className="p-3">画面が正しく動かない・エラーが出る（不具合）</td>
              <td className="p-3">左メニュー「バグ報告」からシステム運営へ</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3">パスワードの再設定・アカウントの追加や権限</td>
              <td className="p-3">教室の管理者</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
              <td className="p-3">教室内の運用ルール・記録の書き方など</td>
              <td className="p-3">教室の管理者・責任者</td>
            </tr>
          </tbody>
        </table>
      </div>

      {/* 操作ヘルプ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">画面右下の「操作ヘルプ」で調べる</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        パソコンで利用しているとき、画面の右下に丸い「？（はてなマーク）」のボタンが表示されます。これが「操作ヘルプ」で、よくある質問と答えをその場で調べられます。いま開いている画面に関係するヘルプが自動で表示されるので、まずここを見てみるのが早道です。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>画面右下の丸い「？」ボタンをクリックします。「操作ヘルプ」というパネルが開きます。</li>
        <li>上部の「ヘルプを検索...」の欄に、調べたい言葉（例:「連絡帳」「パスワード」）を入力すると、関係する質問がすぐに絞り込まれます。</li>
        <li>検索を使わない場合は、一覧から知りたいカテゴリ（項目のまとまり）を選びます。</li>
        <li>質問の一覧が出るので、当てはまる質問をクリックすると、答えが表示されます。</li>
        <li>戻りたいときはパネル内の「＜ 質問一覧に戻る」「＜ カテゴリに戻る」を使います。</li>
        <li>閉じるときは、右上の「×」ボタンを押すか、パネルの外側をクリックします（キーボードのEscキーでも閉じられます）。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 「操作ヘルプ」のボタンは、ドラッグ（押したまま動かす）で好きな位置へ移動できます。他のボタンと重なって押しにくいときは、じゃまにならない場所へずらしてください。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「操作ヘルプ」のボタンはパソコン画面向けの機能で、スマートフォンの小さい画面では表示されません。スマートフォンで使い方を調べたいときは、左メニューの「マニュアル」（このページ）をご利用ください。
        </p>
      </div>

      {/* バグ報告 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">不具合を「バグ報告」で伝える</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「エラーが出て先に進めない」「表示がおかしい」「ボタンを押しても反応しない」といった不具合は、左メニューの一番下「サポート」グループにある「バグ報告」からシステム運営へ直接報告できます。報告後は同じ画面でやり取り（返信）ができ、対応の進み具合も確認できます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>不具合が起きた画面で、ブラウザ上部のアドレスバー（URLの欄）を選択してコピーしておくと、あとで貼り付けられて便利です。</li>
        <li>左メニューの「バグ報告」を開きます。</li>
        <li>右上の「新しい報告」ボタンをクリックします。「バグ報告を作成」の入力画面が開きます。</li>
        <li>「発生したページのURL」には、今いる画面のURLが自動で入っています。別の画面の不具合を報告するときは、コピーしておいたURLに書き換えてください。</li>
        <li>「エラー内容」の欄に、<strong>どんな操作をしたら、何が起きたか</strong>を具体的に書きます（例:「送信ボタンを押したら画面が真っ白になった」）。この欄は必ず入力してください。</li>
        <li>必要に応じて「コンソールログ」「スクリーンショット」「重要度」を入力します（次の項目で説明します）。</li>
        <li>最後に「送信」ボタンを押します。「バグ報告を送信しました」と表示されれば完了です。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「エラー内容」と「発生したページのURL」の両方が入力されていないと「送信」ボタンは押せません。何が起きたか一言でも構いませんので、必ず記入してください。
        </p>
      </div>

      {/* 任意の添付情報 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">報告に添える情報（任意）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        次の情報を添えると、運営が原因を特定しやすくなります。難しければ入力しなくても報告は送れます。無理のない範囲で、できるものだけ添えてください。バグ報告の画面上部にある「バグ報告のコツ」を開くと、手順の案内も確認できます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>スクリーンショット</strong>: 「スクリーンショットを添付」を押して、不具合が写った画面の画像を選びます。文章で説明しづらいときは、画像を添えるだけでも十分役立ちます（画像ファイルは5MB以下）。</li>
        <li><strong>コンソールログ</strong>: キーボードの<strong>F12キー</strong>を押すと開発者ツールが開きます。「Console」タブに赤いエラーメッセージが出ていたら、それをコピーして「コンソールログ」の欄に貼り付けてください。分からなければ空欄のままで構いません。</li>
        <li><strong>重要度</strong>: 不具合の深刻さを「低（軽微な問題）／中（通常の不具合）／高（業務に支障あり）／緊急（業務が停止）」から選びます。初期状態は「中」です。</li>
      </ul>

      {/* 報告後のやり取り */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">報告後の確認とやり取り</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        送信した報告は「バグ報告」の一覧に表示されます。状態（ステータス）で対応の進み具合が分かります。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>未対応</strong>: 運営がまだ対応を始めていない状態です。</li>
        <li><strong>対応済み確認依頼中</strong>: 運営が修正し、直っているかどうかの確認をお願いしている状態です。</li>
        <li><strong>解決済み</strong>: 対応が完了した状態です。</li>
      </ul>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>一覧の上部にある「未対応／対応済み確認依頼中／解決済み／すべて」のボタンで、表示する報告を切り替えられます。「並び替え」で新しい順などの表示順も変えられます。</li>
        <li>報告をクリックすると詳細が開き、運営とのやり取り（返信）を確認できます。下の入力欄にメッセージを書いて「送信」を押すと返信できます。</li>
        <li>状態が「対応済み確認依頼中」になったら、実際に直っているか画面で確かめてください。直っていれば「解決済みにする」、まだ直っていなければ「未対応に戻す」を押して再対応を依頼できます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> エラー内容やスクリーンショットには、必要以上に児童・保護者の個人情報が写り込まないようご注意ください。画面の一部だけを撮る、氏名が写らないようにするなど、配慮をお願いします。
        </p>
      </div>

      {/* 管理者への連絡 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">管理者への連絡</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        次のような内容は、システムの不具合ではなく教室ごとの設定・運用に関わるため、教室の管理者へご相談ください。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>パスワードを忘れた場合の再設定（管理者が「スタッフ管理」から再設定できます）。</li>
        <li>スタッフアカウントの追加や、利用できる機能（権限）の変更。</li>
        <li>教室名・ロゴ・休日など、教室の基本設定に関すること。</li>
        <li>記録の書き方や送信のタイミングなど、教室内の運用ルールに関すること。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 迷ったときは、まず画面右下の「操作ヘルプ」で調べ、それでも解決しなければ、不具合は「バグ報告」、運用のことは管理者へ、と切り分けると早く解決できます。
        </p>
      </div>
    </div>
  );
}
