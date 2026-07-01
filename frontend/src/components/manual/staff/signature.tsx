export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        きづりでは、個別支援計画やモニタリング表について、保護者の同意・署名を紙に頼らず画面上で受け取れます。指導員がタブレットやパソコンの画面に手書きで署名したり、保護者に確認・署名を依頼したりできるため、書類のやり取りを画面上で完結できます。
      </p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        署名を扱う書類は主に次の2つです。それぞれ操作の流れが少し異なるため、書類ごとに手順を分けて説明します。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>個別支援計画</strong>（「個別支援計画」画面）… 職員署名と保護者署名の両方を扱います。保護者に確認・署名を依頼する流れがあります。</li>
        <li><strong>モニタリング表</strong>（「モニタリング」画面）… 職員が署名し、保護者署名は保護者の画面から受け取ります。</li>
      </ul>

      {/* 署名パッドの基本操作 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">署名欄（署名パッド）の基本操作</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        どちらの書類でも、署名は白い枠（署名パッド）に手書きで記入します。まずこの共通の操作を覚えておくと、以降の手順がスムーズです。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>白い署名欄に、マウスや指（タブレットの場合）で直接なぞって署名します。枠内の点線が記入位置の目安です。</li>
        <li>枠が小さくて書きにくいときは、署名欄をクリックするか、下にある<strong>「大きく書く」</strong>ボタンを押します。画面いっぱいの拡大画面が開くので、そこに署名してください。</li>
        <li>拡大画面で書き終えたら、右下の<strong>「署名を適用」</strong>ボタンを押します。書いた署名が元の欄に反映され、拡大画面が閉じます。</li>
        <li>書き損じたときは<strong>「戻す」</strong>で直前の一画を取り消し、<strong>「クリア」</strong>で全部消してやり直せます（拡大画面内にも同じボタンがあります）。</li>
        <li>署名が記入されると、欄の下に緑色の<strong>「署名済み」</strong>の表示が出ます。まだのときは「未署名」と表示されます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> タブレットで保護者に署名してもらうときは、「大きく書く」で拡大画面を開いてから渡すと、広い枠でゆったり署名でき、書き間違いが減ります。</p>
      </div>

      {/* 個別支援計画の署名 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">個別支援計画に署名する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        個別支援計画の署名欄は、計画の編集画面の一番下にある<strong>「D. 同意・署名」</strong>の中にあります。ここで管理責任者名・同意日を入力し、職員署名・保護者署名を記入します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから「個別支援計画」画面を開き、署名したい計画を一覧から選んで編集画面を開きます。</li>
        <li>編集画面を一番下までスクロールし、<strong>「D. 同意・署名」</strong>の見出しを開きます。</li>
        <li><strong>「管理責任者名」</strong>に児童発達支援管理責任者の氏名を入力します。</li>
        <li><strong>「同意日」</strong>に保護者の同意を得た日付を入力します。</li>
        <li><strong>「職員署名」</strong>の欄に、担当職員（管理責任者）が署名します。</li>
        <li>その場で保護者から署名をもらう場合は、<strong>「保護者署名」</strong>の欄に保護者が署名します。後日、保護者の画面から署名してもらう場合は空のままで構いません。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">保護者に確認・署名を依頼する（画面上でやり取りする場合）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        その場で署名をもらわず、保護者に内容を確認してもらってから画面上で署名してもらう流れです。計画は「下書き」→「確認依頼中」→「署名済み」の順で進みます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>計画の内容（目標・支援内容など）を入力し終えたら、画面下部の<strong>「下書き保存」</strong>で一度保存します。</li>
        <li>画面下のボタンから<strong>「確認依頼」</strong>を押します。計画が保護者に確認をお願いする状態（確認依頼中）になり、「計画書案として提出しました」と表示されます。</li>
        <li>保護者は自分の画面で計画内容を確認し、署名します。保護者が署名すると、計画に保護者署名が反映されます。</li>
        <li>保護者からの署名やコメントが届いたら、計画を開いて内容を確認します。職員署名がまだの場合は「職員署名」欄に署名します。</li>
        <li>最後に、画面下の<strong>「署名して確定」</strong>を押します。「署名を保存しました」と表示され、計画が<strong>「署名済み（正式版）」</strong>になります。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>補足:</strong> 「確認依頼」ボタンは、まだ職員署名が記入されていない計画にだけ表示されます。すでに職員が署名した計画では表示されません。</p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">職員署名だけで確定する（その場で署名をもらった場合）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        面談の場でタブレットに直接署名してもらうなど、保護者への依頼を挟まずに確定する場合の流れです。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「D. 同意・署名」で管理責任者名・同意日を入力します。</li>
        <li>「職員署名」欄に職員が署名します（管理責任者名の入力も必須です）。</li>
        <li>その場で保護者からもらう場合は「保護者署名」欄にも署名してもらいます。</li>
        <li>画面下の<strong>「署名して確定」</strong>を押します。計画が「署名済み（正式版）」になります。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>注意:</strong> 職員署名が記入されていない、または管理責任者名が空欄のままだと「署名して確定」はできず、記入をうながすメッセージが表示されます。先に職員署名と管理責任者名を入力してください。</p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">紙で署名をもらった場合の扱い</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        すでに紙に印刷して署名・押印をもらった計画は、画面上で署名し直す必要はありません。画面上では「紙面で署名済み」として確定できます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>該当の計画を開き、長期目標・短期目標・支援内容のいずれかが入力されていることを確認します。</li>
        <li>画面下の<strong>「紙面でサイン済み」</strong>ボタンを押します。</li>
        <li>確認メッセージが表示されるので、内容を確認して進めます。下書き状態が解除され、正式な計画書として扱われます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>注意:</strong> 中身が空のまま「紙面でサイン済み」で確定すると、そのままでは編集できなくなります。必ず目標や支援内容を入力してから確定してください。</p>
      </div>

      {/* 確定後の修正 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">確定した計画を修正したいとき</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        署名して確定（正式版）にした計画は、そのままでは閲覧のみで編集できません（画面上部に「この計画は署名済みのため、編集できません」と表示されます）。誤って確定した場合や内容を直したい場合は、下書きに戻します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>確定済みの計画を開きます。</li>
        <li>画面右下（下部のボタン付近）の<strong>「下書きに戻す」</strong>を押します。</li>
        <li>確認メッセージで進めると、署名情報・確定状態がクリアされ、再び編集できるようになります。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>注意:</strong> 「下書きに戻す」を行うと、それまでに記入された署名情報は消えます。もう一度署名をもらい直す必要があるため、修正が必要なときだけ使ってください。</p>
      </div>

      {/* モニタリング表の署名 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">モニタリング表に署名する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        モニタリング表では、職員が画面上で署名します。保護者署名は、モニタリング表を提出したあと保護者の画面から受け取ります。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから「モニタリング」画面を開き、対象の児童と計画書を選びます。</li>
        <li>達成状況やコメントなど、必要な項目を入力します。</li>
        <li>画面下部の<strong>「電子署名」</strong>の見出しまでスクロールします。</li>
        <li>左側の<strong>「職員署名」</strong>の欄に、担当職員が署名します。</li>
        <li>「職員署名」欄の下にある<strong>「署名日」</strong>に、署名した日付を入力します（初期状態では当日の日付が入っています）。</li>
        <li>右側の<strong>「保護者署名」</strong>は、この画面では記入できません。「保護者からの署名待ち」と表示され、モニタリング表を提出後に保護者の画面から署名してもらいます。</li>
        <li>入力と職員署名が済んだら、画面下の保存・提出ボタンで保存します。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>補足:</strong> 提出済み（正式版）になったモニタリング表は、画面上部に緑色の帯が表示され、内容の編集ができなくなります。修正したいときは、確定前の状態で内容を整えてから提出してください。</p>
      </div>

      {/* 署名の確認 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">保存された署名を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        保存された署名は、書類の画面上で画像として確認できます。誰がいつ署名したかもあわせて確認しましょう。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>個別支援計画:</strong> 署名済みの計画では、職員署名・保護者署名が署名欄に画像で表示されます。印刷・プレビュー画面では、同意日・児童発達支援管理責任者の署名・保護者署名がまとまって表示されます。</li>
        <li><strong>モニタリング表:</strong> 「電子署名」欄に、保存済みの職員署名（署名者名つき）と、保護者署名（保護者署名日つき）が画像で表示されます。保護者がまだ署名していない場合は「保護者からの署名待ち」と表示されます。</li>
      </ul>

      {/* 全体の注意 */}
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>ヒント:</strong> 署名は「署名して確定」や保存を行うまで正式には記録されません。署名欄に記入したら、必ず画面下のボタンで保存・確定まで行ってください。ブラウザを閉じたり別の書類に移動したりする前に、緑色の「署名済み」表示と保存完了のメッセージを確認すると安心です。</p>
      </div>
    </div>
  );
}
