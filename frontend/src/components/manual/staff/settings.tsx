export default function ManualSection() {
  return (
    <div className="space-y-4">
      {/* イントロ */}
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        マスタ管理では、教室の基本情報・スタッフのアカウント・支援案で使うタグ（分類）・毎日の支援（日課）など、システム全体の土台となる設定を行います。ここで整えた内容が、連絡帳・支援案・個別支援計画などの各機能に反映されます。
      </p>

      {/* どこで設定するか */}
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 設定項目は「管理者メニュー」で行うものと、「スタッフメニュー」で行うものに分かれています。教室名や事業所そのものの追加・削除など重要な設定は管理者権限が必要です。ご自身のメニューに項目が表示されない場合は、権限が不足している可能性があります。管理者にご確認ください。
        </p>
      </div>

      {/* ============ 教室基本設定 ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">教室基本設定（管理者）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        教室の住所・電話番号や、加算・機能のオンオフを設定します。管理者メニューの「教室基本設定」から開きます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>管理者でログインし、左メニューの「教室基本設定」を開きます。</li>
        <li>教室のカードに、現在の住所・電話番号が表示されます。</li>
        <li>「住所」「電話番号」を必要に応じて入力・修正します。</li>
        <li>必要に応じて「能力評価システムを使う」「欠席時対応加算を算定する」のチェックを切り替えます。</li>
        <li>右下の「設定を保存」を押すと、保存完了のメッセージが表示されます。</li>
      </ol>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>教室名</strong>: この画面では変更できません（マスター管理者のみ変更可能）。灰色で表示されます。</li>
        <li><strong>能力評価システムを使う</strong>: オンにすると、日々の活動記録の入力時に児童ごとの設問が表示され、個別支援計画の作成時に発達段階別の参考データとして使えます。</li>
        <li><strong>欠席時対応加算を算定する</strong>: 欠席児童に対して加算を取る事業所はオンにします。オンのとき「未送信日誌一覧」から欠席時対応の記録（加算様式）を作成でき、月次利用日数の一覧に算定回数（上限 月4回／児童）が集計されます。</li>
        <li><strong>教室ロゴ</strong>: 登録済みの場合はここに表示されます。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 施設単位で「AI学習基盤への参加（施設）」のカードが表示される場合があります。これは品質改善のための統計利用への同意設定で、変更できるのは企業管理者・マスター管理者のみです。オンにしても、個人を特定しない集計のみに利用されます。
        </p>
      </div>

      {/* ============ 事業所管理（教室管理） ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">事業所管理（マスター管理者）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        事業所（教室）そのものの新規作成・編集・無効化・削除を行います。マスター管理者メニューの「教室管理」から開きます。複数の事業所を運営する場合の親メニューです。
      </p>
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">事業所を新規に追加する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「教室管理」画面を開きます。事業所の一覧が表として表示されます。</li>
        <li>右上の「新規作成」を押します。</li>
        <li>「事業所名」（必須）、「住所」、「電話番号」を入力します。</li>
        <li>「作成」を押すと一覧に追加されます。</li>
      </ol>
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">事業所を編集・停止・削除する</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>編集</strong>: 一覧の「編集」を押し、事業所名・住所・電話番号を修正して「更新」を押します。</li>
        <li><strong>ステータスの切り替え</strong>: 一覧の「有効／無効」バッジを押すと、確認のうえで有効・無効を切り替えられます。</li>
        <li><strong>無効化（削除→無効化）</strong>: 「削除」を押すと選択画面が開きます。「無効化」は元に戻せる停止です。生徒が在籍中の事業所は拒否されます。</li>
        <li><strong>完全削除</strong>: 同じ画面の「完全削除」はデータを物理的に消す操作で、<strong>取り消せません</strong>。生徒・スタッフ・記録・写真などが1件でも残っていれば自動で拒否されます。テスト用や誤って作成した事業所を整理する場合のみ使ってください。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 「完全削除」は取り消しができません。運用中の事業所を一時的に止めたいだけの場合は、必ず「無効化」を使ってください。
        </p>
      </div>

      {/* ============ スタッフ管理 ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">スタッフ管理（管理者）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        スタッフ・管理者のアカウントを登録・編集・削除し、所属教室を割り当てます。管理者メニューの「スタッフ管理」から開きます。
      </p>
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">新しいスタッフを登録する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「スタッフ管理」画面を開きます。登録済みスタッフの一覧が表示されます。</li>
        <li>右上の「新規スタッフ登録」を押します。</li>
        <li>「ユーザー名」（ログインに使う半角英数字）を入力します。</li>
        <li>「パスワード」（6文字以上）を入力します。</li>
        <li>「氏名」を入力します（必須）。必要に応じて「メールアドレス」も入力します。</li>
        <li>企業管理者の場合は「種別」（スタッフ／通常管理者）と「所属教室」を選べます。</li>
        <li>「登録」を押すと一覧に追加されます。</li>
      </ol>
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">スタッフ情報を編集・削除する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>一覧から対象スタッフの行にある「編集」を押します。</li>
        <li>氏名・メールアドレス・ステータス（有効／無効）を変更できます。</li>
        <li>パスワードを変える場合のみ「新しいパスワード」を入力します（変えない場合は空欄のままにします）。</li>
        <li>「更新」を押して保存します。</li>
        <li>削除する場合は行の「削除」を押し、確認画面で「削除する」を押します（この操作は取り消せません）。</li>
      </ol>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>ユーザー名</strong>は登録後に変更できません。編集画面では灰色で表示されます。</li>
        <li>行の「所属教室」を押すと、複数教室の割り当てを設定できます（企業で複数の教室がある場合）。</li>
        <li>上部の検索欄に氏名やユーザー名を入力すると絞り込めます。企業管理者は教室でも絞り込めます。</li>
        <li>種別のバッジは、企業管理者・通常管理者・スタッフを色分けで表します。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> スタッフがパスワードを忘れた場合は、このスタッフ管理画面の「編集」から新しいパスワードを設定して伝えてあげてください。
        </p>
      </div>

      {/* ============ タグ設定（プログラム分類） ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">タグ設定（活動の分類）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        支援案の作成時に選べるタグ（活動の分類ラベル）を、教室ごとに自由に設定します。スタッフメニューの「タグ設定」から開きます。初期状態では「動画」「食」「学習」「イベント」「その他」が用意されています。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>スタッフメニューの「タグ設定」を開きます。現在のタグが番号付きで一覧表示されます。</li>
        <li>タグを増やす場合は「タグを追加」を押し、追加された欄にタグ名を入力します。</li>
        <li>既存のタグを直すときは、各行の入力欄でタグ名を書き換えます。</li>
        <li>不要なタグは、その行のごみ箱アイコン（削除ボタン）で消します。</li>
        <li>右下の「保存する」を押すと保存され、支援案作成時の選択肢に反映されます。</li>
      </ol>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>デフォルトに戻す</strong>: 「デフォルトに戻す」を押すと、確認のうえ初期タグ（動画／食／学習／イベント／その他）にリセットされます。現在の設定は失われます。</li>
        <li><strong>キャンセル</strong>: 保存せずに支援案一覧へ戻ります。入力中の変更は保存されません。</li>
        <li>タグは教室ごとに設定でき、活動の分類に使われます。</li>
      </ul>
      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 空欄のタグは保存されません。少なくとも1つはタグを入力してください。すべて空欄のまま保存しようとするとエラーになります。
        </p>
      </div>

      {/* ============ 毎日の支援（日課設定） ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">毎日の支援（日課設定）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「おやつの時間」「帰りの会」など毎日行うルーティーン活動を、最大10個まで登録できます。登録した内容は、支援案の作成時に「毎日の支援を引用」からワンタッチで追加できます。スタッフメニューの「日課設定」から開きます。
      </p>
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">毎日の支援を登録する</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>スタッフメニューの「日課設定」を開きます。番号付きの入力枠（最初は5つ）が表示されます。</li>
        <li>各枠の「活動名」に活動の名前を入力します（例: おやつの時間、帰りの会）。入力すると枠が緑色に変わります。</li>
        <li>「実施時間」に、その活動にかかる時間を分単位で入力します（例: 30）。</li>
        <li>「活動内容」に、具体的な内容を記入します。</li>
        <li>枠が足りない場合は「＋ 毎日の支援を追加」を押して枠を増やします（最大10個まで）。</li>
        <li>下の「保存する」を押すと登録されます。</li>
      </ol>
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">並び替え・削除（新機能）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        登録した「毎日の支援」は、表示の順番を入れ替えたり、不要な項目を取り除いたりできます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>並び替え</strong>: 各枠の右上にある上向き（↑）・下向き（↓）の矢印ボタンで、その項目を1つ上または下に移動できます。先頭の項目は「上へ」、末尾の項目は「下へ」が押せなくなります。</li>
        <li><strong>削除</strong>: 各枠の右上にある赤い「削除」ボタンを押すと、確認のうえその枠を一覧から外せます。</li>
        <li>並び替え・削除をしたあとは、必ず下の「保存する」を押して確定してください。保存した並び順が、そのまま次回以降の表示順になります。</li>
      </ol>
      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> 並び替えや削除は「保存する」を押して初めて確定します。保存する前に「キャンセル」を押すと、変更はすべて取り消され、保存済みの状態に戻ります。
        </p>
      </div>
      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 現在の登録数は画面下部に「現在 ○ / 10 件」と表示されます。よく使う活動を上に並べておくと、支援案作成時に引用しやすくなります。
        </p>
      </div>

      {/* ============ 日課管理（時間割・管理者） ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">日課管理（管理者・時間割形式）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        管理者メニューには、開始時間・終了時間つきで日課（タイムスケジュール）を管理する画面もあります。教室単位の1日の流れを時系列で登録できます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>管理者メニューの「日課管理」を開きます。時系列で日課の一覧が表示されます。</li>
        <li>右上の「追加」を押します。</li>
        <li>「日課名」、「開始時間」、「終了時間」を入力します（時間はいずれも必須）。</li>
        <li>必要に応じて「説明」を入力し、「有効」のチェックで表示のオンオフを決めます。</li>
        <li>「保存」を押すと一覧に追加されます。</li>
        <li>既存の日課は、鉛筆アイコン（編集）で修正、ごみ箱アイコン（削除）で確認のうえ削除できます。</li>
      </ol>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>「有効」のチェックを外した日課は、一覧で薄く表示され「無効」のバッジが付きます。</li>
        <li>特定の教室に紐づく日課には、教室名のバッジが表示されます。</li>
      </ul>

      {/* ============ その他の設定への案内 ============ */}
      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">関連する設定</h3>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>施設通信設定</strong>: 施設通信（おたより）の表示や生成に関する設定を行います。スタッフメニューの「施設通信設定」から開きます。</li>
        <li><strong>休日設定・休日管理</strong>: 年間の祝日・休業日を設定します（スタッフ／管理者メニュー）。</li>
        <li><strong>学校休業日活動設定</strong>: 長期休みなどの活動に関する設定を行います（スタッフメニュー）。</li>
        <li><strong>スタッフアカウント・管理者アカウント</strong>: マスター管理者は、専用メニューからアカウントをまとめて管理できます。</li>
      </ul>
    </div>
  );
}
