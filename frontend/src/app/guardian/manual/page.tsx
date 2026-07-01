'use client';

import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import Dashboard from '@/components/manual/guardian/dashboard';
import Renrakucho from '@/components/manual/guardian/renrakucho';
import Chat from '@/components/manual/guardian/chat';
import Assessment from '@/components/manual/guardian/assessment';
import SupportPlan from '@/components/manual/guardian/support-plan';
import Signature from '@/components/manual/guardian/signature';
import Flow from '@/components/manual/guardian/flow';
import Request from '@/components/manual/guardian/request';
import AiUsage from '@/components/manual/guardian/ai-usage';

// ---------------------------------------------------------------------------
// 保護者向けご利用ガイド。各セクション本文は src/components/manual/guardian/<id>.tsx に分割。
// ---------------------------------------------------------------------------

interface ManualSection {
  id: string;
  icon: React.ReactNode;
  title: string;
  content: React.ReactNode;
}

const sections: ManualSection[] = [
  { id: 'dashboard', icon: <MaterialIcon name="dashboard" size={16} className="h-5 w-5" />, title: 'ダッシュボード', content: <Dashboard /> },
  { id: 'renrakucho', icon: <MaterialIcon name="description" size={20} />, title: '連絡帳（日々の活動記録）', content: <Renrakucho /> },
  { id: 'chat', icon: <MaterialIcon name="chat" size={20} />, title: 'チャット', content: <Chat /> },
  { id: 'assessment', icon: <MaterialIcon name="handshake" size={20} />, title: 'アセスメント', content: <Assessment /> },
  { id: 'support-plan', icon: <MaterialIcon name="assignment_turned_in" size={16} className="h-5 w-5" />, title: '個別支援計画書', content: <SupportPlan /> },
  { id: 'signature', icon: <MaterialIcon name="draw" size={20} />, title: '電子署名について', content: <Signature /> },
  { id: 'flow', icon: <MaterialIcon name="menu_book" size={20} />, title: '書類作成の全体の流れ', content: <Flow /> },
  { id: 'request', icon: <MaterialIcon name="help" size={16} className="h-5 w-5" />, title: '保護者の皆様へのお願い', content: <Request /> },
  { id: 'ai-usage', icon: <MaterialIcon name="smart_toy" size={20} />, title: 'AI（人工知能）の利用について', content: <AiUsage /> },
];

export default function GuardianManualPage() {
  const [expandedSections, setExpandedSections] = useState<Set<string>>(new Set(['dashboard']));

  const toggleSection = (id: string) => {
    setExpandedSections((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const expandAll = () => {
    setExpandedSections(new Set(sections.map((s) => s.id)));
  };

  const collapseAll = () => {
    setExpandedSections(new Set());
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">ご利用ガイド</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          システムのご利用方法をご案内します
        </p>
      </div>

      {/* Introduction */}
      <Card className="border-l-4 border-l-[var(--brand-80)]">
        <CardBody>
          <div className="flex items-start gap-3">
            <MaterialIcon name="menu_book" size={20} className="mt-0.5 text-[var(--brand-80)]" />
            <div className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
              <p>
                この連絡帳システムは、お子様の日々の活動記録と成長を、保護者の皆様とスタッフが一緒に見守り、
                <strong>根拠に基づいた支援目標</strong>を設定するために開発されました。
              </p>
              <p className="mt-2">
                日々の記録を積み重ねることで、お子様一人ひとりに合った支援計画を作成し、
                より良い成長をサポートしていきます。
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Table of Contents */}
      <Card>
        <CardHeader>
          <CardTitle>目次</CardTitle>
          <div className="flex gap-2">
            <button
              onClick={expandAll}
              className="text-xs text-[var(--brand-80)] hover:underline"
            >
              すべて開く
            </button>
            <span className="text-xs text-[var(--neutral-foreground-4)]">|</span>
            <button
              onClick={collapseAll}
              className="text-xs text-[var(--brand-80)] hover:underline"
            >
              すべて閉じる
            </button>
          </div>
        </CardHeader>
        <CardBody>
          <div className="grid gap-1 sm:grid-cols-2">
            {sections.map((section, index) => (
              <button
                key={section.id}
                onClick={() => toggleSection(section.id)}
                className="flex items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-[var(--brand-80)] hover:bg-[var(--neutral-background-3)] transition-colors"
              >
                <span className="text-xs text-[var(--neutral-foreground-4)]">{index + 1}.</span>
                {section.title}
              </button>
            ))}
          </div>
        </CardBody>
      </Card>

      {/* Accordion Sections */}
      <div className="space-y-3">
        {sections.map((section, index) => {
          const isExpanded = expandedSections.has(section.id);
          return (
            <Card key={section.id} padding={false}>
              <button
                className="flex w-full items-center gap-3 p-4 text-left"
                onClick={() => toggleSection(section.id)}
              >
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[var(--brand-160)] text-[var(--brand-80)]">
                  {section.icon}
                </div>
                <div className="flex-1">
                  <h2 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                    {index + 1}. {section.title}
                  </h2>
                </div>
                {isExpanded ? (
                  <MaterialIcon name="expand_less" size={16} className="text-[var(--neutral-foreground-3)]" />
                ) : (
                  <MaterialIcon name="expand_more" size={16} className="text-[var(--neutral-foreground-3)]" />
                )}
              </button>
              {isExpanded && (
                <div className="border-t border-[var(--neutral-stroke-2)] px-4 pb-4 pt-3">
                  {section.content}
                </div>
              )}
            </Card>
          );
        })}
      </div>
    </div>
  );
}
