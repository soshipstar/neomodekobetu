export default function ManualSection() {
  return (
    <div className="space-y-4">
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        アセスメントは、半期（6か月）ごとにお子様の様子を振り返り、職員と保護者の双方が記入し合う書類です。ここで整理した内容は、個別支援計画やモニタリングを作成するときの土台になります。この画面では、<strong>アセスメント期間の確認 → 職員による記入（AI生成の活用） → 保護者への記入依頼 → 保護者記入の確認 → 内容の確定</strong>までの流れを操作できます。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> アセスメント期間（対象期間と提出期限）は、生徒の支援開始日をもとにシステムが自動で作成します。職員が期間を手動で作る操作はありません。次の期間は、提出期限のおよそ1か月前に自動で追加されます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. アセスメントの画面を開く</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        職員が記入する画面は「アセスメント（職員）」です。次のいずれかの方法で開けます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「アセスメント（職員）」から開く</li>
        <li>生徒の詳細ページにある「アセスメント」から、対象生徒のアセスメント一覧を開く</li>
        <li>ダッシュボードや「対応が必要なタスク（やることリスト）」の案内から、該当の生徒・期間を直接開く</li>
      </ul>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. 生徒とアセスメント期間を選ぶ</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「生徒を選択」の一覧から、記入したいお子様を選びます。</li>
        <li>選ぶと、その生徒のアセスメント期間が一覧（開閉できる見出し）で表示されます。</li>
        <li>各期間の見出しには、期間名・対象期間の日付に加えて、記入状態のバッジ（<strong>未入力／下書き／提出済み</strong>）と、提出期限のバッジ（期限を過ぎている場合は赤色）が表示されます。</li>
        <li>記入したい期間の見出しをクリックすると、その期間の内容が開きます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 期間が1つも表示されない場合は「アセスメント期間がありません。」と表示されます。生徒の支援開始日の登録状況をご確認ください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3. 職員が内容を記入する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        期間を開くと内容が表示されます。まだ記入がない場合は「まだ入力されていません」と表示されます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>右上の「入力開始」ボタン（すでに記入がある場合は「編集」ボタン）を押すと、入力フォームが開きます。</li>
        <li>次の項目を記入します。
          <ul className="ml-6 mt-2 list-disc space-y-1">
            <li><strong>本人の願い</strong></li>
            <li><strong>短期目標</strong>／<strong>長期目標</strong></li>
            <li><strong>5領域</strong>：健康・生活／運動・感覚／認知・行動／言語・コミュニケーション／人間関係・社会性</li>
            <li><strong>その他の課題</strong></li>
          </ul>
        </li>
        <li>入力欄は自由記述です。改行して長い文章も記入できます。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">4. AIで下書きを自動生成する（任意）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        入力フォームの上部に「AIで自動生成」の案内があります。期間内の連絡帳の記録などをもとに、AIが記入の下書きを作ってくれます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「AI生成」ボタンを押します。</li>
        <li>生成が終わると、5領域・短期目標・長期目標などの各欄にAIが作成した文章が自動で入ります。参照した連絡帳の件数がメッセージで表示されます。</li>
        <li>内容は必ず職員の目で確認し、実態に合わせて加筆・修正してください。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> AI生成は対象期間の開始日より前、直近6か月分の連絡帳の記録をもとに行います。連絡帳の記録が少ないと「連絡帳データが見つかりません」と表示され、生成できないことがあります。その場合は、まず連絡帳の記録を入力してからお試しください。
        </p>
      </div>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> AIが作成した文章はあくまで下書きです。そのまま確定せず、内容に誤りや不自然な点がないかを必ず確認してから保存・提出してください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">5. 保存・提出する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        入力フォームの下部にあるボタンで、下書きの保存や提出を行います。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>下書き保存</strong>：入力途中の内容を保存します。あとから続きを記入できます。</li>
        <li><strong>提出</strong>：内容を確定して提出します。確認メッセージが表示され、「提出しますか？提出後も内容の修正は可能です。」に対して「OK」を選ぶと提出されます。</li>
        <li><strong>下書き保存してPDF</strong>：下書きを保存したうえで、その内容をPDFとして書き出します。紙で確認したいときに使います。</li>
        <li><strong>キャンセル</strong>：入力内容を保存せずにフォームを閉じます。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 提出すると、そのお子様の保護者に「アセスメントの入力依頼」の通知が自動で届きます。職員の記入が終わってから提出することで、保護者に記入をお願いする流れになります。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">6. 提出後に内容を修正する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        職員のアセスメントは、提出したあとでも修正できます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>該当の期間を開き、右上の「編集」ボタンを押します。</li>
        <li>内容を修正します。</li>
        <li>下部の「更新」ボタンを押すと、修正内容が保存されます。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">7. 保護者の記入内容を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        保護者が記入したアセスメントは「保護者入力アセスメント確認」の画面で確認します。画面上部のボタンで「保護者入力アセスメント確認」と「スタッフ入力」を切り替えられます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「保護者入力アセスメント確認」を開きます。</li>
        <li>「生徒を選択」でお子様を選びます。</li>
        <li>「アセスメント提出期限を選択」から、確認したい期間を選びます。</li>
        <li>保護者が記入した「本人の願い」「家庭での願い」「目標設定」「五領域の課題」「その他の課題」などが表示されます。提出済みか下書きかも状態バッジで確認できます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 職員の入力画面（アセスメント（職員））で提出済みの内容を開くと、その期間に対する保護者の確認状況が「保護者確認済み」または「保護者未確認」のバッジで表示されます。あわせて、保護者が提出済みの場合はその要点（家庭での状況・心配事・要望）も同じ画面で確認できます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">8. PDFの出力・印刷</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>職員の入力画面では、期間の内容を開いた状態で「PDF」ボタンからPDFを書き出せます。</li>
        <li>保護者入力の確認画面では、「PDF印刷」ボタンでPDFを書き出せるほか、「このページを印刷」ボタンで表示中のページをそのまま印刷できます。</li>
      </ul>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">9. 保護者入力の表示・非表示</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        保護者入力の確認画面では、対象外の保護者アセスメントを一覧から隠すことができます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>期間を開いた下部の「この保護者用アセスメントを非表示」を押すと、その保護者アセスメントを一覧から隠せます。</li>
        <li>隠したものを戻したいときは、期間選択欄の右上にある「非表示を含む」にチェックを入れると再表示され、「この保護者用アセスメントを再表示」で元に戻せます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 提出期限がまだ来ていない期間を非表示にすると、一覧から消えて対応漏れにつながるおそれがあります。非表示にしようとすると警告が表示されるので、内容をよく確認してから操作してください。提出期限が近いのに非表示になっている件数がある場合は、画面上部に注意メッセージが表示されます。
        </p>
      </div>
    </div>
  );
}
