'use client';

/**
 * /staff/free-school-reports
 *
 * フリースクール用報告書 (学校提出用) を管理する画面。
 *
 * タブ構成:
 *  1. 利用者管理   - 児童を「フリースクール利用者」として登録 / 解除
 *  2. 報告書       - 利用者を選び、その活動日 (=出席日) から日を選んで
 *                     AI 生成 → 編集 → DB 保存 → PDF 出力
 *  3. 一括印刷     - 利用者 1 名 + 期間を指定し、表紙 (児童名・期間・発行事業所)
 *                     + 期間内の全報告書を 1 つの PDF にまとめてダウンロード
 */

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

const GRADE_LABELS: Record<string, string> = {
  preschool: '未就学',
  elementary_1: '小1', elementary_2: '小2', elementary_3: '小3',
  elementary_4: '小4', elementary_5: '小5', elementary_6: '小6',
  junior_high_1: '中1', junior_high_2: '中2', junior_high_3: '中3',
  high_school_1: '高1', high_school_2: '高2', high_school_3: '高3',
  elementary: '小学生', junior_high: '中学生', high_school: '高校生',
};

interface FreeSchoolUser {
  id: number;
  classroom_id: number;
  student_id: number;
  registered_at: string | null;
  notes: string | null;
  is_active: boolean;
  student?: { id: number; student_name: string; grade_level?: string; status?: string };
}

interface AttendanceItem {
  daily_record_id: number;
  record_date: string;
  activity_name: string;
  staff_id: number | null;
  has_report: boolean;
  report_id: number | null;
  report_status: string | null;
}

interface FreeSchoolReport {
  id: number;
  classroom_id: number;
  free_school_user_id: number;
  student_id: number;
  daily_record_id: number | null;
  report_date: string;
  title: string | null;
  activity_summary: string | null;
  support_consideration: string | null;
  child_observation: string | null;
  evaluation_and_next: string | null;
  generated_at: string | null;
  generated_by_ai: boolean;
  edited_at: string | null;
  status: string;
  student?: { id: number; student_name: string; grade_level?: string };
}

interface StudentOption {
  id: number;
  student_name: string;
  grade_level?: string;
}

type Tab = 'users' | 'reports' | 'batch';

export default function FreeSchoolReportsPage() {
  const [tab, setTab] = useState<Tab>('reports');

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">フリースクール用報告書</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          フリースクール利用者として登録した児童の活動日ごとに、学校提出用の報告書を作成・編集・PDF 出力できます。
        </p>
      </div>

      <div className="flex flex-wrap gap-2 border-b border-[var(--neutral-stroke-2)] pb-2">
        <TabButton active={tab === 'reports'} onClick={() => setTab('reports')} icon="description">報告書</TabButton>
        <TabButton active={tab === 'users'} onClick={() => setTab('users')} icon="group">利用者管理</TabButton>
        <TabButton active={tab === 'batch'} onClick={() => setTab('batch')} icon="print">期間一括印刷</TabButton>
      </div>

      {tab === 'users' && <UsersTab />}
      {tab === 'reports' && <ReportsTab />}
      {tab === 'batch' && <BatchTab />}
    </div>
  );
}

