'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useAuthStore } from '@/stores/authStore';

interface IndividualTerms {
  monthly_fee?: string | null;
  initial_setup_fee?: string | null;
  registration_proxy_fee?: string | null;
  service_start_date?: string | null;
  contract_term_months?: number | null;
  minimum_term_months?: number | null;
  cancellation_notice_months?: number | null;
  early_termination_fee?: string | null;
  billing_day?: number | null;
  training_visit_count?: number | null;
  training_web_count?: number | null;
  target_classrooms?: string[] | null;
  contractor_name?: string | null;
  contractor_address?: string | null;
  representative?: string | null;
  executed_at?: string | null;
  additional_notes?: string | null;
}

interface CompanySummary {
  id: number;
  name: string;
  custom_amount: number | null;
  current_period_end: string | null;
  contract_started_at: string | null;
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleDateString('ja-JP');
  } catch {
    return value;
  }
}

function or(value: unknown, fallback = '—'): string {
  if (value === null || value === undefined || value === '') return fallback;
  return String(value);
}

export default function BillingTermsPage() {
  const toast = useToast();
  const router = useRouter();
  const { user } = useAuthStore();
  const [terms, setTerms] = useState<IndividualTerms | null>(null);
  const [company, setCompany] = useState<CompanySummary | null>(null);
  const [loading, setLoading] = useState(true);

  // マスター管理者は自社を持たないので、企業課金管理画面へ自動誘導する。
  useEffect(() => {
    if (user?.user_type === 'admin' && user.is_master) {
      router.replace('/admin/master-billing');
    }
  }, [user, router]);

  const fetchTerms = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/api/admin/billing/individual-terms');
      setCompany(res.data?.data?.company ?? null);
      setTerms(res.data?.data?.individual_terms ?? null);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '個別条件書の取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchTerms();
  }, [fetchTerms]);

  const print = () => {
    if (typeof window !== 'undefined') window.print();
  };

  if (loading) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別条件書</h1>
        <SkeletonList count={2} />
      </div>
    );
  }

  return (
    <div className="space-y-6 print:space-y-4">
      <div className="flex items-center justify-between print:hidden">
        <Link href="/admin/billing" className="inline-flex items-center text-sm text-[var(--brand-80)] hover:underline">
          <MaterialIcon name="chevron_left" size={18} />
          請求・契約に戻る
        </Link>
        <Button variant="ghost" onClick={print}>
          <MaterialIcon name="print" size={18} />
          <span className="ml-1">印刷</span>
        </Button>
      </div>

      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別条件書</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">
          きづり 利用契約書の各条において「個別条件書に定める」と引用される具体条件を本書面に記載します。
        </p>
        {company && (
          <p className="mt-1 text-sm text-[var(--neutral-foreground-2)]">
            契約者: <span className="font-medium">{company.name}</span>
          </p>
        )}
      </div>

      {!terms && (
        <Card>
          <CardBody>
            <p className="text-sm text-[var(--neutral-foreground-3)]">
              個別条件書はまだ登録されていません。マスター管理者により設定されると、ここに表示されます。
            </p>
          </CardBody>
        </Card>
      )}

      {terms && (
        <Card>
          <CardBody>
            <table className="w-full text-sm">
              <tbody>
                <Row label="月額利用料" value={or(terms.monthly_fee)} />
                <Row label="初期費用" value={or(terms.initial_setup_fee)} />
                <Row label="登録代行費用" value={or(terms.registration_proxy_fee)} />
                <Row
                  label="サービス開始日"
                  value={or(terms.service_start_date) !== '—' ? terms.service_start_date! : formatDate(company?.contract_started_at)}
                />
                <Row label="契約期間" value={terms.contract_term_months ? `${terms.contract_term_months}ヶ月（自動更新）` : '—'} />
                <Row label="最低利用期間" value={terms.minimum_term_months != null ? `${terms.minimum_term_months}ヶ月` : '—'} />
                <Row label="解約予告期間" value={terms.cancellation_notice_months != null ? `${terms.cancellation_notice_months}ヶ月前まで` : '—'} />
                <Row label="中途解約違約金" value={or(terms.early_termination_fee)} />
                <Row
                  label="毎月の請求日"
                  value={terms.billing_day ? `${terms.billing_day}日` : '—'}
                />
                <Row label="訪問研修回数" value={terms.training_visit_count != null ? `${terms.training_visit_count}回` : '—'} />
                <Row label="WEB研修回数" value={terms.training_web_count != null ? `${terms.training_web_count}回` : '—'} />
                <Row
                  label="対象教室"
                  value={Array.isArray(terms.target_classrooms) && terms.target_classrooms.length > 0
                    ? terms.target_classrooms.join('、')
                    : '—'}
                />
                <Row label="契約者" value={or(terms.contractor_name)} />
                <Row label="契約者住所" value={or(terms.contractor_address)} />
                <Row label="代表者" value={or(terms.representative)} />
                <Row label="締結日" value={or(terms.executed_at)} />
                <Row label="合意管轄裁判所" value="横浜地方裁判所（契約書 第18条 固定）" />
                <Row label="特記事項" value={or(terms.additional_notes)} multiline />
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}

      <p className="text-xs text-[var(--neutral-foreground-3)] print:mt-4">
        本書は「きづり 利用契約書」の付随書面です。本書記載の条件は契約書本文に優先して適用されます。
      </p>

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

function Row({ label, value, multiline }: { label: string; value: string; multiline?: boolean }) {
  return (
    <tr className="border-b border-[var(--neutral-stroke-3)]">
      <th className="w-1/3 py-2 pr-3 text-left align-top font-normal text-[var(--neutral-foreground-3)]">{label}</th>
      <td className={`py-2 pl-3 align-top text-[var(--neutral-foreground-1)] ${multiline ? 'whitespace-pre-wrap' : ''}`}>
        {value || '—'}
      </td>
    </tr>
  );
}
