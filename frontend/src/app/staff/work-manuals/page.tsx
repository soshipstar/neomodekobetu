'use client';

import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useWorkspace } from '@/hooks/useWorkspace';

interface WorkManualStep {
  id?: number;
  sort_order: number;
  title: string;
  description: string | null;
  image_path: string | null;
  video_path: string | null;
  caution: string | null;
  checkpoint: string | null;
}

interface WorkManual {
  id: number;
  classroom_id: number;
  title: string;
  category: string | null;
  summary: string | null;
  difficulty: 'initial' | 'intermediate' | 'advanced' | null;
  estimated_minutes: number | null;
  student_id: number | null;
  is_published: boolean;
  student?: { id: number; student_name: string };
  steps?: WorkManualStep[];
}

const DIFFICULTY_LABEL: Record<string, string> = {
  initial: '初級',
  intermediate: '中級',
  advanced: '上級',
};

export default function WorkManualsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const { terms } = useWorkspace();
  const [editing, setEditing] = useState<WorkManual | null>(null);
  const [creating, setCreating] = useState(false);

  const { data: manuals = [], isLoading } = useQuery({
    queryKey: ['staff', 'work-manuals'],
    queryFn: async () => {
      const res = await api.get<{ data: WorkManual[] }>('/api/staff/work-manuals');
      return res.data.data;
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => api.delete(`/api/staff/work-manuals/${id}`),
    onSuccess: () => {
      toast.success('削除しました');
      queryClient.invalidateQueries({ queryKey: ['staff', 'work-manuals'] });
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-end justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">作業マニュアル</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            写真・動画つき手順書を作成して、現場で {terms.client} と一緒に確認できます。
          </p>
        </div>
        <Button onClick={() => { setCreating(true); setEditing(null); }} leftIcon={<MaterialIcon name="add" size={16} />}>
          新規作成
        </Button>
      </div>

      {isLoading ? (
        <p className="text-sm">読み込み中...</p>
      ) : manuals.length === 0 ? (
        <Card>
          <CardBody>
            <p className="text-sm text-[var(--neutral-foreground-4)]">
              まだ作業マニュアルがありません。「新規作成」から最初の手順書を登録しましょう。
            </p>
          </CardBody>
        </Card>
      ) : (
        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {manuals.map((m) => (
            <Card key={m.id} className="cursor-pointer" onClick={() => setEditing(m)}>
              <CardBody>
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0 flex-1">
                    <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)] truncate">{m.title}</h3>
                    <div className="mt-1 flex flex-wrap gap-1">
                      {m.category && <Badge variant="info" className="text-[10px]">{m.category}</Badge>}
                      {m.difficulty && <Badge variant="default" className="text-[10px]">{DIFFICULTY_LABEL[m.difficulty]}</Badge>}
                      {m.estimated_minutes && (
                        <Badge variant="default" className="text-[10px]">約 {m.estimated_minutes} 分</Badge>
                      )}
                      {m.student && (
                        <Badge variant="warning" className="text-[10px]">個別: {m.student.student_name}</Badge>
                      )}
                    </div>
                    {m.summary && (
                      <p className="mt-2 line-clamp-2 text-xs text-[var(--neutral-foreground-3)]">
                        {m.summary}
                      </p>
                    )}
                  </div>
                </div>
                <div className="mt-3 flex justify-end gap-1">
                  <button
                    onClick={(e) => { e.stopPropagation(); if (confirm('削除しますか?')) deleteMutation.mutate(m.id); }}
                    className="rounded border border-[var(--status-danger-fg)] px-2 py-0.5 text-[10px] text-[var(--status-danger-fg)] hover:bg-red-50"
                  >
                    削除
                  </button>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {(creating || editing) && (
        <ManualEditorModal
          existing={editing}
          onClose={() => { setEditing(null); setCreating(false); }}
          onSaved={() => {
            queryClient.invalidateQueries({ queryKey: ['staff', 'work-manuals'] });
            setEditing(null);
            setCreating(false);
          }}
        />
      )}
    </div>
  );
}

function ManualEditorModal({ existing, onClose, onSaved }: { existing: WorkManual | null; onClose: () => void; onSaved: () => void }) {
  const toast = useToast();
  const [form, setForm] = useState({
    title: existing?.title ?? '',
    category: existing?.category ?? '',
    summary: existing?.summary ?? '',
    difficulty: existing?.difficulty ?? '',
    estimated_minutes: existing?.estimated_minutes ?? 0,
  });
  const [steps, setSteps] = useState<WorkManualStep[]>(existing?.steps ?? []);
  const [saving, setSaving] = useState(false);

  const inputCls = 'block w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm';

  const addStep = () => {
    setSteps((prev) => [
      ...prev,
      { sort_order: prev.length, title: '', description: '', image_path: null, video_path: null, caution: '', checkpoint: '' },
    ]);
  };

  const updateStep = (idx: number, patch: Partial<WorkManualStep>) => {
    setSteps((prev) => prev.map((s, i) => (i === idx ? { ...s, ...patch } : s)));
  };

  const removeStep = (idx: number) => {
    setSteps((prev) => prev.filter((_, i) => i !== idx).map((s, i) => ({ ...s, sort_order: i })));
  };

  const moveStep = (idx: number, direction: -1 | 1) => {
    setSteps((prev) => {
      const next = [...prev];
      const j = idx + direction;
      if (j < 0 || j >= next.length) return next;
      [next[idx], next[j]] = [next[j], next[idx]];
      return next.map((s, i) => ({ ...s, sort_order: i }));
    });
  };

  const handleUpload = async (idx: number, type: 'image' | 'video', file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    try {
      const res = await api.post<{ data: { path: string; url: string } }>('/api/staff/work-manuals/upload', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      updateStep(idx, type === 'image' ? { image_path: res.data.data.path } : { video_path: res.data.data.path });
      toast.success(`${type === 'image' ? '画像' : '動画'}をアップロードしました`);
    } catch {
      toast.error('アップロードに失敗しました');
    }
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload = { ...form, steps: steps.filter((s) => s.title || s.description) };
      if (existing) {
        await api.put(`/api/staff/work-manuals/${existing.id}`, payload);
      } else {
        await api.post('/api/staff/work-manuals', payload);
      }
      toast.success(existing ? '更新しました' : '作成しました');
      onSaved();
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal isOpen onClose={onClose} title={existing ? 'マニュアル編集' : 'マニュアル新規作成'} size="lg">
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <div className="col-span-2">
            <label className="mb-1 block text-xs font-medium">タイトル *</label>
            <input className={inputCls} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium">作業分類</label>
            <input className={inputCls} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} placeholder="例: 袋詰め、清掃" />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium">難易度</label>
            <select className={inputCls} value={form.difficulty} onChange={(e) => setForm({ ...form, difficulty: e.target.value })}>
              <option value="">選択</option>
              <option value="initial">初級</option>
              <option value="intermediate">中級</option>
              <option value="advanced">上級</option>
            </select>
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium">目安所要時間 (分)</label>
            <input className={inputCls} type="number" min={0} max={1440} value={form.estimated_minutes} onChange={(e) => setForm({ ...form, estimated_minutes: Number(e.target.value) })} />
          </div>
          <div className="col-span-2">
            <label className="mb-1 block text-xs font-medium">概要</label>
            <textarea className={inputCls} rows={2} value={form.summary} onChange={(e) => setForm({ ...form, summary: e.target.value })} />
          </div>
        </div>

        <div className="rounded border border-[var(--neutral-stroke-2)] p-3">
          <div className="mb-2 flex items-center justify-between">
            <h4 className="text-sm font-semibold">手順ステップ ({steps.length} 件)</h4>
            <Button size="sm" variant="outline" onClick={addStep} leftIcon={<MaterialIcon name="add" size={14} />}>
              ステップを追加
            </Button>
          </div>
          <div className="space-y-3 max-h-[50vh] overflow-y-auto">
            {steps.map((step, i) => (
              <StepEditor
                key={i}
                index={i}
                step={step}
                onChange={(patch) => updateStep(i, patch)}
                onUpload={(type, file) => handleUpload(i, type, file)}
                onRemove={() => removeStep(i)}
                onMoveUp={() => moveStep(i, -1)}
                onMoveDown={() => moveStep(i, 1)}
                isFirst={i === 0}
                isLast={i === steps.length - 1}
              />
            ))}
            {steps.length === 0 && (
              <p className="py-4 text-center text-xs text-[var(--neutral-foreground-4)]">
                ステップがまだありません。「ステップを追加」から開始してください。
              </p>
            )}
          </div>
        </div>

        <div className="flex justify-end gap-2 pt-2 border-t border-[var(--neutral-stroke-2)]">
          <Button variant="outline" onClick={onClose}>キャンセル</Button>
          <Button onClick={handleSave} isLoading={saving}>保存</Button>
        </div>
      </div>
    </Modal>
  );
}

function StepEditor({ index, step, onChange, onUpload, onRemove, onMoveUp, onMoveDown, isFirst, isLast }: {
  index: number;
  step: WorkManualStep;
  onChange: (patch: Partial<WorkManualStep>) => void;
  onUpload: (type: 'image' | 'video', file: File) => void;
  onRemove: () => void;
  onMoveUp: () => void;
  onMoveDown: () => void;
  isFirst: boolean;
  isLast: boolean;
}) {
  const imgRef = useRef<HTMLInputElement>(null);
  const vidRef = useRef<HTMLInputElement>(null);
  const inputCls = 'block w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm';
  const apiBase = (process.env.NEXT_PUBLIC_API_URL ?? '').replace(/\/api$/, '');

  return (
    <div className="rounded border border-[var(--neutral-stroke-3)] p-3">
      <div className="mb-2 flex items-center justify-between">
        <span className="font-bold text-[var(--brand-80)]">ステップ {index + 1}</span>
        <div className="flex gap-1">
          <button onClick={onMoveUp} disabled={isFirst} className="rounded border px-2 py-0.5 text-xs disabled:opacity-30">↑</button>
          <button onClick={onMoveDown} disabled={isLast} className="rounded border px-2 py-0.5 text-xs disabled:opacity-30">↓</button>
          <button onClick={onRemove} className="rounded border border-[var(--status-danger-fg)] px-2 py-0.5 text-xs text-[var(--status-danger-fg)]">削除</button>
        </div>
      </div>
      <div className="space-y-2">
        <input className={inputCls} placeholder="ステップタイトル" value={step.title} onChange={(e) => onChange({ title: e.target.value })} />
        <textarea className={inputCls} rows={2} placeholder="手順の説明" value={step.description ?? ''} onChange={(e) => onChange({ description: e.target.value })} />

        <div className="grid grid-cols-2 gap-2">
          <div>
            <label className="mb-1 block text-xs font-medium">写真</label>
            {step.image_path && (
              <img src={`${apiBase}/storage/${step.image_path}`} alt="" className="mb-1 max-h-24 rounded border object-contain" />
            )}
            <input ref={imgRef} type="file" accept="image/*" className="hidden" onChange={(e) => e.target.files?.[0] && onUpload('image', e.target.files[0])} />
            <Button size="sm" variant="outline" onClick={() => imgRef.current?.click()} leftIcon={<MaterialIcon name="image" size={14} />}>
              {step.image_path ? '差替え' : '画像追加'}
            </Button>
            {step.image_path && (
              <button onClick={() => onChange({ image_path: null })} className="ml-2 text-xs text-[var(--status-danger-fg)] hover:underline">削除</button>
            )}
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium">動画</label>
            {step.video_path && (
              <video src={`${apiBase}/storage/${step.video_path}`} controls className="mb-1 max-h-24 rounded border" />
            )}
            <input ref={vidRef} type="file" accept="video/*" className="hidden" onChange={(e) => e.target.files?.[0] && onUpload('video', e.target.files[0])} />
            <Button size="sm" variant="outline" onClick={() => vidRef.current?.click()} leftIcon={<MaterialIcon name="videocam" size={14} />}>
              {step.video_path ? '差替え' : '動画追加'}
            </Button>
            {step.video_path && (
              <button onClick={() => onChange({ video_path: null })} className="ml-2 text-xs text-[var(--status-danger-fg)] hover:underline">削除</button>
            )}
          </div>
        </div>

        <div className="grid grid-cols-2 gap-2">
          <textarea className={inputCls} rows={2} placeholder="注意事項 / NG 例" value={step.caution ?? ''} onChange={(e) => onChange({ caution: e.target.value })} />
          <textarea className={inputCls} rows={2} placeholder="完了チェック条件" value={step.checkpoint ?? ''} onChange={(e) => onChange({ checkpoint: e.target.value })} />
        </div>
      </div>
    </div>
  );
}
