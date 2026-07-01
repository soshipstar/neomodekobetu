export default function ManualSection() {
  return (
    <div className="space-y-4">
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        連絡帳は、お子様が施設で過ごした1日の様子や活動の記録を、施設のスタッフが写真といっしょにお届けするページです。
        毎日の記録を読んで、内容を確認したら「確認しました」ボタンを押していただくことで、施設側に「保護者の方が読んでくれた」ことが伝わります。
        このページでは、連絡帳の開き方・活動記録の見方・「確認しました」ボタンでの確認（既読）のしかたを、順番にご案内します。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>連絡帳のメニューは2つあります:</strong> 日付を選んでその日の連絡帳を読む「連絡帳一覧」と、過去の記録をまとめて探せる「連絡帳検索」です。
          ふだんは「連絡帳一覧」を使い、以前の記録をさかのぼって見たいときに「連絡帳検索」を使うと便利です。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. 連絡帳を開く（連絡帳一覧）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        まずは、いちばんよく使う「連絡帳一覧」を開きます。画面の左側にあるメニュー（スマートフォンでは画面下のメニュー）から「連絡帳一覧」を選びます。
        開くと、選んだ日付の連絡帳が表示されます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから「連絡帳一覧」を押します。</li>
        <li>画面の上に日付が表示されます。最初は今日の日付になっています。</li>
        <li>別の日を見たいときは、日付の左右にある矢印（「‹」で前の日、「›」で次の日）を押します。</li>
        <li>日付が書かれた入力欄を押すと、カレンダーから直接見たい日を選ぶこともできます。</li>
        <li>今日の連絡帳に戻りたいときは、右側の「今日」ボタンを押します。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 連絡帳は、施設のスタッフがその日の記録を作成して送信すると表示されます。
          選んだ日にまだ連絡帳が届いていない場合は、「この日の連絡帳はありません」と表示されます。夕方や施設からの送信後に、あらためて開いてみてください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. 活動記録の見方</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        その日の連絡帳が届いていると、「〇〇（お子様のお名前） の連絡帳」というカードが表示されます。
        カードには、その日の活動やお子様の様子が書かれています。お子様が複数いらっしゃる場合は、お子様ごとにカードが分かれて表示されます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>確認済み／未確認のマーク:</strong> カードの右上に表示されます。「未確認」はまだ確認ボタンを押していない連絡帳、「確認済み」は確認が済んだ連絡帳です。</li>
        <li><strong>活動名:</strong> その日にどんな活動をしたか（例: おでかけ、工作 など）が書かれています。</li>
        <li><strong>送信の時刻:</strong> 施設が連絡帳を送った時刻が表示されます。</li>
        <li><strong>本文:</strong> その日のお子様の様子や、活動でのできごとが文章で書かれています。</li>
        <li><strong>写真:</strong> 活動の写真がある場合は、本文の下に並んで表示されます。写真を押すと、大きく表示して見ることができます。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 写真を大きく表示したあとは、画面の閉じるボタン（×）を押すと、もとの連絡帳の画面に戻れます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3.「確認しました」ボタンで確認する（既読）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        連絡帳の内容を読み終えたら、いちばん下にある「確認しました」ボタンを押してください。
        このボタンを押すと、施設側に「保護者の方が連絡帳を読んでくれた」ことが伝わります。
        まだ確認していない連絡帳には、右上に「未確認」のマークが付いています。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>連絡帳の内容と写真をひととおり読みます。</li>
        <li>カードのいちばん下にある「確認しました」ボタンを押します。</li>
        <li>「確認しました」と表示され、右上のマークが「確認済み」に変わります。カードの下には、確認した日付と時刻が表示されます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> いったん「確認済み」になった連絡帳の内容は、いつでも読み返すことができます。
          確認しても文章や写真が消えることはありません。「確認しました」は、あくまで「読みました」という合図です。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">4. 過去の連絡帳を探す（連絡帳検索）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        以前の連絡帳をまとめて見たいときや、特定の期間・キーワードで探したいときは、メニューから「連絡帳検索」を開きます。
        この画面では、最初は直近1か月分の連絡帳が新しい順に表示されます。それより前の記録を見たいときは、画面の上にある検索欄で条件を指定します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから「連絡帳検索」を押します。</li>
        <li>お子様が複数いる場合は、「お子様」欄で見たいお子様を選べます（「すべて」を選ぶと全員分が表示されます）。</li>
        <li>見たい期間を「期間（開始）」と「期間（終了）」の日付欄で指定します。</li>
        <li>特定のテーマにしぼりたいときは、「領域」欄から選びます（健康・生活／運動・感覚／認知・行動／言語・コミュニケーション／人間関係・社会性）。</li>
        <li>言葉で探したいときは、「キーワード」欄に活動内容や様子の言葉を入力します。</li>
        <li>条件を入れたら、右下の「検索」ボタンを押します。条件をやり直したいときは「クリア」ボタンで消せます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「連絡帳検索」の画面でも、「確認しました」ボタンで確認できます。
          この画面では、未確認の連絡帳はカードの左側のふちがオレンジ色、確認済みは緑色で表示されるので、ひと目で見分けられます。
          「確認しました」ボタンを押すと、「この連絡帳を『確認しました』にしてよろしいですか？」という確認が出るので、「OK」を押すと確認が完了します。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 「連絡帳検索」では、記録の件数や、支援の領域ごとの記録数をまとめた「統計情報」も見られます。
          お子様がどんな活動をどれくらい経験しているか、全体の様子を知りたいときの参考にしてください。
        </p>
      </div>
    </div>
  );
}
