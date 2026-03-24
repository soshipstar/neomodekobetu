'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Save, Building2 } from 'lucide-react';

interface ClassroomData {
  id: number;
  classroom_name: string;
  address: string | null;
  phone: string | null;
  logo_path: string | null;
  settings: Record<string, string> | null;
  is_active: boolean;
}

export default function AdminSettingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [form, setForm] = useState<{
    classroom_id: number | null;
    classroom_name: string;
    address: string;
    phone: string;
  }>({ classroom_id: null, classroom_name: '', address: '', phone: '' });

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
      });
    }
  }, [classrooms, form.classroom_id]);

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      return api.put('/api/admin/classroom-settings', {
        classroom_id: data.classroom_id,
        address: data.address || null,
        phone: data.phone || null,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'classroom-settings'] });
      toast.success('設定を保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
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

      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <Building2 className="h-5 w-5" />
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
              leftIcon={<Save className="h-4 w-4" />}
            >
              設定を保存
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
