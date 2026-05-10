'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useWorkspace } from '@/hooks/useWorkspace';

interface StudentLite { id: number; student_name: string }
interface JobApplication {
  id: number; student_id: number; company_name: string; industry: string | null; job_title: string | null;
  employment_type: string | null; application_date: string; source: string | null; status: string;
  interview_date: string | null; result_date: string | null; result_notes: string | null; feedback: string | null;
  student?: StudentLite;
}
interface CompanyInternship {
  id: number; student_id: number; company_name: string; contact_person: string | null; contact_phone: string | null;
  start_date: string; end_date: string | null; total_days: number | null; internship_type: string;
  purpose: string | null; plan_content: string | null; daily_logs: string | null; company_evaluation: string | null;
  attitude_score: number | null; skill_score: number | null; communication_score: number | null;
  staff_evaluation: string | null; outcome: string | null; student?: StudentLite;
}
interface JobPlacementContact {
  id: number; contact_date: string; contact_type: string; contact_with: string | null; content: string;
  issues_raised: string | null; actions_taken: string | null; satisfaction_score: number | null; attendance_rate: number | null;
}
interface JobPlacement {
  id: number; student_id: number; company_name: string; job_title: string | null; start_date: string; end_date: string | null;
  employment_type: string | null; monthly_salary: string | number | null; weekly_hours: number | null; status: string;
  reasonable_accommodations: string | null; next_followup_date: string | null; separation_reason: string | null;
  student?: StudentLite; contacts?: JobPlacementContact[];
}

const APP_STATUS: Record<string, { label: string; variant: 'default' | 'warning' | 'success' | 'danger' }> = {
  applied:               { label: '応募済み',     variant: 'default' },
  screening:             { label: '書類選考中',   variant: 'default' },
  interview_scheduled:   { label: '面接予定',     variant: 'warning' },
  interviewed:           { label: '面接済み',     variant: 'warning' },
  offered:               { label: '内定',         variant: 'success' },
  accepted:              { label: '入社決定',     variant: 'success' },
  rejected:              { label: '不採用',       variant: 'danger' },
  withdrawn:             { label: '辞退',         variant: 'default' },
};

const PLACEMENT_STATUS: Record<string, { label: string; variant: 'success' | 'default' | 'danger' }> = {
  active:     { label: '在籍中',   variant: 'success' },
  resigned:   { label: '自己都合離職', variant: 'default' },
  terminated: { label: '解雇',     variant: 'danger' },
  transferred: { label: '異動',    variant: 'default' },
};

type Tab = 'applications' | 'internships' | 'placements';

