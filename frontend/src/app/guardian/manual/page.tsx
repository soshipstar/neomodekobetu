'use client';

import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import {
  BookOpen,
  LayoutDashboard,
  MessageCircle,
  FileText,
  Handshake,
  ClipboardCheck,
  PenLine,
  ChevronDown,
  ChevronUp,
  HelpCircle,
  ArrowDown,
} from 'lucide-react';

interface ManualSection {
  id: string;
  icon: React.ReactNode;
  title: string;
  content: React.ReactNode;
}

function FlowStep({ number, title, description }: { number: number; title: string; description: string }) {
  return (
    <div className="flex items-start gap-3 rounded-lg bg-[var(--neutral-background-3)] p-3">
      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--brand-80)] text-sm font-bold text-white">
        {number}
      </div>
      <div>
        <p className="text-sm font-semibold text-[var(--neutral-foreground-1)]">{title}</p>
        <p className="mt-0.5 text-xs text-[var(--neutral-foreground-3)]">{description}</p>
      </div>
    </div>
  );
}

function FlowArrow() {
  return (
    <div className="flex justify-center py-1">
      <ArrowDown className="h-4 w-4 text-[var(--brand-80)]" />
    </div>
  );
}

function HighlightBox({
  variant,
  children,
}: {
  variant: 'info' | 'warning' | 'success';
  children: React.ReactNode;
}) {
  const styles = {
    info: 'border-[var(--status-info-fg)]/30 bg-[var(--status-info-bg)]',
    warning: 'border-[var(--status-warning-fg)]/30 bg-[var(--status-warning-bg)]',
    success: 'border-[var(--status-success-fg)]/30 bg-[var(--status-success-bg)]',
  };

  return (
    <div className={`rounded-lg border p-3 text-sm ${styles[variant]}`}>
      {children}
    </div>
  );
}

