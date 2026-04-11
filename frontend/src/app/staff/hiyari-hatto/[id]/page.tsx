'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface HiyariHattoDetail {
  id: number;
  classroom?: { id: number; classroom_name: string };
  student?: { id: number; student_name: string; grade_level?: string };
  reporter?: { id: number; full_name: string };
  confirmed_by?: { id: number; full_name: string };
  occurred_at: string;
  location: string | null;
  activity_before: string | null;
  student_condition: string | null;
  situation: string;
  severity: string;
  category: string | null;
  cause_environmental: string | null;
  cause_human: string | null;
  cause_other: string | null;
  immediate_response: string | null;
  guardian_notified: boolean;
  guardian_notified_at: string | null;
  guardian_notification_content: string | null;
  medical_treatment: boolean;
  medical_detail: string | null;
  injury_description: string | null;
  prevention_measures: string | null;
  environment_improvements: string | null;
  staff_sharing_notes: string | null;
  source_type: string;
  created_at: string;
}

const SEVERITY_LABELS: Record<string, string> = {
  low: '軽度 (ヒヤリ)',
  medium: '中度 (応急処置あり)',
  high: '重度 (医療機関受診)',
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
  allergy: 'アレルギー反応',
  missing: '行方不明・離設',
  conflict: '児童間トラブル',
  self_harm: '自傷行為',
  vehicle: '送迎・車両関連',
  medication: '投薬関連',
  other: 'その他',
};

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-[180px_1fr] gap-3 border-b border-[var(--neutral-stroke-3)] py-2">
      <div className="text-xs font-semibold text-[var(--neutral-foreground-3)]">{label}</div>
      <div className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{children}</div>
    </div>
  );
}

export default function HiyariHattoDetailPage() {
  const params = useParams();
  const router = useRouter();
  const { toast } = useToast();
  const id = Number(params.id);
  const [record, setRecord] = useState<HiyariHattoDetail | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const res = await api.get<{ data: HiyariHattoDetail }>(`/api/staff/hiyari-hatto/${id}`);
        setRecord(res.data.data);
      } catch {
        toast('取得に失敗しました', 'error');
      } finally {
        setLoading(false);
      }
    })();
  }, [id, toast]);

  const handleDelete = async () => {
    if (!window.confirm('本当に削除しますか？この操作は取り消せません。')) return;
    try {
      await api.delete(`/api/staff/hiyari-hatto/${id}`);
      toast('削除しました', 'success');
      router.push('/staff/hiyari-hatto');
    } catch {
      toast('削除に失敗しました', 'error');
    }
  };

  const handlePrint = () => {
    window.open(`/api/staff/hiyari-hatto/${id}/pdf`, '_blank');
  };

  if (loading) {
    return <div className="p-6 text-sm text-[var(--neutral-foreground-3)]">読み込み中...</div>;
  }

  if (!record) {
    return <div className="p-6 text-sm text-[var(--neutral-foreground-3)]">データが見つかりません</div>;
  }

  return (
    <div className="mx-auto max-w-4xl space-y-4 p-4">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <Link href="/staff/hiyari-hatto">
          <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="arrow_back" size={16} />}>
            一覧へ戻る
          </Button>
        </Link>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={handlePrint} leftIcon={<MaterialIcon name="print" size={16} />}>
            印刷 (PDF)
          </Button>
          <Button variant="ghost" size="sm" onClick={handleDelete} leftIcon={<MaterialIcon name="delete" size={16} />} className="text-[var(--status-danger-fg)]">
            削除
          </Button>
        </div>
      </div>

      <Card>
        <CardBody>
          <div className="flex items-center gap-3 flex-wrap mb-4">
            <h1 className="text-xl font-bold text-[var(--neutral-foreground-1)]">ヒヤリハット記録 #{record.id}</h1>
            <Badge variant={SEVERITY_VARIANTS[record.severity] ?? 'default'}>
              {SEVERITY_LABELS[record.severity] ?? record.severity}
            </Badge>
            {record.category && (
              <Badge variant="default">{CATEGORY_LABELS[record.category] ?? record.category}</Badge>
            )}
            {record.source_type === 'integrated_note_ai' && (
              <Badge variant="info">AI 検出</Badge>
            )}
          </div>

          <h2 className="mb-2 mt-3 text-sm font-bold text-[var(--brand-100)]">1. 基本情報</h2>
          <Row label="発生日時">
            {new Date(record.occurred_at).toLocaleString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
          </Row>
          <Row label="発生場所">{record.location ?? '—'}</Row>
          <Row label="対象児童">
            {record.student?.student_name ?? '—'}
            {record.student?.grade_level && ` (${record.student.grade_level})`}
          </Row>
          <Row label="事業所">{record.classroom?.classroom_name ?? '—'}</Row>
          <Row label="記録者">{record.reporter?.full_name ?? '—'}</Row>
          {record.confirmed_by && <Row label="確認者">{record.confirmed_by.full_name}</Row>}

          <h2 className="mb-2 mt-6 text-sm font-bold text-[var(--brand-100)]">2. 発生状況</h2>
          <Row label="発生前の活動">{record.activity_before ?? '—'}</Row>
          <Row label="児童の状態">{record.student_condition ?? '—'}</Row>
          <Row label="発生状況の詳細">{record.situation}</Row>

          <h2 className="mb-2 mt-6 text-sm font-bold text-[var(--brand-100)]">3. 原因分析</h2>
          <Row label="環境要因">{record.cause_environmental ?? '—'}</Row>
          <Row label="人的要因">{record.cause_human ?? '—'}</Row>
          <Row label="その他要因">{record.cause_other ?? '—'}</Row>

          <h2 className="mb-2 mt-6 text-sm font-bold text-[var(--brand-100)]">4. 対応</h2>
          <Row label="即時対応">{record.immediate_response ?? '—'}</Row>
          <Row label="怪我の有無・内容">{record.injury_description ?? '無し'}</Row>
          <Row label="医療機関受診">
            {record.medical_treatment ? '受診した' : '受診せず'}
            {record.medical_detail && ` (${record.medical_detail})`}
          </Row>
          <Row label="保護者連絡">
            {record.guardian_notified ? '連絡済み' : '未連絡'}
            {record.guardian_notified_at && ` (${new Date(record.guardian_notified_at).toLocaleString('ja-JP')})`}
            {record.guardian_notification_content && `\n${record.guardian_notification_content}`}
          </Row>

          <h2 className="mb-2 mt-6 text-sm font-bold text-[var(--brand-100)]">5. 再発防止策</h2>
          <Row label="改善策">{record.prevention_measures ?? '—'}</Row>
          <Row label="環境整備">{record.environment_improvements ?? '—'}</Row>
          <Row label="スタッフ間共有">{record.staff_sharing_notes ?? '—'}</Row>
        </CardBody>
      </Card>
    </div>
  );
}
