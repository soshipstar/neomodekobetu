'use client';

import { useState, useEffect, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface TabletAccount {
  id: number;
  username: string;
  display_name: string;
  full_name: string;
  classroom_id: number;
  classroom?: { id: number; classroom_name: string };
  classrooms?: { id: number; classroom_name: string }[];
  classroom_ids?: number[];
  classroom_names?: string[];
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
}

interface ClassroomOption {
  id: number;
  classroom_name: string;
}

const initialForm = {
  full_name: '',
  username: '',
  password: '',
  classroom_id: '' as string | number,
  classroom_ids: [] as number[],
};

export default function AdminTabletAccountsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [modalOpen, setModalOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<TabletAccount | null>(null);
  const [form, setForm] = useState({ ...initialForm });

  const { data: accounts = [], isLoading } = useQuery({
    queryKey: ['admin', 'tablet-accounts'],
    queryFn: async () => {
      const res = await api.get<{ data: TabletAccount[] }>('/api/admin/tablet-accounts');
      return res.data.data;
    },
  });

  // 自身がアクセスできる教室一覧 (作成・編集の選択肢として使用)
  const { data: myClassrooms = [] } = useQuery({
    queryKey: ['admin', 'tablet-accounts', 'my-classrooms'],
    queryFn: async () => {
      const res = await api.get<{ data: { classrooms: ClassroomOption[] } }>('/api/my-classrooms');
      return res.data?.data?.classrooms ?? [];
    },
  });

  const createMutation = useMutation({
    mutationFn: (data: typeof form) =>
      api.post('/api/admin/tablet-accounts', {
        full_name: data.full_name,
        username: data.username,
        password: data.password,
        classroom_id: Number(data.classroom_id),
        classroom_ids: data.classroom_ids.length ? data.classroom_ids : [Number(data.classroom_id)],
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tablet-accounts'] });
      toast.success('タブレットアカウントを作成しました');
      setModalOpen(false);
      setForm({ ...initialForm });
    },
    onError: (err: { response?: { data?: { message?: string } } }) =>
      toast.error(err?.response?.data?.message ?? '作成に失敗しました'),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<typeof form> }) =>
      api.put(`/api/admin/tablet-accounts/${id}`, {
        ...(data.full_name !== undefined ? { full_name: data.full_name } : {}),
        ...(data.password ? { password: data.password } : {}),
        ...(data.classroom_id !== undefined && data.classroom_id !== '' ? { classroom_id: Number(data.classroom_id) } : {}),
        ...(data.classroom_ids !== undefined ? { classroom_ids: data.classroom_ids } : {}),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tablet-accounts'] });
      toast.success('更新しました');
      setEditTarget(null);
      setModalOpen(false);
      setForm({ ...initialForm });
    },
    onError: (err: { response?: { data?: { message?: string } } }) =>
      toast.error(err?.response?.data?.message ?? '更新に失敗しました'),
  });

  const toggleMutation = useMutation({
    mutationFn: ({ id }: { id: number }) => api.post(`/api/admin/tablet-accounts/${id}/toggle`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tablet-accounts'] });
      toast.success('ステータスを更新しました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // 編集モード起動
  useEffect(() => {
    if (editTarget) {
      setForm({
        full_name: editTarget.full_name ?? editTarget.display_name ?? '',
        username: editTarget.username,
        password: '',
        classroom_id: editTarget.classroom_id,
        classroom_ids: editTarget.classroom_ids ?? editTarget.classrooms?.map((c) => c.id) ?? [editTarget.classroom_id],
      });
      setModalOpen(true);
    }
  }, [editTarget]);

  const handleClose = () => {
    setEditTarget(null);
    setModalOpen(false);
    setForm({ ...initialForm });
  };

  const toggleClassroomId = (id: number) => {
    setForm((prev) => {
      const has = prev.classroom_ids.includes(id);
      return {
        ...prev,
        classroom_ids: has ? prev.classroom_ids.filter((x) => x !== id) : [...prev.classroom_ids, id],
      };
    });
  };

  const submitDisabled = useMemo(() => {
    if (!form.full_name || !form.username || !form.classroom_id) return true;
    if (!editTarget && !form.password) return true;
    return false;
  }, [form, editTarget]);

  const columns: Column<TabletAccount>[] = [
    {
      key: 'display_name',
      label: 'アカウント名',
      render: (a) => (
        <div className="flex items-center gap-2">
          <MaterialIcon name="tablet" size={16} className="text-[var(--neutral-foreground-4)]" />
          <span className="font-medium text-[var(--neutral-foreground-1)]">{a.full_name ?? a.display_name}</span>
        </div>
      ),
    },
    { key: 'username', label: 'ユーザー名' },
    {
      key: 'classroom_names',
      label: '所属教室',
      render: (a) => {
        const names = a.classroom_names && a.classroom_names.length > 0
          ? a.classroom_names
          : (a.classroom?.classroom_name ? [a.classroom.classroom_name] : []);
        if (names.length === 0) return '-';
        return (
          <div className="flex flex-wrap gap-1">
            {names.map((n, i) => (
              <span
                key={`${a.id}-${i}`}
                className={`rounded-full px-2 py-0.5 text-xs ${
                  n === a.classroom?.classroom_name
                    ? 'bg-[var(--brand-160)] text-[var(--brand-60)] font-semibold'
                    : 'bg-[var(--neutral-background-4)] text-[var(--neutral-foreground-3)]'
                }`}
                title={n === a.classroom?.classroom_name ? '主教室 (起動時)' : '横断可能教室'}
              >
                {n}
              </span>
            ))}
          </div>
        );
      },
    },
    {
      key: 'is_active',
      label: 'ステータス',
      render: (a) => (
        <Badge variant={a.is_active ? 'success' : 'danger'} dot>
          {a.is_active ? '有効' : '無効'}
        </Badge>
      ),
    },
    {
      key: 'last_login_at',
      label: '最終利用',
      render: (a) => (a.last_login_at ? new Date(a.last_login_at).toLocaleString('ja-JP') : '未使用'),
    },
    {
      key: 'actions',
      label: '操作',
      render: (a) => (
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={() => setEditTarget(a)} leftIcon={<MaterialIcon name="edit" size={14} />}>
            編集
          </Button>
          <Button
            variant={a.is_active ? 'outline' : 'primary'}
            size="sm"
            onClick={() => toggleMutation.mutate({ id: a.id })}
            leftIcon={
              a.is_active
                ? <MaterialIcon name="power_off" size={14} />
                : <MaterialIcon name="power_settings_new" size={14} />
            }
          >
            {a.is_active ? '無効' : '有効'}
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">タブレットアカウント管理</h1>
        <Button onClick={() => { setEditTarget(null); setForm({ ...initialForm }); setModalOpen(true); }} leftIcon={<MaterialIcon name="add" size={16} />}>
          アカウント作成
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Card>
          <div className="text-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">全アカウント</p>
            <p className="text-3xl font-bold text-[var(--neutral-foreground-1)]">{accounts.length}</p>
          </div>
        </Card>
        <Card>
          <div className="text-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">有効</p>
            <p className="text-3xl font-bold text-green-600">{accounts.filter((a) => a.is_active).length}</p>
          </div>
        </Card>
        <Card>
          <div className="text-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">無効</p>
            <p className="text-3xl font-bold text-red-600">{accounts.filter((a) => !a.is_active).length}</p>
          </div>
        </Card>
      </div>

      {isLoading ? (
        <SkeletonTable rows={5} cols={6} />
      ) : (
        <Table columns={columns} data={accounts} keyExtractor={(a) => a.id} emptyMessage="タブレットアカウントがありません" />
      )}

      <Modal
        isOpen={modalOpen}
        onClose={handleClose}
        title={editTarget ? 'タブレットアカウント編集' : 'タブレットアカウント作成'}
        size="lg"
      >
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (editTarget) {
              updateMutation.mutate({ id: editTarget.id, data: form });
            } else {
              createMutation.mutate(form);
            }
          }}
          className="space-y-4"
        >
          <Input
            label="表示名"
            value={form.full_name}
            onChange={(e) => setForm({ ...form, full_name: e.target.value })}
            required
            placeholder="例: 1F タブレット"
          />
          <Input
            label="ユーザー名"
            value={form.username}
            onChange={(e) => setForm({ ...form, username: e.target.value })}
            required
            disabled={!!editTarget}
            placeholder="例: tablet_1f"
          />
          <Input
            label={editTarget ? 'パスワード (変更する場合のみ入力)' : 'パスワード'}
            type="password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            required={!editTarget}
            placeholder={editTarget ? '空欄なら変更しません' : ''}
          />

          {/* 主教室 (起動時のアクティブ教室) */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">
              起動時の教室 (主教室) <span className="text-[var(--status-danger-fg)]">*</span>
            </label>
            <select
              value={form.classroom_id}
              onChange={(e) => {
                const id = Number(e.target.value);
                setForm((prev) => {
                  // 主教室は必ず classroom_ids に含める
                  const ids = prev.classroom_ids.includes(id) ? prev.classroom_ids : [...prev.classroom_ids, id];
                  return { ...prev, classroom_id: id, classroom_ids: ids };
                });
              }}
              required
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            >
              <option value="">-- 選択してください --</option>
              {myClassrooms.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.classroom_name}
                </option>
              ))}
            </select>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              タブレットを起動したときに最初に表示される教室です。
            </p>
          </div>

          {/* 横断可能教室 (チェックボックス) */}
          <div>
            <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">
              横断可能な教室 (タブレットで切替可能)
            </label>
            <div className="grid grid-cols-1 gap-2 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3 sm:grid-cols-2">
              {myClassrooms.length === 0 ? (
                <p className="text-sm text-[var(--neutral-foreground-4)]">利用可能な教室がありません</p>
              ) : (
                myClassrooms.map((c) => {
                  const checked = form.classroom_ids.includes(c.id);
                  const isPrimary = Number(form.classroom_id) === c.id;
                  return (
                    <label
                      key={c.id}
                      className={`flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors ${
                        checked
                          ? 'border-[var(--brand-80)] bg-white'
                          : 'border-[var(--neutral-stroke-2)] bg-white hover:bg-[var(--neutral-background-4)]'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={checked}
                        disabled={isPrimary} // 主教室はチェックを外せない
                        onChange={() => toggleClassroomId(c.id)}
                      />
                      <span className="flex-1">{c.classroom_name}</span>
                      {isPrimary && (
                        <span className="rounded-full bg-[var(--brand-160)] px-2 py-0.5 text-[10px] font-semibold text-[var(--brand-60)]">
                          主教室
                        </span>
                      )}
                    </label>
                  );
                })
              )}
            </div>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              チェックを入れた教室は、タブレット画面の上部ドロップダウンから切り替えられます。
            </p>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={handleClose}>キャンセル</Button>
            <Button
              type="submit"
              isLoading={createMutation.isPending || updateMutation.isPending}
              disabled={submitDisabled}
            >
              {editTarget ? '保存' : '作成'}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
