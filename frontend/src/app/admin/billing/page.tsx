'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/authStore';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Subscription {
  company_id: number;
  status: string | null;
  is_custom_pricing: boolean;
  tax_inclusive?: boolean;
  amount: number | null;
  amount_total?: number | null;
  tax_rate?: number;
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
  can_cancel?: boolean;
  can_edit_payment_method?: boolean;
  announcement?: {
    level?: string;
    title?: string;
    body?: string;
    shown_until?: string | null;
  } | null;
  support_contact?: { name?: string; email?: string; phone?: string } | null;
}

interface Invoice {
  id: number;
  number: string | null;
  status: string;
  total: number;
  currency: string;
  period_start: string | null;
  period_end: string | null;
  paid_at: string | null;
  can_download: boolean;
}

const STATUS_LABEL: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'default' }> = {
  active: { label: '有効', variant: 'success' },
  trialing: { label: 'トライアル中', variant: 'warning' },
  past_due: { label: '支払い遅延', variant: 'danger' },
  canceled: { label: '解約済み', variant: 'default' },
  incomplete: { label: '未完了', variant: 'warning' },
  unpaid: { label: '未払い', variant: 'danger' },
};

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

export default function BillingPage() {
  const toast = useToast();
  const router = useRouter();
  const { user } = useAuthStore();
  const [subscription, setSubscription] = useState<Subscription | null>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [hiddenByMaster, setHiddenByMaster] = useState(false);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  // マスター管理者は自社を持たないので、企業課金管理画面へ自動誘導する。
  useEffect(() => {
    if (user?.user_type === 'admin' && user.is_master) {
      router.replace('/admin/master-billing');
    }
  }, [user, router]);

  const fetchAll = useCallback(async () => {
    setLoading(true);
    try {
      const [subRes, invRes] = await Promise.all([
        api.get('/api/admin/billing/subscription'),
        api.get('/api/admin/billing/invoices'),
      ]);
      setSubscription(subRes.data.data);
      setInvoices(invRes.data.data || []);
      setHiddenByMaster(invRes.data.meta?.hidden_by_master === true);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '請求情報の取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchAll();
  }, [fetchAll]);

  const openPortal = async () => {
    setActionLoading(true);
    try {
      const res = await api.post('/api/admin/billing/portal', {
        return_url: typeof window !== 'undefined' ? window.location.href : undefined,
      });
      if (res.data?.data?.url) {
        window.location.href = res.data.data.url;
      }
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'ポータルを開けませんでした';
      toast.error(msg);
    } finally {
      setActionLoading(false);
    }
  };

  const cancelPlan = async () => {
    if (!confirm('現在の契約を期間末で解約します。よろしいですか？')) return;
    setActionLoading(true);
    try {
      await api.post('/api/admin/billing/cancel');
      toast.success('解約を予約しました');
      fetchAll();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '解約に失敗しました';
      toast.error(msg);
    } finally {
      setActionLoading(false);
    }
  };

  const resumePlan = async () => {
    setActionLoading(true);
    try {
      await api.post('/api/admin/billing/resume');
      toast.success('契約を継続しました');
      fetchAll();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '操作に失敗しました';
      toast.error(msg);
    } finally {
      setActionLoading(false);
    }
  };

  const downloadInvoice = async (invoice: Invoice) => {
    try {
      const res = await api.get(`/api/admin/billing/invoices/${invoice.id}/pdf`);
      const url = res.data?.data?.invoice_pdf || res.data?.data?.hosted_invoice_url;
      if (url) {
        window.open(url, '_blank', 'noopener');
      } else {
        toast.error('PDFのURLが取得できませんでした');
      }
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'PDFの取得に失敗しました';
      toast.error(msg);
    }
  };

  if (loading) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">請求・契約</h1>
        <SkeletonList count={3} />
      </div>
    );
  }

  if (!subscription) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">請求・契約</h1>
        <Card>
          <CardBody>
            <p className="text-[var(--neutral-foreground-3)]">契約情報を取得できませんでした。</p>
          </CardBody>
        </Card>
      </div>
    );
  }

  const status = subscription.status ? STATUS_LABEL[subscription.status] : null;
  const announcement = subscription.announcement;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">請求・契約</h1>
        <div className="flex items-center gap-3">
          <Link
            href="/admin/billing/contract"
            className="inline-flex items-center gap-1 text-sm text-[var(--brand-80)] hover:underline"
          >
            <MaterialIcon name="description" size={18} />
            契約書を見る
          </Link>
          <Link
            href="/admin/billing/terms"
            className="inline-flex items-center gap-1 text-sm text-[var(--brand-80)] hover:underline"
          >
            <MaterialIcon name="article" size={18} />
            個別条件書を見る
          </Link>
        </div>
      </div>

      {announcement && (announcement.title || announcement.body) && (
        <Card>
          <CardBody>
            <div className="flex items-start gap-3">
              <MaterialIcon
                name={announcement.level === 'critical' ? 'error' : announcement.level === 'warning' ? 'warning' : 'info'}
                size={24}
                className={
                  announcement.level === 'critical'
                    ? 'text-[var(--status-danger-fg)]'
                    : announcement.level === 'warning'
                      ? 'text-[var(--status-warning-fg)]'
                      : 'text-[var(--brand-80)]'
                }
              />
              <div className="flex-1">
                {announcement.title && <p className="font-semibold text-[var(--neutral-foreground-1)]">{announcement.title}</p>}
                {announcement.body && <p className="mt-1 text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{announcement.body}</p>}
              </div>
            </div>
          </CardBody>
        </Card>
      )}

      <Card>
        <CardBody>
          <div className="flex items-start justify-between flex-wrap gap-4">
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">現在のプラン</p>
              <div className="mt-1 flex items-center gap-2">
                <p className="text-lg font-semibold text-[var(--neutral-foreground-1)]">
                  {subscription.plan_label || (subscription.is_custom_pricing ? 'カスタムプラン' : '標準プラン')}
                </p>
                {status && <Badge variant={status.variant}>{status.label}</Badge>}
                {subscription.cancel_at_period_end && <Badge variant="warning">解約予約中</Badge>}
              </div>
              {subscription.amount !== null && subscription.amount !== undefined && (
                <>
                  <p className="mt-2 text-2xl font-bold text-[var(--neutral-foreground-1)]">
                    {formatJpy(subscription.amount)}
                    <span className="ml-1 text-sm font-normal text-[var(--neutral-foreground-3)]">
                      （{subscription.tax_inclusive === false ? '税別' : '税込'}）/ 月
                    </span>
                  </p>
                  {subscription.tax_inclusive === false && subscription.amount_total != null && (
                    <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                      請求額（税込）: {formatJpy(subscription.amount_total)}
                      {subscription.tax_rate ? `（消費税${Math.round(subscription.tax_rate * 100)}%）` : ''}
                    </p>
                  )}
                </>
              )}
            </div>
            <div className="text-right">
              {subscription.current_period_end && (
                <>
                  <p className="text-xs text-[var(--neutral-foreground-3)]">次回請求日</p>
                  <p className="font-medium text-[var(--neutral-foreground-1)]">{formatDate(subscription.current_period_end)}</p>
                </>
              )}
              {subscription.trial_ends_at && (
                <p className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
                  トライアル終了: {formatDate(subscription.trial_ends_at)}
                </p>
              )}
            </div>
          </div>

          <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">支払い方法</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">
                {subscription.pm_type ? `${subscription.pm_type.toUpperCase()} •••• ${subscription.pm_last_four || '----'}` : '未登録'}
              </p>
            </div>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">契約開始日</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">{formatDate(subscription.contract_started_at)}</p>
            </div>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            {subscription.can_edit_payment_method !== false && (
              <Button onClick={openPortal} disabled={actionLoading}>
                <MaterialIcon name="credit_card" size={18} />
                <span className="ml-1">支払い方法・領収書</span>
              </Button>
            )}
            {subscription.can_cancel !== false && !subscription.cancel_at_period_end && subscription.is_active && (
              <Button variant="ghost" onClick={cancelPlan} disabled={actionLoading}>
                解約を予約
              </Button>
            )}
            {subscription.cancel_at_period_end && (
              <Button variant="ghost" onClick={resumePlan} disabled={actionLoading}>
                解約を取り消す
              </Button>
            )}
            {subscription.contract_document_path && (
              <Button variant="ghost" onClick={() => window.open(subscription.contract_document_path!, '_blank', 'noopener')}>
                <MaterialIcon name="description" size={18} />
                <span className="ml-1">契約書を開く</span>
              </Button>
            )}
          </div>
        </CardBody>
      </Card>

      {subscription.support_contact && (subscription.support_contact.name || subscription.support_contact.email) && (
        <Card>
          <CardBody>
            <p className="text-xs text-[var(--neutral-foreground-3)]">担当・サポート窓口</p>
            <div className="mt-1 text-sm text-[var(--neutral-foreground-1)]">
              {subscription.support_contact.name && <p className="font-medium">{subscription.support_contact.name}</p>}
              {subscription.support_contact.email && (
                <p className="text-[var(--neutral-foreground-2)]">
                  <a href={`mailto:${subscription.support_contact.email}`} className="hover:underline">{subscription.support_contact.email}</a>
                </p>
              )}
              {subscription.support_contact.phone && <p className="text-[var(--neutral-foreground-2)]">{subscription.support_contact.phone}</p>}
            </div>
          </CardBody>
        </Card>
      )}

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">請求履歴</h2>
          {hiddenByMaster ? (
            <p className="mt-3 text-sm text-[var(--neutral-foreground-3)]">
              請求履歴の表示は管理者により非公開に設定されています。
            </p>
          ) : invoices.length === 0 ? (
            <p className="mt-3 text-sm text-[var(--neutral-foreground-3)]">請求履歴はまだありません。</p>
          ) : (
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">期間</th>
                    <th className="py-2 pr-4 font-normal">番号</th>
                    <th className="py-2 pr-4 font-normal">状態</th>
                    <th className="py-2 pr-4 font-normal text-right">金額</th>
                    <th className="py-2 pr-4 font-normal">支払日</th>
                    <th className="py-2 font-normal" />
                  </tr>
                </thead>
                <tbody>
                  {invoices.map((inv) => (
                    <tr key={inv.id} className="border-b border-[var(--neutral-stroke-3)]">
                      <td className="py-3 pr-4 text-[var(--neutral-foreground-1)]">
                        {formatDate(inv.period_start)} – {formatDate(inv.period_end)}
                      </td>
                      <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{inv.number || '—'}</td>
                      <td className="py-3 pr-4">
                        {(() => {
                          const s = STATUS_LABEL[inv.status];
                          return s ? <Badge variant={s.variant}>{s.label}</Badge> : <Badge variant="default">{inv.status}</Badge>;
                        })()}
                      </td>
                      <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-1)]">{formatJpy(inv.total)}</td>
                      <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(inv.paid_at)}</td>
                      <td className="py-3 text-right">
                        {inv.can_download && (
                          <Button variant="ghost" onClick={() => downloadInvoice(inv)}>
                            <MaterialIcon name="download" size={18} />
                          </Button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
