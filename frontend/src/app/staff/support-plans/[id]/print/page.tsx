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
            body { margin: 0; padding: 0; font-size: 9pt; }
            .print\\:hidden { display: none !important; }
            @page { size: A4 portrait; margin: 15mm 18mm; }
          }
        `}</style>

        <div style={{ fontFamily: 'sans-serif', color: '#222', lineHeight: 1.35 }}>
          {/* Header */}
          <div style={{ textAlign: 'center', borderBottom: '2.5px solid #2c3e50', paddingBottom: '8px', marginBottom: '12px' }}>
            <h1 style={{ fontSize: '14pt', color: '#2c3e50', margin: 0, letterSpacing: '2pt' }}>活動支援案</h1>
            <p style={{ fontSize: '7pt', color: '#777', marginTop: '2px' }}>放課後等デイサービス 活動計画書</p>
          </div>

          {/* Meta */}
          <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: '10px', fontSize: '9pt' }}>
            <tbody>
              <tr>
                <td style={{ padding: '3px 6px', border: '0.5px solid #aaa', fontWeight: 'bold', background: '#f5f6f8', width: '18%' }}>活動名</td>
                <td style={{ padding: '3px 6px', border: '0.5px solid #aaa' }}>{plan.activity_name}</td>
                <td style={{ padding: '3px 6px', border: '0.5px solid #aaa', fontWeight: 'bold', background: '#f5f6f8', width: '18%' }}>活動日</td>
                <td style={{ padding: '3px 6px', border: '0.5px solid #aaa' }}>{plan.activity_date || ''}</td>
              </tr>
              <tr>
                <td style={{ padding: '3px 6px', border: '0.5px solid #aaa', fontWeight: 'bold', background: '#f5f6f8' }}>総活動時間</td>
                <td style={{ padding: '3px 6px', border: '0.5px solid #aaa' }}>{plan.total_duration}分</td>
                <td style={{ padding: '3px 6px', border: '0.5px solid #aaa', fontWeight: 'bold', background: '#f5f6f8' }}>作成者</td>
                <td style={{ padding: '3px 6px', border: '0.5px solid #aaa' }}>{plan.staff?.full_name || ''}</td>
              </tr>
            </tbody>
          </table>

          {/* Sections */}
          {[
            { title: '活動の目的', content: plan.activity_purpose },
            { title: '活動の内容', content: plan.activity_content },
            { title: '五領域への配慮', content: plan.five_domains_consideration },
          ].map((s) => s.content ? (
            <div key={s.title} style={{ marginBottom: '8px' }}>
              <div style={{ background: '#34495e', color: 'white', padding: '3px 8px', fontWeight: 'bold', fontSize: '9pt' }}>{s.title}</div>
              <div style={{ border: '0.5px solid #aaa', borderTop: 'none', padding: '6px 8px', fontSize: '9pt', lineHeight: 1.35, whiteSpace: 'pre-wrap', wordWrap: 'break-word' }}>
                {nl(s.content)}
              </div>
            </div>
          ) : null)}

          {/* Schedule */}
          {schedule.length > 0 && (
            <div style={{ marginBottom: '8px' }}>
              <div style={{ background: '#34495e', color: 'white', padding: '3px 8px', fontWeight: 'bold', fontSize: '9pt' }}>活動スケジュール</div>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '8pt' }}>
                <thead>
                  <tr style={{ background: '#ecf0f1' }}>
                    <th style={{ border: '0.5px solid #888', padding: '2px 4px', width: '4%' }}>No</th>
                    <th style={{ border: '0.5px solid #888', padding: '2px 4px', width: '10%' }}>種別</th>
                    <th style={{ border: '0.5px solid #888', padding: '2px 4px', width: '22%' }}>活動名</th>
                    <th style={{ border: '0.5px solid #888', padding: '2px 4px', width: '8%' }}>時間</th>
                    <th style={{ border: '0.5px solid #888', padding: '2px 4px', width: '56%' }}>内容</th>
                  </tr>
                </thead>
                <tbody>
                  {schedule.map((item: any, i: number) => (
                    <tr key={i} style={{ background: item.type === 'routine' ? '#fef9e7' : '#eaf2f8' }}>
                      <td style={{ border: '0.5px solid #888', padding: '2px 4px', textAlign: 'center' }}>{i + 1}</td>
                      <td style={{ border: '0.5px solid #888', padding: '2px 4px', textAlign: 'center' }}>{item.type === 'routine' ? '毎日の支援' : '主活動'}</td>
                      <td style={{ border: '0.5px solid #888', padding: '2px 4px' }}>{item.name || ''}</td>
                      <td style={{ border: '0.5px solid #888', padding: '2px 4px', textAlign: 'center' }}>{item.duration || ''}分</td>
                      <td style={{ border: '0.5px solid #888', padding: '2px 4px', whiteSpace: 'pre-wrap', wordWrap: 'break-word', fontSize: '7.5pt', lineHeight: 1.25 }}>{nl(item.content)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {plan.other_notes && (
            <div style={{ marginBottom: '8px' }}>
              <div style={{ background: '#34495e', color: 'white', padding: '3px 8px', fontWeight: 'bold', fontSize: '9pt' }}>その他の注意点</div>
              <div style={{ border: '0.5px solid #aaa', borderTop: 'none', padding: '6px 8px', fontSize: '9pt', lineHeight: 1.35, whiteSpace: 'pre-wrap', wordWrap: 'break-word' }}>
                {nl(plan.other_notes)}
              </div>
            </div>
          )}

          <div style={{ textAlign: 'center', marginTop: '12px', paddingTop: '4px', borderTop: '0.5px solid #ccc', fontSize: '7pt', color: '#999' }}>
            出力日時: {new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}
          </div>
        </div>
      </div>
    </>
  );
}
