'use client';

/**
 * 同意取得ゲート: 認証ユーザーが規約・プライバシーポリシー・AI 利用方針 (該当時)
 * の現行バージョンに同意していない場合、内容を表示して同意を取得する。
 *
 * - 未認証ユーザーには何も表示しない (子要素をそのまま描画)
 * - /legal/* ページや /auth/login ページ等は同意なしでも閲覧可能とする
 * - 同意未完了の間はメインコンテンツを暗くして、モーダル風に同意フローを表示
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R3a (2026-05-17)
 */

import { useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { useAuthStore } from '@/stores/authStore';
import { LegalDocumentView } from '@/components/legal/LegalDocumentView';
import { CONSENT_LABELS, type ConsentStatus, type ConsentType } from '@/types/consent';
import { useToast } from '@/components/ui/Toast';

interface Props {
  children: React.ReactNode;
}

// 同意未取得でもアクセス許可するパスのプレフィックス
const PUBLIC_PATH_PREFIXES = ['/auth', '/legal'];

export function ConsentRequiredGate({ children }: Props) {
  const pathname = usePathname() ?? '';
  const { user, isAuthenticated, isLoading } = useAuthStore();
  const queryClient = useQueryClient();
  const toast = useToast();

  // ゲートを抜けるべきページ
  const isPublicPath = PUBLIC_PATH_PREFIXES.some((p) => pathname.startsWith(p));

  // 同意状態を取得 (認証済みのみ)
  const { data: status } = useQuery({
    queryKey: ['me', 'consents'],
    queryFn: async () => {
      const res = await api.get<{ data: ConsentStatus }>('/api/me/consents');
      return res.data.data;
    },
    enabled: !!isAuthenticated && !!user && !isPublicPath,
  });

  // 同意プロセス内で現在表示中の種別
  const [activeType, setActiveType] = useState<ConsentType | null>(null);

  useEffect(() => {
    if (status?.needs_consent && status.needs_consent.length > 0) {
      setActiveType(status.needs_consent[0]);
    } else {
      setActiveType(null);
    }
  }, [status?.needs_consent]);

  const grantMutation = useMutation({
    mutationFn: async (type: ConsentType) => {
      const version = status?.current_versions?.[type];
      if (!version) throw new Error('現行バージョン情報がありません');
      await api.post('/api/me/consents', { consent_type: type, version });
    },
    onSuccess: () => {
      toast.success('同意を記録しました');
      queryClient.invalidateQueries({ queryKey: ['me', 'consents'] });
    },
    onError: () => toast.error('同意の記録に失敗しました'),
  });

  // 未認証 / 公開パス / 同意未取得情報がまだ無い場合は素通し
  if (!isAuthenticated || isLoading || isPublicPath || !status) {
    return <>{children}</>;
  }

  // 全同意済み
  if (status.needs_consent.length === 0) {
    return <>{children}</>;
  }

  // 同意フロー表示中: メインコンテンツの上にオーバーレイ
  return (
    <>
      <div aria-hidden className="pointer-events-none opacity-30">
        {children}
      </div>
      <div className="fixed inset-0 z-[60] flex items-center justify-center bg-[var(--neutral-foreground-1)]/60 p-4">
        <div className="w-full max-w-3xl rounded-lg bg-[var(--neutral-background-1)] shadow-xl">
          <div className="border-b border-[var(--neutral-stroke-2)] px-4 py-3">
            <h2 className="text-lg font-bold text-[var(--neutral-foreground-1)]">
              ご利用前に同意が必要です
            </h2>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              {status.needs_consent.length} 件の同意項目があります (現在: {' '}
              {activeType ? CONSENT_LABELS[activeType] : ''})。
              各内容を最後までスクロールしてご確認のうえ、ご同意ください。
            </p>
          </div>
          <div className="p-4">
            {activeType && activeType !== 'child_ai_consent' && (
              <LegalDocumentView
                key={activeType}
                type={activeType as 'privacy_policy' | 'terms' | 'ai_usage'}
                showAgreeButton
                agreeButtonLabel={`${CONSENT_LABELS[activeType]} に同意する`}
                agreeBusy={grantMutation.isPending}
                onAgree={() => grantMutation.mutate(activeType)}
              />
            )}
          </div>
        </div>
      </div>
    </>
  );
}