const sections: ManualSection[] = [
  {
    id: 'dashboard',
    icon: <LayoutDashboard className="h-5 w-5" />,
    title: 'ダッシュボード',
    content: (
      <div className="space-y-3">
        <p className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
          ダッシュボードはログイン後に最初に表示されるホーム画面です。お子様に関する重要な情報がまとめて表示されます。
        </p>
        <ul className="ml-4 list-disc space-y-1.5 text-sm text-[var(--neutral-foreground-2)]">
          <li><strong>未確認の連絡帳</strong> - 確認が必要な活動記録の件数</li>
          <li><strong>未読チャット</strong> - スタッフからの新しいメッセージ</li>
          <li><strong>かけはし期限</strong> - 提出期限が近いかけはしの通知</li>
          <li><strong>確認待ち書類</strong> - 支援計画書など確認が必要な書類</li>
        </ul>
        <HighlightBox variant="info">
          <strong>ポイント：</strong>通知のバッジが表示されている項目は、確認や対応が必要なものです。定期的にチェックしましょう。
        </HighlightBox>
      </div>
    ),
  },
  {
    id: 'renrakucho',
    icon: <FileText className="h-5 w-5" />,
    title: '連絡帳（日々の活動記録）',
    content: (
      <div className="space-y-3">
        <p className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
          お子様が教室で活動した日には、スタッフが活動内容を記録し、保護者の皆様にお届けしています。
        </p>
        <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">活動記録に含まれる内容</h4>
        <ul className="ml-4 list-disc space-y-1.5 text-sm text-[var(--neutral-foreground-2)]">
          <li><strong>その日の活動内容</strong> - どんな活動をしたか</li>
          <li><strong>お子様の様子</strong> - 活動中の表情や反応、頑張ったこと</li>
          <li><strong>スタッフからのコメント</strong> - 気づいたことや成長のポイント</li>
        </ul>
        <HighlightBox variant="success">
          <strong>なぜ日々の記録が大切なのか：</strong>日々の小さな変化や成長を記録することで、お子様の
          <strong>得意なこと・苦手なこと・興味のあること</strong>が見えてきます。
          この積み重ねが、次の支援目標を決める際の大切な根拠となります。
        </HighlightBox>
      </div>
    ),
  },
  {
    id: 'chat',
    icon: <MessageCircle className="h-5 w-5" />,
    title: 'チャット',
    content: (
      <div className="space-y-3">
        <p className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
          スタッフとリアルタイムでメッセージのやり取りができます。お子様のことで気になることがあれば、いつでもご相談いただけます。
        </p>
        <ul className="ml-4 list-disc space-y-1.5 text-sm text-[var(--neutral-foreground-2)]">
          <li><strong>テキストメッセージ</strong> - スタッフとの文字でのやり取り</li>
          <li><strong>画像・ファイル送信</strong> - 写真や書類を添付可能</li>
          <li><strong>既読表示</strong> - メッセージが読まれたか確認可能</li>
        </ul>
        <HighlightBox variant="info">
          <strong>ポイント：</strong>チャットはお子様ごとにスタッフとの個別ルームが用意されています。
          欠席連絡やちょっとした相談にもご活用ください。
        </HighlightBox>
      </div>
    ),
  },
  {
    id: 'kakehashi',
    icon: <Handshake className="h-5 w-5" />,
    title: 'かけはし',
    content: (
      <div className="space-y-3">
        <p className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
          「かけはし」は、<strong>保護者とスタッフの情報共有</strong>のための大切な書類です。
          お子様の家庭での様子と教室での様子を共有し、一貫した支援を行うために作成します。
        </p>
        <div className="space-y-2">
          <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
            <h5 className="text-sm font-semibold text-[var(--brand-70)]">保護者かけはし（保護者の皆様が記入）</h5>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              家庭でのお子様の様子、最近の変化、気になること、教室への要望などを記入していただきます。
            </p>
          </div>
          <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
            <h5 className="text-sm font-semibold text-[var(--brand-80)]">スタッフかけはし（スタッフが作成）</h5>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              日々の活動記録をもとに、教室でのお子様の様子、成長したポイント、今後の支援の方向性などをまとめます。
            </p>
          </div>
        </div>
        <HighlightBox variant="info">
          <strong>かけはしの作成サイクル：</strong>かけはしは<strong>6か月ごと</strong>に作成します。
          期限が近づくと、システムから入力のお願いが届きますので、ご協力をお願いいたします。
        </HighlightBox>
      </div>
    ),
  },
  {
    id: 'support-plan',
    icon: <ClipboardCheck className="h-5 w-5" />,
    title: '個別支援計画書',
    content: (
      <div className="space-y-3">
        <p className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
          個別支援計画書は、お子様一人ひとりに合わせた<strong>支援の目標と具体的な内容</strong>を定めた書類です。
          法律で定められた重要な書類であり、6か月ごとに見直しを行います。
        </p>
        <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">計画書の内容</h4>
        <ul className="ml-4 list-disc space-y-1.5 text-sm text-[var(--neutral-foreground-2)]">
          <li><strong>長期目標</strong> - 1年後に目指す姿</li>
          <li><strong>短期目標</strong> - 6か月後に達成したい目標</li>
          <li><strong>具体的な支援内容</strong> - 目標達成のために行う支援</li>
        </ul>
        <HighlightBox variant="warning">
          <strong>根拠に基づいた目標設定：</strong>個別支援計画の目標は、<strong>日々の活動記録</strong>と
          <strong>かけはし</strong>の内容を分析して設定します。
          「なんとなく」ではなく、実際の様子や変化を根拠として、お子様に合った現実的で達成可能な目標を立てています。
        </HighlightBox>
        <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">モニタリング（経過観察）</h4>
        <p className="text-sm text-[var(--neutral-foreground-2)]">
          支援計画の途中で、目標の達成状況を確認する「モニタリング」を行います。
          計画通りに進んでいるか、計画の見直しが必要かを確認し、必要に応じて調整します。
        </p>
      </div>
    ),
  },
  {
    id: 'signature',
    icon: <PenLine className="h-5 w-5" />,
    title: '電子署名について',
    content: (
      <div className="space-y-3">
        <p className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
          個別支援計画書とモニタリング表では、<strong>電子署名</strong>による確認をお願いしています。
          紙の書類に署名する代わりに、画面上で直接署名できます。
        </p>
        <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">確認手順</h4>
        <div className="space-y-1">
          <FlowStep number={1} title="計画書案を確認" description="スタッフが作成した計画書案がシステムに届きます。内容をご確認ください。" />
          <FlowArrow />
          <FlowStep number={2} title="確認またはコメント送信" description="内容に問題がなければ「確認」、変更希望がある場合は「コメントを送信」を選択します。" />
          <FlowArrow />
          <FlowStep number={3} title="面談で電子署名" description="スタッフとの面談時に、画面上で署名をお願いします。" />
        </div>
        <HighlightBox variant="success">
          <strong>電子署名の方法：</strong>スマートフォンやタブレットの場合は<strong>指で直接</strong>、
          パソコンの場合は<strong>マウス</strong>で署名欄に署名できます。書き直したい場合は「クリア」ボタンで消してやり直せます。
        </HighlightBox>
      </div>
    ),
  },
  {
    id: 'flow',
    icon: <BookOpen className="h-5 w-5" />,
    title: '書類作成の全体の流れ',
    content: (
      <div className="space-y-3">
        <p className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
          以下の流れで、日々の記録から支援計画が作成されます。
        </p>
        <div className="space-y-1">
          <FlowStep number={1} title="日々の活動記録" description="スタッフが毎回の活動を記録し、保護者へ送信" />
          <FlowArrow />
          <FlowStep number={2} title="保護者かけはし作成" description="家庭での様子や要望を記入（6か月ごと）" />
          <FlowArrow />
          <FlowStep number={3} title="スタッフかけはし作成" description="日々の記録をもとに、教室での様子をまとめる" />
          <FlowArrow />
          <FlowStep number={4} title="個別支援計画書作成" description="かけはしの内容を踏まえて、次の目標を設定" />
          <FlowArrow />
          <FlowStep number={5} title="保護者確認・同意" description="計画内容をご確認いただき、同意をいただく" />
        </div>
        <HighlightBox variant="success">
          <strong>このサイクルのポイント：</strong>日々の記録 → かけはし → 支援計画 という流れにより、
          <strong>「今のお子様の姿」に基づいた支援</strong>を行うことができます。
        </HighlightBox>
      </div>
    ),
  },
  {
    id: 'request',
    icon: <HelpCircle className="h-5 w-5" />,
    title: '保護者の皆様へのお願い',
    content: (
      <div className="space-y-3">
        <p className="text-sm leading-relaxed text-[var(--neutral-foreground-2)]">
          お子様のより良い支援のために、以下のご協力をお願いいたします。
        </p>
        <div className="space-y-2">
          {[
            {
              title: '活動記録の確認',
              description: '送信された活動記録をご確認ください。お子様の教室での様子がわかります。気になることがあればお気軽にご連絡ください。',
            },
            {
              title: 'かけはしへの記入',
              description: '期限内に保護者かけはしの記入をお願いします。家庭での様子は、支援計画を立てる上で非常に重要な情報です。',
            },
            {
              title: '個別支援計画書の確認・同意',
              description: '作成された計画書をご確認いただき、ご質問やご意見があればお知らせください。内容にご納得いただけましたら、同意の手続きをお願いします。',
            },
            {
              title: '何でもご相談ください',
              description: 'お子様のことで気になることがあれば、いつでもチャットやお電話でご相談ください。一緒にお子様の成長を支えていきましょう。',
            },
          ].map((item) => (
            <div
              key={item.title}
              className="flex gap-3 border-b border-[var(--neutral-stroke-3)] pb-3 last:border-b-0 last:pb-0"
            >
              <div className="mt-0.5 h-5 w-5 shrink-0 text-[var(--status-success-fg)]">
                <svg viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5">
                  <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                </svg>
              </div>
              <div>
                <p className="text-sm font-semibold text-[var(--neutral-foreground-1)]">{item.title}</p>
                <p className="mt-0.5 text-xs text-[var(--neutral-foreground-3)]">{item.description}</p>
              </div>
            </div>
          ))}
        </div>
        <HighlightBox variant="info">
          <strong>コミュニケーションを大切に：</strong>このシステムを通じて、保護者の皆様とスタッフが情報を共有し、
          <strong>お子様を中心とした支援チーム</strong>として一緒に歩んでいければと思います。
          ご不明な点がございましたら、お気軽にお問い合わせください。
        </HighlightBox>
      </div>
    ),
  },
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
            <BookOpen className="mt-0.5 h-5 w-5 shrink-0 text-[var(--brand-80)]" />
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
                  <ChevronUp className="h-4 w-4 text-[var(--neutral-foreground-3)]" />
                ) : (
                  <ChevronDown className="h-4 w-4 text-[var(--neutral-foreground-3)]" />
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
