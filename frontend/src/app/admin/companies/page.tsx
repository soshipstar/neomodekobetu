'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useAuthStore } from '@/stores/authStore';

interface Company {
  id: number;
  name: string;
  code: string | null;
  description: string | null;
  is_active: boolean;
  classrooms_count: number;
  users_count: number;
}

interface Classroom {
  id: number;
  classroom_name: string;
  company_id: number | null;
}

export default function CompaniesPage() {
  const toast = useToast();
  const { user } = useAuthStore();
  const [companies, setCompanies] = useState<Company[]>([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingCompany, setEditingCompany] = useState<Company | null>(null);
  const [form, setForm] = useState({ name: '', code: '', description: '', is_active: true });
  const [saving, setSaving] = useState(false);
  const [assigningCompany, setAssigningCompany] = useState<Company | null>(null);

  const isMaster = user?.user_type === 'admin' && user?.is_master;

  const fetchCompanies = useCallback(async () => {
    try {
      const res = await api.get('/api/admin/companies');
      setCompanies(res.data.data || []);
    } catch {
      toast.error('企業一覧の取得に失敗しました');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => { fetchCompanies(); }, [fetchCompanies]);

  const openCreate = () => {
    setEditingCompany(null);
    setForm({ name: '', code: '', description: '', is_active: true });
    setModalOpen(true);
  };

  const openEdit = (c: Company) => {
    setEditingCompany(c);
    setForm({ name: c.name, code: c.code || '', description: c.description || '', is_active: c.is_active });
    setModalOpen(true);
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      if (editingCompany) {
        await api.put(`/api/admin/companies/${editingCompany.id}`, form);
        toast.success('企業を更新しました');
      } else {
        await api.post('/api/admin/companies', form);
        toast.success('企業を作成しました');
      }
      setModalOpen(false);
      fetchCompanies();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存に失敗しました';
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (c: Company) => {
    if (!confirm(`企業「${c.name}」を削除しますか？`)) return;
    try {
      await api.delete(`/api/admin/companies/${c.id}`);
      toast.success('企業を削除しました');
      fetchCompanies();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '削除に失敗しました';
      toast.error(msg);
    }
  };

  if (!isMaster) {
    return (
      <div className="mx-auto max-w-4xl p-4">
        <Card>
          <CardBody>
            <p className="text-center text-sm text-[var(--neutral-foreground-3)]">
              このページはマスター管理者のみアクセス可能です。
            </p>
          </CardBody>
        </Card>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="mx-auto max-w-4xl p-4">
        <SkeletonList items={3} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl p-4">
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold">企業管理</h1>
          <p className="text-xs text-[var(--neutral-foreground-3)]">複数の教室を束ねる企業を管理します</p>
        </div>
        <Button variant="primary" onClick={openCreate}>
          <MaterialIcon name="add" size={16} className="mr-1" />
          新規企業
        </Button>
      </div>

      {companies.length === 0 ? (
        <Card>
          <CardBody>
            <p className="py-6 text-center text-sm text-[var(--neutral-foreground-3)]">
              企業が登録されていません
            </p>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-3">
          {companies.map((c) => (
            <Card key={c.id}>
              <CardBody>
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <h3 className="font-medium text-[var(--neutral-foreground-1)]">{c.name}</h3>
                      {c.code && <Badge variant="default">{c.code}</Badge>}
                      {!c.is_active && <Badge variant="danger">無効</Badge>}
                    </div>
                    {c.description && (
                      <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">{c.description}</p>
                    )}
                    <div className="mt-2 flex gap-4 text-xs text-[var(--neutral-foreground-2)]">
                      <span>
                        <MaterialIcon name="apartment" size={12} className="mr-1 inline" />
                        教室: {c.classrooms_count}
                      </span>
                      <span>
                        <MaterialIcon name="people" size={12} className="mr-1 inline" />
                        ユーザー: {c.users_count}
                      </span>
                    </div>
                  </div>
                  <div className="flex flex-col gap-1">
                    <Button size="sm" variant="outline" onClick={() => setAssigningCompany(c)}>
                      <MaterialIcon name="apartment" size={14} className="mr-1" />
                      教室割当
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => openEdit(c)}>
                      <MaterialIcon name="edit" size={14} className="mr-1" />
                      編集
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => handleDelete(c)} className="text-[var(--status-danger-fg)]">
                      <MaterialIcon name="delete" size={14} className="mr-1" />
                      削除
                    </Button>
                  </div>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {/* Create/Edit Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editingCompany ? '企業を編集' : '新規企業'} size="md">
        <div className="space-y-4">
          <Input label="企業名 *" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <Input label="コード" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} helperText="識別用の短いコード（任意）" />
          <div>
            <label className="mb-1 block text-sm font-medium">説明</label>
            <textarea
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              rows={3}
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />
            <span>有効</span>
          </label>
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setModalOpen(false)}>キャンセル</Button>
            <Button variant="primary" onClick={handleSave} isLoading={saving}>
              {editingCompany ? '更新' : '作成'}
            </Button>
          </div>
        </div>
      </Modal>

      {/* Classroom Assignment Modal */}
      {assigningCompany && (
        <CompanyClassroomModal
          company={assigningCompany}
          onClose={() => { setAssigningCompany(null); fetchCompanies(); }}
        />
      )}
    </div>
  );
}

function CompanyClassroomModal({ company, onClose }: { company: Company; onClose: () => void }) {
  const toast = useToast();
  const [classrooms, setClassrooms] = useState<Classroom[]>([]);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const res = await api.get('/api/admin/classrooms');
        const list: Classroom[] = res.data.data || [];
        // 他企業に既に割り当てられている教室は選択肢から除外（未所属 or 自企業所属のみ表示）
        const selectable = list.filter((c) => c.company_id === null || c.company_id === company.id);
        setClassrooms(selectable);
        // 既にこの企業に属している教室を初期選択
        setSelectedIds(selectable.filter((c) => c.company_id === company.id).map((c) => c.id));
      } catch {
        toast.error('教室一覧の取得に失敗しました');
      } finally {
        setLoading(false);
      }
    })();
  }, [company.id, toast]);

  const toggleId = (id: number) => {
    setSelectedIds((prev) => prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]);
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.post(`/api/admin/companies/${company.id}/assign-classrooms`, { classroom_ids: selectedIds });
      toast.success('教室を割り当てました');
      onClose();
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal isOpen={true} onClose={onClose} title={`${company.name} に教室を割り当て`} size="md">
      {loading ? (
        <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
      ) : (
        <div className="space-y-3">
          <p className="text-xs text-[var(--neutral-foreground-3)]">
            この企業に属する教室を選択してください。他の企業に割り当て済みの教室は、その関係を解除するまで一覧に表示されません。
          </p>
          <div className="max-h-[50vh] space-y-1 overflow-y-auto rounded border border-[var(--neutral-stroke-2)] p-2">
            {classrooms.length === 0 ? (
              <p className="px-2 py-3 text-center text-xs text-[var(--neutral-foreground-3)]">
                割り当て可能な教室がありません
              </p>
            ) : (
              classrooms.map((c) => (
                <label key={c.id} className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-[var(--neutral-background-3)]">
                  <input type="checkbox" checked={selectedIds.includes(c.id)} onChange={() => toggleId(c.id)} className="h-4 w-4" />
                  <span>{c.classroom_name}</span>
                </label>
              ))
            )}
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={onClose}>キャンセル</Button>
            <Button variant="primary" isLoading={saving} onClick={handleSave}>保存</Button>
          </div>
        </div>
      )}
    </Modal>
  );
}
