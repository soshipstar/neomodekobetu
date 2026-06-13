'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface ClassroomData {
  id: number;
  classroom_name: string;
  address: string | null;
  phone: string | null;
  logo_path: string | null;
  settings: Record<string, string> | null;
  is_active: boolean;
  ability_assessment_enabled: boolean;
}

export default function AdminSettingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [form, setForm] = useState<{
    classroom_id: number | null;
    classroom_name: string;
    address: string;
    phone: string;
    ability_assessment_enabled: boolean;
  }>({ classroom_id: null, classroom_name: '', address: '', phone: '', ability_assessment_enabled: false });

  const { data: classrooms = [], isLoading } = useQuery({
    queryKey: ['admin', 'classroom-settings'],
    queryFn: async () => {
      const res = await api.get<{ data: ClassroomData[] }>('/api/admin/classroom-settings');
      const data = res.data.data;
      return Array.isArray(data) ? data : [];
    },
    retry: false,
  });

  // 通常管理者は1教室のみなので、データ取得後に自動セット
  useEffect(() => {
    if (classrooms.length === 1 && !form.classroom_id) {
      const c = classrooms[0];
      setForm({
        classroom_id: c.id,
        classroom_name: c.classroom_name,
        address: c.address || '',
        phone: c.phone || '',
        ability_assessment_enabled: c.ability_assessment_enabled ?? false,
      });
    }
  }, [classrooms, form.classroom_id]);

  // AI学習基盤: 施設(company)単位の集計同意。masterで所属施設が無い場合は409→未表示。
  const { data: aiConsent } = useQuery({
    queryKey: ['admin', 'ai-consent-company'],
    queryFn: async () => {
      const res = await api.get<{
        data: { company_id: number; company_name: string; ai_consent_aggregate: boolean; ai_consent_aggregate_at: string | null };
      }>('/api/admin/ai-consent/company');
      return res.data.data;
    },
    retry: false,
  });

  const consentMutation = useMutation({
    mutationFn: async (granted: boolean) => api.put('/api/admin/ai-consent/company', { granted }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'ai-consent-company'] });
      toast.success('AI学習基盤の設定を保存しました');
    },
    onError: (err: unknown) => toast.error(formatApiError(err, '保存に失敗しました')),
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      return api.put('/api/admin/classroom-settings', {
        classroom_id: data.classroom_id,
        address: data.address || null,
        phone: data.phone || null,
        ability_assessment_enabled: data.ability_assessment_enabled,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'classroom-settings'] });
      toast.success('設定を保存しました');
    },
    onError: (err: unknown) => toast.error(formatApiError(err, '保存に失敗しました')),
  });

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">教室基本設定</h1>
        <SkeletonList items={4} />
      </div>
    );
  }

  if (classrooms.length === 0) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">教室基本設定</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">設定可能な教室がありません。</p>
      </div>
    );
  }

  const currentClassroom = classrooms.find((c) => c.id === form.classroom_id) || classrooms[0];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">教室基本設定</h1>

      {aiConsent && (
        <Card>
          <CardHeader>
            <CardTitle>
              <div className="flex items-center gap-2">
                <MaterialIcon name="model_training" size={20} />
                AI学習基盤への参加（施設）
              </div>
            </CardTitle>
          </CardHeader>
          <CardBody>
            <label className="flex items-start gap-3 cursor-pointer">
              <input
                type="checkbox"
                className="mt-1 h-4 w-4 accent-[var(--brand-background-1)]"
                checked={aiConsent.ai_consent_aggregate}
                disabled={consentMutation.isPending}
                onChange={(e) => consentMutation.mutate(e.target.checked)}
              />
              <span className="text-sm">
                <span className="font-medium text-[var(--neutral-foreground-1)]">AI学習基盤に参加する（品質改善のための統計利用）</span>
                <span className="mt-1 block text-[var(--neutral-foreground-3)]">
                  生成と修正の傾向を施設単位で統計集計し、文章品質と支援案の改善に役立てます。ONにすると、別途「学習同意」がある児童の記録がAIの改善・学習に利用されます（施設の同意と児童の同意の両方が必要です）。個人を特定しない集計のみに利用します。
                </span>
                {aiConsent.ai_consent_aggregate && aiConsent.ai_consent_aggregate_at && (
                  <span className="mt-1 block text-xs text-[var(--neutral-foreground-4)]">
                    最終更新: {new Date(aiConsent.ai_consent_aggregate_at).toLocaleString('ja-JP')}
                  </span>
                )}
              </span>
            </label>
          </CardBody>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <MaterialIcon name="apartment" size={20} />
              {currentClassroom.classroom_name}
            </div>
          </CardTitle>
        </CardHeader>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            saveMutation.mutate(form);
          }}
          className="space-y-4"
        >
          <Input
            label="教室名"
            value={form.classroom_name}
            disabled
            helperText="教室名はマスター管理者のみ変更可能です"
          />
          <Input
            label="住所"
            value={form.address}
            onChange={(e) => setForm({ ...form, address: e.target.value })}
            placeholder="〒000-0000 東京都..."
          />
          <Input
            label="電話番号"
            value={form.phone}
            onChange={(e) => setForm({ ...form, phone: e.target.value })}
            placeholder="03-0000-0000"
          />
          <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
            <label className="flex items-start gap-3 cursor-pointer">
              <input
                type="checkbox"
                className="mt-1 h-4 w-4 accent-[var(--brand-background-1)]"
                checked={form.ability_assessment_enabled}
                onChange={(e) => setForm({ ...form, ability_assessment_enabled: e.target.checked })}
              />
              <span className="text-sm">
                <span className="font-medium text-[var(--neutral-foreground-1)]">能力評価システムを使う</span>
                <span className="mt-1 block text-[var(--neutral-foreground-3)]">
                  個別支援計画の作成時に能力評価(発達段階別)を参考データとして使います。ONにすると、日々の活動記録の入力時に児童ごとの設問が表示されます。
                </span>
              </span>
            </label>
          </div>
          {currentClassroom.logo_path && (
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">教室ロゴ</label>
              <img
                src={`${process.env.NEXT_PUBLIC_BACKEND_URL ?? 'http://localhost:8000'}/storage/${currentClassroom.logo_path}`}
                alt="教室ロゴ"
                className="h-16 w-16 rounded-lg object-contain border border-[var(--neutral-stroke-2)]"
              />
            </div>
          )}
          <div className="flex justify-end pt-2">
            <Button
              type="submit"
              isLoading={saveMutation.isPending}
              leftIcon={<MaterialIcon name="save" size={16} />}
            >
              設定を保存
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
