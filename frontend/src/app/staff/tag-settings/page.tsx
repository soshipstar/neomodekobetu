'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Tag {
  id: number;
  name: string;
  tag_name: string;
  sort_order: number;
}

const DEFAULT_TAGS = ['動画', '食', '学習', 'イベント', 'その他'];

export default function TagSettingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [localTags, setLocalTags] = useState<string[]>([]);
  const [initialized, setInitialized] = useState(false);

  const { data: serverTags, isLoading } = useQuery({
    queryKey: ['staff', 'tags'],
    queryFn: async () => {
      const res = await api.get<{ data: Tag[] }>('/api/staff/tag-settings');
      return res.data.data;
    },
  });

  useEffect(() => {
    if (serverTags && !initialized) {
      const names = serverTags.length > 0
        ? serverTags.map((t) => t.tag_name || t.name)
        : [...DEFAULT_TAGS];
      setLocalTags(names);
      setInitialized(true);
    }
  }, [serverTags, initialized]);

  const saveMutation = useMutation({
    mutationFn: async (tags: string[]) => {
      return api.post('/api/staff/tag-settings/batch', { tags });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'tags'] });
      toast.success('タグ設定を保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const resetMutation = useMutation({
    mutationFn: async () => {
      return api.post('/api/staff/tag-settings/reset');
    },
    onSuccess: () => {
      setLocalTags([...DEFAULT_TAGS]);
      queryClient.invalidateQueries({ queryKey: ['staff', 'tags'] });
      toast.success('デフォルトタグにリセットしました');
    },
    onError: () => toast.error('リセットに失敗しました'),
  });

  const addTag = () => {
    setLocalTags([...localTags, '']);
    setTimeout(() => {
      const inputs = document.querySelectorAll<HTMLInputElement>('[data-tag-input]');
      inputs[inputs.length - 1]?.focus();
    }, 50);
  };

  const removeTag = (index: number) => {
    setLocalTags(localTags.filter((_, i) => i !== index));
  };

  const updateTag = (index: number, value: string) => {
    const updated = [...localTags];
    updated[index] = value;
    setLocalTags(updated);
  };

  const handleSave = () => {
    const nonEmpty = localTags.filter((t) => t.trim() !== '');
    if (nonEmpty.length === 0) {
      toast.error('タグを1つ以上入力してください');
      return;
    }
    saveMutation.mutate(nonEmpty);
  };

  const handleReset = () => {
    if (confirm('タグをデフォルトに戻しますか？現在の設定は失われます。')) {
      resetMutation.mutate();
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">タグ設定</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">支援案に使用するタグをカスタマイズできます</p>
        </div>
        <Link href="/staff/support-plans">
          <Button variant="secondary" leftIcon={<MaterialIcon name="arrow_back" size={16} />}>
            支援案一覧へ
          </Button>
        </Link>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>タグ一覧</CardTitle>
        </CardHeader>

        <div className="px-4 pb-2">
          <div className="rounded-md border-l-4 border-blue-500 bg-[var(--brand-160)] p-3 text-sm text-[var(--neutral-foreground-2)] dark:bg-blue-950/30">
            支援案作成時に選択できるタグを設定します。<br />
            タグは教室ごとに設定でき、活動の分類に使用されます。
          </div>
        </div>

        {isLoading ? (
          <SkeletonList items={5} />
        ) : (
          <div className="space-y-2 p-4 pt-2">
            <Button onClick={addTag} variant="secondary" leftIcon={<MaterialIcon name="add" size={16} />} className="mb-3">
              タグを追加
            </Button>

            {localTags.map((tag, index) => (
              <div
                key={index}
                className="flex items-center gap-3 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-3"
              >
                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[var(--brand-primary)] text-xs font-semibold text-white">
                  {index + 1}
                </div>
                <Input
                  data-tag-input
                  value={tag}
                  onChange={(e) => updateTag(index, e.target.value)}
                  placeholder="タグ名を入力"
                  className="flex-1"
                />
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => removeTag(index)}
                >
                  <MaterialIcon name="delete" size={16} className="text-[var(--status-danger-fg)]" />
                </Button>
              </div>
            ))}

            {localTags.length === 0 && (
              <p className="py-4 text-center text-sm text-[var(--neutral-foreground-3)]">
                タグがありません。「タグを追加」ボタンでタグを追加してください。
              </p>
            )}

            <div className="flex flex-wrap gap-3 pt-4">
              <Link href="/staff/support-plans" className="flex-1">
                <Button variant="secondary" className="w-full">キャンセル</Button>
              </Link>
              <Button
                variant="secondary"
                onClick={handleReset}
                isLoading={resetMutation.isPending}
                leftIcon={<MaterialIcon name="undo" size={16} />}
                className="flex-1"
              >
                デフォルトに戻す
              </Button>
              <Button
                onClick={handleSave}
                isLoading={saveMutation.isPending}
                className="flex-1"
              >
                保存する
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}
