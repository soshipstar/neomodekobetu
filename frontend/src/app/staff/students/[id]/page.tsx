'use client';

import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Tabs, type TabItem } from '@/components/ui/Tabs';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { formatDate } from '@/lib/utils';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import Link from 'next/link';
import type { Student } from '@/types/user';

const statusLabels: Record<string, string> = {
  active: '在籍', trial: '体験', short_term: '短期', withdrawn: '退所', waiting: '待機',
};

const statusOptions = [
  { value: 'active', label: '在籍' },
  { value: 'trial', label: '体験' },
  { value: 'short_term', label: '短期' },
  { value: 'withdrawn', label: '退所' },
  { value: 'waiting', label: '待機' },
];

const gradeLabels: Record<string, string> = {
  preschool: '未就学', elementary: '小学生', middle: '中学生', high: '高校生', other: 'その他',
};

const dayLabels = ['月', '火', '水', '木', '金', '土', '日'];
const dayKeys = [
  'scheduled_monday', 'scheduled_tuesday', 'scheduled_wednesday',
  'scheduled_thursday', 'scheduled_friday', 'scheduled_saturday', 'scheduled_sunday',
] as const;

export default function StudentDetailPage() {
  const params = useParams();
  const studentId = Number(params.id);

  const { data: student, isLoading } = useQuery({
    queryKey: ['staff', 'student', studentId],
    queryFn: async () => {
      const response = await api.get<{ data: Student }>(`/api/staff/students/${studentId}`);
      return response.data.data;
    },
    enabled: !!studentId,
  });

  if (isLoading) {
    return <div className="space-y-4"><SkeletonCard /><SkeletonCard /></div>;
  }

  if (!student) {
    return <div className="py-12 text-center text-[var(--neutral-foreground-3)]">生徒が見つかりません</div>;
  }

  const tabItems: TabItem[] = [
    {
      key: 'info',
      label: '基本情報',
      icon: <MaterialIcon name="person" size={18} />,
      content: <StudentInfo studentId={studentId} student={student} />,
    },
    {
      key: 'schedule',
      label: '通所曜日',
      icon: <MaterialIcon name="calendar_month" size={18} />,
      content: <StudentSchedule studentId={studentId} student={student} />,
    },
    {
      key: 'assessment',
      label: 'アセスメント',
      icon: <MaterialIcon name="assessment" size={18} />,
      content: <StudentAssessment studentId={studentId} studentName={student.student_name} />,
    },
    {
      key: 'account',
      label: 'アカウント',
      icon: <MaterialIcon name="key" size={18} />,
      content: <StudentAccount studentId={studentId} student={student} />,
    },
  ];

  const actionLinks = [
    { href: `/staff/students/${studentId}/support-plan`, label: '個別支援計画', icon: 'description' },
    { href: `/staff/students/${studentId}/monitoring`, label: 'モニタリング', icon: 'monitoring' },
    { href: `/staff/students/${studentId}/kakehashi`, label: 'かけはし', icon: 'handshake' },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{student.student_name}</h1>
          <div className="mt-1 flex items-center gap-2">
            <Badge variant={student.status === 'active' ? 'success' : 'default'}>
              {statusLabels[student.status] || student.status}
            </Badge>
            <span className="text-sm text-[var(--neutral-foreground-3)]">{gradeLabels[student.grade_level]}</span>
          </div>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        {actionLinks.map((link) => (
          <Link key={link.href} href={link.href}>
            <Card className="flex items-center gap-3 transition-shadow hover:shadow-[var(--shadow-8)] p-3">
              <MaterialIcon name={link.icon} size={20} className="text-[var(--brand-80)]" />
              <span className="text-sm font-medium text-[var(--neutral-foreground-2)]">{link.label}</span>
            </Card>
          </Link>
        ))}
      </div>

      <Tabs items={tabItems} />
    </div>
  );
}

// ---------------------------------------------------------------------------
// 基本情報（編集可能）
// ---------------------------------------------------------------------------

