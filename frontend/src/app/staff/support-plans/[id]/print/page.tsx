'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import Link from 'next/link';

function nl(t: string | null | undefined): string {
  if (!t) return '';
  return t.replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n').replace(/\\r/g, '').replace(/\r\n/g, '\n');
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

  // Signature fallback: _image field or base field
  const staffSig = plan.staff_signature_image || plan.staff_signature || null;
  const guardianSig = plan.guardian_signature_image || plan.guardian_signature || null;
  const isStaffSigImage = staffSig?.startsWith('data:image');
  const isGuardianSigImage = guardianSig?.startsWith('data:image');

  return (
    <>
      <div className="print:hidden mb-4 flex items-center justify-between">
        <Link href="/staff/support-plans">
          <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="arrow_back" size={16} />}>計画書一覧に戻る</Button>
        </Link>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="download" size={16} />} onClick={handlePdf}>PDFダウンロード</Button>
          <Button leftIcon={<MaterialIcon name="print" size={16} />} onClick={() => window.print()}>印刷する</Button>
        </div>
      </div>

      <div className="mx-auto bg-white print:max-w-none print:m-0">
        <style>{`
          @media print {
            * { box-sizing: border-box; }
            body { margin: 0; padding: 0; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .print\\:hidden { display: none !important; }
            @page { size: A3 landscape; margin: 6mm; }
          }
          .pp { font-family: 'MS Gothic', 'Noto Sans JP', sans-serif; color: #222; line-height: 1.2; font-size: 8pt; }
          .pp-header { text-align: center; margin-bottom: 6px; border-bottom: 2px solid #1a1a1a; padding-bottom: 4px; }
          .pp-header h1 { font-size: 14pt; font-weight: 700; margin: 0; letter-spacing: 2pt; }
          .pp-meta { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 8pt; }
          .pp-meta-item { margin-right: 10px; }
          .pp-meta-label { font-weight: bold; }
          .pp-two-col { display: flex; gap: 8px; margin-bottom: 6px; }
          .pp-two-col > div { flex: 1; }
          .pp-sec { margin-bottom: 6px; }
          .pp-sec-head { background: #4a5568; color: white; padding: 3px 8px; font-weight: bold; font-size: 8pt; margin-bottom: 0; }
          .pp-sec-body { padding: 4px 6px; border: 1px solid #999; border-top: none; white-space: pre-wrap; word-wrap: break-word; font-size: 7.5pt; line-height: 1.3; min-height: 30px; overflow: hidden; }
          .pp-goal-header { display: flex; align-items: center; margin-bottom: 2px; font-size: 7.5pt; }
          .pp-goal-title { font-weight: bold; margin-right: 6px; }
          .pp-table { width: 100%; border-collapse: collapse; font-size: 7pt; table-layout: fixed; }
          .pp-table th, .pp-table td { border: 1px solid #555; padding: 2px 4px; text-align: left; vertical-align: top; }
          .pp-table th { background: #e2e8f0; font-weight: bold; text-align: center; font-size: 7pt; }
          .pp-table td { white-space: pre-wrap; word-wrap: break-word; line-height: 1.3; overflow: hidden; }
          .cat-honin { background: #f7fafc; }
          .cat-kazoku { background: #dbeafe; }
          .cat-chiiki { background: #d1fae5; }
          .pp-sig { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; padding-top: 4px; border-top: 1px solid #333; }
          .pp-sig-center { display: flex; gap: 20px; flex: 1; justify-content: center; align-items: center; }
          .pp-sig-item { display: flex; align-items: center; gap: 6px; }
          .pp-sig-label { font-weight: bold; font-size: 7.5pt; white-space: nowrap; }
          .pp-sig-content { display: flex; align-items: center; gap: 4px; border-bottom: 1px solid #333; min-width: 120px; padding: 2px 4px; }
          .pp-sig-img { max-height: 35px; max-width: 100px; }
          .pp-sig-name { font-size: 7.5pt; }
          .pp-issuer { text-align: right; min-width: 180px; }
          .pp-issuer-name { font-size: 9pt; font-weight: bold; }
          .pp-issuer-details { font-size: 7pt; color: #333; }
          .pp-footer { margin-top: 4px; text-align: right; font-size: 6pt; color: #aaa; }
        `}</style>

        <div className="pp">
          <div className="pp-header">
            <h1>個別支援計画書</h1>
          </div>

          <div className="pp-meta">
            <div className="pp-meta-item">
              <span className="pp-meta-label">児童氏名：</span>
              <span>{plan.student_name || plan.student?.student_name || ''}</span>
            </div>
            <div className="pp-meta-item">
              <span className="pp-meta-label">作成日：</span>
              <span>{formatDateJa(plan.created_date)}</span>
            </div>
            <div className="pp-meta-item">
              <span className="pp-meta-label">同意日：</span>
              <span>{formatDateJa(plan.consent_date)}</span>
            </div>
          </div>

          <div className="pp-two-col">
            <div className="pp-sec">
              <div className="pp-sec-head">利用児及び家族の生活に対する意向</div>
              <div className="pp-sec-body">{nl(plan.life_intention || plan.guardian_wish)}</div>
            </div>
            <div className="pp-sec">
              <div className="pp-sec-head">総合的な支援の方針</div>
              <div className="pp-sec-body">{nl(plan.overall_policy)}</div>
            </div>
          </div>

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

          {details.length > 0 && (
            <div className="pp-sec">
              <div className="pp-sec-head">○支援目標及び具体的な支援内容等</div>
              <table className="pp-table">
                <thead>
                  <tr>
                    <th style={{ width: '9%' }}>項目</th>
                    <th style={{ width: '17%' }}>支援目標</th>
                    <th style={{ width: '30%' }}>支援内容</th>
                    <th style={{ width: '7%' }}>達成時期</th>
                    <th style={{ width: '10%' }}>担当者</th>
                    <th style={{ width: '20%' }}>留意事項</th>
                    <th style={{ width: '5%' }}>優先</th>
                  </tr>
                </thead>
                <tbody>
                  {details.map((detail: any, i: number) => {
                    const cat = detail.category || '';
                    const catClass = cat.includes('家族') ? 'cat-kazoku' : cat.includes('地域') ? 'cat-chiiki' : 'cat-honin';
                    return (
                      <tr key={i} className={catClass}>
                        <td style={{ fontSize: '6.5pt' }}>
                          {cat && <div style={{ fontWeight: 'bold' }}>{cat}</div>}
                          {nl(detail.sub_category || detail.domain || '')}
                        </td>
                        <td>{nl(detail.support_goal || detail.goal || '')}</td>
                        <td>{nl(detail.support_content || '')}</td>
                        <td style={{ textAlign: 'center', fontSize: '6.5pt' }}>{detail.achievement_date ? formatDateJa(detail.achievement_date) : ''}</td>
                        <td style={{ fontSize: '6.5pt' }}>{nl(detail.staff_organization || '')}</td>
                        <td>{nl(detail.notes || '')}</td>
                        <td style={{ textAlign: 'center' }}>{detail.priority ?? ''}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
              <p style={{ fontSize: '5.5pt', color: '#777', margin: 0 }}>
                ※ 5領域の視点：「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」
              </p>
            </div>
          )}

          <div className="pp-sig">
            <div className="pp-sig-center">
              <div className="pp-sig-item">
                <div className="pp-sig-label">児童発達支援管理責任者</div>
                <div className="pp-sig-content">
                  {isStaffSigImage ? (
                    <>
                      <img src={staffSig} alt="職員署名" className="pp-sig-img" />
                      {(plan.staff_signer_name || plan.manager_name) && (
                        <div className="pp-sig-name">({plan.staff_signer_name || plan.manager_name})</div>
                      )}
                    </>
                  ) : (
                    <div className="pp-sig-name">{plan.staff_signer_name || plan.manager_name || ''}</div>
                  )}
                </div>
              </div>
              <div className="pp-sig-item">
                <div className="pp-sig-label">保護者署名</div>
                <div className="pp-sig-content">
                  {isGuardianSigImage ? (
                    <img src={guardianSig} alt="保護者署名" className="pp-sig-img" />
                  ) : (
                    <div className="pp-sig-name">{guardianSig || ''}</div>
                  )}
                </div>
              </div>
            </div>
            {plan.student?.classroom && (
              <div className="pp-issuer">
                <div className="pp-issuer-name">{plan.student.classroom.classroom_name || ''}</div>
                <div className="pp-issuer-details">
                  {plan.student.classroom.address && <>{plan.student.classroom.address}</>}
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
