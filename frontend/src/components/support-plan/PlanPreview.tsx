'use client';

import { formatDate, nl } from '@/lib/utils';
import { Badge } from '@/components/ui/Badge';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import type { SupportPlan } from '@/types/support-plan';

interface PlanPreviewProps {
  plan: SupportPlan;
  onRequestSignature?: (planId: number) => void;
}

const statusLabels: Record<string, string> = {
  draft: '下書き', submitted: '提出済', official: '正式',
};

export function PlanPreview({ plan, onRequestSignature }: PlanPreviewProps) {
  const details = plan.details?.sort((a, b) => a.sort_order - b.sort_order) ?? [];

  const staffSig = plan.staff_signature_image || (plan as any).staff_signature || null;
  const guardianSig = plan.guardian_signature_image || plan.guardian_signature || null;
  const isStaffSigImage = staffSig?.startsWith('data:image');
  const isGuardianSigImage = guardianSig?.startsWith('data:image');
  const hasStaffSig = !!staffSig;
  const hasGuardianSig = !!guardianSig;
  const isPublished = plan.status === 'official' || plan.status === 'submitted';

  return (
    <div className="space-y-4">
      {/* Signature alert */}
      {isPublished && (!hasStaffSig || !hasGuardianSig) && (
        <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 print:hidden">
          <div className="flex items-start gap-3">
            <MaterialIcon name="warning" size={20} className="text-amber-600 shrink-0 mt-0.5" />
            <div className="flex-1">
              <p className="text-sm font-semibold text-amber-800">署名が不足しています</p>
              <ul className="mt-1 text-xs text-amber-700 space-y-0.5">
                {!hasStaffSig && <li>- 職員署名が未記入です</li>}
                {!hasGuardianSig && <li>- 保護者署名が未記入です</li>}
              </ul>
              {onRequestSignature && (
                <button
                  onClick={() => onRequestSignature(plan.id)}
                  className="mt-2 inline-flex items-center gap-1 rounded-md bg-amber-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-700"
                >
                  <MaterialIcon name="draw" size={14} />
                  署名を要求する
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Printable area - A4 landscape layout */}
      <style>{`
        @media print {
          body { margin: 0; padding: 0; }
          .print\\:hidden { display: none !important; }
          @page { size: A4 landscape; margin: 8mm 10mm; }
        }
      `}</style>

      <div className="bg-white" style={{ fontFamily: "'MS Gothic', 'Noto Sans JP', sans-serif", color: '#222', lineHeight: 1.3, fontSize: '8pt' }}>
        {/* Header */}
        <div style={{ textAlign: 'center', marginBottom: '6px', borderBottom: '2px solid #1a1a1a', paddingBottom: '4px' }}>
          <h2 style={{ fontSize: '14pt', fontWeight: 700, margin: 0, letterSpacing: '2pt' }}>個別支援計画書</h2>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '4px', fontSize: '8pt' }}>
            <span>作成日: {formatDate(plan.created_date)}</span>
            <Badge variant={plan.status === 'official' ? 'success' : plan.status === 'submitted' ? 'warning' : 'default'} className="print:hidden">
              {statusLabels[plan.status] || plan.status}
            </Badge>
            <span>氏名: {plan.student_name || plan.student?.student_name || ''}</span>
          </div>
        </div>

        {/* Two-column: Intention & Policy */}
        <div style={{ display: 'flex', gap: '8px', marginBottom: '6px' }}>
          <div style={{ flex: 1 }}>
            <div style={{ background: '#4a5568', color: 'white', padding: '3px 8px', fontWeight: 'bold', fontSize: '8pt' }}>利用児及び家族の生活に対する意向</div>
            <div style={{ padding: '4px 6px', border: '1px solid #999', borderTop: 'none', whiteSpace: 'pre-wrap', wordWrap: 'break-word', fontSize: '7.5pt', lineHeight: 1.3, minHeight: '30px' }}>
              {nl(plan.life_intention || plan.guardian_wish) || '（未入力）'}
            </div>
          </div>
          <div style={{ flex: 1 }}>
            <div style={{ background: '#4a5568', color: 'white', padding: '3px 8px', fontWeight: 'bold', fontSize: '8pt' }}>総合的な支援の方針</div>
            <div style={{ padding: '4px 6px', border: '1px solid #999', borderTop: 'none', whiteSpace: 'pre-wrap', wordWrap: 'break-word', fontSize: '7.5pt', lineHeight: 1.3, minHeight: '30px' }}>
              {nl(plan.overall_policy) || '（未入力）'}
            </div>
          </div>
        </div>

        {/* Two-column: Goals */}
        <div style={{ display: 'flex', gap: '8px', marginBottom: '6px' }}>
          <div style={{ flex: 1 }}>
            <div style={{ background: '#4a5568', color: 'white', padding: '3px 8px', fontWeight: 'bold', fontSize: '8pt' }}>長期目標</div>
            {plan.long_term_goal_date && (
              <div style={{ fontSize: '7pt', padding: '2px 6px' }}>達成時期: {formatDate(plan.long_term_goal_date)}</div>
            )}
            <div style={{ padding: '4px 6px', border: '1px solid #999', borderTop: plan.long_term_goal_date ? '1px solid #999' : 'none', whiteSpace: 'pre-wrap', wordWrap: 'break-word', fontSize: '7.5pt', lineHeight: 1.3, minHeight: '30px' }}>
              {nl(plan.long_term_goal) || '（未入力）'}
            </div>
          </div>
          <div style={{ flex: 1 }}>
            <div style={{ background: '#4a5568', color: 'white', padding: '3px 8px', fontWeight: 'bold', fontSize: '8pt' }}>短期目標</div>
            {plan.short_term_goal_date && (
              <div style={{ fontSize: '7pt', padding: '2px 6px' }}>達成時期: {formatDate(plan.short_term_goal_date)}</div>
            )}
            <div style={{ padding: '4px 6px', border: '1px solid #999', borderTop: plan.short_term_goal_date ? '1px solid #999' : 'none', whiteSpace: 'pre-wrap', wordWrap: 'break-word', fontSize: '7.5pt', lineHeight: 1.3, minHeight: '30px' }}>
              {nl(plan.short_term_goal) || '（未入力）'}
            </div>
          </div>
        </div>

        {/* Details table */}
        {details.length > 0 && (
          <div style={{ marginBottom: '6px' }}>
            <div style={{ background: '#4a5568', color: 'white', padding: '3px 8px', fontWeight: 'bold', fontSize: '8pt' }}>
              ○支援目標及び具体的な支援内容等
            </div>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '7pt', tableLayout: 'fixed' }}>
              <thead>
                <tr>
                  <th style={{ width: '9%', border: '1px solid #555', padding: '2px 4px', background: '#e2e8f0', fontWeight: 'bold', textAlign: 'center', fontSize: '7pt' }}>項目</th>
                  <th style={{ width: '17%', border: '1px solid #555', padding: '2px 4px', background: '#e2e8f0', fontWeight: 'bold', textAlign: 'center', fontSize: '7pt' }}>支援目標</th>
                  <th style={{ width: '30%', border: '1px solid #555', padding: '2px 4px', background: '#e2e8f0', fontWeight: 'bold', textAlign: 'center', fontSize: '7pt' }}>支援内容</th>
                  <th style={{ width: '7%', border: '1px solid #555', padding: '2px 4px', background: '#e2e8f0', fontWeight: 'bold', textAlign: 'center', fontSize: '7pt' }}>達成時期</th>
                  <th style={{ width: '10%', border: '1px solid #555', padding: '2px 4px', background: '#e2e8f0', fontWeight: 'bold', textAlign: 'center', fontSize: '7pt' }}>担当者</th>
                  <th style={{ width: '20%', border: '1px solid #555', padding: '2px 4px', background: '#e2e8f0', fontWeight: 'bold', textAlign: 'center', fontSize: '7pt' }}>留意事項</th>
                  <th style={{ width: '5%', border: '1px solid #555', padding: '2px 4px', background: '#e2e8f0', fontWeight: 'bold', textAlign: 'center', fontSize: '7pt' }}>優先</th>
                </tr>
              </thead>
              <tbody>
                {details.map((detail, i) => {
                  const cat = detail.category || '';
                  const bgColor = cat.includes('家族') ? '#dbeafe' : cat.includes('地域') ? '#d1fae5' : '#f7fafc';
                  return (
                    <tr key={detail.id || i} style={{ background: bgColor }}>
                      <td style={{ border: '1px solid #555', padding: '2px 4px', fontSize: '6.5pt', verticalAlign: 'top' }}>
                        {cat && <div style={{ fontWeight: 'bold' }}>{cat}</div>}
                        {nl(detail.sub_category || detail.domain || '')}
                      </td>
                      <td style={{ border: '1px solid #555', padding: '2px 4px', whiteSpace: 'pre-wrap', wordWrap: 'break-word', verticalAlign: 'top' }}>{nl(detail.support_goal || detail.goal || '')}</td>
                      <td style={{ border: '1px solid #555', padding: '2px 4px', whiteSpace: 'pre-wrap', wordWrap: 'break-word', verticalAlign: 'top' }}>{nl(detail.support_content || '')}</td>
                      <td style={{ border: '1px solid #555', padding: '2px 4px', textAlign: 'center', fontSize: '6.5pt', verticalAlign: 'top' }}>{detail.achievement_date ? formatDate(detail.achievement_date) : ''}</td>
                      <td style={{ border: '1px solid #555', padding: '2px 4px', fontSize: '6.5pt', verticalAlign: 'top' }}>{nl(detail.staff_organization || '')}</td>
                      <td style={{ border: '1px solid #555', padding: '2px 4px', whiteSpace: 'pre-wrap', wordWrap: 'break-word', verticalAlign: 'top' }}>{nl(detail.notes || '')}</td>
                      <td style={{ border: '1px solid #555', padding: '2px 4px', textAlign: 'center', verticalAlign: 'top' }}>{detail.priority ?? ''}</td>
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

        {/* Signature section */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '6px', paddingTop: '4px', borderTop: '1px solid #333' }}>
          <div style={{ display: 'flex', gap: '20px', flex: 1, justifyContent: 'center', alignItems: 'center' }}>
            {/* Consent info */}
            {(plan.consent_date || plan.consent_name || plan.manager_name) && (
              <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                <span style={{ fontWeight: 'bold', fontSize: '7.5pt', whiteSpace: 'nowrap' }}>同意日</span>
                <span style={{ borderBottom: '1px solid #333', minWidth: '80px', padding: '2px 4px', fontSize: '7.5pt' }}>
                  {plan.consent_date ? formatDate(plan.consent_date) : ''}
                </span>
              </div>
            )}

            {/* Staff signature */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
              <span style={{ fontWeight: 'bold', fontSize: '7.5pt', whiteSpace: 'nowrap' }}>児童発達支援管理責任者</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: '4px', borderBottom: '1px solid #333', minWidth: '120px', padding: '2px 4px' }}>
                {isStaffSigImage ? (
                  <>
                    <img src={staffSig} alt="職員署名" style={{ maxHeight: '35px', maxWidth: '100px' }} />
                    {(plan.staff_signer_name || plan.manager_name) && (
                      <span style={{ fontSize: '7.5pt' }}>({plan.staff_signer_name || plan.manager_name})</span>
                    )}
                  </>
                ) : (
                  <span style={{ fontSize: '7.5pt' }}>{plan.staff_signer_name || plan.manager_name || ''}</span>
                )}
              </div>
            </div>

            {/* Guardian signature */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
              <span style={{ fontWeight: 'bold', fontSize: '7.5pt', whiteSpace: 'nowrap' }}>保護者署名</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: '4px', borderBottom: '1px solid #333', minWidth: '120px', padding: '2px 4px' }}>
                {isGuardianSigImage ? (
                  <img src={guardianSig} alt="保護者署名" style={{ maxHeight: '35px', maxWidth: '100px' }} />
                ) : (
                  <span style={{ fontSize: '7.5pt' }}>{guardianSig || ''}</span>
                )}
              </div>
            </div>
          </div>

          {/* Classroom info */}
          {plan.student?.classroom && (
            <div style={{ textAlign: 'right', minWidth: '180px' }}>
              <div style={{ fontSize: '9pt', fontWeight: 'bold' }}>{plan.student.classroom.classroom_name || ''}</div>
              <div style={{ fontSize: '7pt', color: '#333' }}>
                {(plan.student.classroom as any).address && <>{(plan.student.classroom as any).address}</>}
                {(plan.student.classroom as any).phone && <><br />TEL: {(plan.student.classroom as any).phone}</>}
              </div>
            </div>
          )}
        </div>

        <div style={{ marginTop: '4px', textAlign: 'right', fontSize: '6pt', color: '#aaa' }}>
          出力日時: {new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}
        </div>
      </div>

      {/* Print button */}
      <div className="flex justify-center print:hidden">
        <button
          onClick={() => window.print()}
          className="rounded-lg bg-[var(--neutral-background-4)] px-6 py-2 text-sm font-medium text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-5)]"
        >
          印刷する
        </button>
      </div>
    </div>
  );
}

export default PlanPreview;