function StudentInfo({ studentId, student }: { studentId: number; student: Student }) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({
    student_name: student.student_name || '',
    birth_date: student.birth_date || '',
    status: student.status || 'active',
    support_start_date: (student as any).support_start_date || '',
    notes: (student as any).notes || '',
  });

  const saveMutation = useMutation({
    mutationFn: async () => api.put(`/api/staff/students/${studentId}`, form),
    onSuccess: () => {
      toast.success('基本情報を更新しました');
      setEditing(false);
      queryClient.invalidateQueries({ queryKey: ['staff', 'student', studentId] });
    },
    onError: (e: any) => toast.error(e?.response?.data?.message || '更新に失敗しました'),
  });

  if (!editing) {
    return (
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>基本情報</CardTitle>
            <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="edit" size={16} />} onClick={() => setEditing(true)}>
              編集
            </Button>
          </div>
        </CardHeader>
        <CardBody>
          <dl className="grid gap-4 sm:grid-cols-2">
            <div>
              <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">生徒名</dt>
              <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">{student.student_name}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">生年月日</dt>
              <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">
                {student.birth_date ? formatDate(student.birth_date) : '-'}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">学年区分</dt>
              <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">{gradeLabels[student.grade_level]}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">保護者</dt>
              <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">{student.guardian?.full_name || '-'}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">状態</dt>
              <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">{statusLabels[student.status] || student.status}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">支援開始日</dt>
              <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">
                {(student as any).support_start_date ? formatDate((student as any).support_start_date) : '-'}
              </dd>
            </div>
            {(student as any).notes && (
              <div className="sm:col-span-2">
                <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">備考</dt>
                <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{(student as any).notes}</dd>
              </div>
            )}
          </dl>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle>基本情報の編集</CardTitle>
          <Button variant="ghost" size="sm" onClick={() => setEditing(false)}>キャンセル</Button>
        </div>
      </CardHeader>
      <CardBody>
        <div className="space-y-4 max-w-lg">
          <Input label="生徒名" value={form.student_name} onChange={(e) => setForm({ ...form, student_name: e.target.value })} required />
          <Input label="生年月日" type="date" value={form.birth_date} onChange={(e) => setForm({ ...form, birth_date: e.target.value })} />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">状態</label>
            <select
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              value={form.status}
              onChange={(e) => setForm({ ...form, status: e.target.value as any })}
            >
              {statusOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          </div>
          <Input label="支援開始日" type="date" value={form.support_start_date} onChange={(e) => setForm({ ...form, support_start_date: e.target.value })} />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">備考</label>
            <textarea
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              rows={3}
              value={form.notes}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
            />
          </div>
          <Button leftIcon={<MaterialIcon name="save" size={16} />} onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending}>
            保存
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// 通所曜日（編集可能）
// ---------------------------------------------------------------------------

function StudentSchedule({ studentId, student }: { studentId: number; student: Student }) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState(false);
  const [schedule, setSchedule] = useState(
    dayKeys.map((k) => !!student[k])
  );

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: Record<string, boolean> = {};
      dayKeys.forEach((k, i) => { payload[k] = schedule[i]; });
      return api.put(`/api/staff/students/${studentId}`, payload);
    },
    onSuccess: () => {
      toast.success('通所曜日を更新しました');
      setEditing(false);
      queryClient.invalidateQueries({ queryKey: ['staff', 'student', studentId] });
    },
    onError: (e: any) => toast.error(e?.response?.data?.message || '更新に失敗しました'),
  });

  const toggleDay = (idx: number) => {
    setSchedule((prev) => prev.map((v, i) => i === idx ? !v : v));
  };

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle>通所曜日</CardTitle>
          {!editing ? (
            <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="edit" size={16} />} onClick={() => setEditing(true)}>
              編集
            </Button>
          ) : (
            <div className="flex gap-2">
              <Button variant="ghost" size="sm" onClick={() => { setEditing(false); setSchedule(dayKeys.map((k) => !!student[k])); }}>キャンセル</Button>
              <Button size="sm" leftIcon={<MaterialIcon name="save" size={16} />} onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending}>保存</Button>
            </div>
          )}
        </div>
      </CardHeader>
      <CardBody>
        <div className="flex gap-3">
          {dayLabels.map((day, i) => {
            const active = editing ? schedule[i] : !!student[dayKeys[i]];
            return (
              <button
                key={day}
                type="button"
                disabled={!editing}
                onClick={() => editing && toggleDay(i)}
                className={`flex h-12 w-12 items-center justify-center rounded-full text-sm font-bold transition-colors ${
                  active
                    ? 'bg-[var(--brand-80)] text-white'
                    : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-4)]'
                } ${editing ? 'cursor-pointer hover:opacity-80' : ''}`}
              >
                {day}
              </button>
            );
          })}
        </div>
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// アセスメント（5領域）
// ---------------------------------------------------------------------------

