'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Company {
  id: number;
  name: string;
  code?: string | null;
}

interface IndividualContract {
  id: number;
  agent_id: number;
  company_id: number;
  contract_date: string | null;
  start_date: string | null;
  end_date: string | null;
  terms: string | null;
  monthly_fee: number | null;
  commission_rate: string | number | null;
  soship_signed: boolean;
  soship_signed_at: string | null;
  agent_signed: boolean;
  agent_signed_at: string | null;
  customer_signed: boolean;
  customer_signed_at: string | null;
  contract_document_path: string | null;
  company?: Company;
  creator?: { id: number; full_name: string } | null;
  updater?: { id: number; full_name: string } | null;
}

type FormState = {
  company_id: string;
  contract_date: string;
  start_date: string;
  end_date: string;
  terms: string;
  monthly_fee: string;
  commission_rate: string;
  agent_signed: boolean;
};

const emptyForm: FormState = {
  company_id: '',
  contract_date: '',
  start_date: '',
  end_date: '',
  terms: '',
  monthly_fee: '',
  commission_rate: '',
  agent_signed: false,
};

function fmtRate(r: string | number | null): string {
  if (r == null || r === '') return '—';
  return `${(parseFloat(String(r)) * 100).toFixed(1)}%`;
}

function fmtYen(n: number | null): string {
  if (n == null) return '—';
  return `¥${n.toLocaleString('ja-JP')}`;
}

