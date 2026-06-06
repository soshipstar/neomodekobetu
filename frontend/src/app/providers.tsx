'use client';

import { useState, type ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { FluentProvider, webLightTheme } from '@fluentui/react-components';
import { ToastProvider } from '@/components/ui/Toast';
import { ConsentRequiredGate } from '@/components/legal/ConsentRequiredGate';

export function Providers({ children }: { children: ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30 * 1000,
            retry: 1,
            refetchOnWindowFocus: false,
          },
        },
      })
  );

  return (
    <FluentProvider theme={webLightTheme}>
      <QueryClientProvider client={queryClient}>
        <ToastProvider>
          {/* AISI R3a: 規約・プライバシーポリシー・AI 利用方針への同意ゲート。
              未同意ユーザーには認証後の最初の画面でモーダル風に同意フローを提示。
              /auth/* と /legal/* は同意なしでも閲覧可能 (ゲート内で素通し)。 */}
          <ConsentRequiredGate>{children}</ConsentRequiredGate>
        </ToastProvider>
      </QueryClientProvider>
    </FluentProvider>
  );
}
