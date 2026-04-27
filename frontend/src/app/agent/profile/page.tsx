'use client';

import { useCallback, useEffect, useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
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

export default function AgentProfilePage() {
  const toast = useToast();
  const [profile, setProfile] = useState<Profile | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchProfile = useCallback(async () => {
    try {
      const res = await api.get('/api/agent/profile');
      setProfile(res.data?.data ?? null);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => { fetchProfile(); }, [fetchProfile]);

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

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">基本情報（閲覧のみ）</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            ここに表示される情報は KIDURI 運営側で管理しています。変更が必要な場合は運営までご連絡ください。
          </p>
          <dl className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">既定手数料率</dt><dd className="flex-1 font-semibold text-[var(--brand-80)]">{(parseFloat(String(profile.default_commission_rate)) * 100).toFixed(1)}%</dd></div>
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">担当者</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{profile.contact_name || '—'}</dd></div>
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">メール</dt><dd className="flex-1 text-[var(--neutral-foreground-1)] break-all">{profile.contact_email || '—'}</dd></div>
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">電話</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{profile.contact_phone || '—'}</dd></div>
            <div className="flex sm:col-span-2"><dt className="w-32 text-[var(--neutral-foreground-3)]">住所</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{profile.address || '—'}</dd></div>
          </dl>
        </CardBody>
      </Card>

      {profile.contract_document_path && (
        <Card>
          <CardBody>
            <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">代理店契約書</h2>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              KIDURI と貴代理店との間で締結された契約書です。
            </p>
            <div className="mt-3">
              <Button onClick={async () => {
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
              }}>
                <MaterialIcon name="description" size={18} />
                <span className="ml-1">契約書PDFを開く</span>
              </Button>
            </div>
          </CardBody>
        </Card>
      )}

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