export default function AgentIndividualContractsPage() {
  const toast = useToast();
  const [contracts, setContracts] = useState<IndividualContract[]>([]);
  const [loading, setLoading] = useState(true);
  const [companies, setCompanies] = useState<Company[]>([]);

  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [saving, setSaving] = useState(false);

  const fetchContracts = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get('/api/agent/individual-contracts');
      const data = res.data?.data ?? [];
      setContracts(Array.isArray(data) ? data : []);
    } catch {
      toast.error('個別契約書の取得に失敗しました');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  const fetchCompanies = useCallback(async () => {
    try {
      const res = await api.get('/api/agent/companies');
      const data = res.data?.data ?? [];
      setCompanies(Array.isArray(data) ? data.map((c: Company) => ({ id: c.id, name: c.name, code: c.code })) : []);
    } catch {
      // ignore
    }
  }, []);

  useEffect(() => { fetchContracts(); fetchCompanies(); }, [fetchContracts, fetchCompanies]);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm);
    setModalOpen(true);
  };

  const openEdit = (c: IndividualContract) => {
    setEditingId(c.id);
    setForm({
      company_id: String(c.company_id),
      contract_date: c.contract_date ?? '',
      start_date: c.start_date ?? '',
      end_date: c.end_date ?? '',
      terms: c.terms ?? '',
      monthly_fee: c.monthly_fee != null ? String(c.monthly_fee) : '',
      commission_rate: c.commission_rate != null ? String(c.commission_rate) : '',
      agent_signed: c.agent_signed,
    });
    setModalOpen(true);
  };

  const handleSave = async () => {
    if (!editingId && !form.company_id) {
      toast.error('顧客企業を選択してください');
      return;
    }
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        contract_date: form.contract_date || null,
        start_date: form.start_date || null,
        end_date: form.end_date || null,
        terms: form.terms.trim() || null,
        monthly_fee: form.monthly_fee === '' ? null : Number(form.monthly_fee),
        commission_rate: form.commission_rate === '' ? null : Number(form.commission_rate),
        agent_signed: form.agent_signed,
      };
      if (editingId) {
        await api.put(`/api/agent/individual-contracts/${editingId}`, payload);
        toast.success('個別契約書を更新しました');
      } else {
        payload.company_id = Number(form.company_id);
        await api.post('/api/agent/individual-contracts', payload);
        toast.success('個別契約書を作成しました');
      }
      setModalOpen(false);
      fetchContracts();
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const errors = e?.response?.data?.errors;
      const msg = errors ? Object.values(errors)[0]?.[0] : e?.response?.data?.message;
      toast.error(msg || '保存に失敗しました');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (c: IndividualContract) => {
    if (!confirm(`${c.company?.name ?? ''} との個別契約書を削除しますか?`)) return;
    try {
      await api.delete(`/api/agent/individual-contracts/${c.id}`);
      toast.success('削除しました');
      fetchContracts();
    } catch {
      toast.error('削除に失敗しました');
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別契約書</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">株式会社ソーシップ × 貴代理店 × 顧客企業 の3者間契約</p>
        </div>
        <Button onClick={openCreate} leftIcon={<MaterialIcon name="add" size={16} />}>
          新規作成
        </Button>
      </div>

      {loading ? (
        <div className="space-y-3">{[...Array(3)].map((_, i) => <Skeleton key={i} className="h-32 rounded-lg" />)}</div>
      ) : contracts.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <MaterialIcon name="description" size={48} className="mx-auto mb-3" />
              <p className="text-sm">個別契約書がまだ登録されていません</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-3">
          {contracts.map((c) => (
            <ContractCard key={c.id} contract={c} onEdit={() => openEdit(c)} onDelete={() => handleDelete(c)} onChanged={fetchContracts} />
          ))}
        </div>
      )}

      {/* Create/Edit modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editingId ? '個別契約書を編集' : '個別契約書を新規作成'} size="lg">
        <div className="space-y-3">
          {!editingId && (
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">顧客企業 *</label>
              <select
                value={form.company_id}
                onChange={(e) => setForm((f) => ({ ...f, company_id: e.target.value }))}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              >
                <option value="">選択してください</option>
                {companies.map((co) => (
                  <option key={co.id} value={co.id}>{co.name}{co.code ? ` (${co.code})` : ''}</option>
                ))}
              </select>
              <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">自代理店が紹介した企業のみ表示されます</p>
            </div>
          )}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Input label="契約日" type="date" value={form.contract_date} onChange={(e) => setForm((f) => ({ ...f, contract_date: e.target.value }))} />
            <Input label="開始日" type="date" value={form.start_date} onChange={(e) => setForm((f) => ({ ...f, start_date: e.target.value }))} />
            <Input label="終了日" type="date" value={form.end_date} onChange={(e) => setForm((f) => ({ ...f, end_date: e.target.value }))} />
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Input label="月額料金 (円・税抜)" type="number" min={0} value={form.monthly_fee} onChange={(e) => setForm((f) => ({ ...f, monthly_fee: e.target.value }))} />
            <Input label="手数料率 (0〜1)" type="number" step="0.01" min={0} max={1} placeholder="例: 0.20" value={form.commission_rate} onChange={(e) => setForm((f) => ({ ...f, commission_rate: e.target.value }))} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">特約条項</label>
            <textarea
              className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm"
              rows={4}
              value={form.terms}
              onChange={(e) => setForm((f) => ({ ...f, terms: e.target.value }))}
            />
          </div>
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={form.agent_signed} onChange={(e) => setForm((f) => ({ ...f, agent_signed: e.target.checked }))} className="rounded border-[var(--neutral-stroke-1)]" />
            <span className="text-sm text-[var(--neutral-foreground-2)]">代理店として署名済としてマークする</span>
          </label>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setModalOpen(false)} disabled={saving}>キャンセル</Button>
            <Button onClick={handleSave} isLoading={saving} leftIcon={<MaterialIcon name="save" size={16} />}>
              {editingId ? '更新' : '作成'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

/**
 * 個別契約書カード。詳細・3者署名状態・PDFアップロード/差し替え/削除/閲覧を含む。
 */
function ContractCard({ contract, onEdit, onDelete, onChanged }: {
  contract: IndividualContract;
  onEdit: () => void;
  onDelete: () => void;
  onChanged: () => void;
}) {
  const toast = useToast();
  const fileRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);

  const hasPdf = !!contract.contract_document_path;
  const fullySigned = contract.soship_signed && contract.agent_signed && contract.customer_signed;

  const handleOpenPdf = async () => {
    try {
      const res = await api.get(`/api/agent/individual-contracts/${contract.id}/document`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      window.open(url, '_blank', 'noopener');
      setTimeout(() => window.URL.revokeObjectURL(url), 60000);
    } catch {
      toast.error('PDFを開けませんでした');
    }
  };

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.type !== 'application/pdf') { toast.error('PDFのみ対応'); e.target.value = ''; return; }
    if (file.size > 10 * 1024 * 1024) { toast.error('10MB以下にしてください'); e.target.value = ''; return; }
    setBusy(true);
    try {
      const fd = new FormData(); fd.append('file', file);
      await api.post(`/api/agent/individual-contracts/${contract.id}/document`, fd);
      toast.success('PDFをアップロードしました');
      onChanged();
    } catch {
      toast.error('アップロードに失敗しました');
    } finally {
      setBusy(false);
      e.target.value = '';
    }
  };

  const handleDeletePdf = async () => {
    if (!confirm('契約書PDFを削除しますか?')) return;
    setBusy(true);
    try {
      await api.delete(`/api/agent/individual-contracts/${contract.id}/document`);
      toast.success('PDFを削除しました');
      onChanged();
    } catch {
      toast.error('削除に失敗しました');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <CardBody>
        <div className="flex items-start justify-between mb-2 gap-2 flex-wrap">
          <div>
            <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">{contract.company?.name ?? `企業ID: ${contract.company_id}`}</h3>
            <p className="text-xs text-[var(--neutral-foreground-3)]">
              契約日: {contract.contract_date ?? '—'} / 期間: {contract.start_date ?? '—'} 〜 {contract.end_date ?? '—'}
            </p>
          </div>
          <div className="flex items-center gap-2">
            {fullySigned ? (
              <Badge variant="success" dot>3者署名完了</Badge>
            ) : (
              <Badge variant="warning" dot>署名待ち</Badge>
            )}
            <Button size="sm" variant="outline" onClick={onEdit} leftIcon={<MaterialIcon name="edit" size={14} />}>編集</Button>
            <Button size="sm" variant="ghost" onClick={onDelete} leftIcon={<MaterialIcon name="delete" size={14} />}>削除</Button>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 text-sm">
          <div><span className="text-[var(--neutral-foreground-3)]">月額料金:</span> {fmtYen(contract.monthly_fee)}</div>
          <div><span className="text-[var(--neutral-foreground-3)]">手数料率:</span> {fmtRate(contract.commission_rate)}</div>
          <div><span className="text-[var(--neutral-foreground-3)]">PDF:</span> {hasPdf ? '登録済' : '未登録'}</div>
        </div>

        {/* 3者署名状態 */}
        <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3 text-xs">
          <SignedBadge label="株式会社ソーシップ" signed={contract.soship_signed} at={contract.soship_signed_at} />
          <SignedBadge label="代理店" signed={contract.agent_signed} at={contract.agent_signed_at} />
          <SignedBadge label="顧客企業" signed={contract.customer_signed} at={contract.customer_signed_at} />
        </div>

        {contract.terms && (
          <div className="mt-3 rounded bg-[var(--neutral-background-2)] p-2 text-xs whitespace-pre-wrap text-[var(--neutral-foreground-2)]">
            <span className="text-[var(--neutral-foreground-3)] font-semibold">特約: </span>{contract.terms}
          </div>
        )}

        <div className="mt-3 flex flex-wrap gap-2 pt-2 border-t border-[var(--neutral-stroke-3)]">
          {hasPdf && (
            <Button size="sm" variant="outline" onClick={handleOpenPdf} leftIcon={<MaterialIcon name="description" size={14} />}>PDFを開く</Button>
          )}
          <Button size="sm" onClick={() => fileRef.current?.click()} isLoading={busy} leftIcon={<MaterialIcon name={hasPdf ? 'swap_horiz' : 'upload_file'} size={14} />}>
            {hasPdf ? 'PDF差し替え' : 'PDFアップロード'}
          </Button>
          {hasPdf && (
            <Button size="sm" variant="ghost" onClick={handleDeletePdf} isLoading={busy} leftIcon={<MaterialIcon name="delete" size={14} />}>PDF削除</Button>
          )}
          <input ref={fileRef} type="file" accept="application/pdf" className="hidden" onChange={handleUpload} />
        </div>
      </CardBody>
    </Card>
  );
}

function SignedBadge({ label, signed, at }: { label: string; signed: boolean; at: string | null }) {
  return (
    <div className={`rounded border px-2 py-1.5 ${signed ? 'border-[var(--status-success-fg)]/30 bg-[var(--status-success-bg)] text-[var(--status-success-fg)]' : 'border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)] text-[var(--neutral-foreground-3)]'}`}>
      <div className="flex items-center gap-1 font-semibold">
        <MaterialIcon name={signed ? 'check_circle' : 'pending'} size={12} />
        {label}
      </div>
      <div className="text-[10px] opacity-80">{signed ? (at ? new Date(at).toLocaleDateString('ja-JP') : '署名済') : '未署名'}</div>
    </div>
  );
}
