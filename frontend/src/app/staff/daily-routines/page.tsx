'use client';

import { useState, useEffect, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface DailyRoutine {
  id: number | null;
  name: string;
  description: string | null;
  scheduled_time: string | null;
  sort_order: number;
  is_active: boolean;
}

interface RoutineSlot {
  id: number | null;
  name: string;
  content: string;
  time: string;
  filled: boolean;
}

const MAX_ROUTINES = 10;
const INITIAL_SLOTS = 5;

export default function DailyRoutinesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [slots, setSlots] = useState<RoutineSlot[]>([]);
  const [initialized, setInitialized] = useState(false);

  const { data: routines = [], isLoading } = useQuery({
    queryKey: ['staff', 'daily-routines'],
    queryFn: async () => {
      const res = await api.get<{ data: DailyRoutine[] }>('/api/staff/daily-routines');
      return res.data.data;
    },
  });

  // サーバの routines から slots(編集用ローカル状態)を組み立てる。
  // 初期化とキャンセル(編集破棄)の両方で使う。
  const buildSlots = useCallback((rs: DailyRoutine[]): RoutineSlot[] => {
    const activeRoutines = rs.filter((r) => r.is_active);
    const count = Math.max(INITIAL_SLOTS, activeRoutines.length);
    const newSlots: RoutineSlot[] = [];
    for (let i = 0; i < count; i++) {
      const routine = activeRoutines[i];
      newSlots.push(routine
        ? {
            id: routine.id,
            name: routine.name || '',
            content: routine.description || '',
            time: routine.scheduled_time || '',
            filled: !!(routine.name && routine.name.trim()),
          }
        : { id: null, name: '', content: '', time: '', filled: false });
    }
    return newSlots;
  }, []);

  // Initialize slots from fetched routines
  useEffect(() => {
    if (isLoading) return;
    setSlots(buildSlots(routines));
    setInitialized(true);
  }, [routines, isLoading, buildSlots]);

  const saveMutation = useMutation({
    mutationFn: async (slotsToSave: RoutineSlot[]) => {
      // Filter to only non-empty slots and send as batch
      const routinesToSave = slotsToSave
        .filter((s) => s.name.trim() !== '')
        .map((s, index) => ({
          name: s.name.trim(),
          content: s.content.trim(),
          time: s.time.trim(),
          sort_order: index + 1,
        }));

      return api.post('/api/staff/daily-routines/batch-save', {
        routines: routinesToSave,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'daily-routines'] });
      toast.success('毎日の支援を保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const updateSlot = useCallback(
    (index: number, field: keyof RoutineSlot, value: string) => {
      setSlots((prev) => {
        const updated = [...prev];
        updated[index] = { ...updated[index], [field]: value };
        updated[index].filled = updated[index].name.trim() !== '';
        return updated;
      });
    },
    []
  );

  // 行を削除する(保存時に batchSave が全削除→残りを再作成するため、配列から外せばDBから消える)。
  const removeSlot = useCallback((index: number) => {
    if (!confirm(`毎日の支援 ${index + 1} を削除しますか？\n(「保存する」で確定します)`)) return;
    setSlots((prev) => prev.filter((_, i) => i !== index));
  }, []);

  // 行を並び替える(上下入れ替え)。保存時の並び順がそのまま sort_order になる。
  const moveSlot = useCallback((index: number, dir: -1 | 1) => {
    setSlots((prev) => {
      const target = index + dir;
      if (target < 0 || target >= prev.length) return prev;
      const updated = [...prev];
      [updated[index], updated[target]] = [updated[target], updated[index]];
      return updated;
    });
  }, []);

  const addSlot = useCallback(() => {
    if (slots.length >= MAX_ROUTINES) {
      toast.error('最大10個まで登録できます');
      return;
    }
    setSlots((prev) => [
      ...prev,
      { id: null, name: '', content: '', time: '', filled: false },
    ]);
  }, [slots.length, toast]);

  const handleSave = () => {
    saveMutation.mutate(slots);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
            毎日の支援設定
          </h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            ルーティーン活動を登録して支援案作成時に引用できます
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>毎日の支援</CardTitle>
        </CardHeader>

        {/* Info box */}
        <div className="mx-4 mb-4 rounded-none border-l-4 border-[var(--status-info-fg)] bg-[var(--status-info-bg)] p-4 text-sm text-[var(--neutral-foreground-2)]">
          毎日行うルーティーン活動を最大10個まで登録できます。
          <br />
          登録した内容は、支援案作成時に「毎日の支援を引用」から簡単に追加できます。
        </div>

        {isLoading || !initialized ? (
          <SkeletonList items={5} />
        ) : (
          <div className="space-y-3 px-4 pb-4">
            {slots.map((slot, index) => (
              <div
                key={index}
                className={`rounded-lg border-2 p-4 transition-colors ${
                  slot.filled
                    ? 'border-[var(--status-success-fg)] bg-[var(--status-success-bg)]'
                    : 'border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] hover:border-[var(--brand-80)]'
                }`}
              >
                {/* Header */}
                <div className="mb-3 flex items-center gap-3">
                  <div
                    className={`flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold text-white ${
                      slot.filled ? 'bg-[var(--status-success-fg)]' : 'bg-[var(--brand-80)]'
                    }`}
                  >
                    {index + 1}
                  </div>
                  <div className="flex-1 font-semibold text-[var(--neutral-foreground-1)]">
                    毎日の支援 {index + 1}
                  </div>
                  <button
                    type="button"
                    onClick={() => moveSlot(index, -1)}
                    disabled={index === 0}
                    title="上へ移動"
                    className="rounded p-1.5 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-4)] disabled:cursor-not-allowed disabled:opacity-30"
                  >
                    <MaterialIcon name="arrow_upward" size={18} />
                  </button>
                  <button
                    type="button"
                    onClick={() => moveSlot(index, 1)}
                    disabled={index === slots.length - 1}
                    title="下へ移動"
                    className="rounded p-1.5 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-4)] disabled:cursor-not-allowed disabled:opacity-30"
                  >
                    <MaterialIcon name="arrow_downward" size={18} />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeSlot(index)}
                    className="rounded bg-[var(--status-danger-fg)] px-3 py-1 text-xs font-medium text-white hover:opacity-80"
                  >
                    削除
                  </button>
                </div>

                {/* Form fields */}
                <div className="mb-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_150px]">
                  <div>
                    <label className="mb-1 block text-xs font-semibold text-[var(--neutral-foreground-3)]">
                      活動名
                    </label>
                    <input
                      type="text"
                      value={slot.name}
                      onChange={(e) => updateSlot(index, 'name', e.target.value)}
                      placeholder="例: おやつの時間、帰りの会"
                      className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-semibold text-[var(--neutral-foreground-3)]">
                      実施時間
                    </label>
                    <div className="flex items-center gap-2">
                      <input
                        type="number"
                        value={slot.time}
                        onChange={(e) => updateSlot(index, 'time', e.target.value)}
                        placeholder="30"
                        min={1}
                        max={480}
                        className="w-20 rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
                      />
                      <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                        分
                      </span>
                    </div>
                  </div>
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold text-[var(--neutral-foreground-3)]">
                    活動内容
                  </label>
                  <textarea
                    value={slot.content}
                    onChange={(e) => updateSlot(index, 'content', e.target.value)}
                    placeholder="活動の具体的な内容を記入してください"
                    rows={3}
                    className="w-full resize-y rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
                  />
                </div>
              </div>
            ))}

            {/* Routine count */}
            <p className="text-center text-xs text-[var(--neutral-foreground-3)]">
              現在 {slots.length} / {MAX_ROUTINES} 件
            </p>

            {/* Add button */}
            <button
              type="button"
              onClick={addSlot}
              disabled={slots.length >= MAX_ROUTINES}
              className="w-full rounded-md bg-[var(--status-info-fg)] py-3 text-sm font-semibold text-white transition-colors hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
            >
              + 毎日の支援を追加
            </button>

            {/* Action buttons */}
            <div className="flex gap-3 pt-2">
              <Button
                variant="secondary"
                className="flex-1"
                onClick={() => {
                  // 編集中のローカル状態をサーバ値へ戻す(編集を破棄)。
                  // 旧実装は invalidateQueries のみで、未保存時はサーバ応答が同一内容のため
                  // React Query の構造共有で routines 参照が変わらず初期化useEffectが再実行されず、
                  // 画面の編集が残って「キャンセルが効かない」状態だった。
                  setSlots(buildSlots(routines));
                  toast.success('変更を取り消しました');
                }}
              >
                キャンセル
              </Button>
              <Button
                className="flex-1"
                onClick={handleSave}
                isLoading={saveMutation.isPending}
                leftIcon={<MaterialIcon name="save" size={16} />}
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
