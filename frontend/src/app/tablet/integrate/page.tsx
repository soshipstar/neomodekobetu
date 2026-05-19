'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { useVoiceInput } from '../useVoiceInput';
import Link from 'next/link';

/**
 * タブレット用 統合連絡帳画面。
 *
 * バグ報告 (タブレットユーザ):
 *   - 「タブレットユーザーで保護者への送信済み連絡帳の内容が確認できない」 → 送信済を画面に明示
 *   - 「タブレットユーザーにAI生成ボタンがない？連絡帳の生成、送信までの機能を実装」
 *
 * 実装:
 *   - 各生徒カードに「AIで作成」(個別生成) ボタンを追加 (BE: Staff\RenrakuchoController::generateIntegrated)
 *   - 既に送信済みの内容は読み取り専用 + 送信済バッジ + 送信日時表示
 *   - 下部に「保護者全員に送信」ボタンを追加 (BE: sendToGuardians)
 *   - 既存「保存する」(下書き保存) は残す
 */

interface Participant {
  id: number;                // = student_id
  student_name: string;
  notes: string | null;       // 元の活動記録
  integrated_id: number | null;
  integrated_content: string | null;
  is_sent: boolean;
  sent_at?: string | null;    // 拡張: BE が返してくれれば表示
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

export default function TabletIntegratePageWrapper() {
  return <Suspense fallback={<div className="p-8 text-center">読み込み中...</div>}><TabletIntegratePage /></Suspense>;
}

function TabletIntegratePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const toast = useToast();
  const queryClient = useQueryClient();
  const { activeField, startVoiceInput } = useVoiceInput();

  const activityId = searchParams.get('id');

  const [contents, setContents] = useState<Record<number, string>>({});
  // 個別 AI 生成中の student_id (UI の loading 表示用)
  const [generatingId, setGeneratingId] = useState<number | null>(null);

