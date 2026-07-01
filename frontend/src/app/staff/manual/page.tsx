'use client';

import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import About from '@/components/manual/staff/about';
import Overview from '@/components/manual/staff/overview';
import Menu from '@/components/manual/staff/menu';
import Daily from '@/components/manual/staff/daily';
import Periodic from '@/components/manual/staff/periodic';
import BasicUsage from '@/components/manual/staff/basic-usage';
import Guardian from '@/components/manual/staff/guardian';
import Student from '@/components/manual/staff/student';
import Submissions from '@/components/manual/staff/submissions';
import Assessment from '@/components/manual/staff/assessment';
import Evaluation from '@/components/manual/staff/evaluation';
import Schedule from '@/components/manual/staff/schedule';
import Settings from '@/components/manual/staff/settings';
import Signature from '@/components/manual/staff/signature';
import Faq from '@/components/manual/staff/faq';
import Tips from '@/components/manual/staff/tips';
import Contact from '@/components/manual/staff/contact';

// ---------------------------------------------------------------------------
// Manual sections
// 各セクションの詳細本文は src/components/manual/staff/<id>.tsx に分割。
// ---------------------------------------------------------------------------

interface ManualSection {
  id: string;
  icon: React.ReactNode;
  title: string;
  content: React.ReactNode;
}

const sections: ManualSection[] = [
  { id: 'about', icon: <MaterialIcon name="menu_book" size={20} />, title: '「きづり（軌綴）」とは', content: <About /> },
  { id: 'overview', icon: <MaterialIcon name="home" size={16} className="h-5 w-5" />, title: 'システム概要', content: <Overview /> },
  { id: 'menu', icon: <MaterialIcon name="checklist" size={20} />, title: 'メニュー構成', content: <Menu /> },
  { id: 'daily', icon: <MaterialIcon name="calendar_month" size={20} />, title: '毎日行うこと', content: <Daily /> },
  { id: 'periodic', icon: <MaterialIcon name="schedule" size={20} />, title: '一定期間ごとに行うこと', content: <Periodic /> },
  { id: 'basic-usage', icon: <MaterialIcon name="edit_square" size={16} className="h-5 w-5" />, title: '基本的な使い方', content: <BasicUsage /> },
  { id: 'guardian', icon: <MaterialIcon name="group" size={20} />, title: '保護者機能', content: <Guardian /> },
  { id: 'student', icon: <MaterialIcon name="school" size={16} className="h-5 w-5" />, title: '生徒機能', content: <Student /> },
  { id: 'submissions', icon: <MaterialIcon name="upload" size={20} />, title: '提出物管理', content: <Submissions /> },
  { id: 'assessment', icon: <MaterialIcon name="handshake" size={20} />, title: 'アセスメント管理', content: <Assessment /> },
  { id: 'evaluation', icon: <MaterialIcon name="assignment_turned_in" size={16} className="h-5 w-5" />, title: '事業所評価', content: <Evaluation /> },
  { id: 'schedule', icon: <MaterialIcon name="schedule" size={20} />, title: '書類作成スケジュールと期限ルール', content: <Schedule /> },
  { id: 'settings', icon: <MaterialIcon name="settings" size={20} />, title: 'マスタ管理', content: <Settings /> },
  { id: 'signature', icon: <MaterialIcon name="draw" size={20} />, title: '電子署名機能', content: <Signature /> },
  { id: 'faq', icon: <MaterialIcon name="help" size={16} className="h-5 w-5" />, title: 'よくある質問', content: <Faq /> },
  { id: 'tips', icon: <MaterialIcon name="lightbulb" size={16} className="h-5 w-5" />, title: 'ヒントとコツ', content: <Tips /> },
  { id: 'contact', icon: <MaterialIcon name="phone" size={20} />, title: 'お問い合わせ', content: <Contact /> },
];

export default function ManualPage() {
  const [openSections, setOpenSections] = useState<Set<string>>(new Set(['about']));

  const toggleSection = (id: string) => {
    setOpenSections((prev) => {
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
    setOpenSections(new Set(sections.map((s) => s.id)));
  };

  const collapseAll = () => {
    setOpenSections(new Set());
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">スタッフマニュアル</h1>
        <div className="flex gap-2">
          <button
            className="text-sm text-[var(--brand-80)] hover:text-[var(--brand-70)]"
            onClick={expandAll}
          >
            すべて開く
          </button>
          <span className="text-[var(--neutral-foreground-3)]">|</span>
          <button
            className="text-sm text-[var(--brand-80)] hover:text-[var(--brand-70)]"
            onClick={collapseAll}
          >
            すべて閉じる
          </button>
        </div>
      </div>

      {/* 重要: 私有物での操作禁止に関する注意喚起（個人情報保護） */}
      <div
        role="alert"
        className="rounded-lg border-2 border-[var(--status-danger-fg)] bg-[rgba(var(--status-danger-rgb,217,48,37),0.08)] p-4"
      >
        <div className="flex items-start gap-3">
          <MaterialIcon name="warning" size={24} className="mt-0.5 shrink-0 text-[var(--status-danger-fg)]" />
          <div className="space-y-1.5">
            <p className="text-base font-bold text-[var(--status-danger-fg)]">
              ご利用にあたっての重要なお願い（必ずお読みください）
            </p>
            <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">
              管理者・スタッフは、私有物（個人所有のスマートフォン・PC・タブレット等）で本システムを操作しないでください。
            </p>
            <p className="text-sm text-[var(--neutral-foreground-2)]">
              個人情報保護の観点から、私有物によるログインによって情報が漏洩した際には、当方は一切の責任を負いません。
              業務での利用は、必ず事業所が貸与・管理する端末で行ってください。
            </p>
          </div>
        </div>
      </div>

      {/* Table of contents */}
      <Card>
        <CardBody>
          <p className="mb-3 text-sm font-medium text-[var(--neutral-foreground-2)]">目次</p>
          <div className="flex flex-wrap gap-2">
            {sections.map((section) => (
              <button
                key={section.id}
                className="rounded-lg bg-[var(--neutral-background-2)] px-3 py-1.5 text-xs font-medium text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-3)] transition-colors"
                onClick={() => {
                  setOpenSections((prev) => new Set(prev).add(section.id));
                  document.getElementById(`section-${section.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }}
              >
                {section.title}
              </button>
            ))}
          </div>
        </CardBody>
      </Card>

      {/* Sections */}
      <div className="space-y-3">
        {sections.map((section) => {
          const isOpen = openSections.has(section.id);
          return (
            <Card key={section.id} id={`section-${section.id}`}>
              <button
                className="flex w-full items-center justify-between px-6 py-4 text-left"
                onClick={() => toggleSection(section.id)}
              >
                <div className="flex items-center gap-3">
                  <span className="text-[var(--brand-80)]">{section.icon}</span>
                  <span className="text-base font-semibold text-[var(--neutral-foreground-1)]">{section.title}</span>
                </div>
                {isOpen ? (
                  <MaterialIcon name="expand_more" size={20} className="text-[var(--neutral-foreground-3)]" />
                ) : (
                  <MaterialIcon name="chevron_right" size={20} className="text-[var(--neutral-foreground-3)]" />
                )}
              </button>
              {isOpen && (
                <CardBody>
                  <div className="text-sm text-[var(--neutral-foreground-2)] leading-relaxed">
                    {section.content}
                  </div>
                </CardBody>
              )}
            </Card>
          );
        })}
      </div>
    </div>
  );
}
