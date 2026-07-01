export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「個別支援計画書」は、お子さまの目標や、事業所でどのような支援を行うかをまとめた大切な書類です。事業所が計画を作成すると、保護者の画面に届き、内容を確認したうえで、承認または電子署名（画面に手書きでサイン）をしていただけます。この画面では、紙のやり取りをせずに、確認・同意・署名までを完結できます。
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        操作はスマートフォン・タブレット・パソコンのいずれでも行えます。指やマウスで画面に署名するため、タブレットやスマートフォンだと書きやすいです。
      </p>

      {/* 画面を開く */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">個別支援計画書の画面を開く</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>きづり（CAREBRIDGE）にログインします。</li>
        <li>メニューから<strong>「個別支援計画書」</strong>を選んで開きます。</li>
        <li>お子さまの計画書が一覧で表示されます。まだ計画書がない場合は「個別支援計画書がありません」と表示されます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>補足:</strong> 確認をお願いしたい計画書があるときは、画面の上のほうに黄色い帯で「確認待ちの個別支援計画書があります。内容をご確認のうえ、承認またはコメントの送信をお願いします。」というお知らせが表示されます。</p>
      </div>

      {/* ステータスの見方 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">計画書の状態（ラベル）の見方</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        各計画書には、お子さまの名前と計画の期間、そして今の状態を示す小さなラベルが付いています。ラベルの意味は次のとおりです。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>確認待ち:</strong> あなたの確認をお待ちしている計画書です。このラベルが付いた計画書には、右上に「内容を確認する」ボタンが表示されます。</li>
        <li><strong>レビュー済み:</strong> あなたが承認、またはコメントの送信を済ませた計画書です。</li>
        <li><strong>署名済み:</strong> あなたが電子署名を済ませた計画書です。</li>
      </ul>

      {/* 内容の確認 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">計画書の内容を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        まずは計画の内容をよくお読みください。一覧のカードの中で、次のような項目が表示されます（事業所が入力した項目のみ表示されます）。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>本人の生活に対する意向</strong> … お子さま本人の希望や思いです。</li>
        <li><strong>総合的な援助の方針</strong> … 事業所全体としての支援の方向性です。</li>
        <li><strong>保護者の願い</strong> … 保護者から事業所に伝えた願いです。</li>
        <li><strong>長期目標・短期目標</strong> … これから目指していく目標です。</li>
        <li><strong>支援内容</strong> … 「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」といった分野ごとに、現状・目標・具体的な支援内容が書かれています。</li>
      </ul>

      {/* 確認画面を開く */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">確認画面を開いて手続きを始める</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>確認したい計画書のカードの右上にある<strong>「内容を確認する」</strong>ボタンを押します。</li>
        <li>「個別支援計画書の確認」という画面（ウィンドウ）が開きます。上のほうに計画の内容がもう一度まとまって表示されるので、スクロールしてすべて読めます。</li>
        <li>内容を読み終えたら、下に用意された3つの進め方から、ご希望の方法を選びます。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        進め方は次の3つです。それぞれの手順は以降で説明します。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>変更なし（承認）</strong> … 署名はせず、内容に問題がないことだけを事業所に伝えます。</li>
        <li><strong>署名して確認</strong> … 署名欄に手書きでサインをして同意します。</li>
        <li><strong>コメントを送る</strong> … 変更してほしい点や質問を文章で事業所に伝えます。</li>
      </ul>

      {/* 承認（署名なし） */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">署名せずに承認する（変更なし）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        内容に問題がなく、署名までは必要ない場合は、承認だけで完了できます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「個別支援計画書の確認」画面で内容を読みます。</li>
        <li><strong>「変更なし（承認）」</strong>ボタンを押します。</li>
        <li>「承認しました」というメッセージが表示され、画面が閉じます。その計画書には「レビュー済み」のラベルが付きます。</li>
      </ol>

      {/* 署名して確認 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">電子署名をして同意する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        計画書に手書きのサイン（電子署名）を残して同意する方法です。画面の白い署名欄に、指やマウスで直接サインします。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「個別支援計画書の確認」画面で内容を読みます。</li>
        <li>画面の中ほどにある<strong>「保護者署名（任意）」</strong>と書かれた白い枠（署名欄）に、指またはマウスで直接サインします。枠内の点線が、書く位置の目安です。</li>
        <li>枠が小さくて書きにくいときは、署名欄を押すか、下の<strong>「大きく書く」</strong>ボタンを押します。画面いっぱいの大きな署名欄が開くので、そこにゆったりサインできます。</li>
        <li>大きな署名欄で書き終えたら、右下の<strong>「署名を適用」</strong>ボタンを押します。書いたサインが元の欄に反映され、大きな画面が閉じます。</li>
        <li>書き間違えたときは、<strong>「戻す」</strong>で直前の一画（ひと筆）を取り消し、<strong>「クリア」</strong>ですべて消してやり直せます。</li>
        <li>サインが記入できると、署名欄の下に緑色の<strong>「署名済み」</strong>という表示が出ます（記入前は「未署名」と表示されます）。</li>
        <li>サインを確認したら、画面右下の<strong>「署名して確認」</strong>ボタンを押します。</li>
        <li>「確認・署名が完了しました」というメッセージが表示され、画面が閉じます。その計画書には「署名済み」のラベルが付き、署名した日付とともにサインが記録されます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>注意:</strong> 署名欄が空のまま「署名して確認」を押すと、「署名を記入してください」というメッセージが出て先に進めません。必ず署名欄にサインをしてから「署名して確認」を押してください。</p>
      </div>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> 署名欄には「（任意）」と書かれていますが、電子署名で同意する場合はサインの記入が必要です。署名までは希望されない場合は、代わりに「変更なし（承認）」を使ってください。</p>
      </div>

      {/* コメントを送る */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">変更してほしい点やコメントを送る</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        内容について直してほしい点や質問がある場合は、承認や署名をする前に、文章で事業所に伝えられます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「個別支援計画書の確認」画面で、左下の<strong>「コメントを送る」</strong>ボタンを押します。</li>
        <li>コメントの入力画面に切り替わります。<strong>「変更希望コメント」</strong>の入力欄に、直してほしい点や質問を入力します。</li>
        <li>入力できたら<strong>「コメントを送信」</strong>ボタンを押します。</li>
        <li>「コメントを送信しました」というメッセージが表示され、画面が閉じます。送ったコメントは、その計画書のカードにオレンジ色で表示され、送信した日付も記録されます。</li>
      </ol>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>コメントの入力をやめて前の画面に戻りたいときは、<strong>「戻る」</strong>ボタンを押します。</li>
        <li>コメント欄が空のままだと「コメントを送信」は押せません（「コメントを入力してください」と表示されます）。何か入力してから送信してください。</li>
      </ul>

      {/* 手続きをやめる */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">手続きを途中でやめたいとき</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        まだ承認・署名・コメント送信をしていない状態であれば、いつでも手続きを中断できます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>「キャンセル」</strong>ボタンを押すと、確認画面が閉じ、何も記録されずに元の一覧に戻ります。あとから改めて「内容を確認する」ボタンで再開できます。</li>
      </ul>

      {/* 署名や状態の確認 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">手続き後の状態を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        承認・署名・コメントを済ませると、計画書のカードに結果が表示されます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>署名した場合:</strong> カードにあなたのサインが画像で表示され、「（日付）に署名済み」と表示されます。事業所側の署名（職員署名）が入っている場合は、あわせて表示されます。</li>
        <li><strong>承認した場合:</strong> 「（日付）にレビュー済み」と表示されます。</li>
        <li><strong>コメントを送った場合:</strong> 送ったコメントの内容と送信日が表示されます。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> 承認・署名・コメントは、それぞれ完了メッセージ（「承認しました」「確認・署名が完了しました」「コメントを送信しました」）が表示されて初めて記録されます。手続きが終わったら、計画書のカードに「署名済み」「レビュー済み」などのラベルや、署名画像・コメントが表示されているかを確認すると安心です。</p>
      </div>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>補足:</strong> 内容についてわからない点や不安なことがあれば、無理に承認・署名をせず、「コメントを送る」で質問したり、事業所に直接お問い合わせいただいたりして構いません。</p>
      </div>
    </div>
  );
}
