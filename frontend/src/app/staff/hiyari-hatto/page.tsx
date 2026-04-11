'use client';

import { useEffect, useState, useCallback } from 'react';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

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

export default function HiyariHattoListPage() {
  const { toast } = useToast();
  const [records, setRecords] = useState<HiyariHattoRecord[]>([]);
  const [meta, setMeta] = useState<Meta | null>(null);
  const [loading, setLoading] = useState(true);
  const [severity, setSeverity] = useState<string>('');
  const [page, setPage] = useState(1);

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

  useEffect(() => {
    fetchList();
  }, [fetchList]);

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
    </div>
  );
}