function TabButton({ active, onClick, icon, children }: { active: boolean; onClick: () => void; icon: string; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
        active
          ? 'bg-[var(--brand-80)] text-white'
          : 'text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]'
      }`}
    >
      <MaterialIcon name={icon} size={16} />
      {children}
    </button>
  );
}

// ===========================================================================
// 1) 利用者管理タブ
// ===========================================================================

function UsersTab() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [showAdd, setShowAdd] = useState(false);
  const [studentId, setStudentId] = useState<string>('');
  const [notes, setNotes] = useState('');

  const { data: users = [], isLoading } = useQuery<FreeSchoolUser[]>({
    queryKey: ['staff', 'free-school', 'users'],
    queryFn: async () => {
      const res = await api.get('/api/staff/free-school/users');
      return res.data.data || [];
    },
  });

  const { data: students = [] } = useQuery<StudentOption[]>({
    queryKey: ['staff', 'students-options-for-fs'],
    queryFn: async () => {
      const res = await api.get('/api/staff/students', { params: { per_page: 500 } });
      const list = res.data?.data ?? [];
      return Array.isArray(list) ? list : (Array.isArray(list.data) ? list.data : []);
    },
  });

  const addMutation = useMutation({
    mutationFn: async () => {
      return api.post('/api/staff/free-school/users', {
        student_id: Number(studentId),
        notes: notes || undefined,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'free-school', 'users'] });
      toast.success('フリースクール利用者を登録しました');
      setShowAdd(false);
      setStudentId('');
      setNotes('');
    },
    onError: (err: unknown) => toast.error(formatApiError(err, '登録に失敗しました')),
  });

  const removeMutation = useMutation({
    mutationFn: async (id: number) => api.delete(`/api/staff/free-school/users/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'free-school', 'users'] });
      toast.success('利用者登録を解除しました');
    },
    onError: (err: unknown) => toast.error(formatApiError(err, '削除に失敗しました')),
  });

  const activeStudentIds = new Set(users.filter((u) => u.is_active).map((u) => u.student_id));
  const studentOptions = students.filter((s) => !activeStudentIds.has(s.id));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold text-[var(--neutral-foreground-2)]">登録済み利用者</h2>
        <Button leftIcon={<MaterialIcon name="person_add" size={16} />} onClick={() => setShowAdd(true)}>
          利用者を追加
        </Button>
      </div>

      {isLoading ? (
        <Skeleton className="h-24 w-full rounded-lg" />
      ) : users.length === 0 ? (
        <Card>
          <CardBody>
            <p className="py-6 text-center text-sm text-[var(--neutral-foreground-3)]">
              まだフリースクール利用者は登録されていません。「利用者を追加」から児童を選択してください。
            </p>
          </CardBody>
        </Card>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {users.map((u) => (
            <Card key={u.id}>
              <CardBody>
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <div className="font-semibold text-[var(--neutral-foreground-1)]">
                      {u.student?.student_name ?? `ID:${u.student_id}`}
                    </div>
                    <div className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                      {GRADE_LABELS[u.student?.grade_level ?? ''] ?? u.student?.grade_level ?? '-'}
                      {' / '}
                      登録: {u.registered_at?.slice(0, 10) ?? '-'}
                    </div>
                  </div>
                  <Badge variant={u.is_active ? 'success' : 'default'}>
                    {u.is_active ? '有効' : '解除済'}
                  </Badge>
                </div>
                {u.notes && (
                  <p className="mt-2 text-xs text-[var(--neutral-foreground-3)] whitespace-pre-wrap">{u.notes}</p>
                )}
                {u.is_active && (
                  <div className="mt-3 flex justify-end">
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => {
                        if (confirm(`${u.student?.student_name ?? ''} の利用者登録を解除しますか？\n(過去の報告書は残ります)`)) {
                          removeMutation.mutate(u.id);
                        }
                      }}
                      leftIcon={<MaterialIcon name="person_remove" size={14} />}
                    >
                      登録解除
                    </Button>
                  </div>
                )}
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      <Modal isOpen={showAdd} onClose={() => setShowAdd(false)} title="フリースクール利用者を追加">
        <div className="space-y-3">
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">児童を選択</label>
            <select
              value={studentId}
              onChange={(e) => setStudentId(e.target.value)}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            >
              <option value="">-- 児童を選んでください --</option>
              {studentOptions.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.student_name} ({GRADE_LABELS[s.grade_level ?? ''] ?? s.grade_level ?? '-'})
                </option>
              ))}
            </select>
            {studentOptions.length === 0 && (
              <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
                未登録の児童がいません (すでに全員登録済み、または児童データがありません)。
              </p>
            )}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">備考 (任意)</label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={2}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              placeholder="例: 在籍校 / 連携学校名など"
            />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setShowAdd(false)}>キャンセル</Button>
            <Button
              onClick={() => addMutation.mutate()}
              isLoading={addMutation.isPending}
              disabled={!studentId}
            >
              登録
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

