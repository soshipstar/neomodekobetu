'use client';

import { useEffect } from 'react';

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const payload = {
        message: error?.message ?? '(no message)',
        name: error?.name ?? '(no name)',
        stack: error?.stack ?? '(no stack)',
        digest: error?.digest ?? '(no digest)',
        url: window.location.href,
        userAgent: navigator.userAgent,
        timestamp: new Date().toISOString(),
      };
      // eslint-disable-next-line no-console
      console.error('[GlobalError]', payload);
      try {
        fetch('/api/client-errors', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
          keepalive: true,
        }).catch(() => {});
      } catch {
        // ignore
      }
    }
  }, [error]);

  const details = [
    `name: ${error?.name ?? ''}`,
    `message: ${error?.message ?? ''}`,
    `digest: ${error?.digest ?? ''}`,
    '',
    error?.stack ?? '',
    '',
    typeof navigator !== 'undefined' ? `UA: ${navigator.userAgent}` : '',
    typeof window !== 'undefined' ? `URL: ${window.location.href}` : '',
  ].join('\n');

  return (
    <html lang="ja">
      <body
        style={{
          margin: 0,
          padding: 16,
          fontFamily:
            '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
          background: '#fafaf9',
          color: '#1c1917',
          minHeight: '100vh',
        }}
      >
        <div
          style={{
            maxWidth: 720,
            margin: '0 auto',
            background: '#fff',
            border: '1px solid #e7e5e4',
            borderRadius: 8,
            padding: 16,
          }}
        >
          <h1 style={{ fontSize: 18, fontWeight: 600, marginTop: 0 }}>
            エラーが発生しました
          </h1>
          <p style={{ fontSize: 14, color: '#57534e' }}>
            下の情報をスクリーンショットで送ってください。
          </p>
          <pre
            style={{
              fontSize: 12,
              background: '#f5f5f4',
              border: '1px solid #e7e5e4',
              borderRadius: 6,
              padding: 12,
              whiteSpace: 'pre-wrap',
              wordBreak: 'break-word',
              maxHeight: '50vh',
              overflow: 'auto',
            }}
          >
            {details}
          </pre>
          <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
            <button
              type="button"
              onClick={() => reset()}
              style={{
                padding: '8px 16px',
                background: '#14a898',
                color: '#fff',
                border: 'none',
                borderRadius: 6,
                fontSize: 14,
                cursor: 'pointer',
              }}
            >
              再試行
            </button>
            <button
              type="button"
              onClick={() => {
                if (typeof window !== 'undefined') {
                  window.location.href = '/auth/login';
                }
              }}
              style={{
                padding: '8px 16px',
                background: '#fff',
                color: '#1c1917',
                border: '1px solid #d6d3d1',
                borderRadius: 6,
                fontSize: 14,
                cursor: 'pointer',
              }}
            >
              ログインへ戻る
            </button>
          </div>
        </div>
      </body>
    </html>
  );
}
