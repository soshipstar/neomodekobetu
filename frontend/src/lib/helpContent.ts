import type { UserType } from '@/types/user';

export interface HelpItem {
  question: string;
  answer: string;
  category: string;
}

export interface HelpCategory {
  id: string;
  icon: string;
  title: string;
  items: HelpItem[];
}

export interface HelpData {
  categories: HelpCategory[];
}

/**
 * パスからカテゴリIDを推定するマッピング
 */
const staffPageToCategory: Record<string, string> = {
  'renrakucho': 'activity',
  'activities': 'activity',
  'support-plans': 'support_plan',
  'daily-routines': 'support_plan',
  'tag-settings': 'support_plan',
  'kakehashi': 'kakehashi',
  'kobetsu-plan': 'plan',
  'kobetsu-monitoring': 'plan',
  'weekly-plans': 'plan',
  'students': 'user',
  'guardians': 'user',
  'bulk-register': 'user',
  'chat': 'chat',
  'student-chats': 'chat',
  'newsletters': 'other',
  'holidays': 'other',
  'makeup-requests': 'other',
  'events': 'other',
};

const guardianPageToCategory: Record<string, string> = {
  'dashboard': 'dashboard',
  'communication': 'communication',
  'chat': 'chat',
  'weekly-plan': 'weekly',
  'kakehashi': 'kakehashi',
  'newsletters': 'newsletter',
  'support-plans': 'plan',
  'monitoring': 'plan',
  'manual': 'manual',
  'profile': 'profile',
  'change-password': 'profile',
};

const studentPageToCategory: Record<string, string> = {
  'dashboard': 'dashboard',
  'chat': 'chat',
  'weekly-plan': 'weekly',
  'schedule': 'schedule',
  'submissions': 'submissions',
};

/**
 * パスからデフォルトカテゴリIDを取得
 */
export function getDefaultCategoryId(pathname: string, userType: UserType): string | null {
  // パスの最後のセグメントを取得
  const segments = pathname.split('/').filter(Boolean);
  const lastSegment = segments[segments.length - 1] || '';
  const secondToLast = segments[segments.length - 2] || '';

  let mapping: Record<string, string>;

  switch (userType) {
    case 'guardian':
      mapping = guardianPageToCategory;
      break;
    case 'student':
      mapping = studentPageToCategory;
      break;
    case 'admin':
    case 'staff':
    default:
      mapping = staffPageToCategory;
      break;
  }

  return mapping[lastSegment] || mapping[secondToLast] || null;
}

