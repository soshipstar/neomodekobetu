export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* リード段落 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        このページでは、日々の業務をより速く・正確に進めるためのコツをまとめています。
        AIによる下書き支援の上手な使い方、入力ミスを防ぐポイント、便利なショートカットや検索の使い方など、
        知っておくと作業がぐっと楽になる実用的なヒントを紹介します。
      </p>

      {/* ================= AI活用のコツ ================= */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">AIによる下書き支援を上手に使う</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「きづり」では、いくつかの画面でAIによる文章の下書き（自動生成）が使えます。ゼロから書くより早く、
        内容を整えるたたき台として役立ちます。どの画面にどのAIボタンがあるかを覚えておくと便利です。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          <strong>連絡帳（統合内容の編集）</strong> - 「統合内容の編集」画面で、児童ごとの
          <strong>「AI生成」</strong>ボタンを押すと、入力済みの観察記録から保護者向けの文章を作成します。
        </li>
        <li>
          <strong>アセスメント（職員）</strong> - 各期間の
          <strong>「AI生成」</strong>ボタンで、期間内の連絡帳データをもとに内容を作成します。
          生成後には「連絡帳〇件を参照」と表示され、何件を根拠にしたかが分かります。
        </li>
        <li>
          <strong>モニタリング</strong> - <strong>「AIで評価を自動生成」</strong>で全ての目標の評価をまとめて作成できます
          （過去6ヶ月の連絡帳データを参照します）。目標ごとの「AI生成」で1件だけ作り直すこともできます。
        </li>
        <li>
          <strong>個別支援計画</strong> - <strong>「AI生成」</strong>ボタンで下書きを作成できます。生成に使った
          参照データ（連絡帳の件数・期間など）が画面に表示されます。
        </li>
        <li>
          <strong>施設通信</strong> - <strong>「AIで通信を生成」</strong>で、支援案・活動記録・イベント情報をもとに
          全セクションを一括作成できます（1〜2分かかることがあります）。セクションごとの「AI生成」も使えます。
        </li>
        <li>
          <strong>面談管理</strong> - 面談で聞き取った内容を入力し、<strong>「保護者アセスメントに反映」</strong>を押すと、
          AIが保護者アセスメントの形に変換して反映します。
        </li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> AIが作成した文章は、あくまで「下書き」です。事実と違う内容や、その児童に合わない表現が
          含まれることがあります。<strong>保存・送信する前に、必ず内容を読み直し、正しく直してから</strong>ご利用ください。
          とくに連絡帳は送信すると取り消せません。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> AI生成は、一括生成を選ぶと<strong>既存の入力内容を上書き</strong>する場合があります。
          「既存の内容は上書きされます」といった確認メッセージが出たら、消えては困る手入力が残っていないかを確かめてから実行してください。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> AIは入力済みの記録を材料にして文章を作ります。連絡帳の観察記録などを先にしっかり入力しておくほど、
          出てくる下書きの質が上がります。まず記録を入れる→AIで整える、の順がおすすめです。
        </p>
      </div>

      {/* ================= 入力を早く終える ================= */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">入力を早く終えるコツ</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        毎日くり返す入力は、ちょっとした操作を覚えるだけで大きく時短できます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          <strong>目標の引用</strong> - 連絡帳の各領域の入力欄の下に、その児童の個別支援計画の目標が表示されます。
          「この目標を引用」を押すと、目標を観察記録欄に取り込めるので、一から書く手間が省けます。
        </li>
        <li>
          <strong>写真の自動添付</strong> - 統合内容の編集では、その日・その児童に一致する写真が自動で添付候補に表示されます。
          不要な写真は写真右上の×で外せます。朝のうちに画面を開いたままでも、あとから上げた写真は
          「写真を再取得」で取り込めます。
        </li>
        <li>
          <strong>途中保存と自動保存</strong> - 統合内容の編集は「途中保存」で下書きが残ります。自動保存も
          5分ごとに働くので、途中で他の作業が入っても入力が消えません。
        </li>
        <li>
          <strong>チャットのテンプレ</strong> - 「これから帰ります」「到着しました」など、よく使う文言は
          「テンプレ保存」で教室の既定として保存できます。次回からは選ぶだけで送れます。
        </li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> キーボードの <strong>Ctrl + S</strong> を押すと、統合内容の編集などで下書きをその場で保存できます。
          こまめに押しておくと安心です。
        </p>
      </div>

      {/* ================= 探す・見つけるコツ ================= */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">目的の情報を素早く見つける</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          <strong>チャットの検索とピン留め</strong> - 保護者チャットの上部にある検索欄に生徒名・保護者名を入れると、
          目的のルームをすぐ探せます。よく使うルームは右上のピンのマークで上部に固定できます。
        </li>
        <li>
          <strong>未読・未対応のまとめ表示</strong> - 「活動管理」（ダッシュボード）の上部「通知・アラート」に、
          未読チャットや未確認連絡帳など、その日に対応が必要な項目がまとまります。ここを起点にすると対応漏れを防げます。
        </li>
        <li>
          <strong>未確認連絡帳の確認</strong> - 送ったのに保護者がまだ見ていない連絡帳は、左メニューの
          「未確認連絡帳」でまとめて確認できます。経過日数ごとに件数が出るので、声かけの目安になります。
        </li>
        <li>
          <strong>保留タスク</strong> - あとで対応する項目は「保留タスク」に集まります。手が空いたときにここを見ると、
          やり残しを拾えます。
        </li>
      </ul>

      {/* ================= 入力の注意点 ================= */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">入力するときの注意点</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>
          <strong>「途中保存」と「送信」を間違えない</strong> - 連絡帳の「保護者に送信」を押すと、内容がすぐ保護者に届き、
          後から取り消せません。まだ確定していないときは必ず「途中保存」を使ってください。
        </li>
        <li>
          <strong>送信前に児童名と内容を確認</strong> - 到着・帰宅の連絡や連絡帳の送信では確認ダイアログが出ます。
          児童名・メッセージ内容が正しいかを見てから「OK」を押しましょう。
        </li>
        <li>
          <strong>下書きの状態を見る</strong> - 各画面の一覧では「下書き」「提出済」「配信済」などの状態が表示されます。
          相手に見えているのはどれかを確認してから作業を進めてください。
        </li>
        <li>
          <strong>日付を確認する</strong> - 連絡帳やモニタリングは、上部の日付ナビゲーションで選んだ日・期間に対して入力されます。
          入力前に対象日が合っているかを確認しましょう。
        </li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 連絡帳のAI統合を実行すると、記録の中から「ヒヤリハット」に当たりそうな出来事をAIが見つけて、
          候補として知らせてくれることがあります。心当たりがあれば、その場から「ヒヤリハット」への登録につなげられます。
        </p>
      </div>

      {/* ================= AIをもっと活かす ================= */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">AIの精度を上げるために</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        AIは、日々の記録がたまるほど、その児童・その教室らしい下書きを出せるようになります。次の点を意識すると、
        使い続けるうちに手直しが減っていきます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>連絡帳の観察記録を、5領域それぞれにできるだけ具体的に入力する。</li>
        <li>個別支援計画やアセスメントで手直しした良い文章は、次回以降の下書きの手本になります。丁寧に直しておくと後が楽になります。</li>
        <li>AIの学習を使うには、施設側・児童側それぞれの同意が必要です。同意の状況は管理者向けの画面で確認・設定できます（詳しくは管理者にご相談ください）。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 本システムは子どもや保護者の個人情報を扱います。作業は必ず事業所が管理する端末で行い、
          画面を開いたまま席を離れないようにしてください。私有のスマートフォンやPCでは操作しないでください。
        </p>
      </div>
    </div>
  );
}
