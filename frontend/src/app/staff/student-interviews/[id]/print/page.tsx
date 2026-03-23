'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { Printer, ChevronLeft, Download } from 'lucide-react';
import Link from 'next/link';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

function nl(t: string | null | undefined): string {
  if (!t) return '';
  return t.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

export default function InterviewPrintPage() {
  const params = useParams();
  const id = params.id as string;

  const { data: interview, isLoading } = useQuery({
    queryKey: ['staff', 'interview-print', id],
    queryFn: async () => {
      const res = await api.get(`/api/staff/student-interviews/${id}`);
      return res.data?.data;
    },
  });

  if (isLoading) {
    return <div className="mx-auto max-w-3xl p-8 space-y-4"><Skeleton className="h-8 w-64" /><Skeleton className="h-60 w-full" /></div>;
  }

  if (!interview) {
    return <div className="p-8 text-center">面談記録が見つかりません</div>;
  }

  const handlePdf = async () => {
    try {
      const res = await api.get(`/api/staff/student-interviews/${id}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `interview_${id}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch { /* ignore */ }
  };

  return (
    <>
      {/* Screen-only toolbar */}
      <div className="print:hidden mb-4 flex items-center justify-between">
        <Link href="/staff/student-interviews">
          <Button variant="ghost" size="sm" leftIcon={<ChevronLeft className="h-4 w-4" />}>面談記録に戻る</Button>
        </Link>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" leftIcon={<Download className="h-4 w-4" />} onClick={handlePdf}>PDFダウンロード</Button>
          <Button leftIcon={<Printer className="h-4 w-4" />} onClick={() => window.print()}>印刷する</Button>
        </div>
      </div>

      {/* Printable content */}
      <div className="mx-auto max-w-3xl bg-white print:max-w-none print:m-0 print:p-0">
        <style>{`
          @media print {
            body { margin: 0; padding: 15mm 18mm; font-size: 10pt; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .print\\:hidden { display: none !important; }
            @page { size: A4 portrait; margin: 15mm 18mm; }
          }
          .print-body { font-family: sans-serif; color: #222; line-height: 1.4; }
          .print-header { text-align: center; border-bottom: 2.5px solid #2c3e50; padding-bottom: 8px; margin-bottom: 16px; }
          .print-header h1 { font-size: 16pt; color: #2c3e50; margin: 0; }
          .print-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 16px; font-size: 10pt; }
          .print-meta-item { padding: 4px 0; }
          .print-meta-label { font-weight: bold; color: #555; font-size: 9pt; }
          .print-section { margin-bottom: 14px; page-break-inside: avoid; }
          .print-section-title { background: #34495e; color: white; padding: 4px 10px; font-weight: bold; font-size: 10pt; margin-bottom: 0; }
          .print-section-content { border: 1px solid #aaa; border-top: none; padding: 8px 10px; font-size: 10pt; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; }
          .print-check-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 14px; }
          @media print { .print-check-grid { grid-template-columns: repeat(3, 1fr); } }
          .print-check-item { border: 1px solid #ccc; border-radius: 4px; padding: 8px; }
          .print-check-label { font-weight: bold; font-size: 9pt; color: #555; margin-bottom: 4px; }
          .print-footer { text-align: center; margin-top: 20px; padding-top: 6px; border-top: 1px solid #ccc; font-size: 8pt; color: #999; }
        `}</style>

        <div className="print-body">
          <div className="print-header">
            <h1>面談記録</h1>
          </div>

          <div className="print-meta">
            <div className="print-meta-item">
              <div className="print-meta-label">児童氏名</div>
              <div>{interview.student?.student_name || ''}</div>
            </div>
            <div className="print-meta-item">
              <div className="print-meta-label">面談日</div>
              <div>{interview.interview_date ? format(new Date(interview.interview_date), 'yyyy年M月d日 (E)', { locale: ja }) : ''}</div>
            </div>
            <div className="print-meta-item">
              <div className="print-meta-label">面談者</div>
              <div>{interview.interviewer?.full_name || ''}</div>
            </div>
          </div>

          {interview.interview_content && (
            <div className="print-section">
              <div className="print-section-title">面談内容</div>
              <div className="print-section-content">{nl(interview.interview_content)}</div>
            </div>
          )}

          {interview.child_wish && (
            <div className="print-section">
              <div className="print-section-title">児童の願い</div>
              <div className="print-section-content">{nl(interview.child_wish)}</div>
            </div>
          )}

          <div className="print-check-grid">
            {interview.check_school && (
              <div className="print-check-item">
                <div className="print-check-label">学校での様子</div>
                <div style={{ fontSize: '9pt', whiteSpace: 'pre-wrap' }}>{nl(interview.check_school_notes) || '（詳細なし）'}</div>
              </div>
            )}
            {interview.check_home && (
              <div className="print-check-item">
                <div className="print-check-label">家庭での様子</div>
                <div style={{ fontSize: '9pt', whiteSpace: 'pre-wrap' }}>{nl(interview.check_home_notes) || '（詳細なし）'}</div>
              </div>
            )}
            {interview.check_troubles && (
              <div className="print-check-item">
                <div className="print-check-label">困りごと・悩み</div>
                <div style={{ fontSize: '9pt', whiteSpace: 'pre-wrap' }}>{nl(interview.check_troubles_notes) || '（詳細なし）'}</div>
              </div>
            )}
          </div>

          {interview.other_notes && (
            <div className="print-section">
              <div className="print-section-title">その他備考</div>
              <div className="print-section-content">{nl(interview.other_notes)}</div>
            </div>
          )}

          <div className="print-footer">
            出力日時: {new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}
          </div>
        </div>
      </div>
    </>
  );
}