interface AssessmentItem {
  key: string;
  label: string;
}

interface AssessmentDomain {
  key: string;
  label: string;
  icon: string;
  color: string;
  items: AssessmentItem[];
}

const ASSESSMENT_DOMAINS: AssessmentDomain[] = [
  {
    key: 'health_life',
    label: '健康・生活',
    icon: 'favorite',
    color: 'text-red-600',
    items: [
      { key: 'eating', label: '食事（偏食・食事量・食べ方等）' },
      { key: 'toileting', label: '排泄（自立度・おむつ使用等）' },
      { key: 'bathing', label: '入浴・清潔（洗髪・歯磨き等）' },
      { key: 'dressing', label: '衣類の着脱（自立度・選択等）' },
      { key: 'sleep', label: '睡眠（就寝時間・質・リズム等）' },
      { key: 'health_management', label: '健康管理（服薬・通院・発作等）' },
      { key: 'safety_awareness', label: '安全意識（危険認知・行動範囲）' },
      { key: 'daily_routine', label: '生活リズム・日課の理解' },
    ],
  },
  {
    key: 'motor_sensory',
    label: '運動・感覚',
    icon: 'directions_run',
    color: 'text-blue-600',
    items: [
      { key: 'gross_motor', label: '粗大運動（走る・跳ぶ・階段等）' },
      { key: 'fine_motor', label: '微細運動（書く・切る・つまむ等）' },
      { key: 'posture', label: '姿勢の保持（座位・立位の安定性）' },
      { key: 'eye_hand_coordination', label: '目と手の協応（書字・道具操作）' },
      { key: 'eye_foot_coordination', label: '目と足の協応（ボール・移動）' },
      { key: 'sensory_hypersensitivity', label: '感覚過敏（聴覚・触覚・視覚・味覚・嗅覚）' },
      { key: 'sensory_hyposensitivity', label: '感覚鈍麻（痛覚・温度覚等）' },
      { key: 'oral_function', label: '口腔機能（咀嚼・構音・唾液等）' },
      { key: 'body_awareness', label: 'ボディイメージ・身体意識' },
    ],
  },
  {
    key: 'cognitive_behavior',
    label: '認知・行動',
    icon: 'psychology',
    color: 'text-purple-600',
    items: [
      { key: 'attention', label: '注意力・集中力の持続' },
      { key: 'anticipation', label: '見通し（予測・スケジュール理解）' },
      { key: 'change_adaptation', label: '急な変化への対応' },
      { key: 'hazard_avoidance', label: '危険回避行動' },
      { key: 'reading_writing', label: '読み書き（文字の認識・書字）' },
      { key: 'number_concept', label: '数概念（数える・計算・金銭等）' },
      { key: 'time_concept', label: '時間概念（時計・曜日・季節等）' },
      { key: 'executive_function', label: '実行機能（計画・段取り・切替）' },
      { key: 'impulse_control', label: '衝動性・多動性' },
      { key: 'perseveration', label: 'こだわり・パターン行動' },
    ],
  },
  {
    key: 'language_communication',
    label: '言語・コミュニケーション',
    icon: 'chat_bubble',
    color: 'text-green-600',
    items: [
      { key: 'receptive_language', label: '言語理解（指示理解・質問理解）' },
      { key: 'expressive_language', label: '言語表出（語彙・文構成・発話量）' },
      { key: 'articulation', label: '構音・発声の明瞭さ' },
      { key: 'dyadic_interaction', label: '二項関係（1対1のやりとり）' },
      { key: 'triadic_interaction', label: '三項関係（共同注意・指さし）' },
      { key: 'nonverbal_communication', label: '非言語コミュニケーション（表情・ジェスチャー）' },
      { key: 'intent_expression', label: '意思表示の手段と適切さ' },
      { key: 'narrative_ability', label: '説明・報告・会話力' },
    ],
  },
  {
    key: 'social_relations',
    label: '人間関係・社会性',
    icon: 'groups',
    color: 'text-amber-600',
    items: [
      { key: 'interest_in_others', label: '他者への関心・興味' },
      { key: 'peer_relationships', label: '友達関係（遊び方・関わり方）' },
      { key: 'group_participation', label: '集団参加（状況理解・ルール順守）' },
      { key: 'conflict_frequency', label: 'トラブル頻度と対処' },
      { key: 'emotion_expression', label: '感情表現・感情調整' },
      { key: 'empathy', label: '共感性・他者理解' },
      { key: 'help_seeking', label: '援助希求（困った時に助けを求める）' },
      { key: 'social_rules', label: '社会的ルール・マナーの理解' },
      { key: 'role_taking', label: '役割取得・当番活動' },
    ],
  },
];

