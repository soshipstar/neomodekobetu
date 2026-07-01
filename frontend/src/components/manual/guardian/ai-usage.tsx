export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* リード段落 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        このシステムでは、個別支援計画書・連絡帳・おたより（施設通信）などの書類を作るときに、
        文章の<strong>下書き（素案）</strong>を作る手助けとして「AI（人工知能）」を使うことがあります。
        AIはあくまで職員の作業を補助する道具です。お子様の診断や治療の判断にAIを使うことはありません。
      </p>

      {/* AIとは何か */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">AIは「下書き」を手伝う道具です</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        AIは、これまでに集めた活動の記録などをもとに、書類の文章のたたき台（下書き）を作ります。
        たとえば、個別支援計画書の目標案や、連絡帳の文章案などを短時間で用意することで、
        職員がお子様一人ひとりと向き合う時間を増やせるようにしています。
      </p>

      {/* 保護者に知っておいてほしい3つのこと */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">保護者の皆様に知っておいていただきたいこと</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          <strong>下書きは必ず職員が確認します</strong> - AIが作るのは下書き（素案）です。
          そのまま保護者の皆様にお届けすることはありません。内容は必ず事業所の職員が読み、
          必要に応じて修正・書き直しをしたうえで、正式な書類としてお届けします。
        </li>
        <li>
          <strong>お名前などの個人情報は伏せて処理します</strong> - AIに文章の作成を頼むときは、
          お子様や保護者のお名前などを<strong>仮の表現に置きかえた（仮名化した）</strong>うえで処理します。
          実名などがそのまま外部に送られないようにしています。
        </li>
        <li>
          <strong>お預かりした情報は学習に使われません</strong> - AIを提供している事業者は、
          事業所が取り扱いのルールを取り決めた委託先です。お預かりした情報が、
          AIをより賢くするための「学習」に使われることはありません。
        </li>
      </ul>

      {/* 赤: 最重要の注意 */}
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> AIが作った文章をそのまま最終的な書類にすることはありません。
          お届けする書類は、すべて職員が内容を確認したものです。
          もし書類の内容に気になる点や事実と違う点があれば、遠慮なく事業所へお知らせください。
        </p>
      </div>

      {/* 保護者が行う操作 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">保護者の皆様が行う操作（書類の確認）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        保護者の皆様がAIを直接操作することはありません。
        AIの助けを借りて職員が作成した書類（個別支援計画書など）が届いたら、
        次の手順で内容をご確認いただきます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          画面左のメニューから<strong>「個別支援計画」</strong>を開きます。確認が必要な書類があるときは、
          <strong>「確認待ち」</strong>と表示されます。
        </li>
        <li>
          確認したい書類の<strong>「内容を確認する」</strong>を押すと、書類の詳細が開きます。
        </li>
        <li>
          書類の内容をよくお読みください。問題がなければ、そのまま次の操作へ進みます。
        </li>
        <li>
          内容に問題がなければ<strong>「変更なし（承認）」</strong>を押します。
          署名を希望される場合は、署名欄に記入したうえで<strong>「署名して確認」</strong>を押します。
        </li>
        <li>
          修正してほしい点や質問がある場合は<strong>「コメントを送る」</strong>を押し、
          内容を入力して<strong>「コメントを送信」</strong>を押します。職員がコメントを確認し、対応します。
        </li>
      </ol>

      {/* 青: 補足 */}
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 署名はスマートフォンやタブレットでは指で、パソコンではマウスで、
          画面の署名欄に直接書けます。書き直したいときは「クリア」で消してやり直せます。
          署名は必須ではなく、まずは内容のご確認をお願いしています。
        </p>
      </div>

      {/* 緑: ヒント */}
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> AIはあくまで下書きづくりのお手伝いです。
          お子様のことをいちばんよく知っているのは、ご家庭の皆様と、日々関わっている職員です。
          気づいたことやご要望は、チャットや面談でいつでもお伝えください。
          いただいた声をもとに、より良い書類・より良い支援につなげていきます。
        </p>
      </div>

      {/* 取り扱いの根拠 */}
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> AIの利用や個人情報の詳しい取り扱いは、
          <strong>利用規約および別途定めるプライバシーポリシー</strong>に従います。
          ご不明な点は事業所までお気軽にお問い合わせください。
        </p>
      </div>
    </div>
  );
}
