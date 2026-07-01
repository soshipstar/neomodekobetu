export default function ManualSection() {
  return (
    <div className="space-y-4">
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「事業所評価」は、年1回を目安に実施する自己評価アンケートの機能です。保護者からの評価と、職員（スタッフ）自身による自己評価の2種類を収集し、集計・公表までを1つの画面で管理できます。左メニューの「事業所評価」から利用します。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 評価期間の作成、集計結果の閲覧、自己評価総括表の編集は<strong>管理者</strong>のみが行えます。一般スタッフは「自己評価の回答」と「回答状況（回収状況）の確認」までが利用範囲です。個々の保護者・スタッフの回答内容を閲覧できるのも管理者だけです。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">評価の流れ（ステータス）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        1つの評価期間は、次の4つのステータスを順に進みます。カード右側のボタン（例:「回答収集中へ」）を押すと次の段階へ進みます。
      </p>
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="bg-[var(--brand-80)] text-white">
              <th className="p-3 text-left">ステータス</th>
              <th className="p-3 text-left">状態の意味</th>
            </tr>
          </thead>
          <tbody>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3 font-medium">下書き</td>
              <td className="p-3">作成直後。まだ回答は受け付けていません。</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
              <td className="p-3 font-medium">回答収集中</td>
              <td className="p-3">保護者・スタッフが回答を入力できる期間です。</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3 font-medium">集計中</td>
              <td className="p-3">回答を締め切り、集計結果の確認・編集や事業所コメントの記入を行う段階です。</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
              <td className="p-3 font-medium">公表済み</td>
              <td className="p-3">集計結果を確定した状態です。必要なら「集計中に戻す」で前段階に戻せます。</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. 評価期間を作成する（管理者）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューから「事業所評価」を開きます。</li>
        <li>画面右上の「新規作成」ボタンを押します。</li>
        <li>「評価期間の作成」ウィンドウが開くので、次の項目を入力します。
          <ul className="ml-6 mt-1 list-disc space-y-1">
            <li><strong>年度</strong>: 評価を実施する年度（西暦）を入力します。初期値は今年が入っています。</li>
            <li><strong>保護者回答〆切</strong>: 保護者の回答期限を選びます（任意）。</li>
            <li><strong>スタッフ回答〆切</strong>: 職員の回答期限を選びます（任意）。</li>
          </ul>
        </li>
        <li>「作成」を押すと、一覧に新しい評価期間のカードが「下書き」状態で追加されます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 〆切は空欄のままでも作成できます。設定した〆切は、カードや保護者側の案内画面に表示されます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. 回答の受付を開始する（管理者）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>一覧で対象の評価期間カードを確認します。</li>
        <li>カード右側の「回答収集中へ」ボタンを押します。</li>
        <li>確認メッセージで「OK」を選ぶと、ステータスが「回答収集中」に変わり、保護者・スタッフが回答できるようになります。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 「回答収集中」になると、保護者のログイン画面に事業所評価のアンケートが表示されます。回答をお願いする旨を、あらかじめ保護者に案内しておくとスムーズです。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3. スタッフ自己評価を回答する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        ステータスが「回答収集中」の間、各スタッフは自分自身の自己評価を入力します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「事業所評価」で対象期間のカードにある「自己評価」ボタンを押します。</li>
        <li>質問がカテゴリ（分類）ごとに表示されます。各質問について「はい」「どちらともいえない」「いいえ」から回答を選びます。</li>
        <li>必要に応じて、質問ごとの「コメント（任意）」欄に補足を記入します。</li>
        <li>「いいえ」を選んだ質問には、赤枠の「改善計画（必須）」欄が表示されます。改善に向けた内容を必ず記入してください。</li>
        <li>途中で中断する場合は「下書き保存」を押します。あとから続きを入力できます。</li>
        <li>すべて記入したら「提出する」を押し、確認メッセージで「OK」を選びます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 提出後は内容を変更できません。また、未回答の質問が残っていたり、「いいえ」の質問に改善計画が未記入だったりすると提出できず、その旨のメッセージが表示されます。提出前に内容をよく確認してください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">4. 回答状況（回収状況）を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        誰が回答済みかを確認できます。この画面はスタッフも閲覧できます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>対象期間のカードにある「回答状況」ボタンを押します。</li>
        <li>「保護者」「スタッフ」のタブで、それぞれの回答状況を切り替えて確認します。</li>
        <li>上部に「提出済み: ○ / △」の進捗バーが表示されます。</li>
        <li>各人の状態は「提出済み」「入力中」「未開始」のいずれかで表示されます。</li>
        <li>（管理者のみ）提出済みの行に表示される「閲覧」ボタンを押すと、その人の回答内容を確認できます。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">5. 回答を締め切って集計する（管理者）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>回答が十分に集まったら、カードの「集計中へ」ボタンを押して締め切ります。</li>
        <li>カードに表示される「集計結果」ボタンを押します。</li>
        <li>集計結果がまだ無い場合は「集計を実行」を、再計算したい場合は右上の「再集計」を押します。確認メッセージで「OK」を選ぶと集計されます。</li>
        <li>「保護者評価」「事業所内評価（スタッフ）」のタブを切り替えて、それぞれの結果を確認します。</li>
        <li>各質問について「はい」「どちらとも」「いいえ」「わからない」の人数と「はい％」が表示されます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「集計中」または「公表済み」のときは、集計結果の各人数をクリックして手入力で修正できます（紙の回答分を合算したい場合など）。同じ画面で質問ごとの「事業所コメント」も入力できます。入力欄をクリックし、記入して「保存」を押してください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">6. 自己評価総括表（別紙3）を作成する（管理者）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        集計結果をもとに、事業所の「強み」と「弱み」をまとめた公表用の総括表を作成します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>カードの「自己評価総括表」ボタンを押します。</li>
        <li>「事業所の強み」「事業所の弱み」それぞれの表に、取組内容や課題、改善に向けた取組を入力します。</li>
        <li>下書きの作成を効率化したい場合は「AI生成」を押すと、集計内容をもとに文案が自動で入ります（既存の内容は上書きされます）。</li>
        <li>内容を確認・修正したら「保存」を押します。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 「AI生成」で作成された文章はあくまで下書きです。必ず内容を確認し、実態に合うよう修正してから保存・公表してください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">7. 結果を公表・出力する（管理者）</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>集計結果や総括表の内容が確定したら、カードの「公表済みへ」ボタンを押してステータスを確定します。</li>
        <li>「集計結果」画面では、右上の「PDF出力」ボタンから、表示中のタブ（保護者／スタッフ）の集計結果をPDFで保存できます。</li>
        <li>「自己評価総括表」画面でも「PDF出力」ボタンから総括表をPDFで保存できます。</li>
        <li>公表後に修正が必要になった場合は、カードの「集計中に戻す」を押すと編集できる状態に戻せます。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">保護者側の見え方（案内の参考）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        保護者に回答方法を案内する際の参考にしてください。ステータスが「回答収集中」の間、保護者はログイン後の「事業所評価」から回答します。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>各質問について「はい」「どちらともいえない」「いいえ」「わからない」から選びます。</li>
        <li>質問ごとに「ご意見（任意）」欄へ意見・要望を記入できます。</li>
        <li>「下書き保存」で途中保存、「回答を提出する」で提出します。提出後は修正できません。</li>
        <li>回答を受け付けている期間がない場合は、「現在、回答を受け付けている評価はありません」と表示されます。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 集計は自動で行われますが、回答が追加された後は「再集計」を押すことで最新の状態に更新できます。締め切り後に紙の回答を加える場合などは、集計値の手入力（5の補足）もあわせて活用してください。
        </p>
      </div>
    </div>
  );
}
