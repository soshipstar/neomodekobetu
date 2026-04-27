'use client';

import { useCallback, useEffect, useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface AgentLite { id: number; name: string; code: string | null }

interface AgentUser {
  id: number;
  agent_id: number;
  agent: AgentLite | null;
  username: string;
  full_name: string;
  email: string | null;
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
}

interface UserForm {
  agent_id: string;
  username: string;
  password: string;
  full_name: string;
  email: string;
  is_active: boolean;
}

const EMPTY_FORM: UserForm = {
  agent_id: '',
  username: '',
  password: '',
  full_name: '',
  email: '',
  is_active: true,
};

function formatDate(d: string | null | undefined): string {
  if (!d) return '—';
  try { return new Date(d).toLocaleDateString('ja-JP'); } catch { return '—'; }
}

export default function AgentAccountsPage() {
  const toast = useToast();
  const [users, setUsers] = useState<AgentUser[]>([]);
  const [agents, setAgents] = useState<AgentLite[]>([]);
  const [loading, setLoading] = useState(true);
  const [agentFilter, setAgentFilter] = useState<string>('');

  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<UserForm>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);

  const fetchAll = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (agentFilter) params.set('agent_id', agentFilter);
      const [uRes, aRes] = await Promise.all([
        api.get(`/api/admin/master/agent-accounts?${params.toString()}`),
        api.get('/api/admin/master/agents'),
      ]);
      setUsers(uRes.data?.data ?? []);
      setAgents((aRes.data?.data ?? []).map((a: AgentLite) => ({ id: a.id, name: a.name, code: a.code })));
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [agentFilter, toast]);

  useEffect(() => { fetchAll(); }, [fetchAll]);

  const openCreate = () => {
    setEditingId(null);
    setForm({ ...EMPTY_FORM, agent_id: agentFilter });
    setModalOpen(true);
  };

  const openEdit = (u: AgentUser) => {
    setEditingId(u.id);
    setForm({
      agent_id: String(u.agent_id),
      username: u.username,
      password: '',
      full_name: u.full_name,
      email: u.email ?? '',
      is_active: u.is_active,
    });
    setModalOpen(true);
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const body: Record<string, unknown> = {
        agent_id: parseInt(form.agent_id, 10),
        username: form.username,
        full_name: form.full_name,
        email: form.email || null,
        is_active: form.is_active,
      };
      if (form.password) body.password = form.password;

      if (editingId) {
        await api.put(`/api/admin/master/agent-accounts/${editingId}`, body);
        toast.success('代理店ユーザーを更新しました');
      } else {
        if (!form.password) {
          toast.error('新規作成時はパスワードが必要です');
          setSaving(false);
          return;
        }
        await api.post('/api/admin/master/agent-accounts', body);
        toast.success('代理店ユーザーを作成しました');
      }
      setModalOpen(false);
      fetchAll();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存に失敗しました';
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (u: AgentUser) => {
    if (!confirm(`代理店ユーザー「${u.username}」を削除しますか？`)) return;
    try {
      await api.delete(`/api/admin/master/agent-accounts/${u.id}`);
      toast.success('削除しました');
      fetchAll();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '削除に失敗しました';
      toast.error(msg);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">代理店アカウント</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            代理店スタッフがログインに使うアカウントです。<code>/agent</code> 配下の機能のみ利用できます。
          </p>
        </div>
        <Button onClick={openCreate} disabled={agents.length === 0}>
          <MaterialIcon name="person_add" size={18} />
          <span className="ml-1">アカウントを発行</span>
        </Button>
      </div>

      <div>
        <label className="block text-xs text-[var(--neutral-foreground-3)]">代理店フィルタ</label>
        <select
          value={agentFilter}
          onChange={(e) => setAgentFilter(e.target.value)}
          className="mt-1 rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm w-64"
        >
          <option value="">すべての代理店</option>
          {agents.map((a) => (<option key={a.id} value={a.id}>{a.name}</option>))}
        </select>
      </div>

      {loading ? (
        <SkeletonList items={3} />
      ) : agents.length === 0 ? (
        <Card><CardBody><p className="text-sm text-[var(--neutral-foreground-3)]">代理店マスタがまだありません。先に <a href="/admin/agents" className="text-[var(--brand-80)] hover:underline">代理店管理</a> で代理店を作成してください。</p></CardBody></Card>
      ) : users.length === 0 ? (
        <Card><CardBody><p className="text-sm text-[var(--neutral-foreground-3)]">該当する代理店ユーザーはまだいません。</p></CardBody></Card>
      ) : (
        <Card>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">所属代理店</th>
                    <th className="py-2 pr-4 font-normal">ユーザー名</th>
                    <th className="py-2 pr-4 font-normal">氏名</th>
                    <th className="py-2 pr-4 font-normal">メール</th>
                    <th className="py-2 pr-4 font-normal">状態</th>
                    <th className="py-2 pr-4 font-normal">最終ログイン</th>
                    <th className="py-2 font-normal" />
                  </tr>
                </thead>
                <tbody>
                  {users.map((u) => (
                    <tr key={u.id} className="border-b border-[var(--neutral-stroke-3)]">
                      <td className="py-3 pr-4 text-[var(--neutral-foreground-1)]">{u.agent?.name || `agent#${u.agent_id}`}</td>
                      <td className="py-3 pr-4 font-mono text-xs text-[var(--neutral-foreground-2)]">{u.username}</td>
                      <td className="py-3 pr-4 text-[var(--neutral-foreground-1)]">{u.full_name}</td>
                      <td className="py-3 pr-4 text-[var(--neutral-foreground-2)] break-all">{u.email || '—'}</td>
                      <td className="py-3 pr-4">
                        {u.is_active ? <Badge variant="success">有効</Badge> : <Badge variant="default">無効</Badge>}
                      </td>
                      <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(u.last_login_at)}</td>
                      <td className="py-3 text-right whitespace-nowrap">
                        <Button variant="ghost" onClick={() => openEdit(u)}>
                          <MaterialIcon name="edit" size={18} />
                        </Button>
                        <Button variant="ghost" onClick={() => handleDelete(u)} className="text-[var(--status-danger-fg)]">
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

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editingId ? '代理店ユーザーを編集' : '代理店ユーザーを発行'}>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <label className="block text-xs text-[var(--neutral-foreground-3)]">所属代理店 *</label>
            <select
              value={form.agent_id}
              onChange={(e) => setForm({ ...form, agent_id: e.target.value })}
              className="mt-1 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            >
              <option value="">選択してください</option>
              {agents.map((a) => (<option key={a.id} value={a.id}>{a.name}</option>))}
            </select>
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">ユーザー名 *</label>
            <Input type="text" value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} placeholder="agent_yamada" />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">氏名 *</label>
            <Input type="text" value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} placeholder="山田 太郎" />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">メール（任意）</label>
            <Input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="agent@example.com" />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)]">パスワード {editingId ? '（変更時のみ）' : '*'}</label>
            <Input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} placeholder="8文字以上" />
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
          <Button
            onClick={handleSave}
            disabled={saving || !form.agent_id || !form.username || !form.full_name}
          >
            {editingId ? '更新' : '発行'}
          </Button>
        </div>
      </Modal>
    </div>
  );
}
