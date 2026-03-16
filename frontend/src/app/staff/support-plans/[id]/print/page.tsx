'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { Printer, ChevronLeft, Download } from 'lucide-react';
import Link from 'next/link';

function nl(t: string | null | undefined): string {
  if (!t) return '';
  return t.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

export default function SupportPlanPrintPage() {
  const params = useParams();
  const id = params.id as string;

  const { data: plan, isLoading } = useQuery({
    queryKey: ['staff', 'support-plan-print', id],
    queryFn: async () => {
      const res = await api.get(`/api/staff/activity-support-plans/${id}`);
      return res.data?.data;
    },
  });

  if (isLoading) {
    return <div className="mx-auto max-w-4xl p-8 space-y-4"><Skeleton className="h-8 w-64" /><Skeleton className="h-60 w-full" /></div>;
  }

  if (!plan) return <div className="p-8 text-center">支援案が見つかりません</div>;

  const handlePdf = async () => {
    try {
      const res = await api.get(`/api/staff/activity-support-plans/${id}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `support_plan_${id}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch { /* ignore */ }
  };

  const schedule = Array.isArray(plan.activity_schedule) ? plan.activity_schedule : [];

  return (
    <>
      <div className="print:hidden mb-4 flex items-center justify-between">
        <Link href="/staff/support-plans">
          <Button variant="ghost" size="sm" leftIcon={<ChevronLeft className="h-4 w-4" />}>支援案一覧に戻る</Button>
        </Link>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" leftIcon={<Download className="h-4 w-4" />} onClick={handlePdf}>PDFダウンロード</Button>
          <Button leftIcon={<Printer className="h-4 w-4" />} onClick={() => window.print()}>印刷する</Button>
        </div>
      </div>

      <div className="mx-auto max-w-4xl bg-white print:max-w-none print:m-0">
        <style>{`
          @media print {
            body { margin: 0; padding: 0; }
            .print\\:hidden { display: none !important; }
            @page { size: A4 portrait; margin: 15mm 18mm; }
          }
          .pp { font-family: 'Hiragino Kaku Gothic Pro', 'Noto Sans JP', sans-serif; color: #333; line-height: 1.5; }
          .pp-header { text-align: center; margin-bottom: 20px; }
          .pp-header h1 { font-size: 18pt; font-weight: 700; color: #1a1a1a; letter-spacing: 4pt; border-bottom: 3px double #1a1a1a; display: inline-block; padding-bottom: 4px; }
          .pp-header-sub { font-size: 9pt; color: #888; margin-top: 4px; }
          .pp-meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
          .pp-meta td { padding: 5px 10px; font-size: 10pt; border: 1px solid #ccc; }
          .pp-meta .lbl { background: #f8f9fa; font-weight: 600; color: #555; width: 15%; white-space: nowrap; }
          .pp-tags { margin-bottom: 12px; }
          .pp-tag { display: inline-block; background: #e9ecef; color: #495057; padding: 2px 10px; border-radius: 12px; font-size: 9pt; margin: 0 4px 4px 0; }
          .pp-sec { margin-bottom: 16px; page-break-inside: avoid; }
          .pp-sec-head { font-size: 11pt; font-weight: 700; color: #2c3e50; border-left: 4px solid #3498db; padding: 4px 0 4px 10px; margin-bottom: 6px; background: #f8f9fa; }
          .pp-sec-body { font-size: 10pt; line-height: 1.7; padding: 10px 14px; border: 1px solid #dee2e6; border-radius: 4px; background: #fff; white-space: pre-wrap; word-wrap: break-word; }
          .pp-sched { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 9.5pt; }
          .pp-sched th { background: #2c3e50; color: #fff; font-weight: 600; padding: 6px 8px; text-align: center; }
          .pp-sched td { border: 1px solid #dee2e6; padding: 6px 8px; vertical-align: top; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; }
          .pp-sched .routine { background: #fff8e1; }
          .pp-sched .main { background: #e3f2fd; }
          .pp-footer { margin-top: 20px; text-align: right; font-size: 8pt; color: #aaa; }
        `}</style>

        <div className="pp">
          {/* Header */}
          <div className="pp-header">
            <h1>活動支援案</h1>
            <div className="pp-header-sub">放課後等デイサービス 活動計画書</div>
          </div>

          {/* Meta */}
          <table className="pp-meta">
            <tbody>
              <tr>
                <td className="lbl">活動名</td>
                <td>{plan.activity_name}</td>
                <td className="lbl">活動日</td>
                <td>{plan.activity_date || ''}</td>
              </tr>
              <tr>
                <td className="lbl">総活動時間</td>
                <td>{plan.total_duration}分</td>
                <td className="lbl">作成者</td>
                <td>{plan.staff?.full_name || ''}</td>
              </tr>
            </tbody>
          </table>

          {/* Tags */}
          {plan.tags && (
            <div className="pp-tags">
              {plan.tags.split(',').map((tag: string, i: number) => (
                <span key={i} className="pp-tag">{tag.trim()}</span>
              ))}
            </div>
          )}

          {/* Sections */}
          {[
            { title: '活動の目的', content: plan.activity_purpose },
            { title: '活動の内容', content: plan.activity_content },
            { title: '五領域への配慮', content: plan.five_domains_consideration },
          ].map((s) => s.content ? (
            <div key={s.title} className="pp-sec">
              <div className="pp-sec-head">{s.title}</div>
              <div className="pp-sec-body">{nl(s.content)}</div>
            </div>
          ) : null)}

          {/* Schedule */}
          {schedule.length > 0 && (
            <div className="pp-sec">
              <div className="pp-sec-head">活動スケジュール</div>
              <table className="pp-sched">
                <thead>
                  <tr>
                    <th style={{ width: '30px' }}>No</th>
                    <th style={{ width: '70px' }}>種別</th>
                    <th>活動名</th>
                    <th style={{ width: '50px' }}>時間</th>
                    <th>内容</th>
                  </tr>
                </thead>
                <tbody>
                  {schedule.map((item: any, i: number) => (
                    <tr key={i} className={item.type === 'routine' ? 'routine' : 'main'}>
                      <td style={{ textAlign: 'center' }}>{i + 1}</td>
                      <td style={{ textAlign: 'center' }}>{item.type === 'routine' ? '毎日の支援' : '主活動'}</td>
                      <td>{item.name || ''}</td>
                      <td style={{ textAlign: 'center' }}>{item.duration || ''}分</td>
                      <td>{nl(item.content)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Other notes */}
          {plan.other_notes && (
            <div className="pp-sec">
              <div className="pp-sec-head">その他の注意点</div>
              <div className="pp-sec-body">{nl(plan.other_notes)}</div>
            </div>
          )}

          <div className="pp-footer">
            出力日時: {new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}
          </div>
        </div>
      </div>
    </>
  );
}
