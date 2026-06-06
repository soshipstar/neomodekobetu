'use client';

/**
 * 規約・プライバシーポリシー・AI 利用方針 用の表示コンポーネント。
 *
 * バックエンドの /api/legal/{type}/{version?} から markdown を取得し、
 * 簡易的にレンダリングする。依存パッケージ追加を避けるため、care-bridge の
 * 規約原稿で実際に使用されている記法 (# / ## / 番号付き ## / - 箇条書き / 段落)
 * のみを処理するミニマルなパーサーを内蔵する。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R3a (2026-05-17)
 */

import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import type { LegalDocument } from '@/types/consent';

interface Props {
  type: 'privacy_policy' | 'terms' | 'ai_usage';
  showAgreeButton?: boolean;
  agreeButtonLabel?: string;
  onAgree?: () => void;
  agreeBusy?: boolean;
}

export function LegalDocumentView({
  type, showAgreeButton, agreeButtonLabel, onAgree, agreeBusy,
}: Props) {
  const { data, isLoading, error } = useQuery({
    queryKey: ['legal', type],
    queryFn: async () => {
      const res = await api.get<{ data: LegalDocument }>(`/api/legal/${type}`);
      return res.data.data;
    },
  });

  const [scrolledToEnd, setScrolledToEnd] = useState(false);

  if (isLoading) {
    return (
      <Card>
        <CardBody>
          <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
        </CardBody>
      </Card>
    );
  }
  if (error || !data) {
    return (
      <Card>
        <CardBody>
          <p className="text-sm text-[var(--status-danger-fg)]">
            規約本文の取得に失敗しました。お問い合わせ窓口までご連絡ください。
          </p>
        </CardBody>
      </Card>
    );
  }

  const handleScroll = (e: React.UIEvent<HTMLDivElement>) => {
    const el = e.currentTarget;
    // 終端近く (残り 40px 以内) まで読んだら同意ボタンを有効化
    if (el.scrollHeight - el.scrollTop - el.clientHeight < 40) {
      setScrolledToEnd(true);
    }
  };

  return (
    <Card>
      <CardBody>
        <div
          onScroll={handleScroll}
          className="max-h-[60vh] overflow-y-auto rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-4 text-sm"
        >
          <MarkdownRender content={data.content} />
          <p className="mt-6 text-right text-xs text-[var(--neutral-foreground-4)]">
            — 以上 (バージョン {data.version}) —
          </p>
        </div>

        {showAgreeButton && (
          <div className="mt-4 flex items-center justify-end gap-3">
            {!scrolledToEnd && (
              <p className="text-xs text-[var(--status-warning-fg)]">
                最後までスクロールしてご確認ください
              </p>
            )}
            <Button
              variant="primary"
              disabled={!scrolledToEnd || agreeBusy}
              isLoading={agreeBusy}
              onClick={onAgree}
            >
              {agreeButtonLabel || '同意して進む'}
            </Button>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

/**
 * care-bridge の規約原稿で実際に使う最小限の Markdown 記法を JSX に変換。
 * 想定:
 *   # 見出し1 / ## 見出し2 / 番号付き ## (例: ## 1. ... )
 *   - 箇条書き  /  空行で段落区切り
 *   - **太字**
 */
function MarkdownRender({ content }: { content: string }) {
  const lines = content.split('\n');
  const blocks: React.ReactNode[] = [];
  let listBuffer: string[] = [];
  let paraBuffer: string[] = [];

  const flushList = () => {
    if (listBuffer.length === 0) return;
    blocks.push(
      <ul key={`ul-${blocks.length}`} className="my-2 ml-4 list-disc space-y-1">
        {listBuffer.map((item, i) => (
          <li key={i}><InlineFormat text={item} /></li>
        ))}
      </ul>,
    );
    listBuffer = [];
  };
  const flushPara = () => {
    if (paraBuffer.length === 0) return;
    blocks.push(
      <p key={`p-${blocks.length}`} className="my-2 leading-relaxed">
        <InlineFormat text={paraBuffer.join(' ')} />
      </p>,
    );
    paraBuffer = [];
  };

  for (const raw of lines) {
    const line = raw.trim();
    if (line === '') {
      flushList();
      flushPara();
      continue;
    }
    if (line.startsWith('# ')) {
      flushList(); flushPara();
      blocks.push(
        <h1 key={`h1-${blocks.length}`} className="mt-2 mb-3 text-xl font-bold text-[var(--neutral-foreground-1)]">
          {line.slice(2)}
        </h1>,
      );
      continue;
    }
    if (line.startsWith('## ')) {
      flushList(); flushPara();
      blocks.push(
        <h2 key={`h2-${blocks.length}`} className="mt-4 mb-2 text-base font-semibold text-[var(--neutral-foreground-1)]">
          {line.slice(3)}
        </h2>,
      );
      continue;
    }
    if (line.startsWith('- ')) {
      flushPara();
      listBuffer.push(line.slice(2));
      continue;
    }
    // 太字のみの行 (バージョン情報など) も段落扱い
    paraBuffer.push(line);
  }
  flushList();
  flushPara();

  return <>{blocks}</>;
}

function InlineFormat({ text }: { text: string }) {
  // **太字** のみ対応
  const parts = text.split(/(\*\*[^*]+\*\*)/g);
  return (
    <>
      {parts.map((p, i) =>
        p.startsWith('**') && p.endsWith('**') ? (
          <strong key={i}>{p.slice(2, -2)}</strong>
        ) : (
          <span key={i}>{p}</span>
        ),
      )}
    </>
  );
}
