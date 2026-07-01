export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* 導入 */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        個別支援計画・モニタリング・アセスメントには、それぞれ決まった作成順序と提出期限があります。このページでは「いつ・どの書類を・どの順番で作るか」と、システムが自動で計算する期限のルール、そして実際の作成手順を説明します。基本の流れは <strong>アセスメント → 個別支援計画 → モニタリング</strong> の順です。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 対応が必要な書類は、左メニューの「保留タスク」(未作成タスク一覧) にまとまって表示されます。まずここを確認すれば、期限が近い書類・未作成の書類を一覧で把握できます。
        </p>
      </div>

      {/* 全体サイクル */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">書類作成の全体サイクル</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        生徒ごとに、支援開始日を起点とした 6ヶ月ごとの「対象期間」があります。各対象期間で、次の順序で書類を作成していきます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>アセスメント</strong>（職員・保護者の双方が記入）で、その期間の子どもの状況を把握します。</li>
        <li>アセスメントの内容をふまえて<strong>個別支援計画</strong>を作成し、保護者の確認・署名を得て確定します。</li>
        <li>計画の作成から一定期間後に<strong>モニタリング</strong>を実施し、目標の達成状況を評価します。</li>
        <li>モニタリングの結果をふまえ、次の対象期間の<strong>アセスメント・個別支援計画</strong>へ進みます（以降くり返し）。</li>
      </ol>

      {/* 期限ルール */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">期限計算のルール</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        期限はシステムが支援開始日を起点に自動で計算します。手入力ではなく、下記の規則で決まります。
      </p>
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="bg-[var(--brand-80)] text-white">
              <th className="p-3 text-left">書類</th>
              <th className="p-3 text-left">初回の期限</th>
              <th className="p-3 text-left">2回目以降の期限</th>
            </tr>
          </thead>
          <tbody>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3 font-medium">アセスメント</td>
              <td className="p-3">支援開始日の前日</td>
              <td className="p-3">その対象期間の開始日の1ヶ月前</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)]">
              <td className="p-3 font-medium">個別支援計画</td>
              <td className="p-3">アセスメントの期限と同じ（支援開始日の前日）</td>
              <td className="p-3">アセスメントの期限の1ヶ月後</td>
            </tr>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <td className="p-3 font-medium">モニタリング</td>
              <td className="p-3">その計画の期限の5ヶ月後</td>
              <td className="p-3">同じく計画の期限の5ヶ月後（以降6ヶ月ごと）</td>
            </tr>
          </tbody>
        </table>
      </div>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>対象期間は6ヶ月単位です。前の期間の終了日の翌日が、次の期間の開始日になります。</li>
        <li>アセスメント期間はシステムが自動で先まで作成します。期限が近づくと（おおむね1ヶ月前になると）、次の期間が自動で用意されます。</li>
        <li>個別支援計画を確定してからおよそ5ヶ月後にモニタリングの期限が来る仕組みです。これは「計画→実施→振り返り」の期間を確保するためです。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「支援案」(左メニュー) は、上記の3書類とは別物です。支援案は活動日ごとの事前計画（活動内容や五領域への配慮）であり、生徒個人の期限管理の対象ではありません。ここで扱う「個別支援計画」とは名前が似ていますが役割が異なるので、混同しないようご注意ください。
        </p>
      </div>

      {/* 保留タスクの見方 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">まず「保留タスク」で状況を確認する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        どの書類を優先して作るべきかは、左メニューの「保留タスク」から確認します。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「保留タスク」を開きます。「未作成タスク一覧」が表示されます。</li>
        <li>上部に「個別支援計画書」「モニタリング」「保護者アセスメント」「スタッフアセスメント」の件数が並びます。0件でない項目に対応が必要です。</li>
        <li>「カテゴリ」で書類の種類を絞り込み、「並び替え」で「期限が近い順」にすると、急ぎの書類から確認できます。「生徒名で検索」で特定の生徒に絞ることもできます。</li>
        <li>各行の右側にあるボタン（「計画書を作成」「モニタリング作成」「作成する」「確認依頼」など）を押すと、その書類の作成画面へ移動します。</li>
      </ol>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        状態バッジの意味は次の通りです。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>未作成</strong> - まだ着手していない書類です。</li>
        <li><strong>下書き</strong> - 作成中で、まだ提出していない状態です。</li>
        <li><strong>要保護者確認</strong> - 提出済みで、保護者の確認・署名を待っている状態です。</li>
        <li><strong>緊急（残り○日）</strong> - 期限が間近（おおむね1週間以内）です。優先して対応してください。</li>
        <li><strong>期限切れ（○日超過）</strong> - 期限を過ぎています。早急に対応してください。</li>
      </ul>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 対応が終わっていて一覧に残しておきたくない項目は、行の「非表示」ボタンで一覧から隠せます（データは削除されません）。誤って隠したくないものまで消さないよう、内容を確認してから押してください。
        </p>
      </div>

      {/* アセスメント */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">手順1: アセスメントを作成する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        アセスメントは職員と保護者の双方が記入します。職員側の記入は「アセスメント（職員）」、保護者への確認・催促は「アセスメント（保護者）」で行います。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「アセスメント（職員）」を開きます（または保留タスク一覧の該当行の「作成する」から移動します）。</li>
        <li>対象の生徒とアセスメント期間を選びます。</li>
        <li>本人の願い、短期・長期目標、五領域の評価や所見などを記入します。過去のデータをもとにAIで下書きを生成することもできます。</li>
        <li>内容を保存します。必要に応じてPDFで出力できます。</li>
        <li>保護者の記入・確認が必要な場合は「アセスメント（保護者）」から確認・催促を行います。</li>
      </ol>

      {/* 個別支援計画 */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">手順2: 個別支援計画を作成・提出・確定する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        アセスメントの内容をふまえて個別支援計画を作成します。作成は「下書き → 計画書案として提出（保護者確認） → 正式版として確定」の順に進みます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「個別支援計画」を開きます（または保留タスク一覧の「計画書を作成」から移動します）。一覧から対象の生徒を選びます。</li>
        <li>新規に作る場合は作成画面を開きます。画面は「A. 基本情報・意向」「B. 目標設定」「C. 支援内容」「D. 同意・署名」の4つのまとまりに分かれています。上部の見出しボタンで各セクションへ移動できます。</li>
        <li><strong>A. 基本情報・意向:</strong> 作成年月日、利用児及び家族の意向、総合的な支援の方針を入力します。意向は「面談から生成」ボタンで面談記録をもとにAIで下書きできます。</li>
        <li><strong>B. 目標設定:</strong> 長期目標・短期目標と、それぞれの達成時期を入力します。</li>
        <li><strong>C. 支援内容:</strong> 支援領域ごとの目標・支援内容・達成時期・担当者などを入力します。行をクリックすると入力用の画面が開きます。「行を追加」で項目を増やせます。</li>
        <li><strong>D. 同意・署名:</strong> 管理責任者氏名、同意日を入力し、職員・保護者の署名欄を確認します。</li>
        <li>画面下部の「下書き保存」で途中保存できます。この時点では保護者には公開されません。</li>
        <li>内容が整ったら「計画書案として提出」を押します。保護者のダッシュボードに署名依頼が表示され、状態は「確認依頼中」になります。</li>
        <li>保護者の確認・署名が済んだら「正式版として確定」を押します。確定後は「署名済み（正式版）」となり、以降は閲覧のみ（編集不可）になります。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 「正式版として確定」を行うと編集できなくなります。内容が空のまま確定しないよう、必ず各セクションの入力を確認してから確定してください。万一、中身が空のまま確定してしまった場合は、計画を下書きに戻して修正できる場合があります。判断に迷うときは管理者に相談してください。
        </p>
      </div>

      {/* モニタリング */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">手順3: モニタリングを実施する</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        モニタリングは、確定した個別支援計画の目標が、どれくらい達成できているかを評価する書類です。<strong>個別支援計画を作成してからおよそ5ヶ月後</strong>に実施のタイミング（期限）が来ます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>左メニューの「モニタリング」を開きます（または保留タスク一覧の「モニタリング作成」から移動します）。</li>
        <li>「生徒を選択」で対象の生徒を選びます。次に「個別支援計画書を選択」で、評価の対象とする計画（作成日で表示されます）を選びます。</li>
        <li>「モニタリング実施日」を入力します。初期値は本日です。</li>
        <li>「支援目標の達成状況」の表で、各支援目標について「達成状況」（未着手・進行中・達成・継続中・見直し必要など）を選び、「モニタリングコメント」を記入します。過去の連絡帳データをもとに「AIで評価を自動生成」でまとめて下書きすることもできます（生成後は必ず内容を確認してください）。</li>
        <li>「長期目標」「短期目標」それぞれの達成状況とコメント、最後に「総合所見」を記入します。</li>
        <li>職員の署名欄に署名し、署名日を入力します。</li>
        <li>途中の場合は「下書き保存（保護者非公開）」で保存します。完成したら「作成・提出（保護者公開）」を押すと保護者に公開され、保護者が署名できるようになります。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 「この生徒にはまだモニタリング対象の個別支援計画書がありません」と表示される場合、まだ計画を作成していないか、計画作成から5ヶ月が経っていない可能性があります。先に個別支援計画を確定してください。また、生徒の設定が「次回の期間から個別支援計画を作成する」になっている場合は、当面は連絡帳のみの利用となり、モニタリングはまだ作成できません。
        </p>
      </div>

      {/* まとめ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">運用のポイント</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>毎日または定期的に「保留タスク」を確認し、期限が近い書類・未作成の書類を早めに着手しましょう。</li>
        <li>期限はシステムが自動計算します。支援開始日が正しく登録されていないと、すべての期限がずれてしまうため、支援開始日は正確に登録してください。</li>
        <li>AIによる下書きはあくまで補助です。生成後は必ず内容を確認・修正してから保存・提出してください。</li>
        <li>保護者の確認・署名が必要な書類（個別支援計画・モニタリング・アセスメント）は、提出後に「要保護者確認」として残ります。保護者への声かけも忘れずに行いましょう。</li>
      </ul>
    </div>
  );
}
