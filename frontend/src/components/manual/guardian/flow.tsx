export default function ManualSection() {
  return (
    <div className="space-y-4">
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        お子様への支援は、1年をとおして「<strong>アセスメント（情報共有）</strong>」→「<strong>個別支援計画書（目標づくり）</strong>」→「<strong>同意（確認・署名）</strong>」→「<strong>モニタリング（途中の振り返り）</strong>」という流れをくり返して進みます。専門的な知識がなくても、画面の案内にそって進めば大丈夫です。ここでは、保護者の皆様が「いつ・どこで・何をすればよいか」を順番にご説明します。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 保護者の皆様が画面で操作していただくのは、主に「<strong>アセスメント入力</strong>」と「<strong>個別支援計画書の確認・同意</strong>」「<strong>モニタリング表の確認</strong>」の3つです。計画書やモニタリング表そのものはスタッフが作成しますので、保護者の皆様は届いた内容をご確認いただくだけで問題ありません。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">年間の流れ（全体像）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        1年をとおして、次の順番でくり返します。この一連の流れはおおよそ6か月ごとに1周します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>日々の連絡帳</strong>：お子様が通われた日ごとに、スタッフが活動の様子を記録してお届けします。この積み重ねが、後の目標づくりの土台になります。</li>
        <li><strong>アセスメント入力</strong>：家庭での様子やご要望を、保護者の皆様に記入していただきます（おおよそ6か月ごと）。</li>
        <li><strong>個別支援計画書の作成</strong>：アセスメントや日々の記録をもとに、スタッフが次の目標と支援内容を作成します。</li>
        <li><strong>確認・同意（署名）</strong>：できあがった計画書の内容を保護者の皆様に確認していただき、同意（承認または署名）をいただきます。</li>
        <li><strong>モニタリング（途中の振り返り）</strong>：計画の途中で、目標がどこまで進んでいるかをスタッフが振り返り、その内容を保護者の皆様に確認・署名していただきます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> どの操作も、順番が近づくとダッシュボード（ログイン後の最初の画面）に「未確認」「期限」などのお知らせが表示されます。まずはダッシュボードを開いて、対応が必要なものがないか確認する習慣をつけると安心です。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">ステップ1：アセスメントを入力する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        アセスメントは、家庭でのお子様の様子やご要望をスタッフに伝えるための入力です。ここで共有いただいた内容が、次の支援計画の目標づくりに使われます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>画面左のメニュー（スマートフォンの場合は下部のメニュー）から「<strong>アセスメント入力</strong>」を開きます。</li>
        <li>お子様が複数いる場合は、はじめに「<strong>お子様を選択</strong>」でお子様を選びます（お一人の場合はこの欄は表示されません）。</li>
        <li>「<strong>アセスメント提出期限を選択</strong>」の欄で、対象となる期限を選びます。まだ選んでいないときは「<strong>-- 提出期限を選択してください --</strong>」と表示されています。</li>
        <li>入力欄が表示されます。上から順に、次の項目を記入します（すべて任意の文章で記入できます）。
          <ul className="ml-6 mt-2 list-disc space-y-1">
            <li><strong>本人の願い</strong>：お子様が望んでいること、なりたい姿</li>
            <li><strong>家庭での願い</strong>：家庭で気になっていること、取り組みたいこと</li>
            <li><strong>目標設定</strong>：短期目標（6か月）・長期目標（1年以上）</li>
            <li><strong>五領域の課題</strong>：健康・生活／運動・感覚／認知・行動／言語・コミュニケーション／人間関係・社会性</li>
            <li><strong>その他の課題</strong>：その他、お伝えしたいこと</li>
          </ul>
        </li>
        <li>途中で作業をやめたいときは「<strong>下書き保存</strong>」を押します。保存しておけば、後日また続きから入力できます。</li>
        <li>すべて記入し終えたら「<strong>提出する</strong>」を押します。「<strong>提出すると変更できなくなります。提出してもよろしいですか？</strong>」と確認が表示されるので、よろしければ「OK」を押します。</li>
        <li>提出が完了すると、状態が「<strong>提出済み</strong>」に変わり、スタッフに内容が届きます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 「提出する」を押すと、その後は内容を変更できなくなります。提出前に内容をよくご確認ください。まだ迷っている場合は「下書き保存」にとどめておくと安心です。
        </p>
      </div>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> すべての項目を必ず埋める必要はありません。書けるところだけでも構いません。過去に提出した内容は、メニューの「<strong>アセスメント履歴</strong>」からいつでも見返せます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">ステップ2：個別支援計画書を確認して同意する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        アセスメントや日々の記録をもとに、スタッフがお子様に合わせた「個別支援計画書」を作成します。できあがると、保護者の皆様に確認・同意のお願いが届きます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから「<strong>個別支援計画書</strong>」を開きます。確認が必要なものがある場合、「<strong>確認待ちの個別支援計画書があります。</strong>」というお知らせと、「<strong>確認待ち</strong>」のマークが表示されます。</li>
        <li>計画書の内容（本人の生活に対する意向・総合的な援助の方針・保護者の願い・長期目標・短期目標・支援内容など）が表示されるので、目をとおします。</li>
        <li>内容を確認するには「<strong>内容を確認する</strong>」ボタンを押します。確認用の画面（「<strong>個別支援計画書の確認</strong>」）が開きます。</li>
        <li>ここで、次の3つから対応を選びます。
          <ul className="ml-6 mt-2 list-disc space-y-1">
            <li><strong>内容に問題がないとき（署名なしで承認）</strong>：「<strong>変更なし（承認）</strong>」を押します。これで同意が完了します。</li>
            <li><strong>署名して同意したいとき</strong>：画面の「<strong>保護者署名（任意）</strong>」の欄に署名し、「<strong>署名して確認</strong>」を押します。</li>
            <li><strong>変更してほしいところがあるとき</strong>：「<strong>コメントを送る</strong>」を押し、変更を希望する内容を入力して「<strong>コメントを送信</strong>」を押します。スタッフに要望が伝わります。</li>
          </ul>
        </li>
        <li>やめたいときは「<strong>キャンセル</strong>」を押すと、対応せずに画面を閉じられます。</li>
        <li>同意が完了すると、状態が「<strong>署名済み</strong>」または「<strong>レビュー済み</strong>」に変わります。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 署名は、スマートフォンやタブレットなら<strong>指で</strong>、パソコンなら<strong>マウス</strong>で署名欄に直接書けます。うまく書けなかったときは、やり直して書き直せます。署名は「任意」なので、署名せず「変更なし（承認）」を選んでいただいても同意として扱われます。
        </p>
      </div>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「コメントを送る」で要望をお送りいただいた場合は、スタッフが内容を見直したうえで、あらためて計画書をお届けすることがあります。気になる点は遠慮なくお伝えください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">ステップ3：モニタリング表を確認して署名する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        計画がスタートしたあと、途中で「目標がどこまで進んだか」をスタッフが振り返るのが「モニタリング」です。振り返りの結果がまとまると、保護者の皆様に確認・署名のお願いが届きます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから「<strong>モニタリング表</strong>」を開きます。確認が必要なものがある場合、「<strong>確認待ちのモニタリング表があります。</strong>」というお知らせと、「<strong>確認待ち</strong>」のマークが表示されます。</li>
        <li>一覧から確認したいモニタリング（「モニタリング（〇年〇月〇日実施）」と表示）の行を押すと、内容が開きます。</li>
        <li>支援目標ごとの達成状況（達成／一部達成／未達成）や、スタッフのコメント、総合コメントなどが表示されるので、目をとおします。</li>
        <li>内容を確認したら「<strong>署名して確認</strong>」ボタンを押します。「<strong>モニタリング表の確認</strong>」の画面が開きます。</li>
        <li>「<strong>保護者署名</strong>」の欄に署名し、必要に応じて「<strong>署名日</strong>」を確認・変更します。</li>
        <li>もう一度「<strong>署名して確認</strong>」を押すと、確認が完了します。やめたいときは「<strong>キャンセル</strong>」を押します。</li>
        <li>完了すると、状態が「<strong>確認済み</strong>」に変わります。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> モニタリング表の確認では署名が必要です。署名欄が空のまま進めようとすると、署名を求めるメッセージが表示されます。指またはマウスで署名欄に記入してから「署名して確認」を押してください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">全体の流れ（早見表）</h3>
      <table className="w-full border-collapse text-sm">
        <thead>
          <tr>
            <th className="border border-[var(--neutral-stroke-2)] px-3 py-2 text-left font-semibold text-[var(--neutral-foreground-1)]">時期・タイミング</th>
            <th className="border border-[var(--neutral-stroke-2)] px-3 py-2 text-left font-semibold text-[var(--neutral-foreground-1)]">開くメニュー</th>
            <th className="border border-[var(--neutral-stroke-2)] px-3 py-2 text-left font-semibold text-[var(--neutral-foreground-1)]">保護者がすること</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">通われた日ごと</td>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">連絡帳一覧</td>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">その日の活動記録を確認する</td>
          </tr>
          <tr>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">おおよそ6か月ごと（期限のお知らせが届いたら）</td>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">アセスメント入力</td>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">家庭での様子・要望を入力して提出する</td>
          </tr>
          <tr>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">計画書が届いたとき（確認待ちのお知らせ）</td>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">個別支援計画書</td>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">内容を確認し、承認・署名またはコメントを送る</td>
          </tr>
          <tr>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">モニタリングが届いたとき（確認待ちのお知らせ）</td>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">モニタリング表</td>
            <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top text-[var(--neutral-foreground-2)]">達成状況を確認し、署名して確認する</td>
          </tr>
        </tbody>
      </table>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 操作の途中で分からないことがあれば、メニューの「<strong>チャット</strong>」からスタッフにいつでもご相談いただけます。また、面談での説明をご希望の場合は「<strong>面談予約</strong>」からご予約いただけます。無理にお一人で進める必要はありませんので、ご安心ください。
        </p>
      </div>
    </div>
  );
}
