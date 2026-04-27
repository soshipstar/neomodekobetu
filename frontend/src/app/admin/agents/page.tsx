'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface AgentRow {
  id: number;
  name: string;
  code: string | null;
  contact_name: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  default_commission_rate: string | number;
  is_active: boolean;
  companies_count: number;
}

interface AgentForm {
  name: string;
  code: string;
  contact_name: string;
  contact_email: string;
  contact_phone: string;
  address: string;
  default_commission_rate: string;
  bank_info: {
    bank_name: string;
    branch: string;
    account_type: string;
    account_number: string;
    account_holder: string;
  };
  contract_terms: string;
  is_active: boolean;
  notes: string;
}

const EMPTY_FORM: AgentForm = {
  name: '',
  code: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  address: '',
  default_commission_rate: '0.20',
  bank_info: { bank_name: '', branch: '', account_type: '普通', account_number: '', account_holder: '' },
  contract_terms: '',
  is_active: true,
  notes: '',
};

export default function AgentsPage() {
  const toast = useToast();
  const [agents, setAgents] = useState<AgentRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<AgentForm>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);

  const fetchAgents = useCallback(async () => {
    try {
      const res = await api.get('/api/admin/master/agents');
      setAgents(res.data?.data || []);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchAgents();
  }, [fetchAgents]);

  const openCreate = () => {
    setEditingId(null);
    setForm(EMPTY_FORM);
    setModalOpen(true);
  };

  const openEdit = async (id: number) => {
    try {
      const res = await api.get(`/api/admin/master/agents/${id}`);
      const a = res.data?.data;
      setEditingId(id);
      setForm({
        name: a.name ?? '',
        code: a.code ?? '',
        contact_name: a.contact_name ?? '',
        contact_email: a.contact_email ?? '',
        contact_phone: a.contact_phone ?? '',
        address: a.address ?? '',
        default_commission_rate: a.default_commission_rate ? String(a.default_commission_rate) : '0.20',
        bank_info: {
          bank_name: a.bank_info?.bank_name ?? '',
          branch: a.bank_info?.branch ?? '',
          account_type: a.bank_info?.account_type ?? '普通',
          account_number: a.bank_info?.account_number ?? '',
          account_holder: a.bank_info?.account_holder ?? '',
        },
        contract_terms: a.contract_terms ?? '',
        is_active: !!a.is_active,
        notes: a.notes ?? '',
      });
      setModalOpen(true);
    } catch {
      toast.error('代理店情報の取得に失敗しました');
    }
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload = {
        ...form,
        default_commission_rate: parseFloat(form.default_commission_rate),
      };
      if (editingId) {
        await api.put(`/api/admin/master/agents/${editingId}`, payload);
        toast.success('代理店を更新しました');
      } else {
        await api.post('/api/admin/master/agents', payload);
        toast.success('代理店を作成しました');
      }
      setModalOpen(false);
      fetchAgents();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存に失敗しました';
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (a: AgentRow) => {
    if (!confirm(`代理店「${a.name}」を削除しますか？`)) return;
    try {
      await api.delete(`/api/admin/master/agents/${a.id}`);
      toast.success('代理店を削除しました');
      fetchAgents();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '削除に失敗しました';
      toast.error(msg);
    }
  };

  const formatRate = (r: number | string): string => `${(parseFloat(String(r)) * 100).toFixed(1)}%`;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">代理店管理</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            販売代理店のマスタ。各企業の販売チャネルでこの代理店を選択すると、紐付き手数料計算の対象になります。
          </p>
        </div>
        <Button onClick={openCreate}>
          <MaterialIcon name="add" size={18} />
          <span className="ml-1">代理店を追加</span>
        </Button>
      </div>

      {loading ? (
        <SkeletonList items={3} />
      ) : agents.length === 0 ? (
        <Card>
          <CardBody>
            <p className="text-sm text-[var(--neutral-foreground-3)]">登録された代理店はまだありません。</p>
          </CardBody>
        </Card>
      ) : (
        <Card>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">代理店名</th>
                    <th className="py-2 pr-4 font-normal">担当連絡先</th>
                    <th className="py-2 pr-4 font-normal text-right">既定手数料率</th>
                    <th className="py-2 pr-4 font-normal text-right">紹介企業</th>
                    <th className="py-2 pr-4 font-normal">状態</th>
                    <th className="py-2 font-normal" />
                  </tr>
                </thead>
                <tbody>
                  {agents.map((a) => (
                    <tr key={a.id} className="border-b border-[var(--neutral-stroke-3)] hover:bg-[var(--neutral-background-2)]">
                      <td className="py-3 pr-4">
                        <Link href={`/admin/agents/${a.id}`} className="font-medium text-[var(--neutral-foreground-1)] hover:underline">
                          {a.name}
                        </Link>
                        {a.code && <p className="text-xs text-[var(--neutral-foreground-4)]">{a.code}</p>}
                      </td>
                      <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">
                        {a.contact_name || '—'}
                        {a.contact_email && <p className="text-xs text-[var(--neutral-foreground-4)]">{a.contact_email}</p>}
                      </td>
                      <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-1)]">{formatRate(a.default_commission_rate)}</td>
                      <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-1)]">{a.companies_count}</td>
                      <td className="py-3 pr-4">
                        {a.is_active
                          ? <Badge variant="success">有効</Badge>
                          : <Badge variant="default">無効</Badge>}
                      </td>
                      <td className="py-3 text-right">
                        <Button variant="ghost" onClick={() => openEdit(a.id)}>
                          <MaterialIcon name="edit" size={18} />
                        </Button>
                        <Button variant="ghost" onClick={() => handleDelete(a)} className="text-[var(--status-danger-fg)]">
                          <MaterialIcon name="delete" size={18} />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      )}

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editingId ? '代理店を編集' : '代理店を追加'}>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <label className="block text-xs text-[var(--neutral-foreground-3)]">代理店名 *</label>
            <Input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">コード（任意）</label>
            <Input type="text" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="ag_xxx" />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">既定手数料率（0〜1）</label>
            <Input type="number" step="0.01" min="0" max="1" value={form.default_commission_rate} onChange={(e) => setForm({ ...form, default_commission_rate: e.target.value })} placeholder="0.20" />
            <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">{(parseFloat(form.default_commission_rate || '0') * 100).toFixed(1)}%</p>
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">担当者名</label>
            <Input type="text" value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">担当メール</label>
            <Input type="email" value={form.contact_email} onChange={(e) => setForm({ ...form, contact_email: e.target.value })} />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">担当電話</label>
            <Input type="tel" value={form.contact_phone} onChange={(e) => setForm({ ...form, contact_phone: e.target.value })} />
          </div>
          <div className="sm:col-span-2">
            <label className="block text-xs text-[var(--neutral-foreground-3)]">住所</label>
            <Input type="text" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
          </div>

          <div className="sm:col-span-2 mt-2">
            <p className="text-xs font-semibold text-[var(--neutral-foreground-2)]">振込先銀行情報（手数料の支払先）</p>
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">銀行名</label>
            <Input type="text" value={form.bank_info.bank_name} onChange={(e) => setForm({ ...form, bank_info: { ...form.bank_info, bank_name: e.target.value } })} />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">支店</label>
            <Input type="text" value={form.bank_info.branch} onChange={(e) => setForm({ ...form, bank_info: { ...form.bank_info, branch: e.target.value } })} />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">口座種別</label>
            <select
              value={form.bank_info.account_type}
              onChange={(e) => setForm({ ...form, bank_info: { ...form.bank_info, account_type: e.target.value } })}
              className="mt-1 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            >
              <option value="普通">普通</option>
              <option value="当座">当座</option>
            </select>
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">口座番号</label>
            <Input type="text" value={form.bank_info.account_number} onChange={(e) => setForm({ ...form, bank_info: { ...form.bank_info, account_number: e.target.value } })} />
          </div>
          <div className="sm:col-span-2">
            <label className="block text-xs text-[var(--neutral-foreground-3)]">口座名義</label>
            <Input type="text" value={form.bank_info.account_holder} onChange={(e) => setForm({ ...form, bank_info: { ...form.bank_info, account_holder: e.target.value } })} placeholder="カ）○○○○" />
          </div>

          <div className="sm:col-span-2">
            <label className="block text-xs text-[var(--neutral-foreground-3)]">契約条件メモ（社内用）</label>
            <textarea
              value={form.contract_terms}
              onChange={(e) => setForm({ ...form, contract_terms: e.target.value })}
              rows={3}
              className="mt-1 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            />
          </div>
          <div className="sm:col-span-2">
            <label className="block text-xs text-[var(--neutral-foreground-3)]">社内メモ</label>
            <textarea
              value={form.notes}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
              rows={2}
              className="mt-1 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            />
          </div>
          <div className="sm:col-span-2">
            <label className="inline-flex items-center gap-2">
              <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />
              <span className="text-sm text-[var(--neutral-foreground-1)]">有効</span>
            </label>
          </div>
        </div>
        <div className="mt-4 flex justify-end gap-2">
          <Button variant="ghost" onClick={() => setModalOpen(false)}>キャンセル</Button>
          <Button onClick={handleSave} disabled={saving || !form.name}>
            {editingId ? '更新' : '作成'}
          </Button>
        </div>
      </Modal>
    </div>
  );
}
