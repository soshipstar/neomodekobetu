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

function formatDateJa(dateStr: string | null | undefined): string {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '';
  return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`;
}

export default function SupportPlanPrintPage() {
  const params = useParams();
  const id = params.id as string;

  const { data: plan, isLoading } = useQuery({
    queryKey: ['staff', 'support-plan-print', id],
    queryFn: async () => {
      const res = await api.get(`/api/staff/support-plans/${id}`);
      return res.data?.data;
    },
  });

  if (isLoading) {
    return <div className="mx-auto max-w-4xl p-8 space-y-4"><Skeleton className="h-8 w-64" /><Skeleton className="h-60 w-full" /></div>;
  }

  if (!plan) return <div className="p-8 text-center">個別支援計画書が見つかりません</div>;

  const handlePdf = async () => {
    try {
      const res = await api.get(`/api/staff/support-plans/${id}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `kobetsu_plan_${id}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch { /* ignore */ }
  };

  const details = Array.isArray(plan.details) ? plan.details : [];

  return (
    <>
      <div className="print:hidden mb-4 flex items-center justify-between">
        <Link href="/staff/support-plans">
          <Button variant="ghost" size="sm" leftIcon={<ChevronLeft className="h-4 w-4" />}>計画書一覧に戻る</Button>
        </Link>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" leftIcon={<Download className="h-4 w-4" />} onClick={handlePdf}>PDFダウンロード</Button>
          <Button leftIcon={<Printer className="h-4 w-4" />} onClick={() => window.print()}>印刷する</Button>
        </div>
      </div>

      <div className="mx-auto max-w-5xl bg-white print:max-w-none print:m-0">
        <style>{`
          @media print {
            body { margin: 0; padding: 0; }
            .print\\:hidden { display: none !important; }
            @page { size: A3 landscape; margin: 8mm; }
          }
          .pp { font-family: 'MS Gothic', 'Noto Sans JP', monospace; color: #333; line-height: 1.3; font-size: 10pt; }
          .pp-header { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; }
          .pp-header h1 { font-size: 18pt; font-weight: 700; margin: 0; }
          .pp-meta { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 10pt; }
          .pp-meta-item { margin-right: 15px; }
          .pp-meta-label { font-weight: bold; display: inline; }
          .pp-two-col { display: flex; gap: 15px; margin-bottom: 15px; }
          .pp-two-col > div { flex: 1; }
          .pp-sec { margin-bottom: 15px; page-break-inside: avoid; }
          .pp-sec-head { background: #4a5568; color: white; padding: 5px 10px; font-weight: bold; font-size: 12pt; margin-bottom: 8px; }
          .pp-sec-body { padding: 8px; border: 1px solid #ccc; min-height: 50px; white-space: pre-wrap; word-wrap: break-word; font-size: 10pt; line-height: 1.5; }
          .pp-goal-header { display: flex; align-items: center; margin-bottom: 5px; }
          .pp-goal-title { font-weight: bold; margin-right: 10px; }
          .pp-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9pt; }
          .pp-table th, .pp-table td { border: 1px solid #333; padding: 4px 6px; text-align: left; vertical-align: top; }
          .pp-table th { background: #e2e8f0; font-weight: bold; text-align: center; }
          .pp-table td { white-space: pre-wrap; min-height: 40px; line-height: 1.5; }
          .cat-honin { background: #f7fafc; }
          .cat-kazoku { background: #dbeafe; }
          .cat-chiiki { background: #d1fae5; }
          .pp-sig { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #333; }
          .pp-sig-center { display: flex; gap: 30px; flex: 1; justify-content: center; align-items: center; }
          .pp-sig-item { display: flex; align-items: center; gap: 10px; }
          .pp-sig-label { font-weight: bold; font-size: 10pt; white-space: nowrap; }
          .pp-sig-content { display: flex; align-items: center; gap: 5px; border-bottom: 1px solid #333; min-width: 150px; padding: 3px 5px; }
          .pp-sig-img { max-height: 40px; max-width: 120px; }
          .pp-sig-name { font-size: 9pt; }
          .pp-issuer { text-align: right; min-width: 200px; }
          .pp-issuer-name { font-size: 11pt; font-weight: bold; }
          .pp-issuer-details { font-size: 9pt; color: #333; }
          .pp-footer { margin-top: 20px; text-align: right; font-size: 8pt; color: #aaa; }
        `}</style>

        <div className="pp">
          {/* Header */}
          <div className="pp-header">
            <h1>個別支援計画書</h1>
          </div>

          {/* Meta */}
          <div className="pp-meta">
            <div className="pp-meta-item">
              <span className="pp-meta-label">児童氏名：</span>
              <span>{plan.student_name || plan.student?.student_name || ''}</span>
            </div>
            <div className="pp-meta-item">
              <span className="pp-meta-label">同意日：</span>
              <span>{formatDateJa(plan.consent_date)}</span>
            </div>
          </div>

          {/* Life Intention & Overall Policy (2-column) */}
          <div className="pp-two-col">
            <div className="pp-sec">
              <div className="pp-sec-head">利用児及び家族の生活に対する意向</div>
              <div className="pp-sec-body">{nl(plan.life_intention)}</div>
            </div>
            <div className="pp-sec">
              <div className="pp-sec-head">総合的な支援の方針</div>
              <div className="pp-sec-body">{nl(plan.overall_policy)}</div>
            </div>
          </div>

          {/* Long-term & Short-term Goals (2-column) */}
          <div className="pp-two-col">
            <div className="pp-sec">
              <div className="pp-sec-head">長期目標</div>
              <div className="pp-goal-header">
                <span className="pp-goal-title">達成時期：</span>
                <span>{formatDateJa(plan.long_term_goal_date)}</span>
              </div>
              <div className="pp-sec-body">{nl(plan.long_term_goal)}</div>
            </div>
            <div className="pp-sec">
              <div className="pp-sec-head">短期目標</div>
              <div className="pp-goal-header">
                <span className="pp-goal-title">達成時期：</span>
                <span>{formatDateJa(plan.short_term_goal_date)}</span>
              </div>
              <div className="pp-sec-body">{nl(plan.short_term_goal)}</div>
            </div>
          </div>

          {/* Support Details Table */}
          {details.length > 0 && (
            <div className="pp-sec">
              <div className="pp-sec-head">支援内容</div>
              <table className="pp-table">
                <thead>
                  <tr>
                    <th style={{ width: '8%' }}>項目</th>
                    <th style={{ width: '18%' }}>支援目標</th>
                    <th style={{ width: '32%' }}>支援内容</th>
                    <th style={{ width: '8%' }}>達成時期</th>
                    <th style={{ width: '12%' }}>担当者/<br />提供機関</th>
                    <th style={{ width: '16%' }}>留意事項</th>
                    <th style={{ width: '6%' }}>優先順位</th>
                  </tr>
                </thead>
                <tbody>
                  {details.map((detail: any, i: number) => {
                    const cat = detail.category || '';
                    const catClass = cat.includes('家族') ? 'cat-kazoku' : cat.includes('地域') ? 'cat-chiiki' : 'cat-honin';
                    return (
                      <tr key={i} className={catClass}>
                        <td>{nl(detail.sub_category || detail.domain || '')}</td>
                        <td>{nl(detail.support_goal || detail.goal || '')}</td>
                        <td>{nl(detail.support_content || '')}</td>
                        <td>{detail.achievement_date ? formatDateJa(detail.achievement_date).replace(/年|月/g, '/').replace(/日/, '') : ''}</td>
                        <td>{nl(detail.staff_organization || '')}</td>
                        <td>{nl(detail.notes || '')}</td>
                        <td style={{ textAlign: 'center' }}>{detail.priority ?? ''}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          {/* Signature Footer */}
          <div className="pp-sig">
            <div className="pp-sig-center">
              <div className="pp-sig-item">
                <div className="pp-sig-label">児童発達支援管理責任者</div>
                <div className="pp-sig-content">
                  {plan.staff_signature_image ? (
                    <>
                      <img src={plan.staff_signature_image} alt="職員署名" className="pp-sig-img" />
                      {(plan.staff_signer_name || plan.manager_name) && (
                        <div className="pp-sig-name">({plan.staff_signer_name || plan.manager_name})</div>
                      )}
                    </>
                  ) : (
                    <div className="pp-sig-name">{plan.manager_name || ''}</div>
                  )}
                </div>
              </div>
              <div className="pp-sig-item">
                <div className="pp-sig-label">保護者署名</div>
                <div className="pp-sig-content">
                  {plan.guardian_signature_image ? (
                    <img src={plan.guardian_signature_image} alt="保護者署名" className="pp-sig-img" />
                  ) : (
                    <div className="pp-sig-name">{plan.guardian_signature || ''}</div>
                  )}
                </div>
              </div>
            </div>
            {plan.student?.classroom && (
              <div className="pp-issuer">
                <div className="pp-issuer-name">{plan.student.classroom.classroom_name || ''}</div>
                <div className="pp-issuer-details">
                  {plan.student.classroom.address && <>〒{plan.student.classroom.address}</>}
                  {plan.student.classroom.phone && <><br />TEL: {plan.student.classroom.phone}</>}
                </div>
              </div>
            )}
          </div>

          <div className="pp-footer">
            出力日時: {new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}
          </div>
        </div>
      </div>
    </>
  );
}
