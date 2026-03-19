'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery, useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { useVoiceInput } from '../../useVoiceInput';
import Link from 'next/link';
import { format } from 'date-fns';

interface Student {
  id: number;
  student_name: string;
  grade_level: string;
}

interface SupportPlan {
  id: number;
  activity_name: string;
  activity_purpose: string | null;
  activity_content: string | null;
  five_domains_consideration: string | null;
  other_notes: string | null;
  staff: { id: number; full_name: string } | null;
}

interface ActivityRecord {
  id: number;
  activity_name: string;
  common_activity: string | null;
  record_date: string;
  student_records: Array<{
    student_id: number;
    student: { id: number; student_name: string };
  }>;
}

export default function TabletActivityEditPageWrapper() {
  return <Suspense fallback={<div className="p-8 text-center">読み込み中...</div>}><TabletActivityEditPage /></Suspense>;
}

function TabletActivityEditPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const toast = useToast();
  const { activeField, startVoiceInput } = useVoiceInput();

  const activityId = searchParams.get('id');
  const recordDate = searchParams.get('date') || format(new Date(), 'yyyy-MM-dd');

  const [activityName, setActivityName] = useState('');
  const [selectedStudentIds, setSelectedStudentIds] = useState<number[]>([]);
  const [selectedPlanId, setSelectedPlanId] = useState<string>('');
  const [showPlanDetails, setShowPlanDetails] = useState(false);
  const [planDetails, setPlanDetails] = useState<SupportPlan | null>(null);

  // 全生徒取得
  const { data: allStudents = [] } = useQuery({
    queryKey: ['tablet', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/tablet/students');
      return res.data.data;
    },
  });

  // 支援案取得
  const { data: supportPlans = [] } = useQuery({
    queryKey: ['tablet', 'support-plans', recordDate],
    queryFn: async () => {
      const res = await api.get<{ data: SupportPlan[] }>('/api/tablet/support-plans', {
        params: { date: recordDate },
      });
      return res.data.data;
    },
  });

  // 既存の活動を編集する場合
  const { data: existingActivity } = useQuery({
    queryKey: ['tablet', 'activity-detail', activityId],
    queryFn: async () => {
      if (!activityId) return null;
      const dateForQuery = recordDate;
      const res = await api.get<{ data: ActivityRecord[] }>(`/api/tablet/activities/${dateForQuery}`);
      return res.data.data.find((a: ActivityRecord) => a.id === Number(activityId)) || null;
    },
    enabled: !!activityId,
  });

  // 既存データをフォームに反映
  useEffect(() => {
    if (existingActivity) {
      setActivityName(existingActivity.activity_name || '');
      setSelectedStudentIds(
        existingActivity.student_records?.map((sr: { student_id: number }) => sr.student_id) || []
      );
    }
  }, [existingActivity]);

  // 支援案選択時
  const handlePlanChange = (planId: string) => {
    setSelectedPlanId(planId);
    if (planId === '') {
      setShowPlanDetails(false);
      setPlanDetails(null);
      return;
    }
    const plan = supportPlans.find((p) => p.id === Number(planId));
    if (plan) {
      setActivityName(plan.activity_name);
      setPlanDetails(plan);
      setShowPlanDetails(true);
    }
  };

  // 保存
  const saveMutation = useMutation({
    mutationFn: async () => {
      if (activityId) {
        return api.put(`/api/tablet/activities/${activityId}`, {
          activity_name: activityName,
          common_activity: activityName,
          student_ids: selectedStudentIds,
        });
      } else {
        return api.post('/api/tablet/activities', {
          record_date: recordDate,
          activity_name: activityName,
          common_activity: activityName,
          student_ids: selectedStudentIds,
        });
      }
    },
    onSuccess: () => {
      toast.success(activityId ? '活動を更新しました' : '活動を保存しました');
      router.push(`/tablet?date=${recordDate}`);
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!activityName.trim()) {
      toast.error('活動名を入力してください');
      return;
    }
    if (selectedStudentIds.length === 0) {
      toast.error('参加者を選択してください');
      return;
    }
    saveMutation.mutate();
  };

  const toggleStudent = (id: number) => {
    setSelectedStudentIds((prev) =>
      prev.includes(id) ? prev.filter((s) => s !== id) : [...prev, id]
    );
  };

  return (
    <div className="space-y-6">
      {/* ヘッダー */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <h1 className="text-3xl font-bold">
          {activityId ? '活動編集' : '新しい活動'}
        </h1>
        <Link
          href={`/tablet`}
          className="mt-2 inline-block text-xl text-blue-600 hover:underline"
        >
          ← 戻る
        </Link>
      </div>

      <form onSubmit={handleSubmit} className="rounded-xl bg-white p-6 shadow-md space-y-8">
        {/* 支援案選択 */}
        {supportPlans.length > 0 ? (
          <div>
            <label className="mb-3 block text-xl font-bold">支援案を選択 (任意)</label>
            <select
              value={selectedPlanId}
              onChange={(e) => handlePlanChange(e.target.value)}
              className="w-full rounded-lg border-2 border-gray-300 p-5 text-xl focus:border-blue-500 focus:outline-none"
            >
              <option value="">支援案を選択しない（手動入力）</option>
              {supportPlans.map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.activity_name}
                  {plan.staff ? ` (作成者: ${plan.staff.full_name})` : ''}
                </option>
              ))}
            </select>

            {showPlanDetails && planDetails && (
              <div className="mt-4 rounded-lg border-l-4 border-purple-500 bg-gray-50 p-5">
                <h3 className="mb-3 text-xl font-bold text-purple-700">選択した支援案の内容</h3>
                {planDetails.activity_purpose && (
                  <div className="mb-3 text-lg">
                    <strong className="text-purple-700">活動の目的:</strong><br />
                    <span className="whitespace-pre-wrap">{planDetails.activity_purpose}</span>
                  </div>
                )}
                {planDetails.activity_content && (
                  <div className="mb-3 text-lg">
                    <strong className="text-purple-700">活動の内容:</strong><br />
                    <span className="whitespace-pre-wrap">{planDetails.activity_content}</span>
                  </div>
                )}
                {planDetails.five_domains_consideration && (
                  <div className="mb-3 text-lg">
                    <strong className="text-purple-700">五領域への配慮:</strong><br />
                    <span className="whitespace-pre-wrap">{planDetails.five_domains_consideration}</span>
                  </div>
                )}
                {planDetails.other_notes && (
                  <div className="text-lg">
                    <strong className="text-purple-700">その他:</strong><br />
                    <span className="whitespace-pre-wrap">{planDetails.other_notes}</span>
                  </div>
                )}
              </div>
            )}
          </div>
        ) : (
          <div className="rounded-lg border-l-4 border-orange-400 bg-orange-50 p-5 text-lg">
            この日（{recordDate}）の支援案がまだ作成されていません。
          </div>
        )}

        {/* 活動名 */}
        <div>
          <label className="mb-3 block text-xl font-bold">活動名</label>
          <div className="flex gap-3">
            <input
              type="text"
              value={activityName}
              onChange={(e) => setActivityName(e.target.value)}
              className="flex-1 rounded-lg border-2 border-gray-300 p-5 text-xl focus:border-blue-500 focus:outline-none"
              required
            />
            <button
              type="button"
              onClick={() => startVoiceInput('activity_name', setActivityName, activityName, false)}
              className={`whitespace-nowrap rounded-lg px-6 py-3 text-xl font-bold text-white transition-all ${
                activeField === 'activity_name'
                  ? 'animate-pulse bg-red-500'
                  : 'bg-blue-600 hover:bg-blue-700'
              }`}
            >
              {activeField === 'activity_name' ? '聞いています...' : '声で入力'}
            </button>
          </div>
        </div>

        {/* 参加者選択 */}
        <div>
          <label className="mb-3 block text-xl font-bold">参加者を選択してください</label>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            {allStudents.map((student) => {
              const isSelected = selectedStudentIds.includes(student.id);
              return (
                <button
                  key={student.id}
                  type="button"
                  onClick={() => toggleStudent(student.id)}
                  className={`rounded-lg border-2 p-5 text-center text-xl transition-all ${
                    isSelected
                      ? 'border-green-500 bg-green-100 font-bold'
                      : 'border-gray-300 bg-gray-50 hover:bg-gray-100'
                  }`}
                >
                  {student.student_name}
                </button>
              );
            })}
          </div>
        </div>

        {/* ボタン */}
        <div className="flex gap-4">
          <button
            type="submit"
            disabled={saveMutation.isPending}
            className="flex-1 rounded-lg bg-green-600 py-5 text-2xl font-bold text-white hover:bg-green-700 disabled:opacity-50"
          >
            {saveMutation.isPending ? '保存中...' : '保存する'}
          </button>
          <button
            type="button"
            onClick={() => router.push('/tablet')}
            className="flex-1 rounded-lg bg-gray-500 py-5 text-2xl font-bold text-white hover:bg-gray-600"
          >
            キャンセル
          </button>
        </div>
      </form>
    </div>
  );
}
