export default function ManualSection() {
  return (
    <div className="space-y-4">
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        「チャット」は、お子様が通う事業所（施設）の職員の方と、メッセージでやり取りするための画面です。
        ちょっとした連絡や質問はもちろん、写真や書類などのファイルを送ることもできます。
        また、この画面からは「欠席の連絡」「イベントへの参加申込」「面談の申し込み」といった、決まった内容の連絡も送れます。
        このページを読めば、はじめての方でも迷わずメッセージを送れるようになります。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ざっくりした流れ:</strong> ①メニューから「チャット」を開く → ②お子様の名前が付いたチャットを選ぶ →
          ③下の入力欄にメッセージを入力して送信、という3ステップです。まずはこの基本を覚えれば大丈夫です。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">1. チャットの画面を開く</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        まずはやり取りしたいお子様のチャットを開きます。チャットは、お子様（生徒）ごとに1つずつ用意されています。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>メニューから「チャット」を開きます。画面の見出しに「チャット」と表示されます。</li>
        <li>画面に、お子様の名前が付いたチャットの一覧が並びます。名前の左には、お名前の最初の1文字が入った丸いマークが表示されます。</li>
        <li>やり取りしたいお子様のチャットを1回押して開きます。</li>
        <li>お子様が複数いる場合など、目的のチャットを探したいときは、上部の「お子様の名前で検索...」と書かれた入力欄に名前を入力すると、一覧をしぼり込めます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> チャットの一覧では、いちばん新しいメッセージの一部と、届いた時間の目安が表示されます。
          まだ読んでいない（未読の）メッセージがあるチャットには、件数を示す赤い数字のマークが付きます。
          そのチャットを開くと、メッセージは自動的に「読んだ状態（既読）」になります。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">2. メッセージを送る</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        チャットを開くと、画面の上側にこれまでのやり取り、いちばん下にメッセージの入力欄が表示されます。
        入力欄の少し上にある種類の選び方が「通常」になっていることを確認してください（初めて開いたときは「通常」が選ばれています）。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>画面いちばん下の「メッセージを入力...」と書かれた欄を1回押します。</li>
        <li>送りたい内容を入力します。文章を改行して次の行に書きたいときは、キーボードの Shift キーを押しながら Enter キーを押します。</li>
        <li>入力し終えたら、欄の右にある紙飛行機のマークのボタン（送信ボタン）を押します。</li>
        <li>送信すると、あなたのメッセージが画面の右側に表示されます。入力欄は自動的に空になり、続けて次のメッセージを入力できます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> Enter キーだけでは送信されません。うっかり送ってしまうのを防ぐため、送信は必ず紙飛行機のマークのボタンで行う仕組みになっています。
          Enter キーは、入力欄では特に何も起きません（改行したいときは Shift + Enter を使います）。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">3. 写真やファイルを添付して送る</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        メッセージには、写真やPDFなどのファイルを1つ添付して送ることができます。
        入力欄の左にある「クリップ（ゼムクリップ）のマーク」のボタンから選びます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>入力欄のいちばん左にあるクリップのマークのボタンを押します。</li>
        <li>お使いの端末のファイル選択画面が開くので、送りたい写真やファイルを1つ選びます。</li>
        <li>選ぶと、入力欄の上にファイル名とファイルの大きさが表示され、添付の準備ができます。</li>
        <li>必要に応じてメッセージの文章も入力し、紙飛行機のマークの送信ボタンを押すと、ファイルと一緒に送信されます。</li>
        <li>添付をやめたいときは、表示されているファイル名の右にある「×」のマークを押すと取り消せます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ファイルの種類と大きさについて:</strong> 写真（画像）を選ぶと、送る前に自動で小さいサイズに圧縮されます（およそ300KB以下）。
          そのため大きな写真でもそのまま選んで大丈夫です。「画像を圧縮しています…」と表示されている間は、少しお待ちください。
          写真以外のファイル（PDF・文書・表計算・動画・音声など）は、3MB（メガバイト）までのものが送れます。
          これより大きいファイルを選ぶと「ファイルサイズは 3MB 以下にしてください」と表示されますので、小さいファイルをお選びください。
        </p>
      </div>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 添付できるのは1回につき1つのファイルです。複数の写真を送りたいときは、
          1枚ずつ、送信をくり返してください。送られた写真はやり取りの中に小さく表示され、押すと大きく開けます。
          写真以外のファイルは、ファイル名を押すとダウンロードして開けます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">4. 相手が読んだかどうかを確認する（既読）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        自分が送ったメッセージは、やり取りの右側に並びます。各メッセージの下には送った時刻が表示されます。
        事業所の職員の方がそのメッセージを読むと、時刻のとなりに「既読」と表示されます。「既読」が付いていない間は、まだ読まれていない状態です。
      </p>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> やり取りは日付ごとに区切られて表示されます。過去のやり取りをさかのぼりたいときは、画面上部に出る
          「過去のメッセージを読み込む」を押すと、古いメッセージが追加で表示されます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">5. 自分が送ったメッセージを取り消す（削除）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        自分が送ったメッセージは、あとから削除できます（相手（職員）が送ったメッセージは削除できません）。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>削除したい自分のメッセージにカーソルを合わせる（スマートフォンでは軽く触れる）と、メッセージの横にゴミ箱のマークのボタンが表示されます。</li>
        <li>ゴミ箱のマークを押します。</li>
        <li>「このメッセージを削除しますか？」という確認が出るので、「OK」を押すと削除されます。</li>
        <li>削除したメッセージは「このメッセージは削除されました」という表示に変わります。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">6. 大切なメッセージをあとで見返せるようにする（アーカイブ）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        あとで見返したい大切なメッセージには、しおり（ブックマーク）を付けておけます。これを「アーカイブ」といいます。
        アーカイブしても、ふだんのやり取りからメッセージが消えるわけではありません。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>アーカイブしたいメッセージにカーソルを合わせる（スマートフォンでは軽く触れる）と、しおりのマークのボタンが表示されます。</li>
        <li>しおりのマークを押すと、そのメッセージがアーカイブされます（もう一度押すと解除できます）。</li>
        <li>アーカイブしたメッセージだけを見たいときは、画面右上の「アーカイブ」ボタンを押します。アーカイブしたメッセージの一覧に切り替わります。</li>
        <li>ふだんのやり取りに戻るときは、もう一度「アーカイブ」ボタンを押します。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">7. 「出発」「帰宅」をかんたんに知らせる</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        チャット画面には、よく使う連絡をワンタッチで送れるボタンが用意されています。
        お迎えなどで事業所へ向かうときや、お子様と帰宅したときに、文章を入力しなくても状況を伝えられます。
      </p>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>やり取りの下にある「今出発しました」または「帰宅しました」のボタンを押します。</li>
        <li>「施設側に『今出発しました』と通知します。よろしいですか？」という確認が出ます（「帰宅しました」の場合も同様です）。</li>
        <li>内容を確認して「OK」を押すと、事業所へ連絡が送られます。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-danger-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>注意:</strong> これらのボタンを押すと、事業所へすぐに連絡が届きます。押し間違いを防ぐため確認が表示されますので、
          内容をよく確かめてから「OK」を押してください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">8. 決まった内容の連絡を送る（欠席・イベント・面談）</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        チャット画面では、ふつうのメッセージ（「通常」）のほかに、決まった形式の連絡を送ることができます。
        入力欄の少し上に「通常」「欠席連絡」「イベント参加」「面談申込」という切り替えボタンが並んでいます。
        送りたい連絡の種類を押すと、その連絡に合わせた入力フォームに切り替わります。
      </p>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">欠席の連絡を送る</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>種類の切り替えで「欠席連絡」を押します。</li>
        <li>「欠席日」に、お休みする日を選びます（必ず入力する項目です）。</li>
        <li>「理由」に、体調不良や家庭の都合など、お休みの理由を入力します（任意です）。</li>
        <li>「振替について」で、別の日に振り替えるかどうかを選びます。「後日決める」または「今すぐ日にちを決める」から選べます。</li>
        <li>「今すぐ日にちを決める」を選んだ場合は、あとから出てくる「振替希望日」に希望する日を入力します。</li>
        <li>入力できたら「送信」ボタンを押します。「欠席連絡を送信しました」と表示されれば完了です。</li>
      </ol>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">イベントへの参加を申し込む</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>種類の切り替えで「イベント参加」を押します。</li>
        <li>「イベント」の欄を押して、参加したいイベントを一覧から選びます（必ず選ぶ項目です）。イベント名と開催日が表示されます。</li>
        <li>伝えたいことがあれば「備考」に入力します（任意です）。</li>
        <li>「送信」ボタンを押します。「イベント参加申込を送信しました」と表示されれば完了です。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-info-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>補足:</strong> 今のところ参加できるイベントがない場合は、「現在参加可能なイベントはありません」と表示されます。
          この場合は申し込みできませんので、しばらくたってからご確認ください。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">面談を申し込む</h3>
      <ol className="ml-6 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li>種類の切り替えで「面談申込」を押します。</li>
        <li>「面談目的」を一覧から選びます（必ず選ぶ項目です）。個別支援計画・モニタリング・進路相談・学習相談・生活や行動について・その他、から選べます。</li>
        <li>相談したい内容など、詳しく伝えたいことがあれば「詳細・補足」に入力します（任意です）。</li>
        <li>「希望日時1」に、面談を希望する日と時刻を入力します（必ず入力する項目です）。</li>
        <li>ほかにも都合のよい候補があれば、「希望日時2」「希望日時3」にも入力しておくと、日程を決めやすくなります（任意です）。</li>
        <li>「送信」ボタンを押します。「面談申込を送信しました」と表示されれば完了です。</li>
      </ol>

      <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--neutral-background-2)] p-4">
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          <strong>ヒント:</strong> 面談の申し込みを送ると、そのあと事業所から日程についての返信が届きます。
          やり取りの中に「面談予約を確認」や「確定内容を確認」といったボタンが表示されたら、それを押すと、
          チャットの画面を離れずに、日程の内容を確認したり返事をしたりできます。
        </p>
      </div>

      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">送信できないときは</h3>
      <p className="text-sm text-[var(--neutral-foreground-2)]">
        送信がうまくいかないときは、画面に理由のメッセージが表示されます。よくある原因と対処は次のとおりです。
      </p>
      <ul className="ml-6 list-disc space-y-2 text-sm text-[var(--neutral-foreground-2)]">
        <li><strong>「送信」ボタンが押せない:</strong> 「必ず入力する項目」（例: 欠席日、面談目的、希望日時1 など）が空のことがあります。空の項目を入力すると押せるようになります。</li>
        <li><strong>ファイルが大きすぎる:</strong> 写真以外のファイルは3MBまでです。大きいファイルは送れません。</li>
        <li><strong>容量がいっぱい:</strong> 事業所ごとに添付ファイルを保存できる容量には上限があります。容量が残りわずかになると画面に注意の表示が出て、いっぱいになると添付が送れないことがあります。その場合はお手数ですが事業所へお知らせください。</li>
        <li><strong>その他のエラー:</strong> 「メッセージの送信に失敗しました。」などが表示された場合は、通信環境を確認し、少し時間をおいてからもう一度お試しください。</li>
      </ul>
    </div>
  );
}
