'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/authStore';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Subscription {
  company_id: number;
  status: string | null;
  is_custom_pricing: boolean;
  amount: number | null;
  current_price_id: string | null;
  current_period_end: string | null;
  trial_ends_at: string | null;
  cancel_at_period_end: boolean;
  on_grace_period: boolean;
  is_active: boolean;
  pm_type: string | null;
  pm_last_four: string | null;
  contract_started_at: string | null;
  contract_document_path: string | null;
  plan_label?: string;
}

function formatJpy(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return `¥${value.toLocaleString('ja-JP')}`;
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' });
  } catch {
    return '—';
  }
}

export default function BillingContractPage() {
  const toast = useToast();
  const router = useRouter();
  const { user } = useAuthStore();
  const [subscription, setSubscription] = useState<Subscription | null>(null);
  const [loading, setLoading] = useState(true);

  // マスター管理者は自社を持たないので、企業課金管理画面へ自動誘導する。
  useEffect(() => {
    if (user?.user_type === 'admin' && user.is_master) {
      router.replace('/admin/master-billing');
    }
  }, [user, router]);

  const fetchSubscription = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/api/admin/billing/subscription');
      setSubscription(res.data.data);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '契約情報の取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchSubscription();
  }, [fetchSubscription]);

  const print = () => {
    if (typeof window !== 'undefined') window.print();
  };

  if (loading) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">契約書</h1>
        <SkeletonList items={3} />
      </div>
    );
  }

  return (
    <div className="space-y-6 print:space-y-4">
      <div className="flex items-center justify-between flex-wrap gap-2 print:hidden">
        <Link href="/admin/billing" className="inline-flex items-center text-sm text-[var(--brand-80)] hover:underline">
          <MaterialIcon name="chevron_left" size={18} />
          請求・契約に戻る
        </Link>
        <div className="flex items-center gap-3">
          <Link
            href="/admin/billing/terms"
            className="inline-flex items-center gap-1 text-sm text-[var(--brand-80)] hover:underline"
          >
            <MaterialIcon name="article" size={18} />
            個別条件書を見る
          </Link>
          <Button variant="ghost" onClick={print}>
            <MaterialIcon name="print" size={18} />
            <span className="ml-1">印刷</span>
          </Button>
        </div>
      </div>

      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">利用契約書</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">KIDURI 個別支援連絡帳システム ご利用契約</p>
      </div>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">契約内容サマリ</h2>
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">プラン</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">
                {subscription?.plan_label || (subscription?.is_custom_pricing ? 'カスタムプラン' : '標準プラン')}
              </p>
            </div>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">月額（税抜）</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">{formatJpy(subscription?.amount)}</p>
            </div>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">契約開始日</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">{formatDate(subscription?.contract_started_at)}</p>
            </div>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">次回請求日</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">{formatDate(subscription?.current_period_end)}</p>
            </div>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">契約状態</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">
                {subscription?.status === 'active' ? '有効' :
                  subscription?.status === 'trialing' ? 'トライアル中' :
                  subscription?.status === 'past_due' ? '支払い遅延' :
                  subscription?.status === 'canceled' ? '解約済み' : '未契約'}
              </p>
            </div>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">支払い方法</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">
                {subscription?.pm_type ? `${subscription.pm_type.toUpperCase()} •••• ${subscription.pm_last_four || '----'}` : '未登録'}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {subscription?.contract_document_path && (
        <Card>
          <CardBody>
            <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">個別契約書（PDF）</h2>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              本契約に固有の追加条項・別紙が登録されています。
            </p>
            <div className="mt-3">
              <Button onClick={() => window.open(subscription.contract_document_path!, '_blank', 'noopener')}>
                <MaterialIcon name="description" size={18} />
                <span className="ml-1">契約書PDFを開く</span>
              </Button>
            </div>
          </CardBody>
        </Card>
      )}

      <Card>
        <CardBody>
          <article className="prose prose-sm max-w-none text-[var(--neutral-foreground-1)]">
            <h2 className="text-base font-semibold">きづり 利用契約書（標準条項）</h2>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              対象サービス: 記録・支援業務支援システム「きづり」
            </p>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              ※ 金額・支払条件・期日等の個別条件は、本書冒頭「契約内容サマリ」および別途交付する見積書／請求書に記載のとおりとします。
            </p>

            <h3 className="mt-4 font-semibold">前文</h3>
            <p className="mt-1">
              甲（株式会社ソーシップ）は、放課後等デイサービス事業者向けに、支援記録、顧客情報管理その他関連業務を支援する
              システム「きづり」（以下「本サービス」という。）を提供する。乙（契約者）は、本サービスの導入および利用を希望し、
              甲乙は以下のとおり本サービスの利用契約（以下「本契約」という。）を締結する。
            </p>

            <h3 className="mt-4 font-semibold">第1条（目的）</h3>
            <p className="mt-1">
              本契約は、甲が乙に対し本サービスを提供し、乙がこれを利用するにあたって必要な条件を定めることを目的とする。
            </p>

            <h3 className="mt-4 font-semibold">第2条（本サービスの内容）</h3>
            <p className="mt-1">
              甲は、乙に対し、本サービスの利用環境を提供するとともに、導入時支援として次の各号の支援を行う。
              料金については別途定める個別条件書に従う。
            </p>
            <ul className="mt-1 list-disc pl-6">
              <li>導入初期設定および研修費セット（金額は個別条件書に定める）</li>
              <li>訪問研修　1回（2時間）。ただし、乙の希望または甲乙協議により、同訪問研修をWEB研修へ切り替えることができる。</li>
              <li>WEB研修　2回（各1時間）</li>
              <li>訪問研修に要する交通費は別途乙の負担とする。</li>
            </ul>

            <h3 className="mt-4 font-semibold">第3条（利用料および支払方法）</h3>
            <p className="mt-1">
              乙は、甲に対し、本サービスの利用料を支払う。具体的な金額・税区分は本書冒頭「契約内容サマリ」または別途交付する個別条件書に定める。
            </p>
            <p className="mt-1">
              前条に定める導入初期設定および研修費セットは、導入時に一括して支払うものとする。
              支払期日、支払方法、振込手数料の負担者その他支払条件は、別途甲が発行する請求書または個別条件書に従う。
            </p>

            <h3 className="mt-4 font-semibold">第4条（契約期間および更新）</h3>
            <p className="mt-1">
              本契約の有効期間は、利用開始日から1年間とする。
              本契約は、期間満了日の属する月の前月末日までに、甲または乙のいずれからも更新を希望しない旨の書面または電磁的方法による
              通知がない限り、同一条件でさらに1年間更新されるものとし、以後も同様とする。
            </p>
            <p className="mt-1">
              乙は、本契約締結後、別途個別条件書に定める「最低利用期間」を満了するまで、本契約を中途解約することができない。
              ただし、第13条第2項および第3項に定める甲乙いずれかの債務不履行・信用不安等による解除の場合はこの限りでない。
            </p>
            <p className="mt-1">
              期間満了に伴う更新拒絶を行う場合、甲乙は、別途個別条件書に定める「解約予告期間」までに、相手方に対し書面または
              電磁的方法により通知するものとする。
            </p>

            <h3 className="mt-4 font-semibold">第5条（導入・登録支援）</h3>
            <p className="mt-1">
              他のサービスから本サービスへ切り替える場合、甲は、乙が保有する顧客データを一括返還・移行するためのプログラムを、
              導入時に乙へ提供する。前項のプログラムを利用したデータ更新・登録作業を乙自身で行う場合、追加料金は発生しない。
              乙が希望する場合、甲は、顧客情報の登録作業を代行し、その対価を別途請求できる（金額は個別条件書に定める）。
            </p>

            <h3 className="mt-4 font-semibold">第6条（乙の協力義務）</h3>
            <p className="mt-1">
              乙は、本サービスの導入および利用に必要な情報、資料、担当者の確保、通信環境その他合理的に必要な協力を行うものとする。
              乙が必要な協力を行わないことにより、本サービスの導入、研修実施、利用開始または保守対応に遅延等が生じた場合、甲はその責任を負わない。
            </p>

            <h3 className="mt-4 font-semibold">第7条（アカウントおよび管理責任）</h3>
            <p className="mt-1">
              乙は、本サービスの利用にあたり付与されるID、パスワードその他認証情報を自己の責任において適切に管理し、第三者に利用させてはならない。
              乙の管理不十分、使用上の過誤または第三者による不正利用により生じた損害について、甲は故意または重過失がある場合を除き責任を負わない。
            </p>

            <h3 className="mt-4 font-semibold">第8条（個人情報・顧客情報の取扱い）</h3>
            <p className="mt-1">
              甲および乙は、本サービスの利用に関連して知り得た個人情報および顧客情報を、個人情報保護法その他関係法令に従い適切に取り扱うものとする。
              甲は、乙から委託を受けて個人情報を取り扱う場合、乙の指示の範囲内でのみこれを利用し、目的外利用を行わない。
              個人情報の安全管理措置、再委託の可否、保存期間、削除方法その他詳細は、必要に応じて別途個人情報取扱特約または
              データ処理契約に定める。
            </p>

            <h3 className="mt-4 font-semibold">第9条（知的財産権）</h3>
            <p className="mt-1">
              本サービスに関するプログラム、画面、仕様書、マニュアルその他一切の知的財産権は、甲または正当な権利者に帰属する。
              乙は、本契約に基づき本サービスを自己の事業のために利用することができるが、甲の事前の書面承諾なく、複製、改変、
              リバースエンジニアリング、第三者提供その他これらに類する行為をしてはならない。
            </p>

            <h3 className="mt-4 font-semibold">第10条（禁止事項）</h3>
            <p className="mt-1">乙は、次の各号の行為を行ってはならない。</p>
            <ul className="mt-1 list-disc pl-6">
              <li>法令、公序良俗または本契約に違反する行為</li>
              <li>本サービスの運営を妨害し、またはそのおそれのある行為</li>
              <li>甲または第三者の権利利益を侵害する行為</li>
              <li>不正アクセス、認証情報の不正使用、または第三者への貸与・譲渡</li>
              <li>本サービスを、乙の契約範囲を超えて第三者に利用させる行為</li>
            </ul>

            <h3 className="mt-4 font-semibold">第11条（保守、仕様変更およびサービス停止）</h3>
            <p className="mt-1">
              甲は、本サービスの維持、改善または法令対応のため、乙に事前または事後に通知のうえ、本サービスの仕様を変更し、
              または本サービスの全部もしくは一部を一時停止できる。次の各号の事由により本サービスの提供が困難となった場合、
              甲は、事前通知が困難なときであっても、本サービスを一時停止できる。
            </p>
            <ul className="mt-1 list-disc pl-6">
              <li>システム保守、障害対応、設備更新</li>
              <li>通信回線、クラウド基盤、電力供給等の障害</li>
              <li>天災地変、感染症、行政措置その他の不可抗力</li>
              <li>情報セキュリティ上の緊急対応が必要な場合</li>
            </ul>

            <h3 className="mt-4 font-semibold">第12条（秘密保持）</h3>
            <p className="mt-1">
              甲および乙は、本契約に関連して相手方から開示を受け、または知り得た技術上、営業上その他一切の非公知情報を、
              相手方の事前承諾なく第三者に開示または漏えいしてはならない。前項の規定は、法令に基づき開示を求められた場合、
              または既に公知であった情報等、通常の秘密保持義務の例外に該当する情報には適用しない。
            </p>

            <h3 className="mt-4 font-semibold">第13条（解約および解除）</h3>
            <p className="mt-1">
              乙は、第4条に定める最低利用期間の満了後、別途甲が定める手続に従い中途解約を申し出ることができる。
              ただし、既に発生した料金および導入関連費用の返金の有無は、別途定める条件による。
            </p>
            <p className="mt-1">
              乙が最低利用期間内に本契約を中途解約する場合、または甲が乙の責めに帰すべき事由により本契約を解除する場合、
              乙は別途個別条件書に定める「中途解約違約金」を甲に支払うものとする。
            </p>
            <p className="mt-1">
              甲または乙は、相手方が本契約に違反し、相当期間を定めて催告したにもかかわらず是正しない場合、本契約の全部または一部を解除することができる。
            </p>
            <p className="mt-1">
              相手方に信用不安、差押え、破産申立て、事業停止その他契約継続が困難となる重大な事由が生じた場合、催告なく直ちに解除できる。
            </p>

            <h3 className="mt-4 font-semibold">第14条（契約終了時のデータ対応）</h3>
            <p className="mt-1">
              本契約終了時、甲は、別途定める方法により乙のデータを返還またはエクスポート可能な状態で提供するものとする。
              ただし、法令上または運用上保持が必要な情報を除く。契約終了後のデータ保管期間、返還方法、追加費用の有無は、
              別紙または個別条件書に定める。
            </p>

            <h3 className="mt-4 font-semibold">第15条（損害賠償および免責）</h3>
            <p className="mt-1">
              甲が本契約に関連して乙に損害を与えた場合、甲の責任は、甲に故意または重過失がある場合を除き、
              乙が直近12か月間に甲へ現実に支払った利用料総額を上限とする。甲は、逸失利益、特別損害、間接損害、
              第三者からの請求その他通常かつ直接の損害を超える損害について責任を負わない。ただし、法令上制限できない場合を除く。
            </p>

            <h3 className="mt-4 font-semibold">第16条（反社会的勢力の排除）</h3>
            <p className="mt-1">
              甲および乙は、自らまたはその役員等が反社会的勢力に該当しないこと、およびこれらと関係を有しないことを表明し保証する。
              相手方が前項に違反した場合、何らの催告なく直ちに本契約を解除することができる。
            </p>

            <h3 className="mt-4 font-semibold">第17条（協議事項）</h3>
            <p className="mt-1">
              本契約に定めのない事項または本契約の各条項の解釈に疑義が生じた場合、甲乙は誠意をもって協議し、これを解決する。
            </p>

            <h3 className="mt-4 font-semibold">第18条（準拠法および合意管轄）</h3>
            <p className="mt-1">
              本契約は日本法に準拠し、本契約に関して生じる一切の紛争については、横浜地方裁判所を第一審の専属的合意管轄裁判所とする。
            </p>

            <h3 className="mt-6 font-semibold">契約締結欄</h3>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              本契約締結の証として、本書を2通作成し、甲乙各自記名押印または電磁的記録により締結のうえ、各1通を保有する。
              甲: 株式会社ソーシップ ／ 乙: 本書冒頭「契約内容サマリ」記載の契約者。締結日・代表者・住所等は別途取り交わす書面によるものとする。
            </p>

            <p className="mt-6 text-xs text-[var(--neutral-foreground-3)]">
              ※ 本契約書は標準条項であり、各条の具体的金額・期日・対象範囲等は個別条件書または別紙に定めます。
              個人情報を取り扱うため、別途「個人情報取扱特約」を付して運用します。
            </p>
            <p className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
              改定日: 2026年4月　／　株式会社ソーシップ
            </p>
          </article>
        </CardBody>
      </Card>

      <style jsx global>{`
        @media print {
          aside, header, nav, .print\\:hidden {
            display: none !important;
          }
          body {
            background: white !important;
          }
        }
      `}</style>
    </div>
  );
}
