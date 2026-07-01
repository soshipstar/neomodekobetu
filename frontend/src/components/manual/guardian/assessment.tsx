export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        アセスメントは、お子様の願いやご家庭で気になっていること、目標などを事業所にお伝えするための入力画面です。ここで入力した内容は、事業所が支援計画を作るための大切な参考になります。専門的な言葉を使う必要はありません。ふだん感じていることを、そのままの言葉で記入してください。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 入力の途中で「下書き保存」ができます。すべての欄を一度に埋める必要はありません。書ける範囲で記入し、続きはあとから追記できます。
        </p>
      </div>

      {/* アセスメント入力画面を開く */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. アセスメント入力画面を開く</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>画面の左側にあるメニューから <strong>「アセスメント入力」</strong> を選びます。</li>
        <li>「アセスメント入力」という画面が開きます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> メニューが見当たらないときは、スマートフォンでは画面の端にあるメニューボタン（三本線のマークなど）を押すと、メニューが表示されます。
        </p>
      </div>

      {/* お子様と提出期限を選ぶ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. お子様と提出期限を選ぶ</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          お子様が複数登録されている場合は、画面上部の <strong>「お子様を選択」</strong> から、記入したいお子様を選びます。お子様がお一人の場合、この欄は表示されず、自動でそのお子様が対象になります。
        </li>
        <li>
          次に <strong>「アセスメント提出期限を選択」</strong> の欄を押し、記入する期間を選びます。ここには「提出期限」と「対象期間」の日付が表示されています。
        </li>
        <li>
          すでに記入を始めている場合は、その期間の後ろに <strong>「[下書き]」</strong> または <strong>「[提出済み]」</strong> と表示されます。まだ何も入力していない場合は何も表示されません。
        </li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「入力可能なアセスメント期間がありません。」と表示されている場合は、現在記入できる期間がありません。時期が来ると事業所側で期間が設定されますので、しばらく待ってから再度ご確認ください。ご不明な点は事業所にお問い合わせください。
        </p>
      </div>

      {/* 内容を入力する */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3. 内容を入力する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        提出期限を選ぶと、入力欄が表示されます。上から順に、次のような項目があります。書ける項目だけ記入すれば大丈夫です。空欄のままでも保存できます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>本人の願い</strong> … お子様が望んでいること、なりたい姿を記入します。</li>
        <li><strong>家庭での願い</strong> … ご家庭で気になっていること、取り組みたいことを記入します。</li>
        <li>
          <strong>目標設定</strong> … 「短期目標（6か月）」と「長期目標（1年以上）」の2つの欄があります。それぞれ、目指したい姿を記入します。
        </li>
        <li>
          <strong>五領域の課題</strong> … 「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」の5つの欄があります。各領域で気になることを記入します。
        </li>
        <li><strong>その他の課題</strong> … 上記に当てはまらない、その他お伝えしたいことを記入します。</li>
      </ul>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>記入したい欄を押します。</li>
        <li>文章を入力します。改行して複数行に分けて書くこともできます。</li>
        <li>他の欄も同じように、書ける範囲で記入していきます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 難しく考える必要はありません。「最近こんなことができるようになった」「ここが少し心配」など、ふだんお子様を見ていて感じることを書いていただくと、事業所に伝わりやすくなります。
        </p>
      </div>

      {/* 下書き保存する */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">4. 下書きとして保存する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        まだ提出せず、あとで続きを書きたいときは、下書きとして保存します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>入力欄の一番下にある <strong>「下書き保存」</strong> ボタンを押します。</li>
        <li>「下書きを保存しました。」というメッセージが表示されれば完了です。</li>
        <li>次に開いたときには、保存した内容が表示され、続きから記入できます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 下書きの状態では、まだ事業所には正式に提出されていません。何度でも書き直しや追記ができます。内容がすべて整ってから、次の「提出する」に進んでください。
        </p>
      </div>

      {/* 提出する */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">5. 提出する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        内容が整ったら、事業所へ提出します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>入力欄の一番下にある <strong>「提出する」</strong> ボタンを押します。</li>
        <li>
          「提出すると変更できなくなります。提出してもよろしいですか？」という確認メッセージが表示されます。内容をよくご確認のうえ、よろしければ <strong>「OK」</strong> を押します。まだ見直したい場合は「キャンセル」を押すと、提出せずに元の画面に戻ります。
        </li>
        <li>「アセスメントを提出しました。」というメッセージが表示されれば完了です。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 一度提出すると、内容を変更できなくなります。提出したあとの画面には「既に提出済みです。提出後は変更できません。」と表示され、入力欄も編集できなくなります。提出する前に、記入内容に間違いがないか、必ずご確認ください。
        </p>
      </div>

      {/* 状態の見方 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">状態の見方</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        提出期限を選ぶと、画面上部に現在の状態が表示されます。次の3つのいずれかが表示されます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>未入力</strong> … まだ何も入力・保存していない状態です。</li>
        <li><strong>下書き</strong> … 下書き保存はしたが、まだ提出していない状態です。編集を続けられます。</li>
        <li><strong>提出済み</strong> … 事業所へ提出が完了した状態です。提出した日時も表示されます。以降は編集できません。</li>
      </ul>

      {/* 過去の内容を見る */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">過去に提出した内容を見る</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        これまでに提出したアセスメントは、あとから見返すことができます。事業所が記入したアセスメントも、同じ画面で確認できます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          左側のメニューから <strong>「アセスメント履歴」</strong> を選びます。または、アセスメント入力画面の右上にある <strong>「アセスメント履歴」</strong> ボタンからも開けます。
        </li>
        <li>
          必要に応じて、画面上部で「お子様」「年度」「月」を選んで、表示する内容をしぼり込めます。
        </li>
        <li>見たい期間の見出しを押すと、その期間の詳しい内容が開きます。</li>
        <li>
          「保護者」「事業所」それぞれの欄にある <strong>「表示」</strong> ボタンを押すと、記入内容を画面で確認できます。<strong>「印刷」</strong> ボタンを押すと、印刷用の画面が別のタブで開きます。
        </li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 見出しに <strong>「保護者記入をお願いします」</strong> と表示されているときは、その期間の保護者アセスメントがまだ提出されていません。「アセスメント入力」画面から記入・提出をお願いします。
        </p>
      </div>

      {/* 事業所アセスメントの確認 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">事業所が作成したアセスメントを確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        事業所がアセスメントを提出すると、履歴画面でその内容を確認し、「確認しました」と伝えることができます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          「アセスメント履歴」画面を開きます。見出しに <strong>「要確認」</strong> と表示されている期間は、確認が必要な事業所アセスメントがあります。
        </li>
        <li>その期間の見出しを押して開き、「事業所」の欄にある <strong>「表示」</strong> ボタンで内容を確認します。</li>
        <li>
          内容を確認したら、<strong>「確認しました」</strong> ボタンを押します。「事業所アセスメントの内容を確認しましたか？」という確認メッセージで <strong>「OK」</strong> を押すと、確認が完了します。
        </li>
        <li>確認が完了すると、状態が「確認済み」に変わり、確認した日時が表示されます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 「確認しました」を押すことで、事業所に「内容を見ていただけた」ことが伝わります。事業所からアセスメントが提出された際は、内容を確認して押していただくとスムーズです。
        </p>
      </div>
    </div>
  );
}
