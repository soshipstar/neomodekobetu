'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Profile {
  id: number;
  name: string;
  code: string | null;
  contact_name: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  address: string | null;
  default_commission_rate: string | number;
  contract_terms: string | null;
  contract_document_path: string | null;
  is_active: boolean;
}

type EditableFields = 'name' | 'contact_name' | 'contact_email' | 'contact_phone' | 'address';
type FormState = Record<EditableFields, string>;

const emptyForm: FormState = {
  name: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  address: '',
};

function profileToForm(p: Profile): FormState {
  return {
    name: p.name ?? '',
    contact_name: p.contact_name ?? '',
    contact_email: p.contact_email ?? '',
    contact_phone: p.contact_phone ?? '',
    address: p.address ?? '',
  };
}

export default function AgentProfilePage() {
  const toast = useToast();
  const [profile, setProfile] = useState<Profile | null>(null);
  const [loading, setLoading] = useState(true);
  const [editMode, setEditMode] = useState(false);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [saving, setSaving] = useState(false);

  const fetchProfile = useCallback(async () => {
    try {
      const res = await api.get('/api/agent/profile');
      const data: Profile | null = res.data?.data ?? null;
      setProfile(data);
      if (data) setForm(profileToForm(data));
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => { fetchProfile(); }, [fetchProfile]);

  const handleStartEdit = () => {
    if (profile) setForm(profileToForm(profile));
    setEditMode(true);
  };

  const handleCancelEdit = () => {
    if (profile) setForm(profileToForm(profile));
    setEditMode(false);
  };

  const handleSave = async () => {
    if (!form.name.trim()) {
      toast.error('代理店名は必須です');
      return;
    }
    setSaving(true);
    try {
      const res = await api.put('/api/agent/profile', {
        name: form.name.trim(),
        contact_name: form.contact_name.trim() || null,
        contact_email: form.contact_email.trim() || null,
        contact_phone: form.contact_phone.trim() || null,
        address: form.address.trim() || null,
      });
      const updated: Profile | null = res.data?.data ?? null;
      if (updated) {
        setProfile(updated);
        setForm(profileToForm(updated));
      }
      setEditMode(false);
      toast.success('代理店情報を更新しました');
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const errors = e?.response?.data?.errors;
      if (errors) {
        const first = Object.values(errors)[0]?.[0];
        toast.error(first || '入力内容に誤りがあります');
      } else {
        toast.error(e?.response?.data?.message || '保存に失敗しました');
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading || !profile) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">代理店情報</h1>
        <SkeletonList items={2} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3 flex-wrap">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{profile.name}</h1>
        {profile.is_active ? <Badge variant="success">有効</Badge> : <Badge variant="default">無効</Badge>}
        {profile.code && <span className="text-sm text-[var(--neutral-foreground-3)]">{profile.code}</span>}
      </div>

      {/* 編集可能な代理店情報 */}
      <Card>
        <CardBody>
          <div className="flex items-start justify-between gap-3 mb-3">
            <div>
              <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">代理店情報</h2>
              <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                代理店名・連絡先情報はご自身で編集できます。手数料率や契約条件などの運用項目は KIDURI 運営側で管理します。
              </p>
            </div>
            {!editMode && (
              <Button variant="outline" size="sm" onClick={handleStartEdit} leftIcon={<MaterialIcon name="edit" size={16} />}>
                編集
              </Button>
            )}
          </div>

          {editMode ? (
            <div className="space-y-3">
              <Input
                label="代理店名 *"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              />
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Input
                  label="担当者"
                  value={form.contact_name}
                  onChange={(e) => setForm((f) => ({ ...f, contact_name: e.target.value }))}
                />
                <Input
                  label="メール"
                  type="email"
                  value={form.contact_email}
                  onChange={(e) => setForm((f) => ({ ...f, contact_email: e.target.value }))}
                />
                <Input
                  label="電話"
                  value={form.contact_phone}
                  onChange={(e) => setForm((f) => ({ ...f, contact_phone: e.target.value }))}
                />
                <Input
                  label="住所"
                  value={form.address}
                  onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
                />
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <Button variant="ghost" onClick={handleCancelEdit} disabled={saving}>キャンセル</Button>
                <Button onClick={handleSave} isLoading={saving} leftIcon={<MaterialIcon name="save" size={16} />}>
                  保存
                </Button>
              </div>
            </div>
          ) : (
            <dl className="grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
              <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">代理店名</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{profile.name}</dd></div>
              <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">担当者</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{profile.contact_name || '—'}</dd></div>
              <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">メール</dt><dd className="flex-1 text-[var(--neutral-foreground-1)] break-all">{profile.contact_email || '—'}</dd></div>
              <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">電話</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{profile.contact_phone || '—'}</dd></div>
              <div className="flex sm:col-span-2"><dt className="w-32 text-[var(--neutral-foreground-3)]">住所</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{profile.address || '—'}</dd></div>
            </dl>
          )}
        </CardBody>
      </Card>

      {/* 運用情報 (閲覧のみ) */}
      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">運用情報（閲覧のみ）</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            手数料率や契約条件は KIDURI 運営側で管理しています。変更が必要な場合は運営までご連絡ください。
          </p>
          <dl className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">既定手数料率</dt><dd className="flex-1 font-semibold text-[var(--brand-80)]">{(parseFloat(String(profile.default_commission_rate)) * 100).toFixed(1)}%</dd></div>
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">代理店コード</dt><dd className="flex-1 text-[var(--neutral-foreground-1)] font-mono">{profile.code || '—'}</dd></div>
          </dl>
        </CardBody>
      </Card>

      <ContractDocumentSection profile={profile} onChanged={fetchProfile} />


      {profile.contract_terms && (
        <Card>
          <CardBody>
            <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">契約条件</h2>
            <p className="mt-2 whitespace-pre-wrap text-sm text-[var(--neutral-foreground-2)]">{profile.contract_terms}</p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}

/**
 * 代理店契約書 PDF の表示・アップロード・削除セクション。
 * ファイルが存在すれば「PDFを開く」+「差し替え」+「削除」、
 * 未登録なら「アップロード」だけを表示。
 */
function ContractDocumentSection({ profile, onChanged }: { profile: Profile; onChanged: () => void }) {
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const hasContract = !!profile.contract_document_path;

  const handleOpen = async () => {
    try {
      const res = await api.get('/api/agent/contract-document', { responseType: 'blob' });
      const blob = new Blob([res.data], { type: 'application/pdf' });
      const url = window.URL.createObjectURL(blob);
      window.open(url, '_blank', 'noopener');
      setTimeout(() => window.URL.revokeObjectURL(url), 60000);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '契約書を開けませんでした';
      toast.error(msg);
    }
  };

  const handleFileSelected = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.type !== 'application/pdf') {
      toast.error('PDFファイルのみアップロードできます');
      e.target.value = '';
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      toast.error('ファイルサイズは10MB以下にしてください');
      e.target.value = '';
      return;
    }
    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      await api.post('/api/agent/contract-document', formData);
      toast.success('代理店契約書をアップロードしました');
      onChanged();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'アップロードに失敗しました';
      toast.error(msg);
    } finally {
      setUploading(false);
      e.target.value = '';
    }
  };

  const handleDelete = async () => {
    if (!confirm('代理店契約書PDFを削除しますか?')) return;
    setDeleting(true);
    try {
      await api.delete('/api/agent/contract-document');
      toast.success('代理店契約書を削除しました');
      onChanged();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '削除に失敗しました';
      toast.error(msg);
    } finally {
      setDeleting(false);
    }
  };

  return (
    <Card>
      <CardBody>
        <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">代理店契約書</h2>
        <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
          KIDURI (株式会社ソーシップ) と貴代理店との間で締結された契約書 PDF をアップロードできます。
          差し替え時は新しいファイルを指定してください。
        </p>
        <div className="mt-3 flex flex-wrap gap-2">
          {hasContract && (
            <Button onClick={handleOpen} variant="outline" leftIcon={<MaterialIcon name="description" size={18} />}>
              契約書PDFを開く
            </Button>
          )}
          <Button
            onClick={() => fileInputRef.current?.click()}
            isLoading={uploading}
            leftIcon={<MaterialIcon name={hasContract ? 'swap_horiz' : 'upload_file'} size={18} />}
          >
            {hasContract ? '差し替え' : 'PDFをアップロード'}
          </Button>
          {hasContract && (
            <Button
              variant="ghost"
              onClick={handleDelete}
              isLoading={deleting}
              leftIcon={<MaterialIcon name="delete" size={18} />}
            >
              削除
            </Button>
          )}
        </div>
        <input
          ref={fileInputRef}
          type="file"
          accept="application/pdf"
          className="hidden"
          onChange={handleFileSelected}
        />
        <p className="mt-2 text-xs text-[var(--neutral-foreground-4)]">PDF形式、10MB以下</p>
      </CardBody>
    </Card>
  );
}
