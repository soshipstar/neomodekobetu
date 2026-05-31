/**
 * /terms — 利用規約 (公開ページ・認証不要)
 *
 * ⚠ 重要: これは技術的なコピー/解析対策の条項を盛り込んだ「雛形」です。
 *   実際に施行する前に、必ず弁護士など法律専門家のレビューを受けてください。
 *   特に損害賠償・免責・管轄の条項は、消費者契約法 (対保護者) や下請法・
 *   独占禁止法 (対事業者) との整合性確認が必要です。
 *
 * 静的ページなので 'use client' は不要 (Server Component)。
 */

import Link from 'next/link';

export const metadata = {
  title: '利用規約 | きづり',
  robots: { index: false, follow: false },
};

const UPDATED_AT = '2026年6月1日';

export default function TermsPage() {
  return (
    <div className="mx-auto min-h-screen max-w-3xl bg-white px-5 py-10 text-[var(--neutral-foreground-1)]">
      <header className="mb-8 border-b border-[var(--neutral-stroke-2)] pb-4">
        <h1 className="text-2xl font-bold">きづり 利用規約</h1>
        <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">最終改定日: {UPDATED_AT}</p>
      </header>

      <p className="mb-6 text-sm leading-relaxed">
        本利用規約 (以下「本規約」) は、きづり (以下「本サービス」) の提供条件および
        本サービスの利用に関する運営者と利用者との間の権利義務関係を定めるものです。
        本サービスを利用することにより、利用者は本規約に同意したものとみなされます。
      </p>

      <Section n="第1条" title="定義">
        <ul className="list-disc space-y-1 pl-5">
          <li>「本サービス」とは、放課後等デイサービス等の事業所運営を支援する本ソフトウェアおよび関連サービスをいいます。</li>
          <li>「利用者」とは、事業者・職員・保護者・児童など、本サービスを利用するすべての者をいいます。</li>
          <li>「コンテンツ」とは、本サービスを通じて提供・記録される文章・画像・帳票・プログラム・デザインその他一切の情報をいいます。</li>
        </ul>
      </Section>

      <Section n="第2条" title="アカウントの管理">
        <ul className="list-disc space-y-1 pl-5">
          <li>利用者は、自己の責任においてアカウント情報 (ID・パスワード等) を管理するものとします。</li>
          <li>アカウントの第三者への貸与・共有・譲渡はできません。</li>
          <li>アカウントを通じて行われた行為は、当該アカウントの利用者による行為とみなします。</li>
        </ul>
      </Section>

      <Section n="第3条" title="禁止事項">
        <p className="mb-2">利用者は、本サービスの利用にあたり、次の各号の行為を行ってはなりません。</p>
        <ol className="list-decimal space-y-1.5 pl-5">
          <li>
            本サービスのソフトウェア、ソースコード、API、データベース構造、画面構成その他一切について、
            <strong>リバースエンジニアリング、逆コンパイル、逆アセンブル、その他の解析行為</strong>を行うこと。
          </li>
          <li>
            本サービスの全部または一部を、運営者の事前の書面による許可なく
            <strong>複製・改変・転載・再配布・販売</strong>し、または
            <strong>類似・競合するサービスを開発・提供する目的で利用</strong>すること。
          </li>
          <li>
            自動化ツール・スクレイピング・クローラ等を用いて、本サービスから
            <strong>データを機械的・大量に取得</strong>すること
            (運営者が明示的に許可した API を除く)。
          </li>
          <li>
            本サービスを通じて知り得た情報を、本来の業務目的
            (児童支援・事業所運営・保護者連絡) <strong>以外の目的で利用</strong>すること。
          </li>
          <li>本サービスの提供を妨害し、またはサーバー・ネットワークに過度の負荷をかける行為。</li>
          <li>不正アクセス、認証の回避、脆弱性の悪用、ハニーポット等の検知機構の回避を試みる行為。</li>
          <li>個人情報・支援記録等を、法令または本規約に反して取得・開示・漏えいする行為。</li>
          <li>法令、公序良俗に反する行為、または運営者・第三者の権利を侵害する行為。</li>
        </ol>
      </Section>

      <Section n="第4条" title="知的財産権">
        <ul className="list-disc space-y-1 pl-5">
          <li>
            本サービスを構成するソフトウェア・プログラム・デザイン・ロゴ・ドキュメント等に関する
            著作権・商標権その他一切の知的財産権は、運営者または正当な権利者に帰属します。
          </li>
          <li>
            本規約は、利用者に対し本サービスの利用を許諾するものであり、
            知的財産権を譲渡・移転するものではありません。
          </li>
          <li>
            利用者が本サービスに入力・記録したコンテンツ (支援記録・連絡帳等) の権利は
            当該利用者または事業所に帰属します。運営者は、本サービスの提供・改善に必要な範囲でのみ
            これを取り扱います。
          </li>
        </ul>
      </Section>

      <Section n="第5条" title="個人情報・データの取り扱い">
        <ul className="list-disc space-y-1 pl-5">
          <li>運営者は、個人情報保護法その他関連法令を遵守し、別途定めるプライバシーポリシーに従って個人情報を取り扱います。</li>
          <li>児童・保護者に関する記録は、機微情報を含むため、利用者は厳重に管理する義務を負います。</li>
          <li>運営者は、本サービスのアクセス状況を監査ログとして記録し、不正利用の検知・防止に利用することがあります。</li>
        </ul>
      </Section>

      <Section n="第6条" title="違反時の措置">
        <ul className="list-disc space-y-1 pl-5">
          <li>
            利用者が本規約に違反したと運営者が判断した場合、運営者は事前の通知なく、
            当該利用者のアカウント停止・利用制限・本サービスの提供停止等の措置を講じることができます。
          </li>
          <li>
            前項の措置により利用者に損害が生じても、運営者は一切の責任を負いません。
          </li>
          <li>
            利用者が本規約に違反し運営者または第三者に損害を与えた場合、利用者はその損害
            (合理的な弁護士費用を含む) を賠償する責任を負います。
          </li>
        </ul>
      </Section>

      <Section n="第7条" title="免責">
        <ul className="list-disc space-y-1 pl-5">
          <li>運営者は、本サービスが利用者の特定の目的に適合すること、完全性・正確性・有用性を有することを保証しません。</li>
          <li>運営者は、本サービスの中断・停止・データの消失等によって利用者に生じた損害について、運営者の故意または重過失による場合を除き、責任を負いません。</li>
        </ul>
      </Section>

      <Section n="第8条" title="規約の変更">
        <p>
          運営者は、必要と判断した場合、利用者に通知のうえ本規約を変更することができます。
          変更後に本サービスを利用した場合、変更後の規約に同意したものとみなします。
        </p>
      </Section>

      <Section n="第9条" title="準拠法・裁判管轄">
        <p>
          本規約の準拠法は日本法とし、本サービスに関して紛争が生じた場合には、
          運営者の所在地を管轄する裁判所を第一審の専属的合意管轄裁判所とします。
        </p>
      </Section>

      <footer className="mt-10 border-t border-[var(--neutral-stroke-2)] pt-4 text-center text-sm text-[var(--neutral-foreground-3)]">
        <Link href="/auth/login" className="text-[var(--brand-80)] hover:underline">
          ← ログイン画面へ戻る
        </Link>
      </footer>
    </div>
  );
}

function Section({ n, title, children }: { n: string; title: string; children: React.ReactNode }) {
  return (
    <section className="mb-7">
      <h2 className="mb-2 text-base font-bold text-[var(--neutral-foreground-1)]">
        {n}（{title}）
      </h2>
      <div className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">{children}</div>
    </section>
  );
}
