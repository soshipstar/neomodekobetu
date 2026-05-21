'use client';

import { useState, useCallback } from 'react';
import api from '@/lib/api';
import Link from 'next/link';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useToast } from '@/components/ui/Toast';
import { format, subMonths } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface AbsenceNotification {
  id: number;
  reason: string | null;
  body_temperature: number | string | null;
  hospital_visit: boolean | null;
  symptom_abdominal_pain: boolean;
  symptom_headache: boolean;
  symptom_sore_throat: boolean;
  symptom_cough: boolean;
  symptom_sneeze: boolean;
  symptom_runny_nose: boolean;
  other_concerns: string | null;
  advice: string | null;
  advice_at: string | null;
  advice_author?: { id: number; full_name: string } | null;
}

interface AbsenceResponseItem {
  id: number;
  absence_date: string;
  absence_reason: string | null;
  response_content: string | null;
  contact_method: string | null;
  contact_content: string | null;
  is_sent: boolean;
  sent_at: string | null;
  guardian_confirmed: boolean;
  student: { id: number; student_name: string; grade_level: string | null } | null;
  staff: { id: number; full_name: string } | null;
  absence_notification: AbsenceNotification | null;
}

const SYMPTOM_LABELS: Array<{ key: keyof Pick<AbsenceNotification, 'symptom_abdominal_pain'|'symptom_headache'|'symptom_sore_throat'|'symptom_cough'|'symptom_sneeze'|'symptom_runny_nose'>; label: string }> = [
  { key: 'symptom_abdominal_pain', label: '腹痛' },
  { key: 'symptom_headache',       label: '頭痛' },
  { key: 'symptom_sore_throat',    label: '咽頭痛' },
  { key: 'symptom_cough',          label: '咳' },
  { key: 'symptom_sneeze',         label: 'くしゃみ' },
  { key: 'symptom_runny_nose',     label: '鼻水' },
];

