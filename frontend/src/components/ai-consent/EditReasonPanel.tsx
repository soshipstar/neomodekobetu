'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface Category { id: number; code: string; label_ja: string; description: string | null }
interface Revision {
  id: number;
  document_type: string;
  section_key: string;
  edit_kind: string | null;
  change_ratio: number | null;
  after_preview: string;
  category_ids: number[];
  free_text: string | null;
  tagged: boolean;
}

const SECTION_LABELS: Record<string, string> = {
  long_term_goal: '長期目標', short_term_goal: '短期目標', overall_policy: '支援方針',
  life_intention: '本人・家族の意向', student_wish: '本人の願い', overall_comment: '総合所見',
  integrated_content: '連絡帳本文', health_life: '健康・生活', motor_sensory: '運動・感覚',
  cognitive_behavior: '認知・行動', language_communication: '言語・コミュニケーション', social_relations: '人間関係・社会性',
};
const DOC_LABELS: Record<string, string> = {
  support_plan: '個別支援計画', monitoring: 'モニタリング', assessment_staff: 'アセスメント', integrated_note: '連絡帳',
};

function sectionLabel(key: string): string {
  if (key.startsWith('detail:')) return SECTION_LABELS[key.split(':')[1]] ?? '支援内容';
  return SECTION_LABELS[key] ?? key;
}

/**
 * 修正理由の記録パネル(§11)。AIが生成した下書きへの職員の修正に「なぜ直したか」を
 * 1クリックchips + 自由記述で付与する。蓄積した理由はAIの生成改善(S5)に使われる。
 * 学習同意が無い等で修正イベントが無ければ何も表示しない(自己ゲート)。
 */
export function EditReasonPanel({ studentId }: { studentId: number }) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [drafts, setDrafts] = useState<Record<number, { cats: number[]; free: string }>>({});
  const [savingId, setSavingId] = useState<number | null>(null);

  const { data: categories = [] } = useQuery({
    queryKey: ['edit-reason-categories'],
    queryFn: async () => (await api.get<{ data: Category[] }>('/api/staff/edit-reason-categories')).data.data,
    retry: false,
  });

  const revKey = ['edit-reasons', studentId];
  const { data: revisions = [], isLoading, error } = useQuery({
    queryKey: revKey,
    queryFn: async () => (await api.get<{ data: Revision[] }>(`/api/staff/students/${studentId}/edit-reasons`)).data.data,
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 403) return null;
  if (isLoading || revisions.length === 0) return null; // 修正イベントが無ければ非表示

  const draftFor = (r: Revision) => drafts[r.id] ?? { cats: r.category_ids ?? [], free: r.free_text ?? '' };

  const toggleCat = (r: Revision, catId: number) => {
    const d = draftFor(r);
    const cats = d.cats.includes(catId) ? d.cats.filter((c) => c !== catId) : [...d.cats, catId];
    setDrafts({ ...drafts, [r.id]: { ...d, cats } });
  };
  const setFree = (r: Revision, free: string) => setDrafts({ ...drafts, [r.id]: { ...draftFor(r), free } });

  const save = async (r: Revision) => {
    const d = draftFor(r);
    setSavingId(r.id);
    try {
      await api.post(`/api/staff/edit-reasons/${r.id}/attach`, {
        category_ids: d.cats,
        free_text: d.free.trim() === '' ? undefined : d.free.trim(),
      });
      await queryClient.invalidateQueries({ queryKey: revKey });
      toast.success('修正理由を記録しました');
    } catch (err) {
      toast.error(formatApiError(err, '保存に失敗しました'));
    } finally {
      setSavingId(null);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center gap-2">
            <MaterialIcon name="rate_review" size={20} />
            修正理由の記録（AIの精度向上に使われます）
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        <p className="mb-3 text-xs text-[var(--neutral-foreground-3)]">
          AIの下書きをどの観点で直したかを記録すると、次回からその傾向を踏まえた下書きになります（任意）。
        </p>
        <div className="space-y-4">
          {revisions.slice(0, 10).map((r) => {
            const d = draftFor(r);
            return (
              <div key={r.id} className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
                <div className="mb-2 flex flex-wrap items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
                  <span className="rounded bg-[var(--neutral-background-3)] px-2 py-0.5">{DOC_LABELS[r.document_type] ?? r.document_type}</span>
                  <span className="font-medium text-[var(--neutral-foreground-2)]">{sectionLabel(r.section_key)}</span>
                  {r.change_ratio != null && <span>修正量 {Math.round(r.change_ratio * 100)}%</span>}
                  {r.tagged && <span className="text-[var(--success-foreground-1,#137333)]">記録済</span>}
                </div>
                {r.after_preview && <p className="mb-2 line-clamp-1 text-xs text-[var(--neutral-foreground-4)]">「{r.after_preview}…」</p>}
                <div className="flex flex-wrap gap-1.5">
                  {categories.map((c) => (
                    <button
                      key={c.id}
                      type="button"
                      title={c.description ?? ''}
                      onClick={() => toggleCat(r, c.id)}
                      className={`rounded-full px-2.5 py-1 text-xs transition-colors ${
                        d.cats.includes(c.id)
                          ? 'bg-[var(--brand-background-1)] text-white'
                          : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
                      }`}
                    >
                      {c.label_ja}
                    </button>
                  ))}
                </div>
                <div className="mt-2 flex items-center gap-2">
                  <input
                    type="text"
                    value={d.free}
                    onChange={(e) => setFree(r, e.target.value)}
                    placeholder="その他の理由（自由記述・任意。新しい観点は候補として管理者へ）"
                    maxLength={1000}
                    className="flex-1 rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm"
                  />
                  <Button size="sm" isLoading={savingId === r.id} onClick={() => save(r)}>保存</Button>
                </div>
              </div>
            );
          })}
        </div>
      </CardBody>
    </Card>
  );
}
