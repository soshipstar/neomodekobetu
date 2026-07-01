export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        きづりでは、お子様の「個別支援計画書」や「モニタリング表」の内容をご確認いただいたしるしとして、<strong>電子署名</strong>をお願いすることがあります。電子署名とは、紙に手書きで署名する代わりに、スマートフォン・タブレット・パソコンの画面上に直接お名前を手書きしていただくものです。書類を郵送したり来所したりしなくても、ご自宅から画面の中で確認と署名を済ませられます。
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        画面上で署名していただいた内容は、紙の署名と同じように「保護者の方が内容を確認し、同意した」ことを示す大切な記録として保存されます。署名した日付とあわせて残りますので、いつ・どなたが確認したかがわかるようになっています。
      </p>

      {/* 署名をお願いする書類 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">署名をお願いする書類</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        署名をお願いするのは、主に次の2つの書類です。書類によって少し操作が違うため、あとの章で書類ごとに手順を分けてご案内します。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>個別支援計画書</strong>（「個別支援計画書」の画面）… お子様の支援の目標や内容をまとめた書類です。内容を確認して署名します。</li>
        <li><strong>モニタリング表</strong>（「モニタリング表」の画面）… 支援がどこまで進んだかを確認する書類です。内容を確認して署名します。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> 署名や確認が必要な書類があるときは、ログイン後の最初の画面（ダッシュボード）に「〇件の署名待ちがあります」「〇件の確認待ちがあります」といったお知らせが表示されます。そのお知らせの中の<strong>「確認する」</strong>を押すと、その書類の画面へ移動できます。</p>
      </div>

      {/* 署名欄の基本操作 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">署名欄（お名前を書く枠）の基本操作</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        どちらの書類でも、署名は画面に出てくる白い枠の中に手書きします。まずこの共通の書き方を覚えておくと、あとの手順がスムーズです。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>白い署名欄の中に、スマートフォンやタブレットでは<strong>指</strong>で、パソコンでは<strong>マウス</strong>でなぞって、お名前を書きます。枠の中に薄い点線が引かれているので、その線を目安に書くとバランスよく書けます。</li>
        <li>枠が小さくて書きにくいときは、署名欄そのものを押すか、下にある<strong>「大きく書く」</strong>ボタンを押します。画面いっぱいの大きな枠が開くので、そこにゆったり署名できます。</li>
        <li>大きな枠には「点線の上に署名してください」と表示されます。書き終えたら、右下の<strong>「署名を適用」</strong>ボタンを押します。書いた署名が元の欄に反映され、大きな枠は閉じます。</li>
        <li>書き間違えたときは、<strong>「戻す」</strong>で直前に書いた一画（一筆）だけを消せます。全部消してやり直したいときは<strong>「クリア」</strong>を押します。大きな枠の中にも同じボタンがあります。</li>
        <li>署名が書けると、枠の近くに緑色の<strong>「署名済み」</strong>という表示が出ます。まだ何も書いていないときは<strong>「未署名」</strong>と表示されます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> うまく書けなかったときも、何度でも「クリア」で消してやり直せます。焦らず、読みやすい大きさで書いてください。</p>
      </div>

      {/* 個別支援計画書に署名する */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">個別支援計画書を確認して署名する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        事業所が計画書案を用意すると、確認のお願いが届きます。内容を読んで、問題がなければ署名します。署名は次の手順で行います。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから<strong>「個別支援計画書」</strong>の画面を開きます。ダッシュボードのお知らせから<strong>「確認する」</strong>を押しても開けます。</li>
        <li>確認が必要な計画書には、オレンジ色の<strong>「確認待ち」</strong>の目印が付いています。その計画書の右上にある<strong>「内容を確認する」</strong>ボタンを押します。</li>
        <li><strong>「個別支援計画書の確認」</strong>という画面（確認用の小窓）が開きます。長期目標・短期目標・支援内容などが表示されるので、スクロールして最後まで内容をご確認ください。</li>
        <li>内容を確認したら、下のほうにある<strong>「保護者署名（任意）」</strong>の白い枠に、前の章のやり方で署名します。</li>
        <li>署名を書いたら、<strong>「署名して確認」</strong>ボタンを押します。<strong>「確認・署名が完了しました」</strong>と表示され、計画書に<strong>「署名済み」</strong>の目印が付きます。これで署名は完了です。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>注意:</strong> 署名欄が空のまま「署名して確認」を押すと、<strong>「署名を記入してください」</strong>と表示され、そのままでは進めません。先に署名欄にお名前を書いてから押してください。</p>
      </div>

      {/* 署名せずに承認・コメント */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">署名の代わりにできること（承認・コメント）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        個別支援計画書の確認画面では、署名のほかに次の2つの方法も選べます。ご都合や希望に合わせてお使いください。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>変更なし（承認）</strong>… 内容に問題がなく、署名は書かずに「これで良い」とお伝えしたいときに押します。押すと<strong>「承認しました」</strong>と表示され、計画書に「レビュー済み」の目印が付きます。</li>
        <li><strong>コメントを送る</strong>… 内容に直してほしいところや質問があるときに押します。押すとコメントを書く欄が開くので、希望する内容を入力し、<strong>「コメントを送信」</strong>を押します。<strong>「コメントを送信しました」</strong>と表示され、事業所に伝わります。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>補足:</strong> コメントを書く画面で内容の入力欄が空のまま「コメントを送信」を押すと、<strong>「コメントを入力してください」</strong>と表示されます。伝えたい内容を入力してから送信してください。書く前の画面に戻りたいときは<strong>「戻る」</strong>を押します。</p>
      </div>

      {/* モニタリング表に署名する */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">モニタリング表を確認して署名する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        モニタリング表も、事業所が提出すると確認のお願いが届きます。内容を確認して署名する手順は次のとおりです。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから<strong>「モニタリング表」</strong>の画面を開きます。ダッシュボードのお知らせから<strong>「確認する」</strong>を押しても開けます。</li>
        <li>お子様が複数いる場合は、画面上の選択欄で対象のお子様を選びます。</li>
        <li>確認が必要なモニタリング表には<strong>「確認待ち」</strong>の目印が付いています。その行を押すと、達成状況やコメントなどの内容が開きます。スクロールして最後まで内容をご確認ください。</li>
        <li>内容を確認したら、開いた内容の一番下にある<strong>「署名して確認」</strong>ボタンを押します。</li>
        <li><strong>「モニタリング表の確認」</strong>という画面（確認用の小窓）が開きます。<strong>「保護者署名」</strong>の白い枠に、前の章のやり方で署名します。</li>
        <li>署名欄の下にある<strong>「署名日」</strong>を確認します。最初はその日の日付が入っています。別の日にしたい場合は日付を選び直せます。</li>
        <li>署名を書いたら、<strong>「署名して確認」</strong>ボタンを押します。<strong>「署名が完了しました」</strong>と表示され、モニタリング表に<strong>「確認済み」</strong>の目印が付きます。これで完了です。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>注意:</strong> 署名欄が空のまま「署名して確認」を押すと、<strong>「署名を入力してください」</strong>と表示されます。先に署名欄にお名前を書いてから押してください。まだ署名したくないときは<strong>「キャンセル」</strong>を押すと、署名せずに画面を閉じられます。</p>
      </div>

      {/* 署名した内容の確認 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">署名した内容を後から確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        一度署名した書類は、いつでも画面上で見返せます。ご自身の署名が正しく残っているか確認できます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>個別支援計画書:</strong> 署名済みの計画書には緑色の<strong>「署名済み」</strong>の目印が付き、書類の下のほうに職員の署名とご自身の署名が画像で表示されます。署名した日付もあわせて表示されます。</li>
        <li><strong>モニタリング表:</strong> 確認済みのモニタリング表には<strong>「確認済み」</strong>の目印が付きます。行を開くと、<strong>「電子署名」</strong>の見出しの下に職員の署名とご自身の署名が画像で表示され、確認した日時もわかります。</li>
      </ul>

      {/* 法的な意味 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">電子署名の意味について</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        画面上で行う電子署名は、紙の書類に手書きで署名するのと同じように、<strong>「保護者の方が書類の内容を確認し、同意した」ことを示す記録</strong>になります。あわてて署名する必要はありませんので、必ず内容をよく読んでから署名してください。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>内容にわからない点や気になる点があるときは、署名する前に事業所へご相談ください。個別支援計画書の場合は<strong>「コメントを送る」</strong>から質問や変更希望を伝えることもできます。</li>
        <li>署名した書類は、事業所が支援を行ううえでの正式な記録として保存されます。</li>
        <li>誤って署名してしまった、内容を直してほしいといった場合は、事業所にご連絡ください。事業所側で計画書やモニタリング表を修正できます。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> 署名は「署名して確認」を押して<strong>「署名が完了しました」</strong>（計画書の場合は<strong>「確認・署名が完了しました」</strong>）と表示されて初めて記録されます。ボタンを押したあとにこのお知らせと「署名済み」「確認済み」の目印が出ていれば、正しく署名できています。</p>
      </div>
    </div>
  );
}
