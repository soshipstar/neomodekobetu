'use client';

import { useState, useRef } from 'react';
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
      key: 'facesheet',
      label: 'フェイスシート',
      icon: <MaterialIcon name="assignment" size={18} />,
      content: <StudentFaceSheet studentId={studentId} studentName={student.student_name} />,
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
  const toDateStr = (d: string | null | undefined) => d ? d.slice(0, 10) : '';
  const [form, setForm] = useState({
    student_name: student.student_name || '',
    birth_date: toDateStr(student.birth_date),
    status: student.status || 'active',
    support_start_date: toDateStr((student as any).support_start_date),
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
// フェイスシート（横浜市青葉区かけはし準拠）
// ---------------------------------------------------------------------------

// ADL選択肢ヘルパー
const ADL4 = ['自立', '見守り', '一部介助', '全介助'] as const;
const ADL3 = ['自立', '一部介助', '全介助'] as const;

interface FaceSheetData {
  daily_life: Record<string, string>;
  physical: Record<string, string>;
  profile: Record<string, string>;
  considerations: Record<string, string>;
  memo: string;
}

// 各セクションの項目定義
interface FieldDef {
  key: string;
  label: string;
  type: 'select' | 'text' | 'textarea' | 'checkbox-group';
  options?: readonly string[];
  placeholder?: string;
}

interface SectionDef {
  key: string;
  title: string;
  fields: FieldDef[];
}

const DAILY_LIFE_SECTIONS: SectionDef[] = [
  {
    key: 'eating', title: '食事',
    fields: [
      { key: 'food_intake', label: '食事摂取', type: 'select', options: ADL4 },
      { key: 'food_count', label: '食事回数（回）', type: 'text', placeholder: '3' },
      { key: 'appetite', label: '食欲', type: 'select', options: ['旺盛', '普通', '無'] },
      { key: 'drinking', label: '飲水', type: 'select', options: ADL4 },
      { key: 'allergy', label: 'アレルギー', type: 'text', placeholder: '無 / 有（内容）' },
      { key: 'swallowing', label: '嚥下', type: 'select', options: ['自立', '見守り'] },
      { key: 'food_form', label: '食事形態', type: 'select', options: ['普通', '一口大', '粗刻み', '極刻み', 'ミキサー', '注入', '他'] },
      { key: 'food_content', label: '食事内容', type: 'text', placeholder: '常食 / 特別食（内容）' },
      { key: 'utensils', label: '使用器具', type: 'text', placeholder: '箸・フォーク・スプーン・手づかみ・自助具' },
      { key: 'medication', label: '服薬管理・服用方法', type: 'textarea', placeholder: '服薬の有無、管理方法等' },
    ],
  },
  {
    key: 'toileting', title: '排泄',
    fields: [
      { key: 'urination', label: '排尿', type: 'select', options: ADL4 },
      { key: 'urination_freq', label: '排尿回数', type: 'text', placeholder: '1日○回' },
      { key: 'urination_aware', label: '尿意', type: 'select', options: ['有', '時々', '無'] },
      { key: 'urination_comm', label: '尿意伝達', type: 'select', options: ['可', '不可'] },
      { key: 'defecation', label: '排便', type: 'select', options: ADL4 },
      { key: 'defecation_freq', label: '排便回数', type: 'text', placeholder: '日に1回' },
      { key: 'defecation_aware', label: '便意', type: 'select', options: ['有', '時々', '無'] },
      { key: 'defecation_comm', label: '便意伝達', type: 'select', options: ['可', '不可'] },
      { key: 'incontinence', label: '失禁', type: 'select', options: ['無', '時々', '常時'] },
      { key: 'bowel_cond', label: '便通', type: 'select', options: ['普通', '便秘', '下痢'] },
      { key: 'time_guidance', label: '時間誘導', type: 'select', options: ['不要', '要'] },
      { key: 'menstruation', label: '生理', type: 'select', options: ADL4 },
      { key: 'toileting_method', label: '具体方法', type: 'textarea', placeholder: '方法（便所・ポータブルトイレ・尿器・オムツ・ストマ等）' },
    ],
  },
  {
    key: 'bathing', title: '入浴',
    fields: [
      { key: 'bathtub_entry', label: '浴槽出入り', type: 'select', options: ADL4 },
      { key: 'body_wash', label: '洗体', type: 'select', options: ADL4 },
      { key: 'hair_wash', label: '洗髪', type: 'select', options: ADL4 },
      { key: 'bath_freq', label: '頻度', type: 'text', placeholder: '毎日 / 月・水・金' },
      { key: 'bath_method', label: '方法', type: 'text', placeholder: '一般浴槽・機械浴槽・リフター・シャワー・清拭' },
      { key: 'bath_notes', label: '留意点', type: 'textarea' },
    ],
  },
  {
    key: 'hygiene', title: '清潔',
    fields: [
      { key: 'tooth_brushing', label: '歯磨き/口腔清潔', type: 'select', options: ADL4 },
      { key: 'face_wash', label: '洗顔', type: 'select', options: ADL4 },
      { key: 'hair_grooming', label: '整髪', type: 'select', options: ADL4 },
      { key: 'nail_cutting', label: '爪きり', type: 'select', options: ADL4 },
    ],
  },
  {
    key: 'dressing', title: '着脱',
    fields: [
      { key: 'upper', label: '上着', type: 'select', options: ADL4 },
      { key: 'lower', label: 'ズボン等', type: 'select', options: ADL4 },
    ],
  },
  {
    key: 'daily_tasks', title: '日常生活',
    fields: [
      { key: 'laundry', label: '洗濯', type: 'select', options: ADL3 },
      { key: 'cleaning', label: '掃除', type: 'select', options: ADL3 },
      { key: 'organizing', label: '整理整頓', type: 'select', options: ADL3 },
      { key: 'phone', label: '電話の利用', type: 'select', options: ADL3 },
      { key: 'shopping', label: '買い物', type: 'select', options: ADL3 },
      { key: 'money', label: '金銭管理', type: 'select', options: ADL3 },
      { key: 'cooking', label: '調理', type: 'select', options: ADL3 },
    ],
  },
  {
    key: 'social', title: '社会生活',
    fields: [
      { key: 'outdoor_move', label: '屋外移動', type: 'select', options: ADL3 },
      { key: 'transportation', label: '交通機関の利用', type: 'select', options: ADL3 },
      { key: 'interpersonal', label: '対人関係', type: 'select', options: ADL3 },
      { key: 'group_life', label: '集団生活', type: 'select', options: ADL3 },
      { key: 'literacy', label: '文字', type: 'select', options: ADL3 },
      { key: 'time_concept', label: '時間', type: 'select', options: ADL3 },
    ],
  },
  {
    key: 'special_notes', title: '要配慮事項',
    fields: [
      { key: 'notes', label: '要配慮事項', type: 'textarea', placeholder: '配慮が必要な事項を記入' },
    ],
  },
];

const PHYSICAL_SECTIONS: SectionDef[] = [
  {
    key: 'floor', title: '床上動作',
    fields: [
      { key: 'turning', label: '寝返り', type: 'select', options: ['自立', '何かにつかまれば可', 'できない'] },
      { key: 'getting_up', label: '起き上がり', type: 'select', options: ['自立', '何かにつかまれば可', 'できない'] },
      { key: 'sitting', label: '座位保持', type: 'select', options: ['自立', '自分で支えれば可・支えが必要', 'できない'] },
      { key: 'standing', label: '立位保持', type: 'select', options: ['自立', '支えが必要', 'できない'] },
      { key: 'electric_bed', label: '電動ベット', type: 'select', options: ['無', '有'] },
      { key: 'air_mattress', label: 'エアーマット', type: 'select', options: ['無', '有'] },
    ],
  },
  {
    key: 'mobility', title: '移動',
    fields: [
      { key: 'transfer', label: '移乗', type: 'select', options: ['自立', '見守り・一部介助', 'できない'] },
      { key: 'indoor', label: '屋内移動', type: 'select', options: ADL4 },
      { key: 'outdoor', label: '屋外移動', type: 'select', options: ADL4 },
      { key: 'assistive_devices', label: '補装具の使用', type: 'text', placeholder: '杖・短下肢装具・歩行器・車椅子・その他・無' },
    ],
  },
];

const PROFILE_SECTIONS: SectionDef[] = [
  {
    key: 'personality', title: '性格・趣味等',
    fields: [
      { key: 'hobbies', label: '趣味・好きなこと', type: 'textarea' },
      { key: 'personality', label: '性格', type: 'textarea' },
      { key: 'strengths', label: '得意なこと', type: 'textarea' },
      { key: 'weaknesses', label: '苦手なこと', type: 'textarea' },
    ],
  },
  {
    key: 'communication', title: 'コミュニケーション手段',
    fields: [
      { key: 'to_others_method', label: '本人から相手に伝えるとき', type: 'text', placeholder: 'ことば（文章）/ ことば（単語）/ ジェスチャー / 写真・絵カード' },
      { key: 'to_others_example', label: '具体的なやりとり例（→相手）', type: 'textarea' },
      { key: 'from_others_method', label: '相手から本人に伝えるとき', type: 'text', placeholder: 'ことば（文章）/ ことば（単語）/ ジェスチャー / 写真・絵カード' },
      { key: 'from_others_example', label: '具体的なやりとり例（←相手）', type: 'textarea' },
      { key: 'friend_interaction', label: '友達とのやりとり・関わり方', type: 'textarea' },
    ],
  },
];

const CONSIDERATION_SECTIONS: SectionDef[] = [
  {
    key: 'medical', title: '身体面・医療面',
    fields: [
      { key: 'epilepsy', label: 'てんかんの有無', type: 'select', options: ['無', '有'] },
      { key: 'epilepsy_notes', label: 'てんかん配慮事項', type: 'textarea', placeholder: '対応の方法など' },
      { key: 'seizure', label: '発作の有無', type: 'select', options: ['無', '有'] },
      { key: 'seizure_notes', label: '発作配慮事項', type: 'textarea', placeholder: '対応の方法など' },
      { key: 'physical_medical', label: '身体面・医療面の詳細', type: 'textarea' },
    ],
  },
  {
    key: 'medical_care', title: '医療的ケア',
    fields: [
      { key: 'tube_feeding', label: '経管栄養', type: 'text', placeholder: '経鼻・胃ろう・腸ろう・その他・無' },
      { key: 'suction', label: '喀痰等吸引', type: 'text', placeholder: '口腔・鼻腔・エアウェイ・気管切開・無' },
      { key: 'oxygen', label: '酸素療法', type: 'text', placeholder: '○ℓ / 無' },
      { key: 'inhalation', label: '吸入', type: 'text', placeholder: '薬液・精製水・生理食塩水・無' },
      { key: 'seizure_response', label: '発作時の対応', type: 'text', placeholder: '坐薬・VNS・その他' },
      { key: 'other_care', label: 'その他の医療的ケア', type: 'textarea' },
    ],
  },
  {
    key: 'behavior', title: '行動面',
    fields: [
      { key: 'panic_causes', label: '混乱・かんしゃく・パニックの原因になりやすいこと', type: 'textarea' },
      { key: 'behavior_tendencies', label: '表現・行動（どのような傾向があるか）', type: 'textarea' },
      { key: 'coping', label: '対処方法', type: 'textarea' },
      { key: 'prevention', label: '予防の方法', type: 'textarea' },
    ],
  },
];

function StudentFaceSheet({ studentId, studentName }: { studentId: number; studentName: string }) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'daily_life' | 'physical' | 'profile' | 'considerations'>('daily_life');
  const [showPrint, setShowPrint] = useState(false);

  const { data: sheet, isLoading } = useQuery({
    queryKey: ['staff', 'student', studentId, 'face-sheet'],
    queryFn: async () => {
      const res = await api.get(`/api/staff/students/${studentId}/face-sheet`);
      return res.data.data as FaceSheetData | null;
    },
    enabled: !!studentId,
  });

  const [form, setForm] = useState<FaceSheetData>({
    daily_life: {}, physical: {}, profile: {}, considerations: {}, memo: '',
  });

  // Sync form with loaded data
  const dataLoaded = useRef(false);
  if (sheet && !dataLoaded.current) {
    dataLoaded.current = true;
    setForm({
      daily_life: sheet.daily_life || {},
      physical: sheet.physical || {},
      profile: sheet.profile || {},
      considerations: sheet.considerations || {},
      memo: (sheet as any).memo || '',
    });
  }

  const saveMutation = useMutation({
    mutationFn: async () => api.post(`/api/staff/students/${studentId}/face-sheet`, form),
    onSuccess: () => {
      toast.success('フェイスシートを保存しました');
      queryClient.invalidateQueries({ queryKey: ['staff', 'student', studentId, 'face-sheet'] });
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const updateField = (section: keyof FaceSheetData, key: string, value: string) => {
    if (section === 'memo') return;
    setForm((prev) => ({
      ...prev,
      [section]: { ...(prev[section] as Record<string, string>), [key]: value },
    }));
  };

  const getVal = (section: keyof FaceSheetData, key: string): string => {
    if (section === 'memo') return '';
    return ((form[section] as Record<string, string>) || {})[key] || '';
  };

  const tabs = [
    { key: 'daily_life' as const, label: '日常生活', icon: 'restaurant' },
    { key: 'physical' as const, label: '身体', icon: 'accessible' },
    { key: 'profile' as const, label: '性格等', icon: 'person' },
    { key: 'considerations' as const, label: '配慮事項', icon: 'warning' },
  ];

  const renderSections = (sections: SectionDef[], dataKey: keyof FaceSheetData) => (
    <div className="space-y-4">
      {sections.map((section) => (
        <div key={section.key} className="rounded-lg border border-[var(--neutral-stroke-2)]">
          <div className="bg-[var(--neutral-background-3)] px-4 py-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">
            {section.title}
          </div>
          <div className="p-4 space-y-3">
            {section.fields.map((field) => (
              <div key={field.key}>
                <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">{field.label}</label>
                {field.type === 'select' && field.options ? (
                  <div className="flex flex-wrap gap-1.5">
                    {field.options.map((opt) => (
                      <button
                        key={opt}
                        type="button"
                        onClick={() => updateField(dataKey, field.key, opt)}
                        className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                          getVal(dataKey, field.key) === opt
                            ? 'bg-[var(--brand-80)] text-white'
                            : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
                        }`}
                      >
                        {opt}
                      </button>
                    ))}
                  </div>
                ) : field.type === 'textarea' ? (
                  <textarea
                    className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
                    rows={3}
                    value={getVal(dataKey, field.key)}
                    onChange={(e) => updateField(dataKey, field.key, e.target.value)}
                    placeholder={field.placeholder}
                  />
                ) : (
                  <input
                    type="text"
                    className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
                    value={getVal(dataKey, field.key)}
                    onChange={(e) => updateField(dataKey, field.key, e.target.value)}
                    placeholder={field.placeholder}
                  />
                )}
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );

  if (showPrint) {
    return <FaceSheetPrintView studentName={studentName} form={form} onClose={() => setShowPrint(false)} />;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex flex-wrap gap-2">
          {tabs.map((t) => (
            <button
              key={t.key}
              onClick={() => setActiveTab(t.key)}
              className={`flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition-colors ${
                activeTab === t.key
                  ? 'bg-[var(--brand-80)] text-white'
                  : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
              }`}
            >
              <MaterialIcon name={t.icon} size={16} />
              {t.label}
            </button>
          ))}
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="print" size={16} />} onClick={() => setShowPrint(true)}>
            印刷
          </Button>
          <Button size="sm" leftIcon={<MaterialIcon name="save" size={16} />} onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending}>
            保存
          </Button>
        </div>
      </div>

      {activeTab === 'daily_life' && renderSections(DAILY_LIFE_SECTIONS, 'daily_life')}
      {activeTab === 'physical' && renderSections(PHYSICAL_SECTIONS, 'physical')}
      {activeTab === 'profile' && renderSections(PROFILE_SECTIONS, 'profile')}
      {activeTab === 'considerations' && renderSections(CONSIDERATION_SECTIONS, 'considerations')}

      {/* MEMO */}
      <div className="rounded-lg border border-[var(--neutral-stroke-2)]">
        <div className="bg-[var(--neutral-background-3)] px-4 py-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">MEMO</div>
        <div className="p-4">
          <textarea
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
            rows={4}
            value={form.memo}
            onChange={(e) => setForm({ ...form, memo: e.target.value })}
            placeholder="メモ欄"
          />
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// フェイスシート印刷プレビュー（A4縦）
// ---------------------------------------------------------------------------

function renderPrintSections(sections: SectionDef[], data: Record<string, string>) {
  return sections.map((section) => {
    const hasData = section.fields.some((f) => data[f.key]);
    if (!hasData) return null;
    return (
      <div key={section.key} style={{ marginBottom: '6px', pageBreakInside: 'avoid' }}>
        <div style={{ background: '#4a5568', color: 'white', padding: '2px 6px', fontWeight: 'bold', fontSize: '8pt' }}>{section.title}</div>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '7.5pt' }}>
          <tbody>
            {section.fields.map((field) => {
              const val = data[field.key];
              if (!val) return null;
              return (
                <tr key={field.key}>
                  <td style={{ border: '1px solid #999', padding: '2px 4px', width: '25%', background: '#f5f6f8', fontWeight: 'bold', fontSize: '7pt' }}>{field.label}</td>
                  <td style={{ border: '1px solid #999', padding: '2px 4px', whiteSpace: 'pre-wrap', wordWrap: 'break-word' }}>{val}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    );
  });
}

function FaceSheetPrintView({ studentName, form, onClose }: { studentName: string; form: FaceSheetData; onClose: () => void }) {
  return (
    <>
      <div className="print:hidden mb-4 flex items-center justify-between">
        <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="arrow_back" size={16} />} onClick={onClose}>戻る</Button>
        <Button leftIcon={<MaterialIcon name="print" size={16} />} onClick={() => window.print()}>印刷する</Button>
      </div>
      <div className="bg-white print:m-0">
        <style>{`
          @media print {
            body { margin: 0; padding: 0; font-size: 8pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print\\:hidden { display: none !important; }
            @page { size: A4 portrait; margin: 10mm 12mm; }
          }
        `}</style>
        <div style={{ fontFamily: "'MS Gothic', 'Noto Sans JP', sans-serif", color: '#222', lineHeight: 1.3, fontSize: '8pt' }}>
          <div style={{ textAlign: 'center', borderBottom: '2px solid #1a1a1a', paddingBottom: '4px', marginBottom: '8px' }}>
            <h1 style={{ fontSize: '14pt', fontWeight: 700, margin: 0, letterSpacing: '2pt' }}>フェイスシート</h1>
            <div style={{ fontSize: '8pt', color: '#777' }}>放課後等デイサービス（横浜市青葉区 かけはし準拠）</div>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px', fontSize: '9pt' }}>
            <div><strong>児童氏名：</strong>{studentName}</div>
            <div><strong>記入日：</strong>{new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
          </div>

          <div style={{ fontSize: '9pt', fontWeight: 'bold', background: '#2c3e50', color: 'white', padding: '3px 8px', marginBottom: '4px' }}>日常生活のこと</div>
          {renderPrintSections(DAILY_LIFE_SECTIONS, form.daily_life || {})}

          {Object.keys(form.physical || {}).length > 0 && (
            <>
              <div style={{ fontSize: '9pt', fontWeight: 'bold', background: '#2c3e50', color: 'white', padding: '3px 8px', marginBottom: '4px', marginTop: '8px' }}>身体面</div>
              {renderPrintSections(PHYSICAL_SECTIONS, form.physical || {})}
            </>
          )}

          <div style={{ fontSize: '9pt', fontWeight: 'bold', background: '#2c3e50', color: 'white', padding: '3px 8px', marginBottom: '4px', marginTop: '8px' }}>性格・コミュニケーション</div>
          {renderPrintSections(PROFILE_SECTIONS, form.profile || {})}

          <div style={{ fontSize: '9pt', fontWeight: 'bold', background: '#2c3e50', color: 'white', padding: '3px 8px', marginBottom: '4px', marginTop: '8px' }}>配慮事項</div>
          {renderPrintSections(CONSIDERATION_SECTIONS, form.considerations || {})}

          {form.memo && (
            <div style={{ marginTop: '8px', pageBreakInside: 'avoid' }}>
              <div style={{ background: '#4a5568', color: 'white', padding: '2px 6px', fontWeight: 'bold', fontSize: '8pt' }}>MEMO</div>
              <div style={{ border: '1px solid #999', padding: '4px 6px', whiteSpace: 'pre-wrap', fontSize: '7.5pt', minHeight: '30px' }}>{form.memo}</div>
            </div>
          )}

          <div style={{ textAlign: 'right', marginTop: '6px', fontSize: '6pt', color: '#aaa' }}>
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
