'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useWorkspace } from '@/hooks/useWorkspace';

interface BillingRow {
  student_id: number;
  student_name: string;
  beneficiary_number: string;
  municipality_code: string;
  disability_category: string;
  disability_grade: string;
  service_code: string;
  service_label: string;
  usage_days: number;
  monthly_usage_days_cap: number | null;
  total_units: number;
  unit_price: number;
  total_amount: number;
  public_share: number;
  user_copay: number;
  monthly_copay_cap: number;
  copay_management_provider: string | null;
  usage_dates: string[];
}

interface BillingSummary {
  student_count: number;
  total_usage_days: number;
  total_units: number;
  total_amount: number;
  total_public_share: number;
  total_user_copay: number;
}

function fmtYen(n: number): string {
  return `¥${Number(n ?? 0).toLocaleString('ja-JP')}`;
}

function currentYearMonth(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function BillingPage() {
  const { terms } = useWorkspace();
  const toast = useToast();
  const [yearMonth, setYearMonth] = useState(currentYearMonth());

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['staff', 'billing', 'summary', yearMonth],
    queryFn: async () => {
      const res = await api.get<{ data: { rows: BillingRow[]; summary: BillingSummary } }>('/api/staff/billing/summary', {
        params: { year_month: yearMonth },
      });
      return res.data.data;
    },
  });

  const downloadCsv = async () => {
    try {
      const res = await api.get('/api/staff/billing/csv', {
        params: { year_month: yearMonth },
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `billing-${yearMonth}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      toast.success('CSV をダウンロードしました');
    } catch {
      toast.error('CSV のダウンロードに失敗しました');
    }
  };

  const downloadProvisionRecord = async (studentId: number) => {
    try {
      const res = await api.get('/api/staff/billing/provision-record', {
        params: { year_month: yearMonth, student_id: studentId },
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `provision-record-${studentId}-${yearMonth}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF のダウンロードに失敗しました');
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">国保連請求</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          月次の利用実績を集計して国保連提出用 CSV を出力します。
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>対象月の選択</CardTitle>
        </CardHeader>
        <CardBody>
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">対象月</label>
              <input
                type="month"
                value={yearMonth}
                onChange={(e) => setYearMonth(e.target.value)}
                className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              />
            </div>
            <Button onClick={() => refetch()} variant="outline" leftIcon={<MaterialIcon name="refresh" size={16} />}>
              再集計
            </Button>
            <Button
              onClick={downloadCsv}
              disabled={!data || data.rows.length === 0}
              leftIcon={<MaterialIcon name="download" size={16} />}
            >
              CSV をダウンロード
            </Button>
          </div>
          <p className="mt-3 text-xs text-[var(--neutral-foreground-4)]">
            ※ 本機能は WAM-NET 標準インターフェースの完全準拠ではありません。実地指導前に各自治体の最新仕様書で
            カラム数・コードを確認してください。
          </p>
        </CardBody>
      </Card>

      {/* 集計サマリ */}
      {data && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <SummaryCard label={`対象${terms.client_plural}`} value={`${data.summary.student_count} 名`} />
          <SummaryCard label="延べ利用日数" value={`${data.summary.total_usage_days} 日`} />
          <SummaryCard label="合計単位数" value={data.summary.total_units.toLocaleString()} />
          <SummaryCard label="総費用" value={fmtYen(data.summary.total_amount)} highlight />
          <SummaryCard label="公費負担" value={fmtYen(data.summary.total_public_share)} />
          <SummaryCard label="利用者負担" value={fmtYen(data.summary.total_user_copay)} />
        </div>
      )}

      {/* 利用者別明細 */}
      <Card>
        <CardHeader>
          <CardTitle>利用者別 明細</CardTitle>
        </CardHeader>
        <CardBody>
          {isLoading ? (
            <p className="text-sm">読み込み中...</p>
          ) : !data || data.rows.length === 0 ? (
            <p className="text-sm text-[var(--neutral-foreground-4)]">
              この月は利用記録がありません。連絡帳に出席記録を登録してから再集計してください。
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
                    <th className="px-3 py-2 text-left">{terms.client}</th>
                    <th className="px-3 py-2 text-left">受給者証番号</th>
                    <th className="px-3 py-2 text-left">市町村</th>
                    <th className="px-3 py-2 text-left">障害区分</th>
                    <th className="px-3 py-2 text-right">利用日数</th>
                    <th className="px-3 py-2 text-right">合計単位</th>
                    <th className="px-3 py-2 text-right">総費用</th>
                    <th className="px-3 py-2 text-right">公費負担</th>
                    <th className="px-3 py-2 text-right">利用者負担</th>
                    <th className="px-3 py-2 text-right">月額上限</th>
                    <th className="px-3 py-2 text-center">実績記録票</th>
                  </tr>
                </thead>
                <tbody>
                  {data.rows.map((r) => (
                    <tr key={r.student_id} className="border-b border-[var(--neutral-stroke-3)]">
                      <td className="px-3 py-2">{r.student_name}</td>
                      <td className="px-3 py-2 font-mono text-xs">{r.beneficiary_number}</td>
                      <td className="px-3 py-2 font-mono text-xs">{r.municipality_code || '-'}</td>
                      <td className="px-3 py-2 text-xs">{r.disability_grade || '-'}</td>
                      <td className="px-3 py-2 text-right">{r.usage_days} 日</td>
                      <td className="px-3 py-2 text-right">{r.total_units.toLocaleString()}</td>
                      <td className="px-3 py-2 text-right font-mono">{fmtYen(r.total_amount)}</td>
                      <td className="px-3 py-2 text-right font-mono">{fmtYen(r.public_share)}</td>
                      <td className="px-3 py-2 text-right font-mono">{fmtYen(r.user_copay)}</td>
                      <td className="px-3 py-2 text-right text-xs">
                        {r.monthly_copay_cap > 0 ? fmtYen(r.monthly_copay_cap) : '-'}
                      </td>
                      <td className="px-3 py-2 text-center">
                        <button
                          onClick={() => downloadProvisionRecord(r.student_id)}
                          className="inline-flex items-center gap-1 rounded border border-[var(--brand-80)] px-2 py-0.5 text-xs text-[var(--brand-70)] hover:bg-[var(--brand-160)]"
                          title="サービス提供実績記録票 PDF"
                        >
                          <MaterialIcon name="picture_as_pdf" size={12} />
                          PDF
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t-2 border-[var(--neutral-stroke-1)] font-bold">
                    <td className="px-3 py-2" colSpan={4}>合計</td>
                    <td className="px-3 py-2 text-right">{data.summary.total_usage_days} 日</td>
                    <td className="px-3 py-2 text-right">{data.summary.total_units.toLocaleString()}</td>
                    <td className="px-3 py-2 text-right font-mono">{fmtYen(data.summary.total_amount)}</td>
                    <td className="px-3 py-2 text-right font-mono">{fmtYen(data.summary.total_public_share)}</td>
                    <td className="px-3 py-2 text-right font-mono">{fmtYen(data.summary.total_user_copay)}</td>
                    <td className="px-3 py-2"></td>
                    <td className="px-3 py-2"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

function SummaryCard({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
  return (
    <div
      className={`rounded-lg border p-3 ${
        highlight
          ? 'border-[var(--brand-80)] bg-[var(--brand-160)]'
          : 'border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)]'
      }`}
    >
      <div className="text-xs text-[var(--neutral-foreground-3)]">{label}</div>
      <div className={`mt-1 text-base font-bold ${highlight ? 'text-[var(--brand-70)]' : 'text-[var(--neutral-foreground-1)]'}`}>
        {value}
      </div>
    </div>
  );
}
