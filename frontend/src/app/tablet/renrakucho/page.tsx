'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery, useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { useVoiceInput } from '../useVoiceInput';
import Link from 'next/link';

/**
 * 5領域の定義（旧アプリと同じ）
 */
const DOMAINS = [
  { key: 'health_life', label: '健康・生活' },
  { key: 'motor_sensory', label: '運動・感覚' },
  { key: 'cognitive_behavior', label: '認知・行動' },
  { key: 'language_communication', label: '言語・コミュニケーション' },
  { key: 'social_relations', label: '人間関係・社会性' },
] as const;

type DomainKey = (typeof DOMAINS)[number]['key'];

interface ActivityRecord {
  id: number;
  activity_name: string;
  common_activity: string | null;
  record_date: string;
  student_records: Array<{
    student_id: number;
    notes: string | null;
    health_life: string | null;
    motor_sensory: string | null;
    cognitive_behavior: string | null;
    language_communication: string | null;
    social_relations: string | null;
    student: { id: number; student_name: string };
  }>;
}

interface StudentFormData {
  student_id: number;
  student_name: string;
  notes: string;
  domain1: DomainKey | '';
  domain1_content: string;
  domain2: DomainKey | '';
  domain2_content: string;
}

/**
 * 旧アプリの domain1/domain2 方式を新アプリの5領域カラムに変換
 */
function domainFormToRecord(form: StudentFormData) {
  const record: Record<string, string | null> = {
    notes: form.notes || null,
    health_life: null,
    motor_sensory: null,
    cognitive_behavior: null,
    language_communication: null,
    social_relations: null,
  };
  if (form.domain1 && form.domain1_content) {
    record[form.domain1] = form.domain1_content;
  }
  if (form.domain2 && form.domain2_content) {
    record[form.domain2] = form.domain2_content;
  }
  return record;
}

/**
 * 新アプリの5領域カラムから旧アプリの domain1/domain2 方式に変換
 */
function recordToDomainForm(sr: ActivityRecord['student_records'][number]): Pick<StudentFormData, 'domain1' | 'domain1_content' | 'domain2' | 'domain2_content' | 'notes'> {
  const filled: Array<{ key: DomainKey; content: string }> = [];
  for (const d of DOMAINS) {
    const val = sr[d.key];
    if (val) {
      filled.push({ key: d.key, content: val });
    }
  }
  return {
    notes: sr.notes || '',
    domain1: filled[0]?.key || '',
    domain1_content: filled[0]?.content || '',
    domain2: filled[1]?.key || '',
    domain2_content: filled[1]?.content || '',
  };
}

