'use client';

import { useEffect, useState, useCallback } from 'react';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';
import { useAuthStore } from '@/stores/authStore';

interface HiyariHattoRecord {
  id: number;
  classroom_id: number;
  student_id: number | null;
  student?: { id: number; student_name: string } | null;
  reporter?: { id: number; full_name: string } | null;
  occurred_at: string;
  location: string | null;
  situation: string;
  severity: string;
  category: string | null;
  created_at: string;
}

interface Meta {
  current_page: number;
  last_page: number;
  total: number;
}

const SEVERITY_LABELS: Record<string, string> = {
  low: '軽度',
  medium: '中度',
  high: '重度',
};

const SEVERITY_VARIANTS: Record<string, 'success' | 'warning' | 'danger'> = {
  low: 'success',
  medium: 'warning',
  high: 'danger',
};

const CATEGORY_LABELS: Record<string, string> = {
  fall: '転倒・転落',
  collision: '衝突・接触',
  choking: '誤嚥・窒息',
  ingestion: '誤食・異物摂取',
  allergy: 'アレルギー',
  missing: '行方不明・離設',
  conflict: '児童間トラブル',
  self_harm: '自傷行為',
  vehicle: '送迎・車両',
  medication: '投薬関連',
  other: 'その他',
};

interface StudentOption {
  id: number;
  student_name: string;
}

