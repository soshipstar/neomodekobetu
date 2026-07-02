// ---------------------------------------------------------------------------
// アプリのバージョン情報 / 変更履歴（リリースノート）の唯一の情報源。
//
// 追加・修正でアプリのバージョンが上がったら、CHANGELOG の先頭に新しい
// リリースを追記し、APP_VERSION を最新の version に合わせる。
//
// audiences で「その更新が誰に関係するか」を指定する:
//   - スタッフ / 管理者 は常にすべての更新を閲覧できる
//   - 保護者(guardian) は audiences に 'guardian' を含む項目のみ閲覧できる
//   - 利用者(生徒 / student) は audiences に 'student' を含む項目のみ閲覧できる
//   → 事業所内部だけの変更は audiences: ['staff'] にする
// ---------------------------------------------------------------------------

export type ChangeAudience = 'staff' | 'guardian' | 'student';

export type ChangeCategory = 'feature' | 'improvement' | 'fix' | 'security';

export interface ChangeItem {
  category: ChangeCategory;
  /** この更新が関係する利用者種別。スタッフ/管理者は種別に関わらず全件閲覧できる。 */
  audiences: ChangeAudience[];
  title: string;
  detail?: string;
}

export interface Release {
  /** セマンティックバージョニング。例: '1.1.1' (major.minor.patch) */
  version: string;
  /** 公開日 (YYYY-MM-DD) */
  date: string;
  /** そのリリースの一言まとめ（任意） */
  title?: string;
  items: ChangeItem[];
}

/**
 * 画面に表示する現在のアプリバージョン。CHANGELOG 先頭の version と一致させる。
 * セマンティックバージョニング(major.minor.patch)。Ver.1.1.1 を起点に、
 * 以降のリリースで数字を上げていく（大きな機能追加=minor、修正=patch を目安に）。
 */
export const APP_VERSION = '1.3.1';

/** カテゴリの表示メタ情報（ラベル・アイコン・色）。色は globals.css の CSS 変数を使用。 */
export const CATEGORY_META: Record<
  ChangeCategory,
  { label: string; icon: string; color: string; bg: string }
> = {
  feature: { label: '新機能', icon: 'new_releases', color: 'var(--status-info-fg)', bg: 'var(--status-info-bg)' },
  improvement: { label: '改善', icon: 'auto_awesome', color: 'var(--status-success-fg)', bg: 'var(--status-success-bg)' },
  fix: { label: '修正', icon: 'build', color: 'var(--status-warning-fg)', bg: 'var(--status-warning-bg)' },
  security: { label: 'セキュリティ', icon: 'security', color: 'var(--status-danger-fg)', bg: 'var(--status-danger-bg)' },
};

/**
 * 表示対象(audience)に応じて閲覧できるリリース／項目に絞り込む純関数。
 * - 'all'      : スタッフ / 管理者。すべての項目を返す。
 * - それ以外   : その audience を含む項目のみ。項目が0件になったリリースは除外。
 * ロジックの回帰検査はこの関数を対象にできる。
 */
export function filterReleasesForAudience(
  changelog: Release[],
  audience: ChangeAudience | 'all'
): Release[] {
  return changelog
    .map((release) => ({
      ...release,
      items:
        audience === 'all'
          ? release.items
          : release.items.filter((item) => item.audiences.includes(audience)),
    }))
    .filter((release) => release.items.length > 0);
}

