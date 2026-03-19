'use client';

import { useState, useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery, useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { useVoiceInput } from '../useVoiceInput';
import Link from 'next/link';

interface Participant {
  id: number;
  student_name: string;
  notes: string | null;
  integrated_id: number | null;
  integrated_content: string | null;
  is_sent: boolean;
}

interface IntegrateData {
  activity: {
    id: number;
    activity_name: string;
    record_date: string;
    common_activity: string | null;
  };
  participants: Participant[];
}

export default function TabletIntegratePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const toast = useToast();
  const { activeField, startVoiceInput } = useVoiceInput();

  const activityId = searchParams.get('id');
  const recordDate = searchParams.get('date') || '';

  const [contents, setContents] = useState<Record<number, string>>({});

  // 統合データ取得
  const { data, isLoading } = useQuery({
    queryKey: ['tablet', 'integrate-data', activityId],
    queryFn: async () => {
      const res = await api.get<{ data: IntegrateData }>(
        `/api/tablet/activities/${activityId}/integrate-data`
      );
      return res.data.data;
    },
    enabled: !!activityId,
  });

  // 既存データを反映
  useEffect(() => {
    if (!data?.participants) return;
    const initial: Record<number, string> = {};
    data.participants.forEach((p) => {
      initial[p.id] = p.integrated_content || '';
    });
    setContents(initial);
  }, [data]);

  // 保存
  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = Object.entries(contents).map(([studentId, content]) => ({
        student_id: Number(studentId),
        content,
      }));
      return api.post(`/api/tablet/activities/${activityId}/integrate-save`, {
        contents: payload,
      });
    },
    onSuccess: () => {
      toast.success('統合連絡帳を保存しました');
      router.push(`/tablet`);
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  if (isLoading) {
    return <div className="py-12 text-center text-xl text-gray-400">読み込み中...</div>;
  }

  if (!data) {
    return (
      <div className="rounded-xl bg-white p-6 shadow-md">
        <p className="text-xl text-gray-500">活動が見つかりません。</p>
        <Link href="/tablet" className="mt-4 inline-block text-xl text-blue-600 hover:underline">
          ← 戻る
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* ヘッダー */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <h1 className="text-3xl font-bold">統合連絡帳作成</h1>
        <div className="mt-2 text-xl text-gray-500">
          活動: {data.activity.activity_name}<br />
          日付: {data.activity.record_date}
        </div>
        <Link href="/tablet" className="mt-2 inline-block text-xl text-blue-600 hover:underline">
          ← 戻る
        </Link>
      </div>

      {/* 各生徒のカード */}
      {data.participants.map((participant) => (
        <div key={participant.id} className="rounded-xl bg-white p-6 shadow-md space-y-4">
          <h2 className="text-2xl font-bold">{participant.student_name}</h2>

          {/* 活動記録（元の記録を表示） */}
          {participant.notes && (
            <div className="rounded-lg border-l-4 border-blue-500 bg-gray-50 p-4">
              <div className="mb-1 text-base text-gray-500">活動記録:</div>
              <div className="whitespace-pre-wrap text-lg">{participant.notes}</div>
            </div>
          )}

          {/* 統合連絡帳の内容 */}
          <div>
            <label className="mb-2 block text-xl font-bold">統合連絡帳の内容</label>
            <div className="flex flex-col gap-3">
              <textarea
                value={contents[participant.id] || ''}
                onChange={(e) =>
                  setContents((prev) => ({ ...prev, [participant.id]: e.target.value }))
                }
                className="w-full rounded-lg border-2 border-gray-300 p-4 text-xl focus:border-blue-500 focus:outline-none"
                rows={5}
                placeholder="保護者に送る内容を入力してください"
              />
              <button
                type="button"
                onClick={() =>
                  startVoiceInput(
                    `integrate_${participant.id}`,
                    (v) => setContents((prev) => ({ ...prev, [participant.id]: v })),
                    contents[participant.id] || ''
                  )
                }
                className={`self-start rounded-lg px-6 py-3 text-xl font-bold text-white ${
                  activeField === `integrate_${participant.id}`
                    ? 'animate-pulse bg-red-500'
                    : 'bg-blue-600 hover:bg-blue-700'
                }`}
              >
                {activeField === `integrate_${participant.id}`
                  ? '聞いています... (クリックで終了)'
                  : '声で入力'}
              </button>
            </div>
          </div>
        </div>
      ))}

      {/* ボタン */}
      <div className="flex gap-4">
        <button
          type="button"
          onClick={() => saveMutation.mutate()}
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
    </div>
  );
}