export default function AbsenceResponsesPage() {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [dateFrom, setDateFrom] = useState(() => format(subMonths(new Date(), 1), 'yyyy-MM-dd'));
  const [dateTo, setDateTo] = useState(() => format(new Date(), 'yyyy-MM-dd'));
  const [showGuide, setShowGuide] = useState(true);
  const [expanded, setExpanded] = useState<Record<number, boolean>>({});
  const [adviceDrafts, setAdviceDrafts] = useState<Record<number, string>>({});

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['staff', 'absence-responses', dateFrom, dateTo],
    queryFn: async () => {
      const res = await api.get('/api/staff/absence-response/list', { params: { date_from: dateFrom, date_to: dateTo } });
      return res.data?.data?.data || [];
    },
  });

  const records: AbsenceResponseItem[] = data || [];

  // バグ報告 (淡田由貴さん): スタッフがアドバイスを入力できる UI が無かった。
  // BE 側のエンドポイント (PUT /api/staff/absence/{id}/advice) を呼び出し、
  // 保存後に保護者へ通知 + チャットへも自動投稿される。
  const adviceMutation = useMutation({
    mutationFn: ({ absenceNotificationId, advice }: { absenceNotificationId: number; advice: string }) =>
      api.put(`/api/staff/absence/${absenceNotificationId}/advice`, { advice }),
    onSuccess: () => {
      toast.success('アドバイスを保存しました。保護者に通知が送られます。');
      queryClient.invalidateQueries({ queryKey: ['staff', 'absence-responses'] });
    },
    onError: (e: { response?: { data?: { message?: string } } }) =>
      toast.error(e?.response?.data?.message || 'アドバイスの保存に失敗しました'),
  });

  const handleCsvDownload = useCallback(async () => {
    try {
      const res = await api.get('/api/staff/absence-response/csv', {
        params: { date_from: dateFrom, date_to: dateTo },
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `absence_response_${format(new Date(), 'yyyyMMdd')}.csv`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch { /* ignore */ }
  }, [dateFrom, dateTo]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">欠席時対応加算一覧</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">欠席時対応加算の記録一覧とCSVダウンロード</p>
        </div>
        <div className="flex gap-2">
          {/* バグ報告 (淡田由貴さん): 「様式はどこから入力しますか？」
              対応内容の新規入力は /staff/unsent-records にあるため、ここから直接遷移できる
              プライマリボタンを目立つ場所に配置する。 */}
          <Link href="/staff/unsent-records">
            <Button variant="primary" size="sm" leftIcon={<MaterialIcon name="add" size={16} />}>
              対応内容を新規入力する
            </Button>
          </Link>
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="download" size={16} />} onClick={handleCsvDownload}>
            CSVダウンロード
          </Button>
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="refresh" size={16} />} onClick={() => refetch()} isLoading={isLoading}>
            更新
          </Button>
        </div>
      </div>

      {/* ────────────────────────────────────────────────────────────────
          使い方ガイド (バグ報告 #淡田由貴さん: 様式の入力導線が分からなかった)
          開閉式。閉じても次回は開いた状態で戻る (要望時に詳細を確認しやすく)。
      ──────────────────────────────────────────────────────────────── */}
      {showGuide && (
        <Card>
          <CardBody>
            <div className="mb-2 flex items-center justify-between gap-2">
              <h2 className="flex items-center gap-2 text-sm font-bold text-[var(--brand-80)]">
                <MaterialIcon name="info" size={18} />
                欠席時対応加算 — 使い方ガイド
              </h2>
              <button
                type="button"
                onClick={() => setShowGuide(false)}
                className="rounded p-1 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)]"
                aria-label="ガイドを閉じる"
              >
                <MaterialIcon name="close" size={16} />
              </button>
            </div>
            {/* 入力箇所別の動線を明示 (淡田由貴さん要望)
                「様式はどこから入力しますか？」への直接回答として、
                3 つの記入箇所と その入口 を表で並べる */}
            <div className="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
              <Link
                href="/staff/unsent-records"
                className="flex flex-col gap-1 rounded-md border-2 border-[var(--brand-80)] bg-white p-3 hover:bg-[var(--brand-160)]"
              >
                <div className="flex items-center gap-1 text-xs font-bold text-[var(--brand-80)]">
                  <MaterialIcon name="edit_note" size={14} />
                  ① 対応内容を新規入力 (スタッフ)
                </div>
                <p className="text-[11px] text-[var(--neutral-foreground-2)]">
                  欠席の子どもに対し電話・メール等で対応した内容 (= 加算の主な様式) を入力します。
                </p>
                <p className="text-[10px] font-semibold text-[var(--brand-80)] underline">
                  → 未送信日誌一覧へ
                </p>
              </Link>
              <div className="flex flex-col gap-1 rounded-md border border-[var(--neutral-stroke-2)] bg-white p-3">
                <div className="flex items-center gap-1 text-xs font-bold text-[var(--neutral-foreground-1)]">
                  <MaterialIcon name="psychology" size={14} className="text-[var(--brand-80)]" />
                  ② アドバイスを記入 (スタッフ)
                </div>
                <p className="text-[11px] text-[var(--neutral-foreground-2)]">
                  保護者の体調情報を踏まえた助言を本画面の各カード「詳細を開く」→ 末尾のフォームから入力。保存で保護者通知 + チャット投稿。
                </p>
                <p className="text-[10px] text-[var(--neutral-foreground-3)]">
                  → ↓ 下の一覧の「詳細を開く」
                </p>
              </div>
              <div className="flex flex-col gap-1 rounded-md border border-[var(--neutral-stroke-2)] bg-white p-3">
                <div className="flex items-center gap-1 text-xs font-bold text-[var(--neutral-foreground-1)]">
                  <MaterialIcon name="thermostat" size={14} className="text-[var(--brand-80)]" />
                  ③ 体温・症状を入力 (保護者)
                </div>
                <p className="text-[11px] text-[var(--neutral-foreground-2)]">
                  保護者がスマートフォンで体温・通院の有無・症状 6 種・その他困りごとを入力。
                </p>
                <p className="text-[10px] text-[var(--neutral-foreground-3)]">
                  → 保護者画面「欠席連絡」ボタン
                </p>
              </div>
            </div>

            <ol className="ml-5 list-decimal space-y-1.5 text-xs text-[var(--neutral-foreground-2)]">
              <li>
                <strong>① 様式の新規作成</strong>: <Link href="/staff/unsent-records" className="font-semibold text-[var(--brand-80)] underline">「未送信日誌一覧」</Link> に欠席児童の一覧があり、各カードの「欠席時対応を入力」から
                電話・メール等で行った対応内容を記入します。これが加算請求の本体の様式になります。
              </li>
              <li>
                <strong>② アドバイスの記入</strong>: 本画面の一覧の各カード「詳細を開く」を押すと、保護者から届いた体調情報 (体温・症状) と
                スタッフのアドバイス入力欄が表示されます。保存すると<strong className="text-[var(--brand-80)]"> 保護者の連絡帳通知 + チャットルームに自動投稿</strong>されます。
              </li>
              <li>
                <strong>③ 保護者の体調情報</strong>: 保護者は <code className="rounded bg-[var(--neutral-background-3)] px-1.5 py-0.5">/guardian/absence</code>{' '}
                (ダッシュボード「欠席連絡」ボタン) から、体温・通院の有無・症状 (腹痛/頭痛/咽頭痛/咳/くしゃみ/鼻水)・その他困っていることを入力できます。
              </li>
              <li>
                <strong>④ 加算記録 → CSV</strong>: 月次の請求根拠資料として、右上「CSVダウンロード」で対応記録一覧を出力できます。
              </li>
            </ol>
            <p className="mt-2 text-[10px] text-[var(--neutral-foreground-4)]">
              ※ このガイドは右上の × で閉じられます。再表示はページを開き直してください。
            </p>
          </CardBody>
        </Card>
      )}

      {/* Date filter */}
      <Card><CardBody>
        <div className="flex flex-wrap items-end gap-3">
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">開始日</label>
            <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm" />
          </div>
          <div>
            <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">終了日</label>
            <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm" />
          </div>
          <Button variant="primary" size="sm" onClick={() => refetch()}>検索</Button>
        </div>
      </CardBody></Card>

      {/* Summary */}
      <div className="grid grid-cols-3 gap-3">
        <Card><CardBody><div className="text-center">
          <p className="text-2xl font-bold text-[var(--brand-80)]">{records.length}</p>
          <p className="text-xs text-[var(--neutral-foreground-3)]">合計</p>
        </div></CardBody></Card>
        <Card><CardBody><div className="text-center">
          <p className="text-2xl font-bold text-[var(--status-success-fg)]">{records.filter((r) => r.is_sent).length}</p>
          <p className="text-xs text-[var(--neutral-foreground-3)]">送信済</p>
        </div></CardBody></Card>
        <Card><CardBody><div className="text-center">
          <p className="text-2xl font-bold text-[var(--status-warning-fg)]">{records.filter((r) => !r.is_sent).length}</p>
          <p className="text-xs text-[var(--neutral-foreground-3)]">未送信</p>
        </div></CardBody></Card>
      </div>

      {/* Records list */}
      {isLoading ? <SkeletonList items={5} /> : records.length > 0 ? (
        <div className="space-y-3">
          {records.map((record) => {
            const an = record.absence_notification;
            const isExpanded = !!expanded[record.id];
            const hasHealthInfo = an && (
              an.body_temperature != null ||
              an.hospital_visit ||
              SYMPTOM_LABELS.some((s) => an[s.key]) ||
              an.other_concerns
            );
            return (
            <Card key={record.id} className="transition-shadow hover:shadow-[var(--shadow-8)]">
              <CardBody>
                <div className="flex items-start justify-between gap-2 mb-2">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">{record.student?.student_name || '不明'}</span>
                    {record.student?.grade_level && <span className="text-xs text-[var(--neutral-foreground-3)]">{record.student.grade_level}</span>}
                    <Badge variant="info">{format(new Date(record.absence_date), 'M月d日(E)', { locale: ja })}</Badge>
                    {record.is_sent ? <Badge variant="success">送信済</Badge> : <Badge variant="warning">未送信</Badge>}
                    {hasHealthInfo && <Badge variant="info">体調情報あり</Badge>}
                    {an?.advice && <Badge variant="success">アドバイス記入済</Badge>}
                  </div>
                  <button
                    type="button"
                    onClick={() => setExpanded((p) => ({ ...p, [record.id]: !p[record.id] }))}
                    className="flex shrink-0 items-center gap-1 rounded border border-[var(--neutral-stroke-2)] bg-white px-2 py-1 text-xs text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]"
                  >
                    <MaterialIcon name={isExpanded ? 'expand_less' : 'expand_more'} size={14} />
                    {isExpanded ? '閉じる' : '詳細を開く'}
                  </button>
                </div>

                {record.absence_reason && (
                  <div className="text-xs text-[var(--neutral-foreground-3)] mb-1">欠席理由: {record.absence_reason}</div>
                )}

                <div className="rounded-md bg-[var(--neutral-background-3)] p-3 text-xs text-[var(--neutral-foreground-2)]">
                  <div className="flex items-center gap-2 mb-1 text-[var(--neutral-foreground-3)]">
                    <MaterialIcon name="description" size={14} />
                    <span>対応内容</span>
                    {record.contact_method && <span>({record.contact_method})</span>}
                    {record.staff && <span className="ml-auto">{record.staff.full_name}</span>}
                  </div>
                  <p className="whitespace-pre-wrap">{record.response_content || ''}</p>
                  {record.contact_content && (
                    <p className="mt-1 text-[var(--neutral-foreground-3)]">連絡内容: {record.contact_content}</p>
                  )}
                  {record.sent_at && (
                    <p className="mt-1 text-[var(--neutral-foreground-4)]">送信日時: {format(new Date(record.sent_at), 'M/d HH:mm')}</p>
                  )}
                </div>

                {/* ──────────────────────────────────────────────────
                    展開時: 保護者の体調情報 + スタッフのアドバイス入力
                ────────────────────────────────────────────────── */}
                {isExpanded && an && (
                  <div className="mt-3 space-y-3 rounded-md border border-[var(--neutral-stroke-2)] bg-white p-3">
                    {/* 保護者の体調情報 */}
                    <div>
                      <h4 className="mb-1.5 flex items-center gap-1 text-xs font-semibold text-[var(--brand-80)]">
                        <MaterialIcon name="thermostat" size={14} />
                        保護者から受け取った体調情報
                      </h4>
                      {hasHealthInfo ? (
                        <dl className="grid grid-cols-1 gap-1.5 text-xs sm:grid-cols-2">
                          {an.body_temperature != null && (
                            <div className="flex items-center gap-1">
                              <dt className="text-[var(--neutral-foreground-3)]">体温:</dt>
                              <dd className="font-semibold text-[var(--neutral-foreground-1)]">{an.body_temperature} ℃</dd>
                            </div>
                          )}
                          <div className="flex items-center gap-1">
                            <dt className="text-[var(--neutral-foreground-3)]">通院:</dt>
                            <dd className="font-semibold text-[var(--neutral-foreground-1)]">{an.hospital_visit ? 'あり' : 'なし'}</dd>
                          </div>
                          <div className="sm:col-span-2">
                            <dt className="text-[var(--neutral-foreground-3)] mb-0.5">症状:</dt>
                            <dd className="flex flex-wrap gap-1">
                              {SYMPTOM_LABELS.filter((s) => an[s.key]).length === 0 ? (
                                <span className="text-[var(--neutral-foreground-4)]">記入なし</span>
                              ) : (
                                SYMPTOM_LABELS.filter((s) => an[s.key]).map((s) => (
                                  <span key={s.key} className="rounded-full bg-[var(--status-warning-bg)] px-2 py-0.5 text-[10px] text-[var(--status-warning-fg)]">
                                    {s.label}
                                  </span>
                                ))
                              )}
                            </dd>
                          </div>
                          {an.other_concerns && (
                            <div className="sm:col-span-2">
                              <dt className="text-[var(--neutral-foreground-3)] mb-0.5">その他困っていること:</dt>
                              <dd className="whitespace-pre-wrap rounded bg-[var(--neutral-background-3)] p-2 text-[var(--neutral-foreground-1)]">{an.other_concerns}</dd>
                            </div>
                          )}
                        </dl>
                      ) : (
                        <p className="text-xs text-[var(--neutral-foreground-4)]">
                          保護者からの体調情報はまだありません。<br />
                          保護者は『欠席連絡』画面から体温・症状などを入力できます。
                        </p>
                      )}
                    </div>

                    {/* スタッフのアドバイス入力 */}
                    <div className="border-t border-[var(--neutral-stroke-2)] pt-3">
                      <h4 className="mb-1.5 flex items-center gap-1 text-xs font-semibold text-[var(--brand-80)]">
                        <MaterialIcon name="psychology" size={14} />
                        スタッフからのアドバイス
                      </h4>
                      {an.advice && (
                        <div className="mb-2 rounded border border-[var(--status-success-fg)]/30 bg-[var(--status-success-bg)] p-2 text-xs">
                          <p className="whitespace-pre-wrap text-[var(--neutral-foreground-1)]">{an.advice}</p>
                          {(an.advice_author || an.advice_at) && (
                            <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">
                              {an.advice_author?.full_name ?? ''}
                              {an.advice_at ? ` (${format(new Date(an.advice_at), 'M/d HH:mm')})` : ''}
                            </p>
                          )}
                        </div>
                      )}
                      <textarea
                        value={adviceDrafts[record.id] ?? an.advice ?? ''}
                        onChange={(e) => setAdviceDrafts((p) => ({ ...p, [record.id]: e.target.value }))}
                        className="block w-full resize-none rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1.5 text-xs text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
                        rows={3}
                        placeholder="体温や症状を踏まえたアドバイスを記入してください (保護者に通知 + チャット投稿されます)"
                      />
                      <div className="mt-2 flex justify-end">
                        <Button
                          variant="primary"
                          size="sm"
                          leftIcon={<MaterialIcon name="send" size={14} />}
                          isLoading={adviceMutation.isPending && adviceMutation.variables?.absenceNotificationId === an.id}
                          onClick={() => {
                            const text = (adviceDrafts[record.id] ?? an.advice ?? '').trim();
                            if (!text) {
                              toast.error('アドバイスを入力してください');
                              return;
                            }
                            adviceMutation.mutate({ absenceNotificationId: an.id, advice: text });
                          }}
                        >
                          {an.advice ? 'アドバイスを更新' : 'アドバイスを保存して保護者に通知'}
                        </Button>
                      </div>
                    </div>
                  </div>
                )}
                {isExpanded && !an && (
                  <div className="mt-3 rounded-md border border-dashed border-[var(--neutral-stroke-2)] bg-white p-3 text-xs text-[var(--neutral-foreground-4)]">
                    この対応記録には保護者からの欠席連絡 (体調情報) が紐づいていません。
                    口頭連絡など、保護者が画面から欠席連絡を送らずに対応した場合に該当します。
                  </div>
                )}
              </CardBody>
            </Card>
            );
          })}
        </div>
      ) : (
        <Card><CardBody>
          <div className="py-10 text-center">
            <MaterialIcon name="check_circle" size={40} className="mx-auto mb-3 text-[var(--status-success-fg)]" />
            <p className="text-sm font-medium text-[var(--neutral-foreground-3)]">該当期間の欠席時対応加算記録はありません</p>
          </div>
        </CardBody></Card>
      )}
    </div>
  );
}