// ========================================
// スタッフ向けヘルプデータ
// ========================================
const staffHelpData: HelpData = {
  categories: [
    {
      id: 'activity',
      icon: 'edit_note',
      title: '活動管理・連絡帳',
      items: [
        {
          question: '活動を登録するには？',
          answer: '【活動の登録方法】\n\n1. メニューから「活動管理」を開く\n2. 「新しい活動を追加」ボタンをクリック\n3. 活動名、日付、内容を入力\n4. 参加者を選択\n5. 「保存」ボタンで登録完了',
          category: 'activity',
        },
        {
          question: '連絡帳を保護者に送るには？',
          answer: '【連絡帳の送信方法】\n\n1. 活動管理で活動を登録\n2. 「統合する」ボタンをクリック（AIが連絡帳を自動生成）\n3. 「統合内容を編集」で内容を確認・修正\n4. 「連絡帳を送信」ボタンで保護者に配信\n\n※統合には数秒かかります',
          category: 'activity',
        },
        {
          question: '活動を編集・削除するには？',
          answer: '【活動の編集・削除】\n\n■ 編集\n活動一覧から該当の活動の「編集」ボタンをクリック\n\n■ 削除\n「削除」ボタンをクリック（確認ダイアログが表示されます）\n\n※送信済みの連絡帳は削除できません',
          category: 'activity',
        },
        {
          question: '統合とは何ですか？',
          answer: '【統合機能について】\n\n「統合」は、登録した活動内容をもとに、AIが参加者ごとの連絡帳を自動生成する機能です。\n\n■ 統合する：AIが連絡帳を生成\n■ 統合内容を編集：生成された内容を確認・修正\n■ 送信済み内容を閲覧：送信後の内容を確認',
          category: 'activity',
        },
      ],
    },
    {
      id: 'support_plan',
      icon: 'assignment',
      title: '支援案',
      items: [
        {
          question: '支援案を作成するには？',
          answer: '【支援案の作成方法】\n\n1. メニューから「支援案」を開く\n2. 「新しい支援案を作成」ボタンをクリック\n3. 活動名、目的、内容、五領域への配慮を入力\n4. 「保存」で登録完了\n\n作成した支援案は活動登録時に選択して使用できます',
          category: 'support_plan',
        },
        {
          question: '毎日の支援とは？',
          answer: '【毎日の支援について】\n\n「朝の会」「帰りの会」など、毎日行う定例活動を設定できます。\n\n1. 支援案画面で「毎日の支援を設定」をクリック\n2. 活動名と内容を入力\n3. 保存\n\n設定した活動は、活動登録画面で簡単に追加できます',
          category: 'support_plan',
        },
        {
          question: 'タグの使い方は？',
          answer: '【タグについて】\n\n支援案を分類するためのラベルです。\n\n■ タグの設定\n支援案画面で「タグを設定」から管理\n\n■ タグの活用\n・支援案作成時にタグを付ける\n・検索時にタグでフィルター\n\nデフォルト：動画、食、学習、イベント、その他',
          category: 'support_plan',
        },
      ],
    },
    {
      id: 'kakehashi',
      icon: 'handshake',
      title: 'かけはし',
      items: [
        {
          question: 'かけはしとは？',
          answer: '【かけはしについて】\n\n個別支援計画書を作成するための情報収集ツールです。\n\n■ かけはし（職員）\nスタッフが生徒の様子や目標を記入\n\n■ かけはし（保護者）\n保護者が家庭での様子や希望を記入\n\n両方の情報をもとに個別支援計画を作成します',
          category: 'kakehashi',
        },
        {
          question: 'かけはしの記入方法は？',
          answer: '【かけはしの記入】\n\n1. メニューから「かけはし（職員）」を開く\n2. 生徒を選択\n3. 提出期限を選択\n4. 各項目を入力（本人の願い、目標、五領域など）\n5. 「下書き保存」または「提出」\n\n※提出期限は個別支援計画の期間に連動しています',
          category: 'kakehashi',
        },
        {
          question: '保護者のかけはしを確認するには？',
          answer: '【保護者かけはしの確認】\n\n1. メニューから「かけはし（保護者）」を開く\n2. 生徒を選択\n3. 提出期限を選択\n4. 保護者が入力した内容を確認\n\n※保護者が未入力の場合は「まだ作成されていません」と表示されます',
          category: 'kakehashi',
        },
      ],
    },
    {
      id: 'plan',
      icon: 'monitoring',
      title: '個別支援計画・モニタリング',
      items: [
        {
          question: '個別支援計画を作成するには？',
          answer: '【個別支援計画の作成】\n\n1. メニューから「個別支援計画」を開く\n2. 生徒を選択\n3. 「新規作成」または「AI生成」ボタン\n4. かけはしの情報をもとに計画を入力\n5. 保存して完了\n\n※かけはしを事前に記入しておくとスムーズです',
          category: 'plan',
        },
        {
          question: 'モニタリングとは？',
          answer: '【モニタリングについて】\n\n個別支援計画の進捗を確認する表です。\n\n■ 作成タイミング\n次の個別支援計画作成の1ヶ月前までに作成\n\n■ 記入内容\n・目標の達成状況\n・課題と改善点\n・次期への引き継ぎ事項',
          category: 'plan',
        },
        {
          question: '週間計画の使い方は？',
          answer: '【週間計画について】\n\n生徒ごとの週間目標・計画を設定できます。\n\n1. メニューから「週間計画」を開く\n2. 生徒と週を選択\n3. 各曜日の目標や活動を入力\n4. 保存\n\n保護者も週間計画を確認できます',
          category: 'plan',
        },
      ],
    },
    {
      id: 'user',
      icon: 'group',
      title: '生徒・保護者管理',
      items: [
        {
          question: '生徒を登録するには？',
          answer: '【生徒の登録方法】\n\n1. メニューから「生徒管理」を開く\n2. 「新規登録」ボタンをクリック\n3. 氏名、生年月日、支援開始日を入力\n4. 通所曜日を選択\n5. 保護者を選択（先に保護者登録が必要）\n6. 保存で完了',
          category: 'user',
        },
        {
          question: '保護者を登録するには？',
          answer: '【保護者の登録方法】\n\n1. メニューから「保護者管理」を開く\n2. 「新規登録」ボタンをクリック\n3. 氏名を入力\n4. 保存で完了\n\n※ログインID・パスワードは自動生成されます\n※編集画面でパスワードを確認できます',
          category: 'user',
        },
        {
          question: '一括登録するには？',
          answer: '【利用者の一括登録】\n\n1. メニューから「利用者一括登録」を開く\n2. CSVファイルをアップロード、またはテキストを貼り付けてAI解析\n3. 確認画面で内容をチェック\n4. 「登録する」で一括登録\n5. ID/パスワード一覧をPDFでダウンロード可能',
          category: 'user',
        },
        {
          question: 'パスワードを確認・変更するには？',
          answer: '【パスワードの確認・変更】\n\n1. 「保護者管理」を開く\n2. 該当の保護者の「編集」ボタン\n3. ログイン情報セクションでパスワードを確認\n4. 「コピー」でクリップボードにコピー\n5. 「自動生成」で新しいパスワードを発行可能',
          category: 'user',
        },
      ],
    },
    {
      id: 'chat',
      icon: 'chat',
      title: 'チャット',
      items: [
        {
          question: '保護者とチャットするには？',
          answer: '【保護者チャット】\n\n1. メニューから「保護者チャット」を開く\n2. チャット相手を選択\n3. メッセージを入力して送信\n\n■ ピン留め機能\n重要なチャットを上部に固定できます\n\n■ 一斉送信\n「一斉送信」ボタンで全保護者にメッセージ送信',
          category: 'chat',
        },
        {
          question: '生徒とチャットするには？',
          answer: '【生徒チャット】\n\n1. メニューから「生徒チャット」を開く\n2. チャット相手を選択\n3. メッセージを入力して送信\n\n※生徒がログインしている場合のみ利用可能です',
          category: 'chat',
        },
      ],
    },
    {
      id: 'other',
      icon: 'settings',
      title: 'その他',
      items: [
        {
          question: '施設通信を作成するには？',
          answer: '【施設通信の作成】\n\n1. メニューから「施設通信」を開く\n2. 「新規作成」ボタン\n3. タイトル、期間を設定\n4. 「AI生成」で下書きを自動作成\n5. 内容を編集して保存\n6. 「公開」で保護者に配信',
          category: 'other',
        },
        {
          question: '休日を設定するには？',
          answer: '【休日設定】\n\n1. メニューから「休日設定」を開く\n2. カレンダーで日付を選択\n3. 休日名を入力（例：年末年始、お盆休み）\n4. 保存で完了\n\n設定した休日は出欠管理に反映されます',
          category: 'other',
        },
        {
          question: '振替管理について',
          answer: '【振替管理】\n\n保護者からの振替リクエストを管理します。\n\n1. メニューから「振替管理」を開く\n2. リクエスト一覧を確認\n3. 「承認」または「却下」を選択\n\n保護者は専用画面から振替を申請できます',
          category: 'other',
        },
        {
          question: 'イベントを登録するには？',
          answer: '【イベント登録】\n\n1. メニューから「イベント」を開く\n2. 「新規作成」ボタン\n3. イベント名、日時、内容を入力\n4. 保存で完了\n\n登録したイベントは保護者にも表示されます',
          category: 'other',
        },
      ],
    },
  ],
};