// ===========================================================================
// 2) 報告書タブ
// ===========================================================================

// PDF を認証付きで取得して別タブで開く / ダウンロードする共通ヘルパー
// (Sanctum の Bearer トークンが必要なため、単純な <a target=_blank> では認証が
//  通らず 401 になる。axios で blob を取って blob URL を作って開く。)
async function fetchAndOpenPdf(url: string, filename: string): Promise<void> {
  const res = await api.get(url, { responseType: 'blob' });
  const objectUrl = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
  // 新規タブで開く + ダウンロード可能なリンクを生成
  const a = document.createElement('a');
  a.href = objectUrl;
  a.target = '_blank';
  a.rel = 'noopener noreferrer';
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => window.URL.revokeObjectURL(objectUrl), 60_000);
}

function ReportsTab() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
  const [editingReport, setEditingReport] = useState<FreeSchoolReport | null>(null);

  const { data: users = [] } = useQuery<FreeSchoolUser[]>({
    queryKey: ['staff', 'free-school', 'users'],
    queryFn: async () => {
      const res = await api.get('/api/staff/free-school/users');
      return res.data.data || [];
    },
  });

  const activeUsers = users.filter((u) => u.is_active);

  const { data: attendance = [], isLoading: attLoading } = useQuery<AttendanceItem[]>({
    queryKey: ['staff', 'free-school', 'attendance', selectedUserId],
    queryFn: async () => {
      if (!selectedUserId) return [];
      const res = await api.get(`/api/staff/free-school/users/${selectedUserId}/attendance`);
      return res.data.data || [];
    },
    enabled: !!selectedUserId,
  });

  const generateMutation = useMutation({
    mutationFn: async ({ dailyRecordId, overwrite }: { dailyRecordId: number; overwrite?: boolean }) => {
      const res = await api.post('/api/staff/free-school/reports/generate', {
        free_school_user_id: selectedUserId,
        daily_record_id: dailyRecordId,
        overwrite: !!overwrite,
      });
      return res.data.data as FreeSchoolReport;
    },
    onSuccess: (report) => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'free-school'] });
      toast.success('AI で報告書を生成しました');
      setEditingReport(report);
    },
    onError: (err: unknown) => {
      const responseErr = err as { response?: { status?: number; data?: { data?: FreeSchoolReport } } };
      if (responseErr?.response?.status === 409) {
        // 既存あり: 上書きの確認
        if (confirm('この日の報告書は既に存在します。AI で再生成して上書きしますか？')) {
          const last = (responseErr.response?.data?.data as FreeSchoolReport);
          if (last) {
            generateMutation.mutate({ dailyRecordId: last.daily_record_id ?? 0, overwrite: true });
          }
        }
        return;
      }
      toast.error(formatApiError(err, 'AI 生成に失敗しました'));
    },
  });

  const openReport = async (reportId: number) => {
    const res = await api.get(`/api/staff/free-school/reports/${reportId}`);
    setEditingReport(res.data.data);
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardBody>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">
            フリースクール利用者を選択
          </label>
          <select
            value={selectedUserId ?? ''}
            onChange={(e) => setSelectedUserId(e.target.value ? Number(e.target.value) : null)}
            className="block w-full max-w-md rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
          >
            <option value="">-- 児童を選んでください --</option>
            {activeUsers.map((u) => (
              <option key={u.id} value={u.id}>
                {u.student?.student_name ?? `ID:${u.student_id}`} ({GRADE_LABELS[u.student?.grade_level ?? ''] ?? '-'})
              </option>
            ))}
          </select>
          {activeUsers.length === 0 && (
            <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
              「利用者管理」タブから児童をフリースクール利用者として登録してください。
            </p>
          )}
        </CardBody>
      </Card>

      {selectedUserId && (
        <Card>
          <CardHeader>
            <CardTitle>活動日一覧 (出席日)</CardTitle>
          </CardHeader>
          <CardBody>
            {attLoading ? (
              <Skeleton className="h-32 w-full rounded-lg" />
            ) : attendance.length === 0 ? (
              <p className="py-4 text-center text-sm text-[var(--neutral-foreground-3)]">
                この児童の活動記録が見つかりません。
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                      <th className="px-2 py-2">活動日</th>
                      <th className="px-2 py-2">活動名</th>
                      <th className="px-2 py-2">報告書</th>
                      <th className="px-2 py-2 text-right">操作</th>
                    </tr>
                  </thead>
                  <tbody>
                    {attendance.map((a) => (
                      <tr key={a.daily_record_id} className="border-b border-[var(--neutral-stroke-3)] last:border-b-0">
                        <td className="px-2 py-2">{a.record_date}</td>
                        <td className="px-2 py-2">{a.activity_name}</td>
                        <td className="px-2 py-2">
                          {a.has_report ? (
                            <Badge variant={a.report_status === 'finalized' ? 'success' : 'warning'}>
                              {a.report_status === 'finalized' ? '確定済' : '下書き'}
                            </Badge>
                          ) : (
                            <span className="text-xs text-[var(--neutral-foreground-4)]">未作成</span>
                          )}
                        </td>
                        <td className="px-2 py-2 text-right">
                          {a.has_report ? (
                            <div className="inline-flex gap-1">
                              <Button size="sm" variant="ghost" onClick={() => openReport(a.report_id!)}>編集</Button>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={async () => {
                                  try {
                                    await fetchAndOpenPdf(
                                      `/api/staff/free-school/reports/${a.report_id}/pdf`,
                                      `free-school-report-${a.report_id}.pdf`,
                                    );
                                  } catch (err) {
                                    toast.error(formatApiError(err, 'PDF を取得できませんでした'));
                                  }
                                }}
                                leftIcon={<MaterialIcon name="picture_as_pdf" size={12} />}
                              >
                                PDF
                              </Button>
                            </div>
                          ) : (
                            <Button
                              size="sm"
                              onClick={() => generateMutation.mutate({ dailyRecordId: a.daily_record_id })}
                              isLoading={generateMutation.isPending}
                              leftIcon={<MaterialIcon name="auto_awesome" size={14} />}
                            >
                              報告書を作成
                            </Button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {editingReport && (
        <ReportEditModal
          report={editingReport}
          onClose={() => setEditingReport(null)}
          onSaved={() => {
            queryClient.invalidateQueries({ queryKey: ['staff', 'free-school'] });
            setEditingReport(null);
          }}
        />
      )}
    </div>
  );
}

function ReportEditModal({ report, onClose, onSaved }: { report: FreeSchoolReport; onClose: () => void; onSaved: () => void }) {
  const toast = useToast();
  const [form, setForm] = useState({
    title: report.title || '',
    activity_summary: report.activity_summary || '',
    support_consideration: report.support_consideration || '',
    child_observation: report.child_observation || '',
    evaluation_and_next: report.evaluation_and_next || '',
    status: report.status,
  });

  const saveMutation = useMutation({
    mutationFn: async () => api.put(`/api/staff/free-school/reports/${report.id}`, form),
    onSuccess: () => {
      toast.success('報告書を保存しました');
      onSaved();
    },
    onError: (err: unknown) => toast.error(formatApiError(err, '保存に失敗しました')),
  });

  return (
    <Modal isOpen={true} onClose={onClose} title={`報告書編集: ${report.student?.student_name ?? ''} / ${report.report_date}`} size="lg">
      <div className="space-y-3">
        <Input label="表題" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        <SectionTextarea label="1. 活動概要" value={form.activity_summary} onChange={(v) => setForm({ ...form, activity_summary: v })} rows={4} />
        <SectionTextarea label="2. 支援内容と五領域への配慮" value={form.support_consideration} onChange={(v) => setForm({ ...form, support_consideration: v })} rows={6} />
        <SectionTextarea label="3. 本人の様子・取り組み" value={form.child_observation} onChange={(v) => setForm({ ...form, child_observation: v })} rows={6} />
        <SectionTextarea label="4. 評価・今後の課題" value={form.evaluation_and_next} onChange={(v) => setForm({ ...form, evaluation_and_next: v })} rows={5} />
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">状態</label>
          <select
            value={form.status}
            onChange={(e) => setForm({ ...form, status: e.target.value })}
            className="block w-full max-w-xs rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
          >
            <option value="draft">下書き</option>
            <option value="finalized">確定</option>
          </select>
        </div>
        <div className="flex justify-between gap-2 pt-2">
          <Button
            variant="outline"
            onClick={async () => {
              try {
                await fetchAndOpenPdf(
                  `/api/staff/free-school/reports/${report.id}/pdf`,
                  `free-school-report-${report.id}.pdf`,
                );
              } catch (err) {
                toast.error(formatApiError(err, 'PDF を取得できませんでした'));
              }
            }}
            leftIcon={<MaterialIcon name="picture_as_pdf" size={14} />}
          >
            PDF を開く
          </Button>
          <div className="flex gap-2">
            <Button variant="ghost" onClick={onClose}>閉じる</Button>
            <Button onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending}>保存</Button>
          </div>
        </div>
      </div>
    </Modal>
  );
}

function SectionTextarea({ label, value, onChange, rows }: { label: string; value: string; onChange: (v: string) => void; rows: number }) {
  return (
    <div>
      <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</label>
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        rows={rows}
        className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm leading-relaxed"
      />
    </div>
  );
}

// ===========================================================================
// 3) 一括印刷タブ
// ===========================================================================

function BatchTab() {
  const toast = useToast();
  const [userId, setUserId] = useState<string>('');
  const today = new Date().toISOString().slice(0, 10);
  const firstDayOfMonth = useMemo(() => `${today.slice(0, 7)}-01`, [today]);
  const [from, setFrom] = useState(firstDayOfMonth);
  const [to, setTo] = useState(today);

  const { data: users = [] } = useQuery<FreeSchoolUser[]>({
    queryKey: ['staff', 'free-school', 'users'],
    queryFn: async () => {
      const res = await api.get('/api/staff/free-school/users');
      return res.data.data || [];
    },
  });
  const activeUsers = users.filter((u) => u.is_active);

  const [downloading, setDownloading] = useState(false);
  const handleDownload = async () => {
    if (!userId) {
      toast.warning('利用者を選んでください');
      return;
    }
    if (!from || !to) {
      toast.warning('期間を指定してください');
      return;
    }
    setDownloading(true);
    try {
      // 認証ヘッダ付きで PDF を取得する必要があるため axios 経由で blob を取る。
      const targetUser = activeUsers.find((u) => u.id === Number(userId));
      const name = targetUser?.student?.student_name || 'free-school';
      await fetchAndOpenPdf(
        `/api/staff/free-school/reports/batch/pdf?free_school_user_id=${userId}&from=${from}&to=${to}`,
        `free-school-reports-${name}-${from}_${to}.pdf`,
      );
    } catch (err) {
      toast.error(formatApiError(err, 'PDF 生成に失敗しました'));
    } finally {
      setDownloading(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>期間一括印刷</CardTitle>
      </CardHeader>
      <CardBody>
        <div className="space-y-3 max-w-lg">
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">利用者</label>
            <select
              value={userId}
              onChange={(e) => setUserId(e.target.value)}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            >
              <option value="">-- 児童を選んでください --</option>
              {activeUsers.map((u) => (
                <option key={u.id} value={u.id}>{u.student?.student_name}</option>
              ))}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input label="期間 (開始)" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
            <Input label="期間 (終了)" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
          </div>
          <div className="rounded-md bg-[var(--brand-160)] p-3 text-xs text-[var(--neutral-foreground-2)]">
            <p>📑 PDF は次の構成で出力されます:</p>
            <ul className="mt-1 list-disc pl-5">
              <li>1 ページ目: 表紙 (児童名・期間・発行事業所)</li>
              <li>2 ページ目以降: 期間内の各日報告書 (A4 1 〜 2 ページ / 件)</li>
            </ul>
          </div>
          <div className="flex justify-end">
            <Button
              leftIcon={<MaterialIcon name="picture_as_pdf" size={16} />}
              onClick={handleDownload}
              isLoading={downloading}
            >
              PDF を生成してダウンロード
            </Button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}
