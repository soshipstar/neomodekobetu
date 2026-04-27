'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface CompanyDetail {
  id: number;
  name: string;
  code: string | null;
  stripe_id: string | null;
  subscription_status: string | null;
  current_price_id: string | null;
  custom_amount: number | null;
  is_custom_pricing: boolean;
  tax_inclusive: boolean;
  current_period_end: string | null;
  cancel_at_period_end: boolean;
  trial_ends_at: string | null;
  contract_started_at: string | null;
  contract_notes: string | null;
  contract_document_path: string | null;
  display_settings: Record<string, unknown> | null;
  feature_flags: Record<string, boolean> | null;
}

interface InvoiceRow {
  id: number;
  number: string | null;
  status: string;
  total: number;
  currency: string;
  period_start: string | null;
  period_end: string | null;
  paid_at: string | null;
  hosted_invoice_url: string | null;
  invoice_pdf: string | null;
}

interface DisplaySettings {
  plan_label: string | null;
  show_amount: boolean;
  show_breakdown: boolean;
  show_next_billing_date: boolean;
  show_invoice_history: 'all' | 'last_12_months' | 'hidden';
  allow_invoice_download: boolean;
  allow_payment_method_edit: boolean;
  allow_self_cancel: boolean;
  announcement: { level?: string; title?: string; body?: string; shown_until?: string | null } | null;
  support_contact: { name?: string; email?: string; phone?: string } | null;
}

const DEFAULT_SETTINGS: DisplaySettings = {
  plan_label: null,
  show_amount: true,
  show_breakdown: true,
  show_next_billing_date: true,
  show_invoice_history: 'all',
  allow_invoice_download: true,
  allow_payment_method_edit: true,
  allow_self_cancel: false,
  announcement: null,
  support_contact: null,
};

function formatJpy(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return `¥${value.toLocaleString('ja-JP')}`;
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleDateString('ja-JP');
  } catch {
    return '—';
  }
}