// ========================================
// 保護者向けヘルプデータ
// ========================================
const guardianHelpData: HelpData = {
  categories: [
    {
      id: 'dashboard',
      icon: 'home',
      title: 'ダッシュボード',
      items: [
        {
          question: 'ダッシュボードでは何が確認できますか？',
          answer: '【ダッシュボードについて】\n\nダッシュボードでは、お子様に関する最新情報をまとめて確認できます。\n\n■ 確認できる内容\n・未読の連絡帳\n・かけはしの入力状況\n・個別支援計画書の確認依頼\n・施設からのお知らせ\n\n新しい情報がある場合は、バッジ（数字）で表示されます。',
          category: 'dashboard',
        },
        {
          question: 'バッジの数字は何を表していますか？',
          answer: '【バッジについて】\n\n各メニューに表示される数字は、未対応の項目数を表しています。\n\n■ 例\n・連絡帳: 未読の連絡帳の数\n・かけはし: 入力が必要なかけはしの数\n・個別支援計画書: 確認が必要な計画書の数\n\nバッジをタップして、詳細を確認してください。',
          category: 'dashboard',
        },
      ],
    },
    {
      id: 'communication',
      icon: 'library_books',
      title: '連絡帳',
      items: [
        {
          question: '連絡帳はどこで確認できますか？',
          answer: '【連絡帳の確認方法】\n\n1. メニューから「連絡帳一覧」を開く\n2. 日付ごとに連絡帳が表示されます\n3. 各連絡帳をタップして詳細を確認\n\n未読の連絡帳には「NEW」バッジが表示されます。',
          category: 'communication',
        },
        {
          question: '連絡帳に返信できますか？',
          answer: '【連絡帳への返信】\n\n連絡帳への直接返信はできませんが、チャット機能を使ってスタッフにメッセージを送ることができます。\n\n連絡帳の内容についてご質問やご意見がある場合は、チャットでお気軽にお問い合わせください。',
          category: 'communication',
        },
      ],
    },
    {
      id: 'chat',
      icon: 'chat',
      title: 'チャット',
      items: [
        {
          question: 'スタッフにメッセージを送るには？',
          answer: '【メッセージの送信方法】\n\n1. メニューから「チャット」を開く\n2. メッセージ入力欄にテキストを入力\n3. 送信ボタン（紙飛行機アイコン）をタップ\n\nスタッフからの返信があると、プッシュ通知でお知らせします。',
          category: 'chat',
        },
        {
          question: '欠席連絡はどうすればいいですか？',
          answer: '【欠席連絡の方法】\n\n1. チャット画面を開く\n2. 「欠席連絡」ボタンをタップ\n3. 欠席日と理由を入力して送信\n\n欠席連絡は当日の朝までにお願いします。急な体調不良の場合は、できるだけ早くご連絡ください。',
          category: 'chat',
        },
        {
          question: '振替希望を出すには？',
          answer: '【振替希望の出し方】\n\n1. チャット画面を開く\n2. 「振替希望」ボタンをタップ\n3. 希望日を選択して送信\n\n振替の可否はスタッフが確認後、チャットでお知らせします。定員の関係でご希望に添えない場合もございます。',
          category: 'chat',
        },
      ],
    },
    {
      id: 'weekly',
      icon: 'edit_note',
      title: '週間計画表',
      items: [
        {
          question: '週間計画表とは何ですか？',
          answer: '【週間計画表について】\n\nお子様の週ごとの目標と達成状況を確認できます。\n\n■ 確認できる内容\n・今週の目標\n・各曜日の活動予定\n・目標の達成状況\n・スタッフからのコメント\n\n週末に更新されることが多いので、定期的にご確認ください。',
          category: 'weekly',
        },
      ],
    },
    {
      id: 'kakehashi',
      icon: 'handshake',
      title: 'かけはし',
      items: [
        {
          question: 'かけはしとは何ですか？',
          answer: '【かけはしについて】\n\n「かけはし」は、ご家庭での様子やお子様への願いを施設に伝えるための大切な書類です。\n\n6ヶ月ごとに入力をお願いしています。この情報をもとに、個別支援計画を作成します。\n\n■ 入力する内容\n・家庭での様子\n・最近の成長や変化\n・今後の希望や要望',
          category: 'kakehashi',
        },
        {
          question: 'かけはしの入力期限はいつですか？',
          answer: '【かけはしの入力期限】\n\n入力期限が近づくと、ダッシュボードとチャットでお知らせします。\n\n期限は個別支援計画書の作成期限の約1ヶ月前です。\n\n期限内にご入力いただけない場合、計画書作成に影響が出る場合がありますので、お早めにご対応ください。',
          category: 'kakehashi',
        },
        {
          question: 'かけはしの入力方法は？',
          answer: '【かけはしの入力方法】\n\n1. メニューから「かけはし入力」を開く\n2. 各項目に記入\n   ・本人の願い\n   ・家庭での様子\n   ・今後の要望など\n3. 「下書き保存」または「提出」ボタンで保存\n\n■ 下書きと提出の違い\n・下書き: 途中保存（後で編集可能）\n・提出: スタッフに送信（編集不可）',
          category: 'kakehashi',
        },
      ],
    },
    {
      id: 'newsletter',
      icon: 'newspaper',
      title: '施設通信',
      items: [
        {
          question: '施設通信はどこで見られますか？',
          answer: '【施設通信の確認方法】\n\n1. メニューから「施設通信」を開く\n2. 月ごとの施設通信が一覧表示されます\n3. 読みたい月をタップして詳細を確認\n\n新しい施設通信が公開されると、ダッシュボードでお知らせします。',
          category: 'newsletter',
        },
      ],
    },
    {
      id: 'plan',
      icon: 'assignment',
      title: '個別支援計画書・モニタリング',
      items: [
        {
          question: '個別支援計画書の確認方法は？',
          answer: '【個別支援計画書の確認】\n\n1. メニューから「個別支援計画書」を開く\n2. お子様を選択\n3. 計画書の一覧が表示されます\n4. 確認したい計画書をタップ\n\n新しい計画書が作成されると、確認のお願いが届きます。',
          category: 'plan',
        },
        {
          question: '計画書案の確認・コメントの送り方は？',
          answer: '【計画書案の確認方法】\n\n1. 「個別支援計画書」画面で該当の計画書を開く\n2. 内容を確認\n3. 以下のいずれかを選択:\n   ・「確認」: 内容に問題がない場合\n   ・「コメントを送信」: 変更希望がある場合\n\n■ コメントを送信する場合\nコメント欄に具体的な変更希望を記入してください。スタッフが確認後、修正した計画書を改めてお送りします。',
          category: 'plan',
        },
        {
          question: '電子署名はどうやってするの？',
          answer: '【電子署名の方法】\n\n電子署名は、スタッフとの面談時に行います。\n\n1. スタッフが署名画面を表示\n2. 署名欄に指（スマホ・タブレット）またはマウス（PC）で署名\n3. 書き直したい場合は「クリア」ボタンで消せます\n4. 署名後、スタッフが保存\n\n署名済みの計画書はPDFでダウンロードできます。',
          category: 'plan',
        },
        {
          question: 'モニタリング表とは何ですか？',
          answer: '【モニタリング表について】\n\nモニタリング表は、個別支援計画の目標がどれくらい達成できたかを評価する書類です。\n\n■ 確認できる内容\n・各目標の達成状況（A/B/C評価など）\n・次期に向けた課題や改善点\n・スタッフからのコメント\n\n計画期間の終わりに作成されます。',
          category: 'plan',
        },
      ],
    },
    {
      id: 'profile',
      icon: 'person',
      title: 'プロフィール・設定',
      items: [
        {
          question: 'パスワードを変更するには？',
          answer: '【パスワードの変更方法】\n\n1. メニューから「パスワード変更」を開く\n2. 現在のパスワードを入力\n3. 新しいパスワードを入力（2回）\n4. 「変更する」ボタンをタップ\n\n■ パスワードの条件\n・8文字以上\n・英数字を含む\n\nパスワードを忘れた場合は、施設にお問い合わせください。',
          category: 'profile',
        },
        {
          question: 'プロフィール情報を変更するには？',
          answer: '【プロフィールの変更】\n\n1. メニューから「プロフィール」を開く\n2. 変更したい項目を編集\n3. 「保存」ボタンをタップ\n\n■ 変更できる項目\n・表示名\n・連絡先（メールアドレス・電話番号）\n\n※ 氏名などの変更は施設にお問い合わせください。',
          category: 'profile',
        },
      ],
    },
    {
      id: 'manual',
      icon: 'help',
      title: 'ご利用ガイド',
      items: [
        {
          question: 'このシステムの使い方がわかりません',
          answer: '【ご利用ガイドについて】\n\nこのページでは、システムの使い方を詳しく説明しています。\n\n■ 目次から探す\n画面上部の目次から、知りたい項目を選んでタップしてください。\n\n■ それでもわからない場合\nチャットでスタッフにお問い合わせいただくか、施設にお電話ください。',
          category: 'manual',
        },
      ],
    },
  ],
};