  // 統合データ取得
  const { data, isLoading } = useQuery({
    queryKey: ['tablet', 'integrate-data', activityId],
    queryFn: async () => {
      const res = await api.get<{ data: IntegrateData }>(
        `/api/tablet/activities/${activityId}/integrate-data`,
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

  // 下書き保存 (既存)
  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = Object.entries(contents).map(([studentId, content]) => ({
        student_id: Number(studentId),
        content,
      }));
      return api.post(`/api/tablet/activities/${activityId}/integrate-save`, { contents: payload });
    },
    onSuccess: () => {
      toast.success('下書きを保存しました');
      queryClient.invalidateQueries({ queryKey: ['tablet', 'integrate-data', activityId] });
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  // AI 生成 (新規)
  // BE: POST /api/tablet/activities/{record}/generate-integrated { student_id }
  // レスポンス: { data: { content: string } } (Staff\RenrakuchoController::generateIntegrated)
  const generateMutation = useMutation({
    mutationFn: async (studentId: number) => {
      const res = await api.post<{ data: { content: string } }>(
        `/api/tablet/activities/${activityId}/generate-integrated`,
        { student_id: studentId },
      );
      return { studentId, content: res.data.data?.content || '' };
    },
    onMutate: (sid) => setGeneratingId(sid),
    onSuccess: ({ studentId, content }) => {
      setContents((p) => ({ ...p, [studentId]: content }));
      toast.success('AIで連絡帳を作成しました');
    },
    onError: (e: { response?: { data?: { message?: string } } }) =>
      toast.error(e?.response?.data?.message || 'AI生成に失敗しました'),
    onSettled: () => setGeneratingId(null),
  });

  // 保護者送信 (新規)
  // BE: POST /api/tablet/activities/{record}/send-to-guardians { notes: [...] }
  const sendMutation = useMutation({
    mutationFn: async () => {
      // is_sent 済みは送らない (二重送信防止)。FE 側でも除外。
      const sentIds = new Set((data?.participants || []).filter((p) => p.is_sent).map((p) => p.id));
      const notes = Object.entries(contents)
        .filter(([sid, content]) => !sentIds.has(Number(sid)) && (content || '').trim().length > 0)
        .map(([sid, content]) => ({ student_id: Number(sid), content }));
      if (notes.length === 0) {
        throw new Error('送信できる内容がありません。各生徒の内容を入力するか、AIで作成してください。');
      }
      return api.post(`/api/tablet/activities/${activityId}/send-to-guardians`, { notes });
    },
    onSuccess: (res) => {
      const msg = (res.data as { message?: string })?.message;
      toast.success(msg || '保護者に送信しました');
      queryClient.invalidateQueries({ queryKey: ['tablet', 'integrate-data', activityId] });
    },
    onError: (e: { response?: { data?: { message?: string } }; message?: string }) =>
      toast.error(e?.response?.data?.message || e?.message || '送信に失敗しました'),
  });

  if (isLoading) {
    return <div className="py-12 text-center text-xl text-[var(--neutral-foreground-4)]">読み込み中...</div>;
  }

  if (!data) {
    return (
      <div className="rounded-xl bg-white p-6 shadow-md">
        <p className="text-xl text-[var(--neutral-foreground-3)]">活動が見つかりません。</p>
        <Link href="/tablet" className="mt-4 inline-block text-xl text-[var(--brand-80)] hover:underline">
          ← 戻る
        </Link>
      </div>
    );
  }

  const unsentCount = data.participants.filter((p) => !p.is_sent && (contents[p.id] || '').trim().length > 0).length;
  const allSent = data.participants.length > 0 && data.participants.every((p) => p.is_sent);

  return (
    <div className="space-y-6">
      {/* ヘッダー */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <h1 className="text-3xl font-bold">統合連絡帳作成</h1>
        <div className="mt-2 text-xl text-[var(--neutral-foreground-3)]">
          活動: {data.activity.activity_name}<br />
          日付: {data.activity.record_date}
        </div>
        <Link href="/tablet" className="mt-2 inline-block text-xl text-[var(--brand-80)] hover:underline">
          ← 戻る
        </Link>
      </div>

      {/* 使い方ガイド */}
      <div className="rounded-xl border-2 border-blue-200 bg-blue-50 p-4 text-base sm:p-5">
        <p className="mb-2 flex items-center gap-2 text-lg font-bold text-blue-800">
          📘 使い方
        </p>
        <ol className="ml-5 list-decimal space-y-1 text-blue-900">
          <li><strong>「AIで作成」</strong>を押すと活動記録から保護者向け文章を AI が下書きします。</li>
          <li>必要に応じて手書き / 声入力で修正してください。</li>
          <li>下部の<strong>「保護者全員に送信」</strong>で一括送信。送信済みは読み取り専用になります。</li>
          <li>続きを書きたい場合は「下書き保存」してから後で再開できます。</li>
        </ol>
      </div>

      {/* 各生徒のカード */}
      {data.participants.map((participant) => {
        const isSent = participant.is_sent;
        const value = contents[participant.id] || '';
        return (
          <div
            key={participant.id}
            className={`rounded-xl bg-white p-6 shadow-md space-y-4 ${isSent ? 'border-2 border-green-300' : ''}`}
          >
            <div className="flex items-center justify-between flex-wrap gap-2">
              <h2 className="text-2xl font-bold">{participant.student_name}</h2>
              {isSent && (
                <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-base font-bold text-green-700">
                  ✓ 送信済み
                </span>
              )}
            </div>

            {/* 活動記録（元の記録を表示） */}
            {participant.notes && (
              <div className="rounded-lg border-l-4 border-blue-500 bg-[var(--neutral-background-3)] p-4">
                <div className="mb-1 text-base text-[var(--neutral-foreground-3)]">活動記録:</div>
                <div className="whitespace-pre-wrap text-lg">{participant.notes}</div>
              </div>
            )}

            {/* 統合連絡帳の内容 */}
            <div>
              <label className="mb-2 block text-xl font-bold">
                {isSent ? '送信した連絡帳の内容 (読み取り専用)' : '統合連絡帳の内容'}
              </label>
              <div className="flex flex-col gap-3">
                <textarea
                  value={value}
                  onChange={(e) => setContents((prev) => ({ ...prev, [participant.id]: e.target.value }))}
                  readOnly={isSent}
                  className={`w-full rounded-lg border-2 p-4 text-xl focus:outline-none ${
                    isSent
                      ? 'border-green-300 bg-green-50/50 cursor-not-allowed'
                      : 'border-[var(--neutral-stroke-1)] focus:border-[var(--brand-80)]'
                  }`}
                  rows={5}
                  placeholder={isSent ? '' : '保護者に送る内容を入力してください'}
                />
                {!isSent && (
                  <div className="flex flex-wrap gap-3">
                    <button
                      type="button"
                      onClick={() => generateMutation.mutate(participant.id)}
                      disabled={generatingId === participant.id}
                      className="rounded-lg bg-purple-600 px-5 py-3 text-xl font-bold text-white hover:bg-purple-700 disabled:opacity-50"
                    >
                      {generatingId === participant.id ? 'AI 作成中…' : '🤖 AIで作成'}
                    </button>
                    <button
                      type="button"
                      onClick={() =>
                        startVoiceInput(
                          `integrate_${participant.id}`,
                          (v) => setContents((prev) => ({ ...prev, [participant.id]: v })),
                          value,
                        )
                      }
                      className={`rounded-lg px-6 py-3 text-xl font-bold text-white ${
                        activeField === `integrate_${participant.id}`
                          ? 'animate-pulse bg-red-500'
                          : 'bg-[var(--brand-80)] hover:bg-blue-700'
                      }`}
                    >
                      {activeField === `integrate_${participant.id}`
                        ? '聞いています... (クリックで終了)'
                        : '🎤 声で入力'}
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>
        );
      })}

      {/* 下部アクション */}
      <div className="sticky bottom-0 -mx-4 border-t-2 border-[var(--neutral-stroke-2)] bg-white px-4 py-3 shadow-[0_-2px_8px_rgba(0,0,0,0.08)]">
        <div className="mb-2 text-center text-base text-[var(--neutral-foreground-3)]">
          {allSent ? (
            <span className="font-bold text-green-700">全員に送信済みです</span>
          ) : (
            <>未送信: <strong>{unsentCount}名</strong> / {data.participants.length}名</>
          )}
        </div>
        <div className="flex flex-col gap-3 sm:flex-row">
          <button
            type="button"
            onClick={() => saveMutation.mutate()}
            disabled={saveMutation.isPending}
            className="flex-1 rounded-lg bg-amber-500 py-4 text-2xl font-bold text-white hover:bg-amber-600 disabled:opacity-50"
          >
            {saveMutation.isPending ? '保存中...' : '💾 下書き保存'}
          </button>
          <button
            type="button"
            onClick={() => {
              if (unsentCount === 0) {
                toast.error('送信できる内容がありません');
                return;
              }
              if (confirm(`${unsentCount}名の保護者に連絡帳を送信します。よろしいですか？\n(送信後は読み取り専用になります)`)) {
                sendMutation.mutate();
              }
            }}
            disabled={sendMutation.isPending || unsentCount === 0}
            className="flex-1 rounded-lg bg-green-600 py-4 text-2xl font-bold text-white hover:bg-green-700 disabled:opacity-50"
          >
            {sendMutation.isPending ? '送信中…' : `📤 保護者全員に送信 (${unsentCount}名)`}
          </button>
          <button
            type="button"
            onClick={() => router.push('/tablet')}
            className="rounded-lg bg-[var(--neutral-background-4)] px-6 py-4 text-xl font-bold text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-5)]"
          >
            戻る
          </button>
        </div>
      </div>
    </div>
  );
}
