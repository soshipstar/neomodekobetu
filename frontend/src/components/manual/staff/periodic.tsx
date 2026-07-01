export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        毎日の記録とは別に、一定の周期で作成・提出する書類があります。ここでは「おたより（毎月）」「個別支援計画（6ヶ月ごと）」「モニタリング（計画の途中）」「アセスメント（計画の前）」「事業所評価（年1回）」の5つについて、それぞれの<strong>入口となるメニュー</strong>と<strong>具体的な操作手順</strong>を説明します。
      </p>

      {/* 周期の一覧表 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">周期とメニューの早見表</h3>
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="bg-[var(--brand-80)] text-white">
              <th className="p-3 text-left">時期の目安</th>
              <th className="p-3 text-left">やること</th>
              <th className="p-3 text-left">左メニューの名前</th>
            </tr>
          </thead>
          <tbody>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3 font-medium">毎月</td>
              <td className="p-3">おたより（施設通信）の作成・配信</td>
              <td className="p-3">情報発信 &gt; 施設通信 / 施設通信設定</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
              <td className="p-3 font-medium">6ヶ月ごと</td>
              <td className="p-3">個別支援計画の作成・更新</td>
              <td className="p-3">計画・支援 &gt; 個別支援計画</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3 font-medium">計画作成から約5ヶ月後</td>
              <td className="p-3">モニタリング（達成状況の評価）</td>
              <td className="p-3">計画・支援 &gt; モニタリング</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
              <td className="p-3 font-medium">計画を作る前（半期ごと）</td>
              <td className="p-3">アセスメント（職員・保護者）</td>
              <td className="p-3">アセスメント &gt; アセスメント（職員）/（保護者）</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3 font-medium">年1回</td>
              <td className="p-3">事業所評価アンケートの実施・集計</td>
              <td className="p-3">管理・設定 &gt; 事業所評価</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 期限が近い書類や期限を過ぎた書類は、ダッシュボード（左メニュー「活動管理」）や「保留タスク」にまとめて表示されます。まずはそこで対応が必要な書類を確認すると漏れがありません。
        </p>
      </div>

      {/* ===================================================================== */}
      {/* 1. おたより（毎月） */}
      {/* ===================================================================== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. おたより（施設通信）を作る ― 毎月</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        毎月の保護者向けおたよりを作成し、配信します。入口は左メニュー「施設通信」です。あいさつ文や活動の様子などを、AIで自動生成することもできます。
      </p>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>おたよりを作成・配信する手順</strong></p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「施設通信」を開きます。過去のおたより一覧が表示されます。</li>
        <li>右上の「新規作成」を押します。入力画面（お便り新規作成）が開きます。</li>
        <li>上部で「年」「月」「タイトル」を確認します。年・月を変えると、タイトルと「報告期間」「予定期間」の日付が自動で入り直します。</li>
        <li>「報告期間（活動記録の対象）」と「予定期間（イベント予定の対象）」の日付を必要に応じて調整します。</li>
        <li>いったん下部の「作成」を押して保存します（保存すると、この後のAI生成が使えるようになります）。</li>
        <li>各セクション（あいさつ文、行事予定カレンダー、行事の詳細、活動の様子、行事の結果報告、お願い事項、その他 など）に本文を入力します。</li>
        <li>本文を自動で作りたいときは、上部の「AIで通信を生成」を押すと全セクションが一括生成されます。特定のセクションだけ生成したいときは、そのセクション右上の「AI生成」を押します。</li>
        <li>写真を載せたいセクションでは、そのセクションの「写真を引用」を押し、写真を選んで挿入します。挿入した写真はセクションの下にサムネイルで表示され、右上の×で取り消せます。</li>
        <li>入力できたら下部の「下書き保存」を押します。まだ保護者には公開されません。</li>
        <li>内容が確定したら「配信する」を押し、確認ダイアログで進めると保護者に公開されます。一覧では「配信済み」のバッジが付きます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 入力画面下部の「PDF」「Word」ボタンから、印刷用ファイルを書き出せます。紙で配布したいときに利用してください。
        </p>
      </div>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>毎月使う定型文やAIの指示をあらかじめ設定しておく</strong></p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        左メニュー「施設通信設定」では、おたよりに毎回入れたい内容をあらかじめ決めておけます。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「表示セクション設定」で、おたよりに表示するセクション（施設名、ロゴ、あいさつ文、イベントカレンダー など）をオン・オフで切り替えます。</li>
        <li>「カレンダー形式」で、イベントカレンダーを「リスト形式」か「テーブル形式」から選びます。</li>
        <li>「デフォルトテキスト」で、お願い事項・その他の定型文を入力しておくと、新規作成時に自動で入ります。</li>
        <li>「AI生成指示」で、各セクションをAI生成するときの指示文を入力しておけます（空欄なら標準の指示が使われます）。</li>
        <li>設定を変えたら、右上または下部の「保存」を押します。</li>
      </ul>

      {/* ===================================================================== */}
      {/* 2. 個別支援計画（6ヶ月） */}
      {/* ===================================================================== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. 個別支援計画を作る ― 6ヶ月ごと</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        生徒ごとの個別支援計画を作成・更新します。入口は左メニュー「個別支援計画」です。画面上部には「1. 下書き作成 → 2. 保護者に確認依頼 → 3. 署名して確定」という流れが表示されます。この3ステップに沿って進めます。
      </p>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>計画を新しく作る手順</strong></p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「個別支援計画」を開きます。</li>
        <li>「生徒を選択」の一覧から対象の生徒を選びます。その生徒の計画一覧が下に表示されます。</li>
        <li>「新規作成」を押すと、入力画面が開きます。入力欄は「A. 基本情報・意向」「B. 目標設定」「C. 支援内容」「D. 同意・署名」の4つのまとまりに分かれています。</li>
        <li>「A. 基本情報・意向」で作成年月日、利用児及び家族の生活に対する意向、総合的な支援の方針を入力します。面談記録から意向を作りたいときは「面談から生成」を押します。</li>
        <li>「B. 目標設定」で長期目標・短期目標と、それぞれの達成時期を入力します。</li>
        <li>「C. 支援内容」の表で、各項目の行をクリックすると編集画面が開きます。カテゴリ（本人支援・家族支援・移行支援 など）、支援目標、支援内容、達成時期、担当者、優先順位、留意事項を入力します。行を増やすときは「行を追加」を押します。</li>
        <li>「D. 同意・署名」で管理責任者氏名と同意日を入力します（署名は後の工程でも入力できます）。</li>
        <li>まだ内容がまとまっていなくても、画面下部の「下書き保存」でいつでも保存できます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足（AI生成）:</strong> 画面下部の「AI生成」を押すと、アセスメントや連絡帳などを参照して各欄の下書きが作られます。生成後は画面下部に「AI生成の参照データ（連絡帳の件数・期間、最新モニタリング など）」が表示されるので、内容を必ず確認してから保存してください。
        </p>
      </div>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>保護者に確認を依頼し、署名で確定する手順</strong></p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>下書きを保存した状態で、画面下部の「確認依頼」を押します。この時点の内容が「原案」として保存され、保護者が確認できるようになります。</li>
        <li>保護者が原案にコメントを返すと、入力画面の「原案・保護者コメント・個別支援会議」欄に「保護者コメント」として表示されます。</li>
        <li>保護者に提示する前の会議記録は、同じ欄の「個別支援会議 議事録」で「議事録を追加」から会議日・出席者・協議内容を記録できます。</li>
        <li>保護者コメントと議事録を反映した本案の下書きを作りたいときは、「本案下書きを生成」を押します（原案をもとに一部を追加・削除する形で作られ、変更点が注釈で表示されます）。</li>
        <li>内容が確定したら、画面下部の「署名して確定」を押します。職員署名と管理責任者氏名が必要です。保護者署名も入力できます。</li>
        <li>紙で署名をもらった場合は「紙面でサイン済み」を押すと正式版として確定します。長期目標・短期目標・各項目のいずれかが未入力だと確定できません。</li>
      </ol>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>計画一覧からできること</strong></p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>各計画には状態バッジ（下書き / 確認依頼中 / 署名済み）が付きます。署名済みの計画はボタンが「編集」から「閲覧」に変わります。</li>
        <li>「計画案」「正式版」ボタンから、印刷用の表示を別タブで開けます。</li>
        <li>「根拠」ボタンから、その計画が参照したデータの根拠を確認できます。</li>
        <li>入力画面下部の「PDF」「CSV」から書類を書き出せます。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 内容が空のまま「紙面でサイン済み」で確定してしまうと編集できなくなります。誤って確定した場合は、閲覧画面下部の「下書きに戻す」を押すと再編集できる状態に戻せます（署名情報・確定状態はクリアされます）。
        </p>
      </div>

      {/* ===================================================================== */}
      {/* 3. モニタリング */}
      {/* ===================================================================== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3. モニタリングを実施する ― 計画の途中で評価</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        作成した個別支援計画の達成状況を評価するのがモニタリングです。入口は左メニュー「モニタリング」です。対象となる個別支援計画書を選んでから評価を入力します。
      </p>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>モニタリング表を作る手順</strong></p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「モニタリング」を開きます。</li>
        <li>「生徒を選択」から対象の生徒を選びます。</li>
        <li>右側の「個別支援計画書を選択」で、評価の対象にする計画書（作成日で表示されます）を選びます。</li>
        <li>「モニタリング実施日」を入力します。</li>
        <li>「支援目標の達成状況」の表で、計画の項目ごとに「達成状況」（未着手・進行中・達成・継続中・見直し必要）を選び、「モニタリングコメント」を入力します。</li>
        <li>コメントを自動で作りたいときは、上部の「AIで評価を自動生成」を押すと全項目が生成されます。項目ごとに作りたいときは、各行の「AI生成」を押します。</li>
        <li>「目標の達成状況」で、長期目標・短期目標それぞれの達成状況とコメントを入力します。</li>
        <li>「総合所見」に全体の振り返りを入力します。</li>
        <li>「電子署名」欄で職員署名を手書きし、署名日を入力します。保護者署名は提出後に保護者画面から入力されます。</li>
        <li>途中まで保存したいときは「下書き保存（保護者非公開）」を押します。まだ保護者には公開されません。</li>
        <li>確定して保護者に見せる場合は「作成・提出（保護者公開）」を押します。提出後は閲覧のみとなります。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 個別支援計画書が1つもない生徒はモニタリングできません。画面には「個別支援計画書を作成してから5ヶ月後にモニタリングが可能になります」と案内が表示されます。まず計画を作成してください。既存のモニタリング表は上部のボタン一覧から選んで開き直せるほか、削除やPDF出力もできます。
        </p>
      </div>

      {/* ===================================================================== */}
      {/* 4. アセスメント */}
      {/* ===================================================================== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">4. アセスメントを記入する ― 計画を作る前の見立て</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        アセスメントは、個別支援計画を作る前に子どもの状況を見立てて記録するものです。職員が記入する「アセスメント（職員）」と、保護者に記入を依頼する「アセスメント（保護者）」があり、どちらも左メニューの「アセスメント」のまとまりにあります。
      </p>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>職員アセスメントを入力する手順</strong></p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「アセスメント（職員）」を開きます。</li>
        <li>「生徒を選択」から対象の生徒を選びます。その生徒のアセスメント期間が一覧で表示されます。</li>
        <li>記入したい期間の行を押して開きます。期間には状態バッジ（未入力 / 下書き / 提出済み）と提出期限が表示されます。</li>
        <li>「入力開始」（または既に入力済みなら「編集」）を押します。</li>
        <li>本人の願い、短期目標、長期目標を入力します。</li>
        <li>「5領域」（健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性）と「その他の課題」を入力します。</li>
        <li>連絡帳をもとに下書きを作りたいときは「AI生成」を押します（参照した連絡帳の件数が表示されます）。</li>
        <li>途中の内容は「下書き保存」で保存できます。完成したら「提出」を押します（提出後も内容の修正は可能です）。</li>
        <li>紙で確認したいときは「下書き保存してPDF」を押すと、保存と同時にPDFが書き出されます。</li>
      </ol>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>保護者アセスメントについて</strong></p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>保護者は自分の画面（アセスメント入力）から、家庭での状況・心配事・要望などを記入します。</li>
        <li>保護者が提出した内容は、職員アセスメントの閲覧画面に「保護者記入（提出済み）」として表示され、職員記入分と見比べられます。</li>
        <li>職員記入分を提出すると、保護者側で確認でき、確認されると「保護者確認済み」のバッジが付きます。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> アセスメント期間は自動で用意されます。提出期限を過ぎた期間は期限のバッジが赤く表示されるので、ダッシュボードとあわせて未提出がないか確認してください。
        </p>
      </div>

      {/* ===================================================================== */}
      {/* 5. 事業所評価 */}
      {/* ===================================================================== */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">5. 事業所評価を行う ― 年1回</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        年1回、保護者と職員に事業所の評価アンケートを実施し、集計・公表します。入口は左メニュー「事業所評価」です。評価期間の作成や集計結果・自己評価総括表の閲覧は管理者が行い、スタッフは自分の自己評価回答と回答状況の確認ができます。
      </p>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>評価の進み方（ステータス）</strong></p>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        1つの評価期間は「下書き → 回答収集中 → 集計中 → 公表済み」の順に進みます。各期間のカードにあるボタン（例：「回答収集中へ」）を押して次の段階に進めます。
      </p>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>評価期間を作成する手順（管理者）</strong></p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「事業所評価」を開きます。</li>
        <li>右上の「新規作成」を押します。</li>
        <li>年度、保護者回答〆切、スタッフ回答〆切を入力し、「作成」を押します。</li>
        <li>作成した期間のカードで「回答収集中へ」を押すと、保護者・スタッフが回答できるようになります。</li>
      </ol>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>スタッフの自己評価を回答する手順</strong></p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「回答収集中」になっている評価期間のカードで「自己評価」を押します。</li>
        <li>質問ごとに「はい」「どちらともいえない」「いいえ」から回答を選びます。必要に応じてコメントも入力します。</li>
        <li>「いいえ」を選んだ質問には、改善計画の入力が必要です。</li>
        <li>途中まで保存したいときは「下書き保存」を押します。すべて回答したら「提出する」を押します（提出後は変更できません）。</li>
      </ol>

      <p className="text-sm text-[var(--neutral-foreground-2)]"><strong>集計と公表（管理者）</strong></p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>各期間のカードの「回答状況」から、保護者・スタッフの提出状況（提出済み人数）を確認できます。</li>
        <li>期間を「集計中」に進めた後、「集計結果」を開き「集計を実行」または「再集計」を押すと、質問ごとの「はい」の割合などが表示されます。「保護者評価」「事業所内評価（スタッフ）」のタブで切り替えられます。</li>
        <li>集計結果の各質問には、事業所コメントを入力できます。必要に応じて集計値を手直しすることもできます。</li>
        <li>「自己評価総括表」では、事業所の強み・弱みを整理します。「AI生成」で下書きを作り、内容を確認して「保存」します。</li>
        <li>集計結果・自己評価総括表は、それぞれの画面から「PDF出力」できます。仕上がったら期間を「公表済み」に進めます。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> AIで生成した文章（おたより・計画・モニタリング・アセスメント・自己評価総括表）は、あくまで下書きです。必ず内容を確認し、実態に合わせて修正してから保存・提出してください。
        </p>
      </div>
    </div>
  );
}
