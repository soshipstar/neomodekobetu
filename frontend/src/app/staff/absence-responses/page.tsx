'use client';

import { useState, useCallback } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useQuery } from '@tanstack/react-query';
import { format, subMonths } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

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
}

export default function AbsenceResponsesPage() {
  const [dateFrom, setDateFrom] = useState(() => format(subMonths(new Date(), 1), 'yyyy-MM-dd'));
  const [dateTo, setDateTo] = useState(() => format(new Date(), 'yyyy-MM-dd'));

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['staff', 'absence-responses', dateFrom, dateTo],
    queryFn: async () => {
      const res = await api.get('/api/staff/absence-response/list', { params: { date_from: dateFrom, date_to: dateTo } });
      return res.data?.data?.data || [];
    },
  });

  const records: AbsenceResponseItem[] = data || [];

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
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="download" size={16} />} onClick={handleCsvDownload}>
            CSVダウンロード
          </Button>
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="refresh" size={16} />} onClick={() => refetch()} isLoading={isLoading}>
            更新
          </Button>
        </div>
      </div>

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
          {records.map((record) => (
            <Card key={record.id} className="transition-shadow hover:shadow-[var(--shadow-8)]">
              <CardBody>
                <div className="flex items-start justify-between gap-2 mb-2">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">{record.student?.student_name || '不明'}</span>
                    {record.student?.grade_level && <span className="text-xs text-[var(--neutral-foreground-3)]">{record.student.grade_level}</span>}
                    <Badge variant="info">{format(new Date(record.absence_date), 'M月d日(E)', { locale: ja })}</Badge>
                    {record.is_sent ? <Badge variant="success">送信済</Badge> : <Badge variant="warning">未送信</Badge>}
                  </div>
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
              </CardBody>
            </Card>
          ))}
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
