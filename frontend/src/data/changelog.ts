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
  /** 例: '2026.7.0' */
  version: string;
  /** 公開日 (YYYY-MM-DD) */
  date: string;
  /** そのリリースの一言まとめ（任意） */
  title?: string;
  items: ChangeItem[];
}

/** 画面に表示する現在のアプリバージョン。CHANGELOG 先頭の version と一致させる。 */
export const APP_VERSION = '2026.7.0';

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
    version: '2026.7.0',
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
    version: '2026.6.2',
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
    version: '2026.6.1',
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
    version: '2026.6.0',
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