export default function TransitionSupportPage() {
  const { serviceType, terms } = useWorkspace();
  const [tab, setTab] = useState<Tab>('applications');

  if (serviceType !== 'transition') {
    return (
      <Card>
        <CardBody>
          <div className="flex items-center gap-3 p-4">
            <MaterialIcon name="info" size={24} className="text-[var(--brand-80)]" />
            <p className="text-sm text-[var(--neutral-foreground-2)]">
              この画面は<strong>就労移行支援</strong>のみで利用できます。
            </p>
          </div>
        </CardBody>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">就労移行支援</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          求職活動・企業実習・就職後の定着支援を一元管理します。
        </p>
      </div>

      <div className="flex gap-2 border-b border-[var(--neutral-stroke-2)]">
        <TabButton active={tab === 'applications'} onClick={() => setTab('applications')} icon="work_history" label="求職活動" />
        <TabButton active={tab === 'internships'}  onClick={() => setTab('internships')}  icon="business_center" label="企業実習" />
        <TabButton active={tab === 'placements'}   onClick={() => setTab('placements')}   icon="emoji_events" label="就職後定着" />
      </div>

      {tab === 'applications' && <ApplicationsPanel terms={terms} />}
      {tab === 'internships'  && <InternshipsPanel  terms={terms} />}
      {tab === 'placements'   && <PlacementsPanel   terms={terms} />}
    </div>
  );
}

function TabButton({ active, onClick, icon, label }: { active: boolean; onClick: () => void; icon: string; label: string }) {
  return (
    <button
      onClick={onClick}
      className={`flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
        active
          ? 'border-[var(--brand-80)] text-[var(--brand-70)]'
          : 'border-transparent text-[var(--neutral-foreground-3)] hover:text-[var(--neutral-foreground-1)]'
      }`}
    >
      <MaterialIcon name={icon} size={18} />
      {label}
    </button>
  );
}

// ---------- Applications ----------
function ApplicationsPanel({ terms }: { terms: { client: string } }) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [showForm, setShowForm] = useState(false);

  const { data: students = [] } = useQuery({
    queryKey: ['staff', 'students', 'lite'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentLite[] }>('/api/staff/students?per_page=200');
      return res.data.data ?? [];
    },
  });
  const { data: items = [], isLoading } = useQuery({
    queryKey: ['staff', 'job-applications'],
    queryFn: async () => {
      const res = await api.get<{ data: JobApplication[] }>('/api/staff/job-applications');
      return res.data.data;
    },
  });

  const createMutation = useMutation({
    mutationFn: async (payload: Partial<JobApplication>) => api.post('/api/staff/job-applications', payload),
    onSuccess: () => {
      toast.success('応募記録を追加しました');
      queryClient.invalidateQueries({ queryKey: ['staff', 'job-applications'] });
      setShowForm(false);
    },
    onError: () => toast.error('追加に失敗しました'),
  });

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button onClick={() => setShowForm(true)} leftIcon={<MaterialIcon name="add" size={16} />}>
          応募を記録
        </Button>
      </div>
      <Card>
        <CardBody>
          {isLoading ? <p className="text-sm">読み込み中...</p>
            : items.length === 0 ? <p className="text-sm text-[var(--neutral-foreground-4)]">まだ応募記録がありません。</p>
            : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
                      <th className="px-3 py-2 text-left">{terms.client}</th>
                      <th className="px-3 py-2 text-left">企業</th>
                      <th className="px-3 py-2 text-left">職種</th>
                      <th className="px-3 py-2 text-left">応募日</th>
                      <th className="px-3 py-2 text-left">面接日</th>
                      <th className="px-3 py-2 text-left">状態</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((a) => (
                      <tr key={a.id} className="border-b border-[var(--neutral-stroke-3)]">
                        <td className="px-3 py-2">{a.student?.student_name ?? `#${a.student_id}`}</td>
                        <td className="px-3 py-2">{a.company_name}</td>
                        <td className="px-3 py-2 text-xs">{a.job_title ?? '-'}</td>
                        <td className="px-3 py-2 text-xs">{a.application_date}</td>
                        <td className="px-3 py-2 text-xs">{a.interview_date ?? '-'}</td>
                        <td className="px-3 py-2">
                          <Badge variant={APP_STATUS[a.status]?.variant ?? 'default'}>
                            {APP_STATUS[a.status]?.label ?? a.status}
                          </Badge>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
        </CardBody>
      </Card>

      {showForm && (
        <ApplicationFormModal
          students={students}
          onSubmit={(data) => createMutation.mutate(data)}
          onClose={() => setShowForm(false)}
          isSaving={createMutation.isPending}
        />
      )}
    </div>
  );
}

function ApplicationFormModal({ students, onSubmit, onClose, isSaving }: { students: StudentLite[]; onSubmit: (d: Partial<JobApplication>) => void; onClose: () => void; isSaving: boolean }) {
  const [form, setForm] = useState({
    student_id: students[0]?.id ?? 0,
    company_name: '',
    industry: '',
    job_title: '',
    employment_type: 'full_time',
    application_date: new Date().toISOString().slice(0, 10),
    source: 'hello_work',
    status: 'applied',
  });
  const inputCls = 'block w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm';
  return (
    <Modal isOpen onClose={onClose} title="求職応募を記録" size="md">
      <div className="space-y-3">
        <div>
          <label className="mb-1 block text-xs font-medium">利用者</label>
          <select className={inputCls} value={form.student_id} onChange={(e) => setForm({ ...form, student_id: Number(e.target.value) })}>
            {students.map((s) => <option key={s.id} value={s.id}>{s.student_name}</option>)}
          </select>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-xs font-medium">会社名 *</label>
            <input className={inputCls} value={form.company_name} onChange={(e) => setForm({ ...form, company_name: e.target.value })} />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium">業種</label>
            <input className={inputCls} value={form.industry} onChange={(e) => setForm({ ...form, industry: e.target.value })} placeholder="例: 製造業" />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium">職種</label>
            <input className={inputCls} value={form.job_title} onChange={(e) => setForm({ ...form, job_title: e.target.value })} placeholder="例: 事務" />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium">雇用形態</label>
            <select className={inputCls} value={form.employment_type} onChange={(e) => setForm({ ...form, employment_type: e.target.value })}>
              <option value="full_time">正社員</option>
              <option value="part_time">パート・アルバイト</option>
              <option value="contract">契約社員</option>
              <option value="other">その他</option>
            </select>
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium">応募日 *</label>
            <input className={inputCls} type="date" value={form.application_date} onChange={(e) => setForm({ ...form, application_date: e.target.value })} />
          </div>
          <div>
            <label className="mb-1 block text-xs font-medium">経路</label>
            <select className={inputCls} value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })}>
              <option value="hello_work">ハローワーク</option>
              <option value="introduction">紹介</option>
              <option value="direct">直接応募</option>
              <option value="event">合同説明会</option>
              <option value="other">その他</option>
            </select>
          </div>
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" onClick={onClose}>キャンセル</Button>
          <Button onClick={() => onSubmit(form)} isLoading={isSaving}>登録</Button>
        </div>
      </div>
    </Modal>
  );
}

