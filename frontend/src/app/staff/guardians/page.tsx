'use client';

import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { useDebounce } from '@/hooks/useDebounce';
import {
  Search,
  Plus,
  Pencil,
  Printer,
  Copy,
  RefreshCw,
  Trash2,
} from 'lucide-react';
import { format } from 'date-fns';
import Link from 'next/link';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Guardian {
  id: number;
  full_name: string;
  username: string | null;
  email: string | null;
  password_plain: string | null;
  is_active: boolean;
  students: { id: number; student_name: string }[];
  last_login_at: string | null;
  created_at: string;
}

interface GuardianForm {
  full_name: string;
  email: string;
  username: string;
  password: string;
}

function generatePassword(length = 8): string {
  const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let pw = '';
  for (let i = 0; i < length; i++) {
    pw += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return pw;
}

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function GuardiansPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editingGuardian, setEditingGuardian] = useState<Guardian | null>(null);
  const [form, setForm] = useState<GuardianForm>({ full_name: '', email: '', username: '', password: '' });
  const debouncedSearch = useDebounce(search, 300);

  // Fetch guardians
  const { data: guardians = [], isLoading } = useQuery({
    queryKey: ['staff', 'guardians', debouncedSearch],
    queryFn: async () => {
      const res = await api.get('/api/staff/guardians', {
        params: { search: debouncedSearch || undefined },
      });
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload as Guardian[] : [];
    },
  });

  // Save mutation (create: only full_name + email; edit: full_name + username + email + password)
  const saveMutation = useMutation({
    mutationFn: async (data: GuardianForm) => {
      if (editingGuardian) {
        const payload: Record<string, string> = {
          full_name: data.full_name,
          username: data.username,
          email: data.email,
        };
        if (data.password) payload.password = data.password;
        return api.put(`/api/staff/guardians/${editingGuardian.id}`, payload);
      }
      // 新規作成: ユーザー名・パスワードはサーバー側で自動生成
      return api.post('/api/staff/guardians', {
        full_name: data.full_name,
        email: data.email || undefined,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'guardians'] });
      toast.success(editingGuardian ? '保護者情報を更新しました' : '保護者を登録しました');
      closeModal();
    },
    onError: (err: any) => toast.error(err.response?.data?.message || '保存に失敗しました'),
  });

  // Delete mutation (実際に削除)
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/guardians/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'guardians'] });
      toast.success('保護者を削除しました');
      closeModal();
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const closeModal = () => {
    setModalOpen(false);
    setEditingGuardian(null);
    setForm({ full_name: '', email: '', username: '', password: '' });
  };

  const openEdit = (g: Guardian) => {
    setEditingGuardian(g);
    setForm({
      full_name: g.full_name,
      email: g.email || '',
      username: g.username || '',
      password: '',
    });
    setModalOpen(true);
  };

  const openCreate = () => {
    setEditingGuardian(null);
    setForm({ full_name: '', email: '', username: '', password: '' });
    setModalOpen(true);
  };

  const copyToClipboard = useCallback((text: string) => {
    navigator.clipboard.writeText(text);
    toast.success('コピーしました');
  }, [toast]);

  const inputCls = 'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]';

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">保護者管理</h1>
          <p className="text-sm text-[var(--neutral-foreground-4)]">保護者の登録・編集</p>
        </div>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreate}>
          保護者を追加
        </Button>
      </div>

      {/* Search */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input placeholder="氏名・ユーザー名・メールで検索..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
      </div>

      {/* Table */}
      {isLoading ? (
        <div className="space-y-2">{[...Array(6)].map((_, i) => <Skeleton key={i} className="h-12 rounded-lg" />)}</div>
      ) : guardians.length === 0 ? (
        <Card><CardBody><p className="py-8 text-center text-sm text-[var(--neutral-foreground-4)]">保護者が見つかりません</p></CardBody></Card>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-[var(--neutral-stroke-2)]">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">ID</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">氏名</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">ユーザー名</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">メールアドレス</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">紐づく生徒</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">状態</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">登録日</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">操作</th>
              </tr>
            </thead>
            <tbody>
              {guardians.map((g) => (
                <tr key={g.id} className="border-b border-[var(--neutral-stroke-3)] hover:bg-[var(--neutral-background-3)] transition-colors">
                  <td className="px-3 py-2 text-[var(--neutral-foreground-4)]">{g.id}</td>
                  <td className="px-3 py-2 font-medium text-[var(--neutral-foreground-1)]">{g.full_name}</td>
                  <td className="px-3 py-2 text-[var(--neutral-foreground-2)]">{g.username || '-'}</td>
                  <td className="px-3 py-2 text-[var(--neutral-foreground-2)]">{g.email || '-'}</td>
                  <td className="px-3 py-2">
                    {g.students.length > 0 ? (
                      <span className="text-[var(--neutral-foreground-2)]">{g.students.length}名</span>
                    ) : '-'}
                  </td>
                  <td className="px-3 py-2">
                    <Badge variant={g.is_active ? 'success' : 'danger'}>
                      {g.is_active ? '有効' : '無効'}
                    </Badge>
                  </td>
                  <td className="px-3 py-2 text-[var(--neutral-foreground-3)] text-xs">
                    {format(new Date(g.created_at), 'yyyy/MM/dd')}
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex gap-1">
                      <Button variant="outline" size="sm" onClick={() => openEdit(g)}>
                        編集
                      </Button>
                      <Link href={`/staff/guardians/${g.id}/manual`} target="_blank">
                        <Button variant="ghost" size="sm" title="マニュアル印刷">
                          <Printer className="h-3.5 w-3.5" />
                        </Button>
                      </Link>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Create/Edit Modal */}
      <Modal isOpen={modalOpen} onClose={closeModal} title={editingGuardian ? '保護者情報の編集' : '保護者の新規登録'} size="md">
        <div className="space-y-4">
          {/* 氏名 */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">保護者氏名 <span className="text-[var(--status-danger-fg)]">*</span></label>
            <input value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })}
              className={inputCls} required placeholder="例: 山田 花子" />
          </div>

          {/* メール */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">メールアドレス（任意）</label>
            <input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })}
              className={inputCls} placeholder="例: yamada@example.com" />
          </div>

          {editingGuardian ? (
            <>
              {/* 編集時: ログイン情報セクション */}
              <div className="border-t border-[var(--neutral-stroke-2)] pt-4">
                <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)] mb-3">ログイン情報</h4>

                {/* ユーザー名（編集可能） */}
                <div className="mb-3">
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">ログインID</label>
                  <input value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })}
                    className={inputCls} required />
                </div>

                {/* 現在のパスワード（読み取り専用） */}
                <div className="mb-3">
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">現在のパスワード</label>
                  <div className="flex gap-2 items-center">
                    <input
                      value={editingGuardian.password_plain || '（未設定）'}
                      readOnly
                      className={`${inputCls} flex-1 bg-[var(--neutral-background-3)]`}
                    />
                    {editingGuardian.password_plain && (
                      <Button variant="outline" size="sm" type="button" onClick={() => copyToClipboard(editingGuardian.password_plain!)}>
                        コピー
                      </Button>
                    )}
                  </div>
                </div>

                {/* 新しいパスワード */}
                <div>
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">
                    新しいパスワード（変更する場合のみ）
                  </label>
                  <div className="flex gap-2">
                    <input
                      value={form.password}
                      onChange={(e) => setForm({ ...form, password: e.target.value })}
                      className={`${inputCls} flex-1`}
                      placeholder="変更しない場合は空欄"
                    />
                    <Button
                      variant="outline"
                      size="sm"
                      type="button"
                      leftIcon={<RefreshCw className="h-3.5 w-3.5" />}
                      onClick={() => setForm({ ...form, password: generatePassword() })}
                    >
                      自動生成
                    </Button>
                    {form.password && (
                      <Button variant="ghost" size="sm" type="button" onClick={() => copyToClipboard(form.password)}>
                        <Copy className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                  <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">8文字以上で設定してください</p>
                </div>
              </div>
            </>
          ) : (
            /* 新規作成時: 自動生成の説明 */
            <div className="rounded-lg bg-[var(--neutral-background-3)] p-3">
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                ログインID・パスワードは自動生成されます。登録後に編集画面で変更できます。
              </p>
            </div>
          )}

          {/* Actions */}
          <div className="flex items-center justify-between pt-2">
            <div>
              {editingGuardian && (
                <Button variant="ghost" size="sm" onClick={() => {
                  if (confirm(`本当に「${editingGuardian.full_name}」を削除しますか？\n\nこの操作は取り消せません。関連する生徒との紐付けも解除されます。`))
                    deleteMutation.mutate(editingGuardian.id);
                }}>
                  <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
                  <span className="ml-1 text-[var(--status-danger-fg)]">削除</span>
                </Button>
              )}
            </div>
            <div className="flex gap-2">
              <Button variant="secondary" onClick={closeModal}>キャンセル</Button>
              <Button onClick={() => saveMutation.mutate(form)} isLoading={saveMutation.isPending}>
                {editingGuardian ? '更新する' : '登録する'}
              </Button>
            </div>
          </div>
        </div>
      </Modal>
    </div>
  );
}
