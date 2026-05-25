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
import { format } from 'date-fns';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

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

// 「紐づき生徒」フィルタの種別。デフォルトは利用中の生徒がいる保護者のみ表示。
type StudentLinkFilter = 'with' | 'without' | 'all';

export default function GuardiansPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  // バグ報告: 利用している生徒がいない保護者と、いる保護者で表示を切り替えたい
  // デフォルトは生徒がいる保護者のみ
  const [linkFilter, setLinkFilter] = useState<StudentLinkFilter>('with');
  const [modalOpen, setModalOpen] = useState(false);
  const [editingGuardian, setEditingGuardian] = useState<Guardian | null>(null);
  const [form, setForm] = useState<GuardianForm>({ full_name: '', email: '', username: '', password: '' });
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const debouncedSearch = useDebounce(search, 300);

  // Fetch guardians
  const { data: allGuardians = [], isLoading } = useQuery({
    queryKey: ['staff', 'guardians', debouncedSearch],
    queryFn: async () => {
      const res = await api.get('/api/staff/guardians', {
        params: { search: debouncedSearch || undefined },
      });
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload as Guardian[] : [];
    },
  });

  // 紐づき生徒の有無で client-side フィルタリング
  const guardians = allGuardians.filter((g) => {
    if (linkFilter === 'all') return true;
    const hasStudents = g.students.length > 0;
    return linkFilter === 'with' ? hasStudents : !hasStudents;
  });

  // 集計値 (タブの件数表示用)
  const withCount = allGuardians.filter((g) => g.students.length > 0).length;
  const withoutCount = allGuardians.length - withCount;

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
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">保護者管理</h1>
          <p className="text-sm text-[var(--neutral-foreground-4)]">保護者の登録・編集</p>
        </div>
        <div className="flex gap-2 flex-wrap">
          {selectedIds.size > 0 && (
            <Link
              href={`/staff/guardians/manual-bulk?ids=${Array.from(selectedIds).join(',')}`}
              target="_blank"
            >
              <Button variant="outline" leftIcon={<MaterialIcon name="print" size={16} />}>
                選択中{selectedIds.size}件を一括印刷
              </Button>
            </Link>
          )}
          <Button leftIcon={<MaterialIcon name="add" size={16} />} onClick={openCreate}>
            保護者を追加
          </Button>
        </div>
      </div>

      {/* Search */}
      <div className="relative">
        <MaterialIcon name="search" size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input placeholder="氏名・ユーザー名・メールで検索..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
      </div>

      {/* 紐づき生徒フィルタ (デフォルトは「利用中」= 生徒がいる保護者のみ) */}
      <div className="flex flex-wrap items-center gap-1.5">
        {([
          { key: 'with',    label: `利用中の生徒あり`,  count: withCount,    desc: '紐づく生徒がいる保護者のみ' },
          { key: 'without', label: `紐づく生徒なし`,    count: withoutCount, desc: '退所/登録準備中などで生徒がいない保護者' },
          { key: 'all',     label: `すべて`,            count: allGuardians.length, desc: '上記すべて表示' },
        ] as const).map((opt) => (
          <button
            key={opt.key}
            type="button"
            onClick={() => setLinkFilter(opt.key)}
            title={opt.desc}
            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
              linkFilter === opt.key
                ? 'bg-[var(--brand-80)] text-white'
                : 'border border-[var(--neutral-stroke-2)] bg-white text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]'
            }`}
          >
            {opt.label}
            <span className="ml-1.5 text-[10px] opacity-80">({opt.count})</span>
          </button>
        ))}
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
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">
                  <input
                    type="checkbox"
                    aria-label="すべて選択"
                    checked={guardians.length > 0 && guardians.every((g) => selectedIds.has(g.id))}
                    onChange={(e) => {
                      if (e.target.checked) setSelectedIds(new Set(guardians.map((g) => g.id)));
                      else setSelectedIds(new Set());
                    }}
                  />
                </th>
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
                  <td className="px-3 py-2">
                    <input
                      type="checkbox"
                      aria-label={`${g.full_name} を選択`}
                      checked={selectedIds.has(g.id)}
                      onChange={(e) => {
                        const next = new Set(selectedIds);
                        if (e.target.checked) next.add(g.id); else next.delete(g.id);
                        setSelectedIds(next);
                      }}
                    />
                  </td>
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
                          <MaterialIcon name="print" size={14} />
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

                {/* 現在のパスワード（読み取り専用）
                    保護者が自分で変更した後は password_plain が NULL になるため、
                    その旨を明示してスタッフが古い値を案内しないようにする。 */}
                <div className="mb-3">
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">現在のパスワード</label>
                  <div className="flex gap-2 items-center">
                    <input
                      value={editingGuardian.password_plain || '（保護者により変更済み・スタッフからは確認できません）'}
                      readOnly
                      className={`${inputCls} flex-1 bg-[var(--neutral-background-3)]`}
                    />
                    {editingGuardian.password_plain && (
                      <Button variant="outline" size="sm" type="button" onClick={() => copyToClipboard(editingGuardian.password_plain!)}>
                        コピー
                      </Button>
                    )}
                  </div>
                  {!editingGuardian.password_plain && (
                    <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
                      保護者が自分でパスワードを変更すると、安全のため初期パスワードは消去されます。紛失時は下の「新しいパスワード」で再発行してください。
                    </p>
                  )}
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
                      leftIcon={<MaterialIcon name="refresh" size={16} className="h-3.5 w-3.5" />}
                      onClick={() => setForm({ ...form, password: generatePassword() })}
                    >
                      自動生成
                    </Button>
                    {form.password && (
                      <Button variant="ghost" size="sm" type="button" onClick={() => copyToClipboard(form.password)}>
                        <MaterialIcon name="content_copy" size={16} className="h-3.5 w-3.5" />
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
                  <MaterialIcon name="delete" size={16} className="text-[var(--status-danger-fg)]" />
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
