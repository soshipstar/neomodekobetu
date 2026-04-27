import type { Metadata, Viewport } from 'next';
import { Noto_Sans_JP } from 'next/font/google';
import './globals.css';
import { Providers } from './providers';

const notoSansJp = Noto_Sans_JP({
  variable: '--font-noto-sans-jp',
  subsets: ['latin'],
  weight: ['300', '400', '500', '600', '700'],
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'KIDURI - 個別支援連絡帳システム',
  description: '放課後等デイサービス向け 個別支援連絡帳システム',
  manifest: '/manifest.json',
  icons: {
    icon: '/assets/icons/icon.svg',
    apple: [
      { url: '/assets/icons/icon-192x192.svg', sizes: '192x192' },
      { url: '/assets/icons/icon-152x152.svg', sizes: '152x152' },
      { url: '/assets/icons/icon-144x144.svg', sizes: '144x144' },
    ],
  },
  appleWebApp: {
    capable: true,
    statusBarStyle: 'black-translucent',
    title: 'KIDURI',
  },
  other: {
    'mobile-web-app-capable': 'yes',
  },
};

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  maximumScale: 1,
  viewportFit: 'cover',
  themeColor: '#14a898',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="ja">
      <head>
        {/* iOS Safari は PNG の apple-touch-icon を要求する（SVG 非推奨）。
            180x180 が標準サイズで、他サイズは iOS が縮小/拡大して使う。 */}
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" />
      </head>
      <body className={`${notoSansJp.variable} font-sans antialiased`}>
        <Providers>{children}</Providers>
        <script
          dangerouslySetInnerHTML={{
            __html: `
              // ServiceWorker は HTTPS 必須かつ本番のみ有効化する。
              // 開発時は dev server の HMR と SW のキャッシュが競合し、
              // 古いキャッシュが残ったまま新しいビルドが反映されない問題が出るため除外。
              if ('serviceWorker' in navigator && location.protocol === 'https:') {
                window.addEventListener('load', function() {
                  navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                      console.log('ServiceWorker registered:', registration.scope);
                    })
                    .catch(function(error) {
                      console.log('ServiceWorker registration failed:', error);
                    });
                });
              } else if ('serviceWorker' in navigator) {
                // 開発時は既存登録を解除しキャッシュを削除（リロード後に有効）
                navigator.serviceWorker.getRegistrations().then(function(regs) {
                  regs.forEach(function(r) { r.unregister(); });
                });
                if (window.caches) {
                  caches.keys().then(function(keys) { keys.forEach(function(k) { caches.delete(k); }); });
                }
              }
            `,
          }}
        />
      </body>
    </html>
  );
}
