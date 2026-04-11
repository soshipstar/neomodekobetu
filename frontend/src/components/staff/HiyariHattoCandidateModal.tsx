'use client';

import { useState } from 'react';
import api from '@/lib/api';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface Candidate {
  detected: boolean;
  studentId: number;
  recordId: number;
  reason: string;
  severity: 'low' | 'medium' | 'high';
  category: string;
  situation: string;
  immediate_response: string;
  prevention_measures: string;
}

const SEVERITY_LABELS: Record<string, string> = {
  low: '軽度 (ヒヤリ)',
  medium: '中度 (応急処置あり)',
  high: '重度 (医療機関受診)',
};

const CATEGORY_LABELS: Record<string, string> = {
  fall: '転倒・転落',
  collision: '衝突・接触',
  choking: '誤嚥・窒息',
  ingestion: '誤食・異物摂取',
  allergy: 'アレルギー反応',
  missing: '行方不明・離設',
  conflict: '児童間トラブル',
  self_harm: '自傷行為',
  vehicle: '送迎・車両関連',
  medication: '投薬関連',
  other: 'その他',
};

interface Props {
  candidate: Candidate;
  classroomId: number;
  onClose: () => void;
}

/**
 * AI が検出したヒヤリハット候補を確認し、編集して登録 or 登録しない モーダル
 */
export function HiyariHattoCandidateModal({ candidate, classroomId, onClose }: Props) {
  const toast = useToast();
  const [form, setForm] = useState({
    occurred_at: new Date().toISOString().slice(0, 16),
    location: '',
    activity_before: '',
    student_condition: '',
    situation: candidate.situation || '',
    severity: candidate.severity,
    category: candidate.category,
    immediate_response: candidate.immediate_response || '',
    injury_description: '',
    medical_treatment: false,
    guardian_notified: false,
    prevention_measures: candidate.prevention_measures || '',
    environment_improvements: '',
  });
  const [saving, setSaving] = useState(false);

  const handleRegister = async () => {
    if (!form.situation.trim()) {
      toast.error('発生状況は必須です');
      return;
    }
    setSaving(true);
    try {
      await api.post('/api/staff/hiyari-hatto', {
        classroom_id: classroomId,
        student_id: candidate.studentId,
        source_daily_record_id: candidate.recordId,
        source_type: 'integrated_note_ai',
        ...form,
      });
      toast.success('ヒヤリハットを登録しました');
      onClose();
    } catch {
      toast.error('登録に失敗しました');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal isOpen={true} onClose={onClose} title="ヒヤリハット候補の検出" size="lg">
      <div className="space-y-4">
        <div className="rounded border border-[var(--status-warning-fg)]/30 bg-[var(--status-warning-bg)] p-3">
          <div className="flex items-center gap-2 text-sm font-semibold text-[var(--status-warning-fg)]">
            <MaterialIcon name="warning" size={18} />
            AI がこの記録にヒヤリハットを検出しました
          </div>
          <p className="mt-2 text-xs text-[var(--neutral-foreground-2)]">
            {candidate.reason || '観察記録から危険事象の可能性を検出しました。'}
          </p>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
            ヒヤリハット記録が不要な場合は「登録しない」を押してください。必要な場合は以下を確認・編集して登録してください。
          </p>
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
              発生日時 *
            </label>
            <input
              type="datetime-local"
              value={form.occurred_at}
              onChange={(e) => setForm({ ...form, occurred_at: e.target.value })}
              className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-1.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
              発生場所
            </label>
            <input
              type="text"
              value={form.location}
              onChange={(e) => setForm({ ...form, location: e.target.value })}
              placeholder="例: プレイルーム、廊下、送迎車内"
              className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-1.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
              危険度 *
            </label>
            <select
              value={form.severity}
              onChange={(e) => setForm({ ...form, severity: e.target.value as Candidate['severity'] })}
              className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-1.5 text-sm"
            >
              {Object.entries(SEVERITY_LABELS).map(([k, v]) => (
                <option key={k} value={k}>{v}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
              事故分類
            </label>
            <select
              value={form.category}
              onChange={(e) => setForm({ ...form, category: e.target.value })}
              className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-1.5 text-sm"
            >
              {Object.entries(CATEGORY_LABELS).map(([k, v]) => (
                <option key={k} value={k}>{v}</option>
              ))}
            </select>
          </div>
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
            発生前の活動
          </label>
          <textarea
            value={form.activity_before}
            onChange={(e) => setForm({ ...form, activity_before: e.target.value })}
            rows={2}
            placeholder="どのような活動中の出来事か"
            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
            発生状況 *
          </label>
          <textarea
            value={form.situation}
            onChange={(e) => setForm({ ...form, situation: e.target.value })}
            rows={3}
            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
            怪我の有無・内容
          </label>
          <textarea
            value={form.injury_description}
            onChange={(e) => setForm({ ...form, injury_description: e.target.value })}
            rows={2}
            placeholder="無ければ空欄、あれば部位・程度"
            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
            即時対応
          </label>
          <textarea
            value={form.immediate_response}
            onChange={(e) => setForm({ ...form, immediate_response: e.target.value })}
            rows={2}
            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-2 text-sm"
          />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <label className="flex items-center gap-2 rounded border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm cursor-pointer">
            <input
              type="checkbox"
              checked={form.medical_treatment}
              onChange={(e) => setForm({ ...form, medical_treatment: e.target.checked })}
            />
            医療機関受診
          </label>
          <label className="flex items-center gap-2 rounded border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm cursor-pointer">
            <input
              type="checkbox"
              checked={form.guardian_notified}
              onChange={(e) => setForm({ ...form, guardian_notified: e.target.checked })}
            />
            保護者連絡済み
          </label>
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
            再発防止策
          </label>
          <textarea
            value={form.prevention_measures}
            onChange={(e) => setForm({ ...form, prevention_measures: e.target.value })}
            rows={2}
            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
            環境整備・改善
          </label>
          <textarea
            value={form.environment_improvements}
            onChange={(e) => setForm({ ...form, environment_improvements: e.target.value })}
            rows={2}
            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-2 text-sm"
          />
        </div>

        <div className="flex justify-end gap-2 pt-2 border-t border-[var(--neutral-stroke-2)]">
          <Button variant="outline" onClick={onClose}>
            登録しない
          </Button>
          <Button
            variant="primary"
            onClick={handleRegister}
            isLoading={saving}
            leftIcon={<MaterialIcon name="save" size={16} />}
          >
            ヒヤリハット登録
          </Button>
        </div>
      </div>
    </Modal>
  );
}