export default function MasterBillingDetailPage() {
  const params = useParams<{ companyId: string }>();
  const companyId = params?.companyId;
  const toast = useToast();
  const [company, setCompany] = useState<CompanyDetail | null>(null);
  const [invoices, setInvoices] = useState<InvoiceRow[]>([]);
  const [settings, setSettings] = useState<DisplaySettings>(DEFAULT_SETTINGS);
  const [loading, setLoading] = useState(true);
  const [savingPrice, setSavingPrice] = useState(false);
  const [savingSettings, setSavingSettings] = useState(false);
  const [savingFlags, setSavingFlags] = useState(false);
  const [savingSpot, setSavingSpot] = useState(false);
  const [savingCancel, setSavingCancel] = useState(false);

  const [newAmount, setNewAmount] = useState<string>('');
  const [newTaxMode, setNewTaxMode] = useState<'inclusive' | 'exclusive'>('inclusive');
  const [spotAmount, setSpotAmount] = useState<string>('');
  const [spotTaxMode, setSpotTaxMode] = useState<'inclusive' | 'exclusive'>('inclusive');
  const [spotDescription, setSpotDescription] = useState<string>('');
  const TAX_RATE = 0.10;
  const [flagsText, setFlagsText] = useState<string>('{}');
  const [trialDays, setTrialDays] = useState<string>('');
  const [billingDay, setBillingDay] = useState<string>('');
  const [savingSubscribe, setSavingSubscribe] = useState(false);
  const [customerInfo, setCustomerInfo] = useState<{
    name: string;
    email: string;
    phone: string;
    address: { line1: string; line2: string; city: string; state: string; postal_code: string; country: string };
  }>({
    name: '',
    email: '',
    phone: '',
    address: { line1: '', line2: '', city: '', state: '', postal_code: '', country: 'JP' },
  });
  const [savingCustomer, setSavingCustomer] = useState(false);
  const [terms, setTerms] = useState<{
    monthly_fee: string;
    initial_setup_fee: string;
    registration_proxy_fee: string;
    service_start_date: string;
    contract_term_months: string;
    minimum_term_months: string;
    cancellation_notice_months: string;
    early_termination_fee: string;
    billing_day: string;
    training_visit_count: string;
    training_web_count: string;
    target_classrooms_text: string;
    contractor_name: string;
    contractor_address: string;
    representative: string;
    executed_at: string;
    additional_notes: string;
  }>({
    monthly_fee: '', initial_setup_fee: '', registration_proxy_fee: '',
    service_start_date: '', contract_term_months: '',
    minimum_term_months: '', cancellation_notice_months: '', early_termination_fee: '',
    billing_day: '',
    training_visit_count: '', training_web_count: '', target_classrooms_text: '',
    contractor_name: '', contractor_address: '', representative: '',
    executed_at: '', additional_notes: '',
  });
  const [savingTerms, setSavingTerms] = useState(false);

  const fetchTerms = useCallback(async () => {
    if (!companyId) return;
    try {
      const res = await api.get(`/api/admin/master/billing/companies/${companyId}/individual-terms`);
      const t = res.data?.data?.individual_terms || {};
      setTerms({
        monthly_fee: t.monthly_fee ?? '',
        initial_setup_fee: t.initial_setup_fee ?? '',
        registration_proxy_fee: t.registration_proxy_fee ?? '',
        service_start_date: t.service_start_date ?? '',
        contract_term_months: t.contract_term_months != null ? String(t.contract_term_months) : '',
        minimum_term_months: t.minimum_term_months != null ? String(t.minimum_term_months) : '',
        cancellation_notice_months: t.cancellation_notice_months != null ? String(t.cancellation_notice_months) : '',
        early_termination_fee: t.early_termination_fee ?? '',
        billing_day: t.billing_day != null ? String(t.billing_day) : '',
        training_visit_count: t.training_visit_count != null ? String(t.training_visit_count) : '',
        training_web_count: t.training_web_count != null ? String(t.training_web_count) : '',
        target_classrooms_text: Array.isArray(t.target_classrooms) ? t.target_classrooms.join('\n') : '',
        contractor_name: t.contractor_name ?? '',
        contractor_address: t.contractor_address ?? '',
        representative: t.representative ?? '',
        executed_at: t.executed_at ?? '',
        additional_notes: t.additional_notes ?? '',
      });
    } catch {
      // ignore
    }
  }, [companyId]);

  const fetchCustomerInfo = useCallback(async () => {
    if (!companyId) return;
    try {
      const res = await api.get(`/api/admin/master/billing/companies/${companyId}/customer-info`);
      const d = res.data?.data;
      setCustomerInfo({
        name: d?.name ?? '',
        email: d?.email ?? '',
        phone: d?.phone ?? '',
        address: {
          line1: d?.address?.line1 ?? '',
          line2: d?.address?.line2 ?? '',
          city: d?.address?.city ?? '',
          state: d?.address?.state ?? '',
          postal_code: d?.address?.postal_code ?? '',
          country: d?.address?.country ?? 'JP',
        },
      });
    } catch {
      // Customer 未作成時は 200/空が返るので無視
    }
  }, [companyId]);

  const fetchDetail = useCallback(async () => {
    if (!companyId) return;
    setLoading(true);
    try {
      const res = await api.get(`/api/admin/master/billing/companies/${companyId}`);
      const d = res.data?.data;
      setCompany(d?.company ?? null);
      setInvoices(d?.invoices ?? []);
      const ds = { ...DEFAULT_SETTINGS, ...(d?.display_settings || {}) };
      setSettings(ds);
      setFlagsText(JSON.stringify(d?.company?.feature_flags ?? {}, null, 2));
      setNewAmount(d?.company?.custom_amount ? String(d.company.custom_amount) : '');
      setNewTaxMode(d?.company?.tax_inclusive === false ? 'exclusive' : 'inclusive');
      fetchCustomerInfo();
      fetchTerms();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '詳細の取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [companyId, toast]);

  useEffect(() => {
    fetchDetail();
  }, [fetchDetail]);

  const submitPrice = async () => {
    const amount = parseInt(newAmount, 10);
    if (!amount || amount <= 0) {
      toast.error('金額を正しく入力してください');
      return;
    }
    const stripeAmount = newTaxMode === 'inclusive' ? amount : Math.round(amount * (1 + TAX_RATE));
    const taxLabel = newTaxMode === 'inclusive'
      ? `${formatJpy(amount)}（税込）`
      : `${formatJpy(amount)}（税別）／請求額 ${formatJpy(stripeAmount)}`;
    if (!confirm(`カスタム価格 ${taxLabel} を設定し、契約があれば即時切替えます。よろしいですか？`)) return;
    setSavingPrice(true);
    try {
      await api.put(`/api/admin/master/billing/companies/${companyId}/price`, {
        amount,
        tax_mode: newTaxMode,
        interval: 'month',
      });
      toast.success('カスタム価格を設定しました');
      fetchDetail();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '価格設定に失敗しました';
      toast.error(msg);
    } finally {
      setSavingPrice(false);
    }
  };

  const submitSettings = async () => {
    setSavingSettings(true);
    try {
      await api.put(`/api/admin/master/billing/companies/${companyId}/display-settings`, settings);
      toast.success('表示設定を保存しました');
      fetchDetail();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存に失敗しました';
      toast.error(msg);
    } finally {
      setSavingSettings(false);
    }
  };

  const submitFlags = async () => {
    setSavingFlags(true);
    try {
      let parsed: Record<string, boolean>;
      try {
        parsed = JSON.parse(flagsText);
      } catch {
        toast.error('JSON が正しくありません');
        setSavingFlags(false);
        return;
      }
      await api.put(`/api/admin/master/billing/companies/${companyId}/feature-flags`, {
        feature_flags: parsed,
      });
      toast.success('機能フラグを保存しました');
      fetchDetail();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存に失敗しました';
      toast.error(msg);
    } finally {
      setSavingFlags(false);
    }
  };

  const submitSpot = async () => {
    const amount = parseInt(spotAmount, 10);
    if (!amount || amount <= 0 || !spotDescription) {
      toast.error('金額と説明を入力してください');
      return;
    }
    const stripeAmount = spotTaxMode === 'inclusive' ? amount : Math.round(amount * (1 + TAX_RATE));
    const taxLabel = spotTaxMode === 'inclusive'
      ? `${formatJpy(amount)}（税込）`
      : `${formatJpy(amount)}（税別）／請求額 ${formatJpy(stripeAmount)}`;
    if (!confirm(`スポット請求 ${taxLabel} を発行します。よろしいですか？`)) return;
    setSavingSpot(true);
    try {
      await api.post(`/api/admin/master/billing/companies/${companyId}/spot-invoice`, {
        amount,
        tax_mode: spotTaxMode,
        description: spotDescription,
      });
      toast.success('スポット請求を発行しました');
      setSpotAmount('');
      setSpotDescription('');
      fetchDetail();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '発行に失敗しました';
      toast.error(msg);
    } finally {
      setSavingSpot(false);
    }
  };

  const submitTerms = async () => {
    setSavingTerms(true);
    try {
      const payload = {
        individual_terms: {
          monthly_fee: terms.monthly_fee || null,
          initial_setup_fee: terms.initial_setup_fee || null,
          registration_proxy_fee: terms.registration_proxy_fee || null,
          service_start_date: terms.service_start_date || null,
          contract_term_months: terms.contract_term_months ? parseInt(terms.contract_term_months, 10) : null,
          minimum_term_months: terms.minimum_term_months ? parseInt(terms.minimum_term_months, 10) : null,
          cancellation_notice_months: terms.cancellation_notice_months ? parseInt(terms.cancellation_notice_months, 10) : null,
          early_termination_fee: terms.early_termination_fee || null,
          billing_day: terms.billing_day ? parseInt(terms.billing_day, 10) : null,
          training_visit_count: terms.training_visit_count ? parseInt(terms.training_visit_count, 10) : null,
          training_web_count: terms.training_web_count ? parseInt(terms.training_web_count, 10) : null,
          target_classrooms: terms.target_classrooms_text
            ? terms.target_classrooms_text.split(/\r?\n/).map((s) => s.trim()).filter(Boolean)
            : [],
          contractor_name: terms.contractor_name || null,
          contractor_address: terms.contractor_address || null,
          representative: terms.representative || null,
          executed_at: terms.executed_at || null,
          additional_notes: terms.additional_notes || null,
        },
      };
      await api.put(`/api/admin/master/billing/companies/${companyId}/individual-terms`, payload);
      toast.success('個別条件書を保存しました');
      fetchTerms();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存に失敗しました';
      toast.error(msg);
    } finally {
      setSavingTerms(false);
    }
  };

  const submitCustomerInfo = async () => {
    setSavingCustomer(true);
    try {
      const body = {
        name: customerInfo.name,
        email: customerInfo.email || undefined,
        phone: customerInfo.phone || undefined,
        address: customerInfo.address,
      };
      await api.put(`/api/admin/master/billing/companies/${companyId}/customer-info`, body);
      toast.success('請求先情報を更新しました');
      fetchCustomerInfo();
      fetchDetail();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '更新に失敗しました';
      toast.error(msg);
    } finally {
      setSavingCustomer(false);
    }
  };

  const submitSubscribe = async () => {
    if (!company?.current_price_id) {
      toast.error('先にカスタム価格を設定してください');
      return;
    }
    if (!company?.stripe_id) {
      toast.error('先に企業管理者の画面でカードを登録してください（Stripe顧客とカードが必要）');
      return;
    }
    if (!confirm(`現在の価格 ${formatJpy(company.custom_amount)} で月額契約を開始します。よろしいですか？`)) return;
    setSavingSubscribe(true);
    try {
      const body: { price_id: string; trial_days?: number; billing_day?: number } = { price_id: company.current_price_id };
      const td = parseInt(trialDays, 10);
      if (!Number.isNaN(td) && td > 0) body.trial_days = td;
      const bd = parseInt(billingDay, 10);
      if (!Number.isNaN(bd) && bd >= 1 && bd <= 28) body.billing_day = bd;
      await api.post(`/api/admin/master/billing/companies/${companyId}/subscribe`, body);
      toast.success('月額契約を開始しました');
      setTrialDays('');
      fetchDetail();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '契約の開始に失敗しました';
      toast.error(msg);
    } finally {
      setSavingSubscribe(false);
    }
  };

  const submitCancel = async (mode: 'end_of_period' | 'now') => {
    const label = mode === 'now' ? '即時解約' : '期間末で解約';
    if (!confirm(`${label}を実行します。よろしいですか？`)) return;
    setSavingCancel(true);
    try {
      await api.post(`/api/admin/master/billing/companies/${companyId}/cancel`, { mode });
      toast.success(`${label}しました`);
      fetchDetail();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '解約に失敗しました';
      toast.error(msg);
    } finally {
      setSavingCancel(false);
    }
  };

  const settingsCheckbox = (key: keyof DisplaySettings, label: string, hint?: string) => (
    <label className="flex items-start gap-2">
      <input
        type="checkbox"
        className="mt-1"
        checked={Boolean(settings[key])}
        onChange={(e) => setSettings({ ...settings, [key]: e.target.checked })}
      />
      <div>
        <span className="text-sm text-[var(--neutral-foreground-1)]">{label}</span>
        {hint && <p className="text-xs text-[var(--neutral-foreground-3)]">{hint}</p>}
      </div>
    </label>
  );

  const announcement = useMemo(() => settings.announcement || { level: 'info', title: '', body: '', shown_until: null }, [settings.announcement]);
  const updateAnnouncement = (patch: Partial<NonNullable<DisplaySettings['announcement']>>) => {
    const next = { ...announcement, ...patch };
    const empty = !next.title && !next.body;
    setSettings({ ...settings, announcement: empty ? null : next });
  };

  if (loading || !company) {
    return (
      <div className="space-y-4">
        <Link href="/admin/master-billing" className="inline-flex items-center text-sm text-[var(--brand-80)] hover:underline">
          <MaterialIcon name="chevron_left" size={18} />
          一覧に戻る
        </Link>
        <SkeletonList count={5} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Link href="/admin/master-billing" className="inline-flex items-center text-sm text-[var(--brand-80)] hover:underline">
        <MaterialIcon name="chevron_left" size={18} />
        一覧に戻る
      </Link>

      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{company.name}</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">{company.code || '—'}</p>
      </div>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">契約状況</h2>
          <div className="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">状態</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">{company.subscription_status || '未契約'}</p>
            </div>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">月額</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">{formatJpy(company.custom_amount)}</p>
            </div>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">次回請求日</p>
              <p className="mt-1 font-medium text-[var(--neutral-foreground-1)]">{formatDate(company.current_period_end)}</p>
            </div>
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">Stripe顧客ID</p>
              <p className="mt-1 truncate text-xs text-[var(--neutral-foreground-2)]">{company.stripe_id || '未作成'}</p>
            </div>
          </div>
          {company.cancel_at_period_end && (
            <div className="mt-3">
              <Badge variant="warning">解約予約中</Badge>
            </div>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">請求先情報（請求書に表示される顧客情報）</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            Stripe Customer の名称・住所・連絡先を更新します。請求書PDFの「Bill To」欄に反映されます。
          </p>
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">企業名（請求書表示）</label>
              <Input
                type="text"
                value={customerInfo.name}
                onChange={(e) => setCustomerInfo({ ...customerInfo, name: e.target.value })}
                placeholder="例: 株式会社XXX"
              />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">メール（領収書送付先）</label>
              <Input
                type="email"
                value={customerInfo.email}
                onChange={(e) => setCustomerInfo({ ...customerInfo, email: e.target.value })}
                placeholder="billing@example.com"
              />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">電話番号</label>
              <Input
                type="tel"
                value={customerInfo.phone}
                onChange={(e) => setCustomerInfo({ ...customerInfo, phone: e.target.value })}
                placeholder="03-0000-0000"
              />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">国コード</label>
              <Input
                type="text"
                value={customerInfo.address.country}
                onChange={(e) => setCustomerInfo({ ...customerInfo, address: { ...customerInfo.address, country: e.target.value.toUpperCase() } })}
                maxLength={2}
                placeholder="JP"
                className="w-20"
              />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">郵便番号</label>
              <Input
                type="text"
                value={customerInfo.address.postal_code}
                onChange={(e) => setCustomerInfo({ ...customerInfo, address: { ...customerInfo.address, postal_code: e.target.value } })}
                placeholder="100-0001"
              />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">都道府県（state）</label>
              <Input
                type="text"
                value={customerInfo.address.state}
                onChange={(e) => setCustomerInfo({ ...customerInfo, address: { ...customerInfo.address, state: e.target.value } })}
                placeholder="東京都"
              />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">市区町村（city）</label>
              <Input
                type="text"
                value={customerInfo.address.city}
                onChange={(e) => setCustomerInfo({ ...customerInfo, address: { ...customerInfo.address, city: e.target.value } })}
                placeholder="千代田区"
              />
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs text-[var(--neutral-foreground-3)]">住所1（line1）</label>
              <Input
                type="text"
                value={customerInfo.address.line1}
                onChange={(e) => setCustomerInfo({ ...customerInfo, address: { ...customerInfo.address, line1: e.target.value } })}
                placeholder="千代田1-1-1"
              />
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs text-[var(--neutral-foreground-3)]">住所2（line2・建物名など）</label>
              <Input
                type="text"
                value={customerInfo.address.line2}
                onChange={(e) => setCustomerInfo({ ...customerInfo, address: { ...customerInfo.address, line2: e.target.value } })}
                placeholder="○○ビル 5F"
              />
            </div>
          </div>
          <div className="mt-3">
            <Button onClick={submitCustomerInfo} disabled={savingCustomer}>
              請求先情報を保存
            </Button>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">個別条件書</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            標準契約書本文では具体金額・期日を抽象化し、ここに「個別条件書」として企業ごとに定めます。
            企業管理者は <code>/admin/billing/terms</code> で閲覧・印刷できます。
          </p>
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">月額利用料（表示用）</label>
              <Input type="text" value={terms.monthly_fee} onChange={(e) => setTerms({ ...terms, monthly_fee: e.target.value })} placeholder="例: ¥10,000（税別）" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">初期費用（表示用）</label>
              <Input type="text" value={terms.initial_setup_fee} onChange={(e) => setTerms({ ...terms, initial_setup_fee: e.target.value })} placeholder="例: ¥80,000（税別）" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">登録代行費用（任意）</label>
              <Input type="text" value={terms.registration_proxy_fee} onChange={(e) => setTerms({ ...terms, registration_proxy_fee: e.target.value })} placeholder="例: ¥40,000（希望時）" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">サービス開始日</label>
              <Input type="text" value={terms.service_start_date} onChange={(e) => setTerms({ ...terms, service_start_date: e.target.value })} placeholder="例: 2026-04-27" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">契約期間（ヶ月）</label>
              <Input type="number" value={terms.contract_term_months} onChange={(e) => setTerms({ ...terms, contract_term_months: e.target.value })} placeholder="12" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">最低利用期間（ヶ月）</label>
              <Input type="number" value={terms.minimum_term_months} onChange={(e) => setTerms({ ...terms, minimum_term_months: e.target.value })} placeholder="12" />
              <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">この期間内は中途解約不可</p>
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">解約予告期間（ヶ月）</label>
              <Input type="number" value={terms.cancellation_notice_months} onChange={(e) => setTerms({ ...terms, cancellation_notice_months: e.target.value })} placeholder="1" />
              <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">期間満了前の通知期限</p>
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs text-[var(--neutral-foreground-3)]">中途解約違約金</label>
              <Input type="text" value={terms.early_termination_fee} onChange={(e) => setTerms({ ...terms, early_termination_fee: e.target.value })} placeholder="例: 残期間の月額利用料相当額" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">毎月の請求日</label>
              <Input type="number" value={terms.billing_day} onChange={(e) => setTerms({ ...terms, billing_day: e.target.value })} placeholder="27" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">訪問研修回数</label>
              <Input type="number" value={terms.training_visit_count} onChange={(e) => setTerms({ ...terms, training_visit_count: e.target.value })} placeholder="1" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">WEB研修回数</label>
              <Input type="number" value={terms.training_web_count} onChange={(e) => setTerms({ ...terms, training_web_count: e.target.value })} placeholder="2" />
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs text-[var(--neutral-foreground-3)]">対象教室（1行ごとに1教室）</label>
              <textarea
                value={terms.target_classrooms_text}
                onChange={(e) => setTerms({ ...terms, target_classrooms_text: e.target.value })}
                rows={3}
                className="mt-1 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                placeholder="デモ教室"
              />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">契約者名</label>
              <Input type="text" value={terms.contractor_name} onChange={(e) => setTerms({ ...terms, contractor_name: e.target.value })} placeholder="○○株式会社" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">代表者</label>
              <Input type="text" value={terms.representative} onChange={(e) => setTerms({ ...terms, representative: e.target.value })} placeholder="代表取締役 山田太郎" />
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs text-[var(--neutral-foreground-3)]">契約者住所</label>
              <Input type="text" value={terms.contractor_address} onChange={(e) => setTerms({ ...terms, contractor_address: e.target.value })} placeholder="東京都..." />
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs text-[var(--neutral-foreground-3)]">締結日</label>
              <Input type="text" value={terms.executed_at} onChange={(e) => setTerms({ ...terms, executed_at: e.target.value })} placeholder="例: 2026-04-27" />
              <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">合意管轄裁判所は契約書本文（第18条）で「横浜地方裁判所」固定です</p>
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs text-[var(--neutral-foreground-3)]">特記事項</label>
              <textarea
                value={terms.additional_notes}
                onChange={(e) => setTerms({ ...terms, additional_notes: e.target.value })}
                rows={4}
                className="mt-1 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                placeholder="追加の合意事項などを記入"
              />
            </div>
          </div>
          <div className="mt-3">
            <Button onClick={submitTerms} disabled={savingTerms}>
              個別条件書を保存
            </Button>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">カスタム価格設定</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            既存契約があれば新Priceに即時切替され、次回請求から反映されます。
          </p>
          <div className="mt-3 flex flex-wrap items-end gap-3">
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">月額（円）</label>
              <Input
                type="number"
                value={newAmount}
                onChange={(e) => setNewAmount(e.target.value)}
                placeholder="例: 30000"
                className="w-40"
              />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">税区分</label>
              <div className="mt-1 flex gap-3 text-sm">
                <label className="inline-flex items-center gap-1">
                  <input type="radio" name="newTaxMode" value="inclusive" checked={newTaxMode === 'inclusive'} onChange={() => setNewTaxMode('inclusive')} />
                  税込
                </label>
                <label className="inline-flex items-center gap-1">
                  <input type="radio" name="newTaxMode" value="exclusive" checked={newTaxMode === 'exclusive'} onChange={() => setNewTaxMode('exclusive')} />
                  税別
                </label>
              </div>
            </div>
            <Button onClick={submitPrice} disabled={savingPrice}>
              価格を設定・更新
            </Button>
          </div>
          {newAmount && parseInt(newAmount, 10) > 0 && (
            <p className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
              {newTaxMode === 'inclusive'
                ? `→ Stripe 請求額: ${formatJpy(parseInt(newAmount, 10))}（税込）`
                : `→ Stripe 請求額: ${formatJpy(Math.round(parseInt(newAmount, 10) * (1 + TAX_RATE)))}（税込・消費税${Math.round(TAX_RATE * 100)}%加算）`}
            </p>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">月額契約の開始</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            現在のカスタム価格で Stripe 上に Subscription を作成し、毎月自動課金を開始します。
            事前にカスタム価格設定と、企業側でのカード登録が必要です。
          </p>
          <div className="mt-3 flex flex-wrap items-end gap-3">
            <div>
              <p className="text-xs text-[var(--neutral-foreground-3)]">適用価格</p>
              <p className="font-medium text-[var(--neutral-foreground-1)]">
                {formatJpy(company.custom_amount)} / 月
              </p>
              <p className="text-[10px] text-[var(--neutral-foreground-4)]">{company.current_price_id || 'price未設定'}</p>
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">毎月の請求日（任意・1〜28日）</label>
              <select
                value={billingDay}
                onChange={(e) => setBillingDay(e.target.value)}
                className="mt-1 rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm w-32"
              >
                <option value="">指定しない</option>
                {Array.from({ length: 28 }, (_, i) => i + 1).map((d) => (
                  <option key={d} value={d}>{d}日</option>
                ))}
              </select>
              <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">指定日まで無料、以降毎月その日に課金</p>
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">トライアル日数（任意）</label>
              <Input
                type="number"
                value={trialDays}
                onChange={(e) => setTrialDays(e.target.value)}
                placeholder="例: 14"
                className="w-28"
              />
              <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">請求日指定より優先</p>
            </div>
            <Button
              onClick={submitSubscribe}
              disabled={savingSubscribe || !company.current_price_id || !company.stripe_id || company.subscription_status === 'active' || company.subscription_status === 'trialing'}
            >
              月額契約を開始
            </Button>
          </div>
          {(company.subscription_status === 'active' || company.subscription_status === 'trialing') && (
            <p className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
              すでに有効な契約があります（status: {company.subscription_status}）。新規開始するには先に解約してください。
            </p>
          )}
          {!company.stripe_id && (
            <p className="mt-2 text-xs text-[var(--status-warning-fg)]">
              Stripe顧客が未作成です。企業管理者画面（/admin/billing）で「支払い方法・領収書」ボタンを一度クリックして
              カードを登録してください。
            </p>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">表示制御（企業管理者ビュー）</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            企業管理者の請求画面で何を見せるかを制御します。
          </p>
          <div className="mt-4 space-y-4">
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">プラン名の上書き表示</label>
              <Input
                type="text"
                value={settings.plan_label ?? ''}
                onChange={(e) => setSettings({ ...settings, plan_label: e.target.value || null })}
                placeholder="例: A社特別プラン"
              />
            </div>

            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
              {settingsCheckbox('show_amount', '金額を表示する')}
              {settingsCheckbox('show_breakdown', '内訳を表示する')}
              {settingsCheckbox('show_next_billing_date', '次回請求日を表示する')}
              {settingsCheckbox('allow_invoice_download', '請求書PDFのダウンロードを許可')}
              {settingsCheckbox('allow_payment_method_edit', '支払い方法の編集を許可')}
              {settingsCheckbox('allow_self_cancel', '企業管理者による解約予約を許可')}
            </div>

            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">請求履歴の表示範囲</label>
              <select
                value={settings.show_invoice_history}
                onChange={(e) => setSettings({ ...settings, show_invoice_history: e.target.value as DisplaySettings['show_invoice_history'] })}
                className="mt-1 rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              >
                <option value="all">すべて表示</option>
                <option value="last_12_months">直近12ヶ月のみ</option>
                <option value="hidden">非表示</option>
              </select>
            </div>

            <div>
              <p className="text-xs font-semibold text-[var(--neutral-foreground-2)]">お知らせバナー（任意）</p>
              <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                  <label className="block text-xs text-[var(--neutral-foreground-3)]">レベル</label>
                  <select
                    value={announcement.level || 'info'}
                    onChange={(e) => updateAnnouncement({ level: e.target.value })}
                    className="mt-1 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                  >
                    <option value="info">情報</option>
                    <option value="warning">警告</option>
                    <option value="critical">重要</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-[var(--neutral-foreground-3)]">表示終了日</label>
                  <Input
                    type="date"
                    value={announcement.shown_until ?? ''}
                    onChange={(e) => updateAnnouncement({ shown_until: e.target.value || null })}
                  />
                </div>
              </div>
              <div className="mt-2">
                <label className="block text-xs text-[var(--neutral-foreground-3)]">タイトル</label>
                <Input
                  type="text"
                  value={announcement.title ?? ''}
                  onChange={(e) => updateAnnouncement({ title: e.target.value })}
                  placeholder="例: 来月から価格改定のお知らせ"
                />
              </div>
              <div className="mt-2">
                <label className="block text-xs text-[var(--neutral-foreground-3)]">本文</label>
                <textarea
                  value={announcement.body ?? ''}
                  onChange={(e) => updateAnnouncement({ body: e.target.value })}
                  rows={3}
                  className="mt-1 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                />
              </div>
            </div>

            <Button onClick={submitSettings} disabled={savingSettings}>
              表示設定を保存
            </Button>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">機能フラグ（feature_flags）</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            JSONで上書きします。例: {`{ "ai_analysis": true, "excel_export": false }`}
          </p>
          <textarea
            value={flagsText}
            onChange={(e) => setFlagsText(e.target.value)}
            rows={6}
            className="mt-2 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 font-mono text-xs"
          />
          <div className="mt-2">
            <Button onClick={submitFlags} disabled={savingFlags}>
              機能フラグを保存
            </Button>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">スポット請求</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            初期費用や臨時の追加機能などの都度請求を発行します。
          </p>
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">金額（円）</label>
              <Input type="number" value={spotAmount} onChange={(e) => setSpotAmount(e.target.value)} />
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs text-[var(--neutral-foreground-3)]">説明（請求書に表示）</label>
              <Input type="text" value={spotDescription} onChange={(e) => setSpotDescription(e.target.value)} placeholder="例: 初期セットアップ費用" />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">税区分</label>
              <div className="mt-1 flex gap-3 text-sm">
                <label className="inline-flex items-center gap-1">
                  <input type="radio" name="spotTaxMode" value="inclusive" checked={spotTaxMode === 'inclusive'} onChange={() => setSpotTaxMode('inclusive')} />
                  税込
                </label>
                <label className="inline-flex items-center gap-1">
                  <input type="radio" name="spotTaxMode" value="exclusive" checked={spotTaxMode === 'exclusive'} onChange={() => setSpotTaxMode('exclusive')} />
                  税別
                </label>
              </div>
            </div>
          </div>
          {spotAmount && parseInt(spotAmount, 10) > 0 && (
            <p className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
              {spotTaxMode === 'inclusive'
                ? `→ Stripe 請求額: ${formatJpy(parseInt(spotAmount, 10))}（税込）`
                : `→ Stripe 請求額: ${formatJpy(Math.round(parseInt(spotAmount, 10) * (1 + TAX_RATE)))}（税込・消費税${Math.round(TAX_RATE * 100)}%加算）`}
            </p>
          )}
          <div className="mt-3">
            <Button onClick={submitSpot} disabled={savingSpot}>
              スポット請求を発行
            </Button>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">解約</h2>
          <div className="mt-3 flex flex-wrap gap-2">
            <Button variant="ghost" onClick={() => submitCancel('end_of_period')} disabled={savingCancel}>
              期間末で解約予約
            </Button>
            <Button variant="ghost" onClick={() => submitCancel('now')} disabled={savingCancel} className="text-[var(--status-danger-fg)]">
              即時解約
            </Button>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">請求履歴（Full）</h2>
          {invoices.length === 0 ? (
            <p className="mt-3 text-sm text-[var(--neutral-foreground-3)]">請求はまだありません。</p>
          ) : (
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">期間</th>
                    <th className="py-2 pr-4 font-normal">番号</th>
                    <th className="py-2 pr-4 font-normal">状態</th>
                    <th className="py-2 pr-4 font-normal text-right">金額</th>
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
                      <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{inv.status}</td>
                      <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-1)]">{formatJpy(inv.total)}</td>
                      <td className="py-3 text-right">
                        {inv.invoice_pdf && (
                          <a href={inv.invoice_pdf} target="_blank" rel="noopener" className="text-[var(--brand-80)] hover:underline">
                            <MaterialIcon name="download" size={18} />
                          </a>
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

      {company.contract_notes && (
        <Card>
          <CardBody>
            <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">契約メモ（社内）</h2>
            <p className="mt-2 whitespace-pre-wrap text-sm text-[var(--neutral-foreground-2)]">{company.contract_notes}</p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