export default function HiyariHattoListPage() {
  const { toast } = useToast();
  const { user } = useAuthStore();
  const [records, setRecords] = useState<HiyariHattoRecord[]>([]);
  const [meta, setMeta] = useState<Meta | null>(null);
  const [loading, setLoading] = useState(true);
  const [severity, setSeverity] = useState<string>('');
  const [page, setPage] = useState(1);
  const [createOpen, setCreateOpen] = useState(false);
  const [students, setStudents] = useState<StudentOption[]>([]);

  const fetchList = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, string | number> = { page, per_page: 30 };
      if (severity) params.severity = severity;
      const res = await api.get('/api/staff/hiyari-hatto', { params });
      setRecords(res.data.data.data || []);
      setMeta({
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        total: res.data.data.total,
      });
    } catch {
      toast('ヒヤリハット一覧の取得に失敗しました', 'error');
    } finally {
      setLoading(false);
    }
  }, [page, severity, toast]);

  useEffect(() => { fetchList(); }, [fetchList]);

  useEffect(() => {
    (async () => {
      try {
        const res = await api.get('/api/staff/students', { params: { per_page: 200 } });
        const list = res.data?.data?.data || res.data?.data || [];
        setStudents(list.map((s: { id: number; student_name: string }) => ({ id: s.id, student_name: s.student_name })));
      } catch { /* ignore */ }
    })();
  }, []);

  const columns: Column<HiyariHattoRecord>[] = [
    {
      key: 'occurred_at',
      label: '発生日時',
      render: (r) => new Date(r.occurred_at).toLocaleString('ja-JP', {
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
      }),
    },
    {
      key: 'student',
      label: '対象児童',
      render: (r) => r.student?.student_name ?? '—',
    },
    {
      key: 'severity',
      label: '危険度',
      render: (r) => (
        <Badge variant={SEVERITY_VARIANTS[r.severity] ?? 'default'}>
          {SEVERITY_LABELS[r.severity] ?? r.severity}
        </Badge>
      ),
    },
    {
      key: 'category',
      label: '分類',
      render: (r) => r.category ? (CATEGORY_LABELS[r.category] ?? r.category) : '—',
    },
    {
      key: 'location',
      label: '場所',
      render: (r) => r.location ?? '—',
    },
    {
      key: 'situation',
      label: '状況',
      render: (r) => (
        <span className="line-clamp-2 text-xs text-[var(--neutral-foreground-3)]">
          {r.situation}
        </span>
      ),
    },
    {
      key: 'reporter',
      label: '記録者',
      render: (r) => r.reporter?.full_name ?? '—',
    },
    {
      key: 'actions',
      label: '操作',
      render: (r) => (
        <div className="flex gap-1">
          <Link href={`/staff/hiyari-hatto/${r.id}`}>
            <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="visibility" size={14} />}>
              詳細
            </Button>
          </Link>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">ヒヤリハット一覧</h1>
          <p className="text-xs text-[var(--neutral-foreground-3)] mt-1">
            危険事象・事故未遂の記録。連絡帳の統合ボタンから AI が検出した候補も登録できます。
          </p>
        </div>
        <Button
          variant="primary"
          onClick={() => setCreateOpen(true)}
          leftIcon={<MaterialIcon name="add" size={16} />}
        >
          新規作成
        </Button>
      </div>

      <Card>
        <CardBody>
          <div className="flex items-center gap-3 flex-wrap">
            <label className="text-sm font-medium">危険度フィルタ</label>
            <select
              value={severity}
              onChange={(e) => { setSeverity(e.target.value); setPage(1); }}
              className="rounded border border-[var(--neutral-stroke-2)] px-3 py-1.5 text-sm"
            >
              <option value="">すべて</option>
              <option value="low">軽度</option>
              <option value="medium">中度</option>
              <option value="high">重度</option>
            </select>
            {meta && (
              <span className="ml-auto text-xs text-[var(--neutral-foreground-3)]">
                {meta.total} 件 / {meta.current_page} 頁目 / 全 {meta.last_page} 頁
              </span>
            )}
          </div>
        </CardBody>
      </Card>

      {loading ? (
        <SkeletonTable rows={6} cols={7} />
      ) : (
        <Table
          columns={columns}
          data={records}
          keyExtractor={(r) => r.id}
          currentPage={meta?.current_page}
          totalPages={meta?.last_page}
          onPageChange={setPage}
          emptyMessage="ヒヤリハット記録がありません"
        />
      )}

      {/* 新規作成モーダル */}
      {createOpen && user?.classroom_id && (
        <HiyariHattoCreateModal
          classroomId={user.classroom_id}
          students={students}
          onClose={() => setCreateOpen(false)}
          onCreated={() => {
            setCreateOpen(false);
            fetchList();
          }}
        />
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// 新規作成モーダル
// ---------------------------------------------------------------------------

function HiyariHattoCreateModal({
  classroomId,
  students,
  onClose,
  onCreated,
}: {
  classroomId: number;
  students: StudentOption[];
  onClose: () => void;
  onCreated: () => void;
}) {
  const { toast } = useToast();
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    student_id: '',
    occurred_at: new Date().toISOString().slice(0, 16),
    location: '',
    activity_before: '',
    student_condition: '',
    situation: '',
    severity: 'low',
    category: 'other',
    cause_environmental: '',
    cause_human: '',
    cause_other: '',
    immediate_response: '',
    injury_description: '',
    medical_treatment: false,
    guardian_notified: false,
    guardian_notification_content: '',
    prevention_measures: '',
    environment_improvements: '',
    staff_sharing_notes: '',
  });

  const handleSubmit = async () => {
    if (!form.situation.trim()) {
      toast('発生状況は必須です', 'error');
      return;
    }
    setSaving(true);
    try {
      await api.post('/api/staff/hiyari-hatto', {
        classroom_id: classroomId,
        student_id: form.student_id || null,
        occurred_at: form.occurred_at,
        location: form.location || null,
        activity_before: form.activity_before || null,
        student_condition: form.student_condition || null,
        situation: form.situation,
        severity: form.severity,
        category: form.category,
        cause_environmental: form.cause_environmental || null,
        cause_human: form.cause_human || null,
        cause_other: form.cause_other || null,
        immediate_response: form.immediate_response || null,
        injury_description: form.injury_description || null,
        medical_treatment: form.medical_treatment,
        guardian_notified: form.guardian_notified,
        guardian_notification_content: form.guardian_notification_content || null,
        prevention_measures: form.prevention_measures || null,
        environment_improvements: form.environment_improvements || null,
        staff_sharing_notes: form.staff_sharing_notes || null,
        source_type: 'manual',
      });
      toast('ヒヤリハットを記録しました', 'success');
      onCreated();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '登録に失敗しました';
      toast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  const inputCls = 'block w-full rounded border border-[var(--neutral-stroke-2)] bg-white px-3 py-1.5 text-sm';
  const labelCls = 'mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]';

  return (
    <Modal isOpen={true} onClose={onClose} title="ヒヤリハット 新規作成" size="lg">
      <div className="space-y-4 max-h-[70vh] overflow-y-auto">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label className={labelCls}>対象児童</label>
            <select value={form.student_id} onChange={(e) => setForm({ ...form, student_id: e.target.value })} className={inputCls}>
              <option value="">選択しない</option>
              {students.map((s) => <option key={s.id} value={s.id}>{s.student_name}</option>)}
            </select>
          </div>
          <div>
            <label className={labelCls}>発生日時 *</label>
            <input type="datetime-local" value={form.occurred_at} onChange={(e) => setForm({ ...form, occurred_at: e.target.value })} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>発生場所</label>
            <input type="text" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} placeholder="例: プレイルーム" className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>危険度 *</label>
            <select value={form.severity} onChange={(e) => setForm({ ...form, severity: e.target.value })} className={inputCls}>
              {Object.entries(SEVERITY_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </div>
          <div className="sm:col-span-2">
            <label className={labelCls}>事故分類</label>
            <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className={inputCls}>
              {Object.entries(CATEGORY_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </div>
        </div>

        <div>
          <label className={labelCls}>発生前の活動</label>
          <textarea value={form.activity_before} onChange={(e) => setForm({ ...form, activity_before: e.target.value })} rows={2} className={inputCls} />
        </div>
        <div>
          <label className={labelCls}>児童の状態</label>
          <textarea value={form.student_condition} onChange={(e) => setForm({ ...form, student_condition: e.target.value })} rows={2} className={inputCls} />
        </div>
        <div>
          <label className={labelCls}>発生状況 *</label>
          <textarea value={form.situation} onChange={(e) => setForm({ ...form, situation: e.target.value })} rows={3} className={inputCls} placeholder="何が、どのように起こったか" />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label className={labelCls}>環境要因</label>
            <textarea value={form.cause_environmental} onChange={(e) => setForm({ ...form, cause_environmental: e.target.value })} rows={2} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>人的要因</label>
            <textarea value={form.cause_human} onChange={(e) => setForm({ ...form, cause_human: e.target.value })} rows={2} className={inputCls} />
          </div>
        </div>

        <div>
          <label className={labelCls}>即時対応</label>
          <textarea value={form.immediate_response} onChange={(e) => setForm({ ...form, immediate_response: e.target.value })} rows={2} className={inputCls} />
        </div>
        <div>
          <label className={labelCls}>怪我の有無・内容</label>
          <textarea value={form.injury_description} onChange={(e) => setForm({ ...form, injury_description: e.target.value })} rows={2} className={inputCls} placeholder="無ければ空欄" />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <label className="flex items-center gap-2 rounded border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm cursor-pointer">
            <input type="checkbox" checked={form.medical_treatment} onChange={(e) => setForm({ ...form, medical_treatment: e.target.checked })} />
            医療機関受診
          </label>
          <label className="flex items-center gap-2 rounded border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm cursor-pointer">
            <input type="checkbox" checked={form.guardian_notified} onChange={(e) => setForm({ ...form, guardian_notified: e.target.checked })} />
            保護者連絡済み
          </label>
        </div>

        {form.guardian_notified && (
          <div>
            <label className={labelCls}>保護者連絡の内容</label>
            <textarea value={form.guardian_notification_content} onChange={(e) => setForm({ ...form, guardian_notification_content: e.target.value })} rows={2} className={inputCls} />
          </div>
        )}

        <div>
          <label className={labelCls}>再発防止策</label>
          <textarea value={form.prevention_measures} onChange={(e) => setForm({ ...form, prevention_measures: e.target.value })} rows={2} className={inputCls} />
        </div>
        <div>
          <label className={labelCls}>環境整備・改善</label>
          <textarea value={form.environment_improvements} onChange={(e) => setForm({ ...form, environment_improvements: e.target.value })} rows={2} className={inputCls} />
        </div>
        <div>
          <label className={labelCls}>スタッフ間共有事項</label>
          <textarea value={form.staff_sharing_notes} onChange={(e) => setForm({ ...form, staff_sharing_notes: e.target.value })} rows={2} className={inputCls} />
        </div>
      </div>

      <div className="flex justify-end gap-2 pt-4 border-t border-[var(--neutral-stroke-2)] mt-4">
        <Button variant="outline" onClick={onClose}>キャンセル</Button>
        <Button
          variant="primary"
          onClick={handleSubmit}
          isLoading={saving}
          disabled={!form.situation.trim()}
          leftIcon={<MaterialIcon name="save" size={16} />}
        >
          登録
        </Button>
      </div>
    </Modal>
  );
}