// ========================================
// 生徒向けヘルプデータ
// ========================================
const studentHelpData: HelpData = {
  categories: [
    {
      id: 'dashboard',
      icon: 'home',
      title: 'ホーム',
      items: [
        {
          question: 'ホーム画面では何ができますか？',
          answer: '【ホーム画面について】\n\nホーム画面では、あなたに関する情報をまとめて確認できます。\n\n■ 確認できること\n・今日のスケジュール\n・週間計画の目標\n・スタッフからのメッセージ\n・提出物の状況',
          category: 'dashboard',
        },
      ],
    },
    {
      id: 'chat',
      icon: 'chat',
      title: 'チャット',
      items: [
        {
          question: 'スタッフにメッセージを送るには？',
          answer: '【メッセージの送り方】\n\n1. メニューから「チャット」を開く\n2. メッセージを入力\n3. 送信ボタンをタップ\n\nスタッフからの返信があると通知が届きます。',
          category: 'chat',
        },
        {
          question: 'ファイルを送ることはできますか？',
          answer: '【ファイル送信について】\n\nチャット画面の添付ボタン（クリップアイコン）をタップすると、画像やファイルを送ることができます。\n\n送信できるファイル：画像、PDF、ドキュメント',
          category: 'chat',
        },
      ],
    },
    {
      id: 'weekly',
      icon: 'edit_note',
      title: '週間計画',
      items: [
        {
          question: '週間計画の書き方は？',
          answer: '【週間計画の書き方】\n\n1. メニューから「週間計画」を開く\n2. 今週の目標を入力\n3. 各曜日の予定や目標を入力\n4. 「保存」ボタンで保存\n\nスタッフや保護者も確認できます。毎週更新しましょう。',
          category: 'weekly',
        },
      ],
    },
    {
      id: 'schedule',
      icon: 'calendar_today',
      title: 'スケジュール',
      items: [
        {
          question: 'スケジュールの確認方法は？',
          answer: '【スケジュールの確認】\n\n1. メニューから「スケジュール」を開く\n2. カレンダーで日付を選択\n3. その日の予定が表示されます\n\nイベントや休日も表示されます。',
          category: 'schedule',
        },
      ],
    },
    {
      id: 'submissions',
      icon: 'task',
      title: '提出物',
      items: [
        {
          question: '提出物の確認と提出方法は？',
          answer: '【提出物について】\n\n1. メニューから「提出物」を開く\n2. 未提出の提出物が一覧表示されます\n3. 各提出物をタップして内容を確認\n4. 「提出する」ボタンで提出完了\n\n期限が近い提出物は赤く表示されます。',
          category: 'submissions',
        },
        {
          question: 'パスワードを変更するには？',
          answer: '【パスワードの変更】\n\n1. メニューから「パスワード変更」を開く\n2. 現在のパスワードを入力\n3. 新しいパスワードを2回入力\n4. 「変更する」をタップ\n\nわからない場合はスタッフに聞いてください。',
          category: 'submissions',
        },
      ],
    },
  ],
};

/**
 * ユーザータイプに応じたヘルプデータを取得
 */
export function getHelpData(userType: UserType): HelpData {
  switch (userType) {
    case 'guardian':
      return guardianHelpData;
    case 'student':
      return studentHelpData;
    case 'admin':
    case 'staff':
    case 'tablet':
    default:
      return staffHelpData;
  }
}