interface AssessmentData {
  id?: number;
  student_id: number;
  domain: string;
  item_key: string;
  current_status: string;
  support_needs: string;
  level: number; // 1-5
  notes: string;
  updated_at?: string;
}

function StudentAssessment({ studentId, studentName }: { studentId: number; studentName: string }) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [activeDomain, setActiveDomain] = useState(ASSESSMENT_DOMAINS[0].key);
  const [editingItem, setEditingItem] = useState<{ domain: string; itemKey: string } | null>(null);
  const [editForm, setEditForm] = useState({ current_status: '', support_needs: '', level: 3, notes: '' });
  const [showPrint, setShowPrint] = useState(false);

  const { data: assessments = [], isLoading } = useQuery({
    queryKey: ['staff', 'student', studentId, 'assessments'],
    queryFn: async () => {
      const res = await api.get<{ data: AssessmentData[] }>(`/api/staff/students/${studentId}/assessments`);
      return res.data.data || [];
    },
    enabled: !!studentId,
  });

  const saveMutation = useMutation({
    mutationFn: async (payload: { domain: string; item_key: string; current_status: string; support_needs: string; level: number; notes: string }) => {
      return api.post(`/api/staff/students/${studentId}/assessments`, payload);
    },
    onSuccess: () => {
      toast.success('アセスメントを保存しました');
      setEditingItem(null);
      queryClient.invalidateQueries({ queryKey: ['staff', 'student', studentId, 'assessments'] });
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const getAssessment = (domain: string, itemKey: string) =>
    assessments.find((a) => a.domain === domain && a.item_key === itemKey);

  const openEdit = (domain: string, itemKey: string) => {
    const existing = getAssessment(domain, itemKey);
    setEditForm({
      current_status: existing?.current_status || '',
      support_needs: existing?.support_needs || '',
      level: existing?.level || 3,
      notes: existing?.notes || '',
    });
    setEditingItem({ domain, itemKey });
  };

  const domain = ASSESSMENT_DOMAINS.find((d) => d.key === activeDomain)!;
  const domainAssessments = assessments.filter((a) => a.domain === activeDomain);
  const filledCount = domainAssessments.filter((a) => a.current_status || a.support_needs).length;

  const levelLabels = ['', '要支援', 'やや課題', '年相応', 'やや得意', '得意'];
  const levelColors = ['', 'bg-red-100 text-red-700', 'bg-amber-100 text-amber-700', 'bg-gray-100 text-gray-700', 'bg-blue-100 text-blue-700', 'bg-green-100 text-green-700'];

  const totalFilled = assessments.filter((a) => a.current_status || a.support_needs).length;

  if (showPrint) {
    return <AssessmentPrintView studentName={studentName} assessments={assessments} onClose={() => setShowPrint(false)} />;
  }

  return (
    <div className="space-y-4">
      {/* Header with print button */}
      <div className="flex items-center justify-between">
        <div className="text-xs text-[var(--neutral-foreground-3)]">{totalFilled}/44 項目入力済</div>
        {totalFilled > 0 && (
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="print" size={16} />} onClick={() => setShowPrint(true)}>
            印刷プレビュー
          </Button>
        )}
      </div>

      {/* Domain tabs */}
      <div className="flex flex-wrap gap-2">
        {ASSESSMENT_DOMAINS.map((d) => {
          const filled = assessments.filter((a) => a.domain === d.key && (a.current_status || a.support_needs)).length;
          return (
            <button
              key={d.key}
              onClick={() => setActiveDomain(d.key)}
              className={`flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition-colors ${
                activeDomain === d.key
                  ? 'bg-[var(--brand-80)] text-white'
                  : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
              }`}
            >
              <MaterialIcon name={d.icon} size={16} />
              {d.label}
              {filled > 0 && (
                <span className={`rounded-full px-1.5 text-[10px] ${
                  activeDomain === d.key ? 'bg-white/30 text-white' : 'bg-[var(--brand-160)] text-[var(--brand-80)]'
                }`}>
                  {filled}/{d.items.length}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* Domain content */}
      <Card>
        <CardHeader>
          <div className="flex items-center gap-2">
            <MaterialIcon name={domain.icon} size={20} className={domain.color} />
            <CardTitle>{domain.label}</CardTitle>
            <Badge variant="info">{filledCount}/{domain.items.length} 入力済</Badge>
          </div>
        </CardHeader>
        <CardBody>
          <div className="space-y-2">
            {domain.items.map((item) => {
              const assessment = getAssessment(domain.key, item.key);
              const hasData = !!(assessment?.current_status || assessment?.support_needs);

              return (
                <div
                  key={item.key}
                  onClick={() => openEdit(domain.key, item.key)}
                  className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] p-3 cursor-pointer hover:bg-[var(--neutral-background-3)] transition-colors"
                >
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">{item.label}</span>
                      {hasData && <MaterialIcon name="check_circle" size={16} className="text-green-500 shrink-0" />}
                    </div>
                    {assessment?.current_status && (
                      <p className="mt-1 text-xs text-[var(--neutral-foreground-3)] line-clamp-1">{assessment.current_status}</p>
                    )}
                  </div>
                  <div className="flex items-center gap-2 shrink-0 ml-2">
                    {assessment?.level && (
                      <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${levelColors[assessment.level]}`}>
                        {levelLabels[assessment.level]}
                      </span>
                    )}
                    <MaterialIcon name="chevron_right" size={18} className="text-[var(--neutral-foreground-4)]" />
                  </div>
                </div>
              );
            })}
          </div>
        </CardBody>
      </Card>

      {/* Edit modal */}
      {editingItem && (() => {
        const domainDef = ASSESSMENT_DOMAINS.find((d) => d.key === editingItem.domain)!;
        const itemDef = domainDef.items.find((i) => i.key === editingItem.itemKey)!;
        return (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setEditingItem(null)}>
            <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-xl bg-[var(--neutral-background-1)] shadow-[var(--shadow-28)]" onClick={(e) => e.stopPropagation()}>
              <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-5 py-4">
                <div>
                  <h3 className="text-lg font-bold text-[var(--neutral-foreground-1)]">{itemDef.label}</h3>
                  <p className="text-xs text-[var(--neutral-foreground-3)]">{domainDef.label}</p>
                </div>
                <button type="button" onClick={() => setEditingItem(null)} className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-1)]">
                  <MaterialIcon name="close" size={20} />
                </button>
              </div>
              <div className="p-5 space-y-4">
                <div>
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">評価レベル</label>
                  <div className="flex gap-1">
                    {[1, 2, 3, 4, 5].map((lv) => (
                      <button
                        key={lv}
                        type="button"
                        onClick={() => setEditForm({ ...editForm, level: lv })}
                        className={`flex-1 rounded-md px-2 py-2 text-xs font-medium transition-colors ${
                          editForm.level === lv ? levelColors[lv] + ' ring-2 ring-offset-1 ring-current' : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-3)]'
                        }`}
                      >
                        {levelLabels[lv]}
                      </button>
                    ))}
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">現在の状況</label>
                  <textarea
                    className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
                    rows={4}
                    value={editForm.current_status}
                    onChange={(e) => setEditForm({ ...editForm, current_status: e.target.value })}
                    placeholder="現在の状況を具体的に記入..."
                    autoFocus
                  />
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">支援の必要性・方針</label>
                  <textarea
                    className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
                    rows={4}
                    value={editForm.support_needs}
                    onChange={(e) => setEditForm({ ...editForm, support_needs: e.target.value })}
                    placeholder="必要な支援内容や方針を記入..."
                  />
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">備考</label>
                  <textarea
                    className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
                    rows={2}
                    value={editForm.notes}
                    onChange={(e) => setEditForm({ ...editForm, notes: e.target.value })}
                    placeholder="その他メモ..."
                  />
                </div>
              </div>
              <div className="flex items-center justify-end gap-2 border-t border-[var(--neutral-stroke-2)] px-5 py-4">
                <Button variant="outline" size="sm" onClick={() => setEditingItem(null)}>キャンセル</Button>
                <Button
                  variant="primary"
                  size="sm"
                  leftIcon={<MaterialIcon name="check" size={16} />}
                  onClick={() => saveMutation.mutate({ domain: editingItem.domain, item_key: editingItem.itemKey, ...editForm })}
                  isLoading={saveMutation.isPending}
                >
                  入力完了
                </Button>
              </div>
            </div>
          </div>
        );
      })()}
    </div>
  );
}

// ---------------------------------------------------------------------------
// アセスメント印刷プレビュー（A4縦）
// ---------------------------------------------------------------------------

function AssessmentPrintView({
  studentName,
  assessments,
  onClose,
}: {
  studentName: string;
  assessments: AssessmentData[];
  onClose: () => void;
}) {
  const levelLabels = ['', '要支援', 'やや課題', '年相応', 'やや得意', '得意'];

  const getAssessment = (domain: string, itemKey: string) =>
    assessments.find((a) => a.domain === domain && a.item_key === itemKey);

  return (
    <>
      {/* Toolbar */}
      <div className="print:hidden mb-4 flex items-center justify-between">
        <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="arrow_back" size={16} />} onClick={onClose}>
          一覧に戻る
        </Button>
        <Button leftIcon={<MaterialIcon name="print" size={16} />} onClick={() => window.print()}>
          印刷する
        </Button>
      </div>

      {/* Printable area */}
      <div className="bg-white print:m-0">
        <style>{`
          @media print {
            body { margin: 0; padding: 0; font-size: 8pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print\\:hidden { display: none !important; }
            @page { size: A4 portrait; margin: 10mm 12mm; }
          }
          .asmnt { font-family: 'MS Gothic', 'Noto Sans JP', sans-serif; color: #222; line-height: 1.3; font-size: 8pt; }
          .asmnt-header { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 4px; margin-bottom: 8px; }
          .asmnt-header h1 { font-size: 14pt; font-weight: 700; margin: 0; letter-spacing: 2pt; }
          .asmnt-meta { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 9pt; }
          .asmnt-domain { margin-bottom: 8px; page-break-inside: avoid; }
          .asmnt-domain-head { background: #4a5568; color: white; padding: 3px 8px; font-weight: bold; font-size: 9pt; margin-bottom: 0; }
          .asmnt-table { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
          .asmnt-table th { background: #e2e8f0; border: 1px solid #888; padding: 2px 4px; text-align: center; font-size: 7pt; font-weight: bold; }
          .asmnt-table td { border: 1px solid #888; padding: 2px 4px; vertical-align: top; line-height: 1.3; }
          .asmnt-table td.item-name { width: 22%; font-size: 7pt; }
          .asmnt-table td.level { width: 8%; text-align: center; font-size: 7pt; }
          .asmnt-table td.status { width: 30%; white-space: pre-wrap; word-wrap: break-word; }
          .asmnt-table td.needs { width: 30%; white-space: pre-wrap; word-wrap: break-word; }
          .asmnt-table td.notes-col { width: 10%; white-space: pre-wrap; word-wrap: break-word; font-size: 6.5pt; }
          .asmnt-table tr.filled { background: #fafbfc; }
          .asmnt-table tr.empty { background: #fff; }
          .asmnt-footer { text-align: right; margin-top: 6px; font-size: 6pt; color: #aaa; }
          .level-1 { color: #b91c1c; font-weight: bold; }
          .level-2 { color: #b45309; }
          .level-3 { color: #6b7280; }
          .level-4 { color: #2563eb; }
          .level-5 { color: #059669; font-weight: bold; }
        `}</style>

        <div className="asmnt">
          <div className="asmnt-header">
            <h1>アセスメントシート</h1>
            <div style={{ fontSize: '8pt', color: '#777' }}>放課後等デイサービス 5領域アセスメント</div>
          </div>

          <div className="asmnt-meta">
            <div><strong>児童氏名：</strong>{studentName}</div>
            <div><strong>評価日：</strong>{new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
          </div>

          {ASSESSMENT_DOMAINS.map((domainDef) => {
            const domainAssessments = assessments.filter((a) => a.domain === domainDef.key);
            const filledCount = domainAssessments.filter((a) => a.current_status || a.support_needs).length;

            return (
              <div key={domainDef.key} className="asmnt-domain">
                <div className="asmnt-domain-head">
                  {domainDef.label}（{filledCount}/{domainDef.items.length}）
                </div>
                <table className="asmnt-table">
                  <thead>
                    <tr>
                      <th>項目</th>
                      <th>評価</th>
                      <th>現在の状況</th>
                      <th>支援の必要性・方針</th>
                      <th>備考</th>
                    </tr>
                  </thead>
                  <tbody>
                    {domainDef.items.map((item) => {
                      const a = getAssessment(domainDef.key, item.key);
                      const hasFill = !!(a?.current_status || a?.support_needs);
                      return (
                        <tr key={item.key} className={hasFill ? 'filled' : 'empty'}>
                          <td className="item-name">{item.label}</td>
                          <td className="level">
                            {a?.level ? (
                              <span className={`level-${a.level}`}>{levelLabels[a.level]}</span>
                            ) : ''}
                          </td>
                          <td className="status">{a?.current_status || ''}</td>
                          <td className="needs">{a?.support_needs || ''}</td>
                          <td className="notes-col">{a?.notes || ''}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            );
          })}

          <div className="asmnt-footer">
            出力日時: {new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}
          </div>
        </div>
      </div>
    </>
  );
}

// ---------------------------------------------------------------------------
// アカウント
// ---------------------------------------------------------------------------

function StudentAccount({ studentId, student }: { studentId: number; student: Student }) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [username, setUsername] = useState(student.username || '');
  const [password, setPassword] = useState('');

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: Record<string, string> = {};
      if (username.trim()) payload.username = username.trim();
      if (password.trim()) payload.password = password.trim();
      if (Object.keys(payload).length === 0) throw new Error('変更がありません');
      return api.put(`/api/staff/students/${studentId}`, payload);
    },
    onSuccess: () => {
      toast.success('アカウント情報を更新しました');
      setPassword('');
      queryClient.invalidateQueries({ queryKey: ['staff', 'student', studentId] });
    },
    onError: (e: any) => toast.error(e?.response?.data?.message || e?.message || '更新に失敗しました'),
  });

  return (
    <Card>
      <CardBody>
        <p className="mb-4 text-sm text-[var(--neutral-foreground-3)]">
          生徒のログインID・パスワードを変更できます
        </p>
        <div className="space-y-4 max-w-md">
          <Input label="ログインID（ユーザー名）" value={username} onChange={(e) => setUsername(e.target.value)} placeholder="ログインIDを入力" />
          <Input label="新しいパスワード" type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="変更しない場合は空欄" helperText="変更する場合のみ入力してください（6文字以上）" />
          <Button leftIcon={<MaterialIcon name="save" size={16} />} onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending} disabled={!username.trim() && !password.trim()}>
            アカウント情報を保存
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}