// ---------- Internships ----------
function InternshipsPanel({ terms }: { terms: { client: string } }) {
  const { data: items = [], isLoading } = useQuery({
    queryKey: ['staff', 'company-internships'],
    queryFn: async () => {
      const res = await api.get<{ data: CompanyInternship[] }>('/api/staff/company-internships');
      return res.data.data;
    },
  });

  return (
    <Card>
      <CardHeader><CardTitle>企業実習一覧</CardTitle></CardHeader>
      <CardBody>
        {isLoading ? <p className="text-sm">読み込み中...</p>
          : items.length === 0 ? (
            <p className="text-sm text-[var(--neutral-foreground-4)]">
              まだ企業実習の記録がありません。今後 API 経由で追加できます。
            </p>
          )
          : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
                    <th className="px-3 py-2 text-left">{terms.client}</th>
                    <th className="px-3 py-2 text-left">実習先</th>
                    <th className="px-3 py-2 text-left">期間</th>
                    <th className="px-3 py-2 text-center">日数</th>
                    <th className="px-3 py-2 text-center">就労意欲</th>
                    <th className="px-3 py-2 text-center">技能</th>
                    <th className="px-3 py-2 text-center">対人</th>
                    <th className="px-3 py-2 text-left">結果</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((i) => (
                    <tr key={i.id} className="border-b border-[var(--neutral-stroke-3)]">
                      <td className="px-3 py-2">{i.student?.student_name ?? `#${i.student_id}`}</td>
                      <td className="px-3 py-2">{i.company_name}</td>
                      <td className="px-3 py-2 text-xs">{i.start_date} - {i.end_date ?? '進行中'}</td>
                      <td className="px-3 py-2 text-center">{i.total_days ?? '-'}</td>
                      <td className="px-3 py-2 text-center">{i.attitude_score ? `${i.attitude_score}/5` : '-'}</td>
                      <td className="px-3 py-2 text-center">{i.skill_score ? `${i.skill_score}/5` : '-'}</td>
                      <td className="px-3 py-2 text-center">{i.communication_score ? `${i.communication_score}/5` : '-'}</td>
                      <td className="px-3 py-2 text-xs">{i.outcome ?? '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
      </CardBody>
    </Card>
  );
}

// ---------- Placements ----------
function PlacementsPanel({ terms }: { terms: { client: string } }) {
  const { data: items = [], isLoading } = useQuery({
    queryKey: ['staff', 'job-placements'],
    queryFn: async () => {
      const res = await api.get<{ data: JobPlacement[] }>('/api/staff/job-placements');
      return res.data.data;
    },
  });

  const active = items.filter((i) => i.status === 'active');
  const inactive = items.filter((i) => i.status !== 'active');

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>就職後定着支援 - 在籍中 ({active.length} 名)</CardTitle>
        </CardHeader>
        <CardBody>
          {isLoading ? <p className="text-sm">読み込み中...</p>
            : active.length === 0 ? <p className="text-sm text-[var(--neutral-foreground-4)]">在籍中の OB/OG はまだいません。</p>
            : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
                      <th className="px-3 py-2 text-left">{terms.client}</th>
                      <th className="px-3 py-2 text-left">就職先</th>
                      <th className="px-3 py-2 text-left">職種</th>
                      <th className="px-3 py-2 text-left">就職日</th>
                      <th className="px-3 py-2 text-right">月収</th>
                      <th className="px-3 py-2 text-right">週時間</th>
                      <th className="px-3 py-2 text-left">次回フォロー</th>
                      <th className="px-3 py-2 text-center">面談履歴</th>
                    </tr>
                  </thead>
                  <tbody>
                    {active.map((p) => (
                      <tr key={p.id} className="border-b border-[var(--neutral-stroke-3)]">
                        <td className="px-3 py-2">{p.student?.student_name ?? `#${p.student_id}`}</td>
                        <td className="px-3 py-2">{p.company_name}</td>
                        <td className="px-3 py-2 text-xs">{p.job_title ?? '-'}</td>
                        <td className="px-3 py-2 text-xs">{p.start_date}</td>
                        <td className="px-3 py-2 text-right text-xs">
                          {p.monthly_salary ? `¥${Number(p.monthly_salary).toLocaleString()}` : '-'}
                        </td>
                        <td className="px-3 py-2 text-right text-xs">{p.weekly_hours ?? '-'} h</td>
                        <td className="px-3 py-2 text-xs">{p.next_followup_date ?? '-'}</td>
                        <td className="px-3 py-2 text-center text-xs">{p.contacts?.length ?? 0} 件</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
        </CardBody>
      </Card>

      {inactive.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>離職した OB/OG ({inactive.length} 名)</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
                    <th className="px-3 py-2 text-left">{terms.client}</th>
                    <th className="px-3 py-2 text-left">就職先</th>
                    <th className="px-3 py-2 text-left">在籍期間</th>
                    <th className="px-3 py-2 text-left">状態</th>
                  </tr>
                </thead>
                <tbody>
                  {inactive.map((p) => (
                    <tr key={p.id} className="border-b border-[var(--neutral-stroke-3)]">
                      <td className="px-3 py-2">{p.student?.student_name ?? `#${p.student_id}`}</td>
                      <td className="px-3 py-2">{p.company_name}</td>
                      <td className="px-3 py-2 text-xs">{p.start_date} - {p.end_date ?? '?'}</td>
                      <td className="px-3 py-2">
                        <Badge variant={PLACEMENT_STATUS[p.status]?.variant ?? 'default'}>
                          {PLACEMENT_STATUS[p.status]?.label ?? p.status}
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