export default function TabletRenrakuchoPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const toast = useToast();
  const { activeField, startVoiceInput } = useVoiceInput();

  const activityId = searchParams.get('activity_id');

  const [commonActivity, setCommonActivity] = useState('');
  const [studentForms, setStudentForms] = useState<StudentFormData[]>([]);
  const [currentStudentIndex, setCurrentStudentIndex] = useState(0);

  // 活動データ取得（ID指定で詳細を取得）
  const { data: activity, isLoading } = useQuery({
    queryKey: ['tablet', 'renrakucho-activity', activityId],
    queryFn: async () => {
      if (!activityId) return null;
      const res = await api.get<{ data: ActivityRecord }>(`/api/tablet/activities/detail/${activityId}`);
      return res.data.data;
    },
    enabled: !!activityId,
  });

  // 活動データをフォームに反映
  useEffect(() => {
    if (!activity) return;
    setCommonActivity(activity.common_activity || activity.activity_name || '');

    const forms: StudentFormData[] = (activity.student_records || []).map((sr) => {
      const domainData = recordToDomainForm(sr);
      return {
        student_id: sr.student_id,
        student_name: sr.student?.student_name || `生徒${sr.student_id}`,
        ...domainData,
      };
    });
    setStudentForms(forms);
    setCurrentStudentIndex(0);
  }, [activity]);

  // フォーム更新ヘルパー
  const updateStudentForm = useCallback(
    (index: number, field: keyof StudentFormData, value: string) => {
      setStudentForms((prev) => {
        const next = [...prev];
        next[index] = { ...next[index], [field]: value };
        return next;
      });
    },
    []
  );

  // 個別保存
  const saveStudentMutation = useMutation({
    mutationFn: async (form: StudentFormData) => {
      const record = domainFormToRecord(form);
      return api.post('/api/tablet/renrakucho/student', {
        daily_record_id: Number(activityId),
        student_id: form.student_id,
        ...record,
      });
    },
    onSuccess: () => {
      toast.success('保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  // 全体保存
  const saveAllMutation = useMutation({
    mutationFn: async () => {
      const students = studentForms.map((form) => ({
        student_id: form.student_id,
        ...domainFormToRecord(form),
      }));
      return api.post('/api/tablet/renrakucho/bulk', {
        daily_record_id: Number(activityId),
        common_activity: commonActivity,
        students,
      });
    },
    onSuccess: () => {
      toast.success('全て保存しました');
      router.push('/tablet');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  if (isLoading) {
    return <div className="py-12 text-center text-xl text-gray-400">読み込み中...</div>;
  }

  if (!activity || studentForms.length === 0) {
    return (
      <div className="space-y-6">
        <div className="rounded-xl bg-white p-6 shadow-md">
          <h1 className="text-2xl font-bold">連絡帳入力</h1>
          <p className="mt-4 text-lg text-gray-500">参加者がいません。先に活動を作成してください。</p>
          <Link href="/tablet" className="mt-4 inline-block text-xl text-blue-600 hover:underline">
            ← 戻る
          </Link>
        </div>
      </div>
    );
  }

  const currentForm = studentForms[currentStudentIndex];

  return (
    <div className="space-y-6">
      {/* ヘッダー */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <h1 className="text-3xl font-bold">連絡帳入力フォーム</h1>
        <div className="mt-2 text-xl text-gray-500">
          活動: {activity.activity_name} | {studentForms.length}名参加
        </div>
        <Link href="/tablet" className="mt-2 inline-block text-xl text-blue-600 hover:underline">
          ← 戻る
        </Link>
      </div>

      {/* 本日の活動（共通） */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <label className="mb-3 block text-xl font-bold">本日の活動（共通）</label>
        <div className="flex gap-3">
          <textarea
            value={commonActivity}
            onChange={(e) => setCommonActivity(e.target.value)}
            className="flex-1 rounded-lg border-2 border-gray-300 p-4 text-xl focus:border-blue-500 focus:outline-none"
            rows={3}
          />
          <button
            type="button"
            onClick={() =>
              startVoiceInput('common_activity', (v) => setCommonActivity(v), commonActivity)
            }
            className={`self-start whitespace-nowrap rounded-lg px-6 py-3 text-xl font-bold text-white ${
              activeField === 'common_activity'
                ? 'animate-pulse bg-red-500'
                : 'bg-blue-600 hover:bg-blue-700'
            }`}
          >
            {activeField === 'common_activity' ? '聞いています... (クリックで終了)' : '声で入力'}
          </button>
        </div>
      </div>

      {/* 生徒タブ */}
      <div className="flex flex-wrap gap-2">
        {studentForms.map((form, idx) => (
          <button
            key={form.student_id}
            onClick={() => setCurrentStudentIndex(idx)}
            className={`rounded-lg px-5 py-3 text-lg font-bold transition-all ${
              idx === currentStudentIndex
                ? 'bg-blue-600 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-100'
            }`}
          >
            {form.student_name}
          </button>
        ))}
      </div>

      {/* 現在の生徒のフォーム */}
      {currentForm && (
        <div className="rounded-xl bg-white p-6 shadow-md space-y-6">
          <h2 className="text-2xl font-bold border-b-2 border-blue-500 pb-3">
            {currentForm.student_name}
          </h2>

          {/* 気になったこと1 */}
          <div>
            <label className="mb-2 block text-xl font-bold">気になったこと 1つ目</label>
            <select
              value={currentForm.domain1}
              onChange={(e) => updateStudentForm(currentStudentIndex, 'domain1', e.target.value)}
              className="mb-3 w-full rounded-lg border-2 border-gray-300 p-4 text-xl focus:border-blue-500 focus:outline-none"
            >
              <option value="">領域を選択</option>
              {DOMAINS.filter((d) => d.key !== currentForm.domain2).map((d) => (
                <option key={d.key} value={d.key}>{d.label}</option>
              ))}
            </select>
            <div className="flex gap-3">
              <textarea
                value={currentForm.domain1_content}
                onChange={(e) => updateStudentForm(currentStudentIndex, 'domain1_content', e.target.value)}
                className="flex-1 rounded-lg border-2 border-gray-300 p-4 text-xl focus:border-blue-500 focus:outline-none"
                rows={3}
                placeholder="具体的な様子を入力..."
              />
              <button
                type="button"
                onClick={() =>
                  startVoiceInput(
                    `domain1_${currentForm.student_id}`,
                    (v) => updateStudentForm(currentStudentIndex, 'domain1_content', v),
                    currentForm.domain1_content
                  )
                }
                className={`self-start whitespace-nowrap rounded-lg px-6 py-3 text-xl font-bold text-white ${
                  activeField === `domain1_${currentForm.student_id}`
                    ? 'animate-pulse bg-red-500'
                    : 'bg-blue-600 hover:bg-blue-700'
                }`}
              >
                {activeField === `domain1_${currentForm.student_id}` ? '聞いています...' : '声で入力'}
              </button>
            </div>
          </div>

          {/* 気になったこと2 */}
          <div>
            <label className="mb-2 block text-xl font-bold">気になったこと 2つ目</label>
            <select
              value={currentForm.domain2}
              onChange={(e) => updateStudentForm(currentStudentIndex, 'domain2', e.target.value)}
              className="mb-3 w-full rounded-lg border-2 border-gray-300 p-4 text-xl focus:border-blue-500 focus:outline-none"
            >
              <option value="">領域を選択</option>
              {DOMAINS.filter((d) => d.key !== currentForm.domain1).map((d) => (
                <option key={d.key} value={d.key}>{d.label}</option>
              ))}
            </select>
            <div className="flex gap-3">
              <textarea
                value={currentForm.domain2_content}
                onChange={(e) => updateStudentForm(currentStudentIndex, 'domain2_content', e.target.value)}
                className="flex-1 rounded-lg border-2 border-gray-300 p-4 text-xl focus:border-blue-500 focus:outline-none"
                rows={3}
                placeholder="具体的な様子を入力..."
              />
              <button
                type="button"
                onClick={() =>
                  startVoiceInput(
                    `domain2_${currentForm.student_id}`,
                    (v) => updateStudentForm(currentStudentIndex, 'domain2_content', v),
                    currentForm.domain2_content
                  )
                }
                className={`self-start whitespace-nowrap rounded-lg px-6 py-3 text-xl font-bold text-white ${
                  activeField === `domain2_${currentForm.student_id}`
                    ? 'animate-pulse bg-red-500'
                    : 'bg-blue-600 hover:bg-blue-700'
                }`}
              >
                {activeField === `domain2_${currentForm.student_id}` ? '聞いています...' : '声で入力'}
              </button>
            </div>
          </div>

          {/* 備考 */}
          <div>
            <label className="mb-2 block text-xl font-bold">備考（任意）</label>
            <div className="flex gap-3">
              <textarea
                value={currentForm.notes}
                onChange={(e) => updateStudentForm(currentStudentIndex, 'notes', e.target.value)}
                className="flex-1 rounded-lg border-2 border-gray-300 p-4 text-xl focus:border-blue-500 focus:outline-none"
                rows={3}
                placeholder="その他気になったことなど..."
              />
              <button
                type="button"
                onClick={() =>
                  startVoiceInput(
                    `notes_${currentForm.student_id}`,
                    (v) => updateStudentForm(currentStudentIndex, 'notes', v),
                    currentForm.notes
                  )
                }
                className={`self-start whitespace-nowrap rounded-lg px-6 py-3 text-xl font-bold text-white ${
                  activeField === `notes_${currentForm.student_id}`
                    ? 'animate-pulse bg-red-500'
                    : 'bg-blue-600 hover:bg-blue-700'
                }`}
              >
                {activeField === `notes_${currentForm.student_id}` ? '聞いています...' : '声で入力'}
              </button>
            </div>
          </div>

          {/* 個別保存 */}
          <button
            type="button"
            onClick={() => saveStudentMutation.mutate(currentForm)}
            disabled={saveStudentMutation.isPending}
            className="w-full rounded-lg bg-blue-600 py-4 text-xl font-bold text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {saveStudentMutation.isPending ? '保存中...' : `${currentForm.student_name}の記録を保存`}
          </button>

          {/* 前後ナビ */}
          <div className="flex justify-between">
            <button
              type="button"
              disabled={currentStudentIndex === 0}
              onClick={() => setCurrentStudentIndex((i) => i - 1)}
              className="rounded-lg bg-gray-200 px-8 py-3 text-xl font-bold text-gray-700 hover:bg-gray-300 disabled:opacity-30"
            >
              ← 前の生徒
            </button>
            <button
              type="button"
              disabled={currentStudentIndex >= studentForms.length - 1}
              onClick={() => setCurrentStudentIndex((i) => i + 1)}
              className="rounded-lg bg-gray-200 px-8 py-3 text-xl font-bold text-gray-700 hover:bg-gray-300 disabled:opacity-30"
            >
              次の生徒 →
            </button>
          </div>
        </div>
      )}

      {/* 全体保存 */}
      <div className="flex gap-4">
        <button
          type="button"
          onClick={() => saveAllMutation.mutate()}
          disabled={saveAllMutation.isPending}
          className="flex-1 rounded-lg bg-green-600 py-5 text-2xl font-bold text-white hover:bg-green-700 disabled:opacity-50"
        >
          {saveAllMutation.isPending ? '保存中...' : '全て保存して戻る'}
        </button>
        <button
          type="button"
          onClick={() => router.push('/tablet')}
          className="flex-1 rounded-lg bg-gray-500 py-5 text-2xl font-bold text-white hover:bg-gray-600"
        >
          キャンセル
        </button>
      </div>
    </div>
  );
}