// 新しいリリースは配列の先頭に追加する（新しい順で表示される）。
export const CHANGELOG: Release[] = [
  {
    version: '1.3.1',
    date: '2026-07-03',
    title: '能力評価の回答済み表示の改善',
    items: [
      {
        category: 'fix',
        audiences: ['staff'],
        title: '能力評価の「本日回答済」に、誰が・いつ・どの活動で記録したかを表示するようにしました',
        detail:
          '能力評価の設問はお子様ごとに1日3問で、同じ日に別の活動記録や別のスタッフが回答した場合もすべての画面で「本日回答済」と表示されます。回答していないのに回答済みに見えるとのご報告を受け、回答済みの設問に記録の出所（時刻・記録したスタッフ・活動名）を表示するようにしました。',
      },
    ],
  },
  {
    version: '1.3.0',
    date: '2026-07-03',
    title: 'アセスメントAI生成の時系列分析対応',
    items: [
      {
        category: 'improvement',
        audiences: ['staff'],
        title: 'スタッフアセスメントのAI生成が、お子様の成長の変化を明記するようになりました',
        detail:
          '直近6か月の連絡帳を月ごとの時系列で分析するようになりました。直近の記録を「現在の様子」の基準とし、期間はじめの様子と比較して成長・改善が読み取れる場合は、その変化を時期の根拠とともにアセスメントに記載します。過去の課題が直近の記録に見られない場合も「解決した」と断定せず、事実の範囲で記述します。',
      },
    ],
  },
  {
    version: '1.2.0',
    date: '2026-07-02',
    title: '活動記録の複数作成・一括登録のふりがな対応',
    items: [
      {
        category: 'feature',
        audiences: ['staff'],
        title: '利用者一括登録に「ふりがな」列を追加しました',
        detail:
          'CSVでの一括登録で、生徒氏名・保護者氏名それぞれの「ふりがな」を登録できるようになりました。「CSVテンプレートをダウンロード」から取得できるテンプレートにふりがなの列が追加されています（ふりがな列のない従来のCSVもそのまま利用できます）。',
      },
      {
        category: 'fix',
        audiences: ['staff'],
        title: '同じ活動名で同じ日に複数の活動記録を作成できるようになりました',
        detail:
          '同じ支援案（同じ活動名）から、同じ日に2件以上の活動記録を作成できるようになりました。あわせて、記録を保存したはずなのに一覧に表示されないことがあった不具合を解消しました。',
      },
    ],
  },
  {
    version: '1.1.1',
    date: '2026-07-01',
    title: 'マニュアルの全面刷新',
    items: [
      {
        category: 'improvement',
        audiences: ['staff', 'guardian'],
        title: 'マニュアル／ご利用ガイドを全面的に見直しました',
        detail:
          '各項目を、実際の画面に沿った具体的な操作手順（どのメニューを開き、どのボタンを押すと何が起きるか）に書き直しました。スタッフマニュアルは17項目、保護者向けご利用ガイドは9項目に整理しています。',
      },
      {
        category: 'feature',
        audiences: ['staff', 'guardian', 'student'],
        title: '「更新履歴」から更新内容を確認できるようになりました',
        detail:
          'アプリの追加・改善・修正の履歴を、この画面からいつでも確認できるようになりました。保護者・利用者の方には、ご自身に関係する更新のみを表示します。',
      },
    ],
  },
  {
    version: '1.1.0',
    date: '2026-06-27',
    title: '日課設定（毎日の支援）の改善',
    items: [
      {
        category: 'feature',
        audiences: ['staff'],
        title: '「日課設定」で項目の並び替え・削除ができるようになりました',
        detail:
          '毎日の支援の一覧で、各項目を上下に移動して順番を入れ替えたり、不要な項目を削除できるようになりました。',
      },
      {
        category: 'fix',
        audiences: ['staff'],
        title: '「日課設定」でキャンセルボタンが効かない不具合を修正しました',
        detail: '編集中にキャンセルを押しても入力内容が元に戻らないことがある問題を修正しました。',
      },
    ],
  },
  {
    version: '1.0.1',
    date: '2026-06-26',
    title: 'セキュリティと安定性の向上',
    items: [
      {
        category: 'security',
        audiences: ['staff', 'guardian'],
        title: '他の教室のデータにアクセスできてしまう可能性があった問題を修正しました',
        detail:
          'スタッフ・保護者向けの一部機能で、本来アクセスできないはずの他教室の情報を参照できる可能性があった問題（越境アクセス）を、教室単位で厳密に制限するよう修正しました。',
      },
      {
        category: 'fix',
        audiences: ['staff'],
        title: '生徒情報の編集が保存できないことがある不具合を修正しました',
        detail:
          'ブラウザの自動入力によってログイン欄が意図せず書き換わり、保存に失敗することがある問題を修正しました。',
      },
    ],
  },
  {
    version: '1.0.0',
    date: '2026-06-25',
    title: 'ロゴの一新ほか',
    items: [
      {
        category: 'improvement',
        audiences: ['staff', 'guardian', 'student'],
        title: 'アプリのロゴを新しいデザインに変更しました',
        detail: 'ログイン画面・トップ画面・ヘッダー・サイドバーのロゴを一新しました。',
      },
      {
        category: 'fix',
        audiences: ['staff'],
        title: '保護者への一斉送信の宛先を「在籍児童基準」に修正しました',
        detail:
          '一斉送信の対象が正しく絞り込まれず、送信漏れが起きる可能性があった問題を修正しました。既定で「在籍中のみ」を対象とします。',
      },
    ],
  },
];
