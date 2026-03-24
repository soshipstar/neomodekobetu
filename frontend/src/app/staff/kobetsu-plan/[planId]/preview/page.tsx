'use client';

import { useParams, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import Link from 'next/link';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

function nl(t: string | null | undefined): string {
  if (!t) return '';
  return t.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

function fmtDate(d: string | null | undefined): string {
  if (!d) return '';
  try { return format(new Date(d), 'yyyy年M月d日'); } catch { return d; }
}

export default function PlanPreviewPage() {
  const params = useParams();
  const searchParams = useSearchParams();
  const planId = params.planId as string;
  const isOfficial = searchParams.get('type') === 'official';

  const { data: plan, isLoading } = useQuery({
    queryKey: ['staff', 'plan-preview', planId],
    queryFn: async () => {
      const res = await api.get(`/api/staff/support-plans/${planId}`);
      return res.data?.data;
    },
  });

  if (isLoading) {
    return <div className="mx-auto max-w-4xl p-8 space-y-4"><Skeleton className="h-8 w-64" /><Skeleton className="h-[600px] w-full" /></div>;
  }

  if (!plan) return <div className="p-8 text-center">計画が見つかりません</div>;

  const handlePdf = async () => {
    try {
      const res = await api.get(`/api/staff/support-plans/${planId}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `support_plan_${planId}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch { /* ignore */ }
  };

  const details = Array.isArray(plan.details) ? plan.details : [];
  const student = plan.student;

  return (
    <>
      {/* Toolbar */}
      <div className="print:hidden mb-4 flex items-center justify-between">
        <Link href="/staff/kobetsu-plan">
          <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="chevron_left" size={16} />}>個別支援計画に戻る</Button>
        </Link>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="download" size={16} />} onClick={handlePdf}>PDFダウンロード</Button>
          <Button leftIcon={<MaterialIcon name="print" size={16} />} onClick={() => window.print()}>印刷する</Button>
        </div>
      </div>

      {/* Printable area */}
      <div className="mx-auto max-w-4xl bg-white print:max-w-none print:m-0">
        <style>{`
          @media print {
            body { margin: 0; padding: 0; font-size: 8pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print\\:hidden { display: none !important; }
            @page { size: A4 portrait; margin: 15mm 18mm; }
          }
          .plan-page { font-family: sans-serif; color: #222; line-height: 1.3; }
          .plan-header { text-align: center; border-bottom: 2.5px solid #2c3e50; padding-bottom: 6px; margin-bottom: 10px; }
          .plan-header h1 { font-size: 14pt; color: #2c3e50; margin: 0; letter-spacing: 2pt; }
          .plan-header .sub { font-size: 7pt; color: #777; }
          .plan-meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8pt; }
          .plan-meta td { padding: 2px 6px; border: 0.5px solid #aaa; }
          .plan-meta .label { font-weight: bold; background: #f5f6f8; width: 18%; color: #444; }
          .plan-section { margin-bottom: 6px; }
          .plan-section-title { background: #34495e; color: white; padding: 3px 8px; font-weight: bold; font-size: 8.5pt; }
          .plan-section-body { border: 0.5px solid #aaa; border-top: none; padding: 5px 8px; font-size: 8pt; white-space: pre-wrap; word-wrap: break-word; line-height: 1.35; }
          .plan-goals { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8pt; }
          .plan-goals { page-break-inside: auto; }
          .plan-goals th { background: #ecf0f1; border: 0.5px solid #888; padding: 2px 4px; text-align: center; font-size: 8pt; }
          .plan-goals td { border: 0.5px solid #888; padding: 2px 4px; vertical-align: top; font-size: 8pt; line-height: 1.25; white-space: pre-wrap; word-wrap: break-word; }
          .plan-goals tr { page-break-inside: avoid; }
          .plan-goals .cat-cell { background: #f9f9f9; font-weight: bold; text-align: center; width: 7%; }
          .plan-goals .sub-cat { font-size: 7pt; color: #555; font-weight: normal; }
          .plan-consent { border: 1px solid #aaa; padding: 8px; margin-top: 8px; font-size: 8pt; }
          .plan-consent .title { font-weight: bold; margin-bottom: 4px; }
          .plan-footer { text-align: center; margin-top: 8px; padding-top: 4px; border-top: 0.5px solid #ccc; font-size: 6.5pt; color: #999; }
          .sig-box { display: inline-block; border-bottom: 1px solid #333; min-width: 150px; padding: 2px 0; margin-left: 8px; }
        `}</style>

        <div className="plan-page">
          <div className="plan-header">
            <h1>{isOfficial ? '個別支援計画書（正式版）' : '個別支援計画書'}</h1>
            <div className="sub">放課後等デイサービス</div>
          </div>

          {/* Meta */}
          <table className="plan-meta">
            <tbody>
              <tr>
                <td className="label">氏名</td>
                <td>{student?.student_name || ''}</td>
                <td className="label">作成年月日</td>
                <td>{fmtDate(plan.created_date)}</td>
              </tr>
            </tbody>
          </table>

          {/* 意向・方針 */}
          <div className="plan-section">
            <div className="plan-section-title">利用児及び家族の生活に対する意向</div>
            <div className="plan-section-body">{nl(plan.life_intention || plan.guardian_wish) || '（未入力）'}</div>
          </div>

          <div className="plan-section">
            <div className="plan-section-title">総合的な支援の方針</div>
            <div className="plan-section-body">{nl(plan.overall_policy) || '（未入力）'}</div>
          </div>

          {/* 目標 */}
          <table className="plan-meta" style={{ marginBottom: '4px' }}>
            <tbody>
              <tr>
                <td className="label">長期目標</td>
                <td style={{ whiteSpace: 'pre-wrap' }}>{nl(plan.long_term_goal) || '（未入力）'}</td>
                <td className="label" style={{ width: '12%' }}>達成時期</td>
                <td style={{ width: '15%' }}>{fmtDate(plan.long_term_goal_date)}</td>
              </tr>
              <tr>
                <td className="label">短期目標</td>
                <td style={{ whiteSpace: 'pre-wrap' }}>{nl(plan.short_term_goal) || '（未入力）'}</td>
                <td className="label">達成時期</td>
                <td>{fmtDate(plan.short_term_goal_date)}</td>
              </tr>
            </tbody>
          </table>

          {/* 支援目標テーブル */}
          <div className="plan-section-title" style={{ marginBottom: 0 }}>○支援目標及び具体的な支援内容等</div>
          <table className="plan-goals">
            <thead>
              <tr>
                <th style={{ width: '7%' }}>項目</th>
                <th style={{ width: '18%' }}>支援目標<br /><span style={{ fontSize: '6pt', fontWeight: 'normal' }}>（具体的な到達目標）</span></th>
                <th style={{ width: '25%' }}>支援内容<br /><span style={{ fontSize: '6pt', fontWeight: 'normal' }}>（内容・5領域との関連性等）</span></th>
                <th style={{ width: '8%' }}>達成時期</th>
                <th style={{ width: '10%' }}>担当者</th>
                <th style={{ width: '22%' }}>留意事項</th>
                <th style={{ width: '4%' }}>優先</th>
              </tr>
            </thead>
            <tbody>
              {details.map((d: any, i: number) => (
                <tr key={i}>
                  <td className="cat-cell">
                    {d.category || '本人支援'}
                    <br />
                    <span className="sub-cat">{d.sub_category || ''}</span>
                  </td>
                  <td>{nl(d.support_goal || d.goal)}</td>
                  <td>{nl(d.support_content)}</td>
                  <td style={{ textAlign: 'center', fontSize: '6.5pt' }}>{fmtDate(d.achievement_date)}</td>
                  <td style={{ fontSize: '6.5pt' }}>{nl(d.staff_organization)}</td>
                  <td style={{ fontSize: '6.5pt' }}>{nl(d.notes)}</td>
                  <td style={{ textAlign: 'center' }}>{d.priority || ''}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <p style={{ fontSize: '6pt', color: '#777', marginBottom: '6px' }}>
            ※ 5領域の視点：「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」
          </p>

          {/* 同意 */}
          <div className="plan-consent">
            <div className="title">同意</div>
            <div style={{ display: 'flex', gap: '20px', flexWrap: 'wrap', fontSize: '8pt' }}>
              <div>管理責任者氏名: <span className="sig-box">{plan.consent_name || plan.manager_name || ''}</span></div>
              <div>同意日: <span className="sig-box">{fmtDate(plan.consent_date)}</span></div>
            </div>
            {isOfficial && (
              <div style={{ marginTop: '8px', fontSize: '8pt' }}>
                <div>保護者署名: <span className="sig-box">{plan.guardian_signature || plan.guardian_signature_text || ''}</span></div>
                {plan.staff_signer_name && (
                  <div style={{ marginTop: '4px' }}>職員署名: <span className="sig-box">{plan.staff_signer_name}</span>
                    {plan.staff_signature_date && ` （${fmtDate(plan.staff_signature_date)}）`}
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="plan-footer">
            出力日時: {new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}
            {isOfficial && ' ・ 正式版'}
          </div>
        </div>
      </div>
    </>
  );
}
