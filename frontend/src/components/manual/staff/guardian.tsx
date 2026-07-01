export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        このセクションでは、保護者の方がログイン後に利用できる機能をまとめています。指導員・管理者の皆さまが、保護者に「アプリで何ができるか」「どこを押せばよいか」を案内する際の手引きとしてお使いください。保護者は自分専用のIDでログインすると、左側のメニュー（スマートフォンでは画面下部のメニュー）から各機能にアクセスできます。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 保護者に複数のお子様が登録されている場合、多くの画面で「お子様を選択」する欄が表示されます。お子様が1人だけの場合は選択欄が省略され、自動的にそのお子様の情報が表示されます。
        </p>
      </div>

      {/* 保護者メニュー一覧 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">保護者が使えるメニュー一覧</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        保護者のログイン後の画面には、次のメニューが表示されます。案内の際は下記の名称をそのままお伝えください。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>ダッシュボード</strong> — 確認が必要な項目とカレンダーをまとめて表示する最初の画面</li>
        <li><strong>連絡帳一覧</strong> — お子様ごとの連絡帳を確認する画面</li>
        <li><strong>連絡帳検索</strong> — 過去の連絡帳を期間・キーワード・領域で検索する画面</li>
        <li><strong>チャット</strong> — 事業所とメッセージをやり取りする画面（欠席連絡・イベント参加・面談申込もここから）</li>
        <li><strong>面談予約</strong> — 面談の申し込み状況・確定内容を確認する画面</li>
        <li><strong>週間計画表</strong> — 週ごとの活動予定を確認する画面</li>
        <li><strong>アセスメント入力</strong> — お子様や家庭の願い・目標を記入して提出する画面</li>
        <li><strong>アセスメント履歴</strong> — 過去に提出したアセスメントを見る画面</li>
        <li><strong>お知らせ</strong> / <strong>施設通信</strong> — 事業所からのお知らせや通信を読む画面</li>
        <li><strong>個別支援計画書</strong> — 計画書を確認し、承認・コメント・署名を行う画面</li>
        <li><strong>モニタリング表</strong> — モニタリング結果を確認し、署名を行う画面</li>
        <li><strong>事業所評価</strong> — 事業所評価アンケートに回答する画面</li>
        <li><strong>ご利用ガイド</strong> — 保護者向けの使い方説明</li>
        <li><strong>プロフィール</strong> / <strong>パスワード変更</strong> — 登録情報の確認とパスワードの変更</li>
      </ul>

      {/* ダッシュボード */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. ダッシュボード（最初の画面）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        保護者がログインすると最初に「連絡帳ダッシュボード」が開きます。ここには対応が必要なことが一覧でまとまっているため、保護者にはまずこの画面を見ていただくよう案内すると分かりやすくなります。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>画面上部の<strong>「確認が必要な項目」</strong>に、未読チャット・未確認の連絡帳・確認待ちの計画書やモニタリング表・提出期限が近いアセスメント・面談の回答待ち・アンケート依頼などが色付きのカードで表示されます。</li>
        <li>各カードの下にあるリンク（例:「チャットを開く」「確認する」「アセスメントを作成する」など）を押すと、その手続きの画面へ直接移動できます。</li>
        <li>その下には<strong>月間カレンダー</strong>があり、活動予定日・連絡帳の有無・欠席・振替・イベント・面談予定などが日ごとに表示されます。<strong>「前月」「今月」「次月」</strong>のボタンで表示月を切り替えられます。</li>
        <li>カレンダー内の連絡帳やイベント、面談の項目を押すと、その場で詳細が開きます。</li>
        <li>さらに下には<strong>お子様ごとのカード</strong>と、よく使う機能への<strong>ショートカット</strong>（チャット・個別支援計画・面談・事業所評価・アセスメント・連絡帳・モニタリング・欠席連絡）が並んでいます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 「確認が必要な項目」に何も表示されていなければ、その時点で保護者側の対応事項はない状態です。保護者から「何をすればいいか分からない」と相談があったときは、まずこのダッシュボードを開いてもらうのが確実です。
        </p>
      </div>

      {/* 連絡帳の確認 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. 連絡帳を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        事業所が送信した連絡帳（活動記録）を保護者が読み、「確認しました」の操作を行う機能です。確認操作をしていただくと、事業所側で保護者が読んだことを把握できます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューの<strong>「連絡帳一覧」</strong>を開きます。ダッシュボードのお子様カードにある<strong>「すべての連絡帳を見る」</strong>からも移動できます。</li>
        <li>読みたい連絡帳を押すと、活動内容や写真などの詳細が表示されます。</li>
        <li>内容を確認したら、<strong>「確認しました」</strong>ボタンを押します。確認の確認メッセージが出る場合は<strong>「OK」</strong>を押します。</li>
        <li>確認が完了すると<strong>「確認済み」</strong>の表示に変わり、確認した日時が記録されます。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        写真が添付されている場合は、写真をタップすると拡大表示されます。
      </p>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">連絡帳を検索する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        過去の連絡帳をさかのぼって探したいときは、メニューの<strong>「連絡帳検索」</strong>を使います。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「連絡帳検索」を開きます。初期状態では<strong>直近1か月分</strong>の連絡帳が表示されます。</li>
        <li>画面上部の検索フォームで、<strong>お子様</strong>（複数登録時）・<strong>期間（開始・終了）</strong>・<strong>領域</strong>・<strong>キーワード</strong>を指定します。</li>
        <li><strong>「検索」</strong>ボタンを押すと、条件に合う連絡帳が一覧で表示されます。</li>
        <li>条件をリセットしたいときは<strong>「クリア」</strong>ボタンを押します。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「領域」は、健康・生活／運動・感覚／認知・行動／言語・コミュニケーション／人間関係・社会性の5つから選べます。この検索画面には、記録件数を領域別に示す「統計情報」も表示されます。1か月より前の連絡帳を探すときは、必ず期間を指定するよう保護者に案内してください。
        </p>
      </div>

      {/* チャット */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3. チャットでやり取りする</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        事業所と保護者がメッセージをやり取りする機能です。通常のメッセージのほか、<strong>欠席連絡・イベント参加申込・面談申込</strong>もこのチャット画面から行えます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューの<strong>「チャット」</strong>を開きます。お子様ごとのチャットルームが一覧で表示されます（名前で検索も可能です）。</li>
        <li>やり取りしたいお子様のルームを押して開きます。</li>
        <li>画面下部の入力欄にメッセージを入力し、送信します。ファイルの添付も可能です。</li>
        <li>入力欄の上には<strong>「今出発しました」「帰宅しました」</strong>のボタンがあり、押すだけで事業所に到着・帰宅を知らせられます（確認メッセージで「OK」を押すと送信されます）。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        メッセージ入力欄の上部には、送るメッセージの種類を切り替えるタブがあります。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>通常</strong> — 普通のメッセージを送ります。</li>
        <li><strong>欠席連絡</strong> — 欠席日・理由・振替の希望（後日決める／今すぐ日にちを決める）を入力して送ります。</li>
        <li><strong>イベント参加</strong> — 参加したいイベントを選び、備考を添えて申し込みます。</li>
        <li><strong>面談申込</strong> — 面談目的を選び、希望日時（第1〜第3希望）を入力して申し込みます。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 過去のメッセージは<strong>「過去のメッセージを読み込む」</strong>で、さらに古いものは画面右上の<strong>「アーカイブ」</strong>で確認できます。
        </p>
      </div>

      {/* 欠席連絡（専用画面） */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">欠席連絡（専用画面）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        チャットからだけでなく、専用の<strong>「欠席連絡」</strong>画面（ダッシュボードのショートカットからも移動できます）でも欠席を送れます。体調の情報を詳しく伝えたいときはこちらが便利です。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「欠席連絡」画面を開き、<strong>お子様・欠席日・理由</strong>を入力します。</li>
        <li>必要に応じて<strong>体調（任意）</strong>欄に、体温・通院の有無・症状（腹痛・頭痛・咽頭痛・咳・くしゃみ・鼻水）・その他困っていることを入力します。</li>
        <li>振替を希望する場合は<strong>「振替を希望する」</strong>にチェックを入れ、振替希望日を入力します。</li>
        <li><strong>「送信する」</strong>を押すと欠席連絡が送られ、画面下部の<strong>「送信履歴」</strong>に記録されます。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        送信履歴では、振替の状況（振替申請中／振替承認済／振替不可）や、スタッフからのアドバイスも確認できます。
      </p>

      {/* アセスメント入力 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">4. アセスメントを記入する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        個別支援計画を作成するために、お子様や家庭の願い・目標などを保護者に記入していただく機能です。提出期限が近づくとダッシュボードに案内が表示されます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューの<strong>「アセスメント入力」</strong>を開きます。</li>
        <li>複数のお子様がいる場合は<strong>「お子様を選択」</strong>し、次に<strong>「アセスメント提出期限を選択」</strong>から対象の期間を選びます。</li>
        <li>各項目に入力します。項目は<strong>「本人の願い」「家庭での願い」「目標設定（短期目標・長期目標）」「五領域の課題」「その他の課題」</strong>です。</li>
        <li>途中で保存したいときは<strong>「下書き保存」</strong>を押します。あとから続きを入力できます。</li>
        <li>入力が終わったら<strong>「提出する」</strong>を押します。確認メッセージが出るので<strong>「OK」</strong>を押すと提出が完了します。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 一度<strong>「提出する」</strong>を押すと、その後は内容を変更できません。保護者には「まだ書き途中のときは下書き保存を使い、最後に提出してください」と案内してください。過去に提出した内容は<strong>「アセスメント履歴」</strong>から確認できます。
        </p>
      </div>

      {/* 個別支援計画書の同意 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">5. 個別支援計画書を確認・同意する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        事業所が作成した個別支援計画書を保護者が確認し、<strong>承認・コメント・署名</strong>のいずれかを行う機能です。確認待ちの計画書があるときは、画面上部に案内が表示されます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューの<strong>「個別支援計画書」</strong>を開きます。お子様ごとに計画書がカードで表示されます。</li>
        <li>確認が必要な計画書には<strong>「確認待ち」</strong>の表示が付いています。カード右上の<strong>「内容を確認する」</strong>を押します。</li>
        <li>開いた画面で、本人の意向・援助の方針・目標・支援内容などを確認します。</li>
        <li>次のいずれかの方法で対応します。
          <ul className="ml-6 mt-2 list-disc space-y-1">
            <li>内容に問題がなければ<strong>「変更なし（承認）」</strong>を押します。</li>
            <li>署名を希望する場合は、署名欄に指（またはマウス）で署名を書き、<strong>「署名して確認」</strong>を押します。</li>
            <li>変更を希望する場合は<strong>「コメントを送る」</strong>を押し、変更してほしい内容を入力して<strong>「コメントを送信」</strong>を押します。</li>
          </ul>
        </li>
        <li>対応が完了すると、計画書に<strong>「署名済み」</strong>または<strong>「レビュー済み」</strong>の表示が付きます。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 署名は任意です。承認だけでも手続きは進みますが、書面での同意を求める運用の場合は「署名して確認」を案内してください。面談の場で紙に署名いただく運用の場合も、アプリ上ではまず内容確認をしていただくと行き違いが減ります。
        </p>
      </div>

      {/* モニタリング表 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">6. モニタリング表を確認・署名する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        計画に対する達成状況をまとめたモニタリング表を保護者が確認し、署名する機能です。手順は個別支援計画書とほぼ同じです。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューの<strong>「モニタリング表」</strong>を開きます。確認待ちのものには<strong>「確認待ち」</strong>の表示が付きます。</li>
        <li>対象のモニタリング表を開き、達成状況やコメントを確認します。</li>
        <li><strong>「署名して確認」</strong>を押し、署名欄に署名を記入して確定します。</li>
        <li>完了すると<strong>「確認済み」</strong>の表示に変わり、確認日時が記録されます。</li>
      </ol>

      {/* 面談予約 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">7. 面談予約を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        申し込んだ面談の状況や、事業所が確定した日時を確認する機能です。面談の申し込みそのものはチャットの<strong>「面談申込」</strong>から行います。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューの<strong>「面談予約」</strong>を開きます。申し込み済みの面談が一覧で表示されます。</li>
        <li>各面談には状況（回答待ち・確定など）が表示されます。確定した面談には確定日時が表示されます。</li>
        <li>面談を押すと、目的・担当スタッフ・候補日時などの詳細が確認できます。</li>
      </ol>

      {/* 事業所評価 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">8. 事業所評価アンケートに回答する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        事業所評価アンケートに保護者が回答する機能です。回答依頼があるときはダッシュボードに案内が表示されます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューの<strong>「事業所評価」</strong>を開きます。</li>
        <li>各設問に、<strong>「はい」「どちらともいえない」「いいえ」「わからない」</strong>から選んで回答します。必要に応じてコメントも入力できます。</li>
        <li>すべて回答したら、画面の案内に従って提出します。</li>
      </ol>

      {/* その他 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">その他の機能</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>週間計画表</strong> — メニューの「週間計画表」から、週ごとの活動予定を確認できます。</li>
        <li><strong>お知らせ・施設通信</strong> — 事業所からのお知らせや通信を読めます。</li>
        <li><strong>プロフィール</strong> — 登録されている保護者情報を確認できます。</li>
        <li><strong>パスワード変更</strong> — ログイン用のパスワードを変更できます。パスワードが分からなくなった場合は、事業所側での対応が必要になることがあります。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 保護者への案内時は、「毎回まずダッシュボードを開き、『確認が必要な項目』を上から順に対応してもらう」という流れを伝えると、連絡帳の確認漏れや計画書・アセスメントの提出遅れを防ぎやすくなります。
        </p>
      </div>
    </div>
  );
}
