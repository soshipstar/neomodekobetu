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
  title: 'CARE-BRIDGE - 個別支援連絡帳システム',
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
    title: 'CARE-BRIDGE',
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

              // 日付入力 (date / datetime-local / month) で年フィールドが
              // 6桁まで入力できるブラウザ挙動を抑止する。
              // 1) 全ての該当 input に max/min 属性を強制適用 (HTML5 validation で
              //    赤枠フィードバック + フォーム送信ブロック)
              // 2) input イベントを監視し、年が 5桁以上になったら 4桁に切り詰める
              //    (React の controlled input 対応のため native setter で値を反映)
              (function() {
                var DATE_TYPES = ['date', 'datetime-local', 'month'];
                function applyLimit(el) {
                  if (!el || el.tagName !== 'INPUT') return;
                  if (DATE_TYPES.indexOf(el.type) === -1) return;
                  if (!el.max) el.max = '9999-12-31';
                  if (!el.min) el.min = '1000-01-01';
                }
                function clampYear(el) {
                  if (!el.value) return;
                  var m = el.value.match(/^(\\d+)(.*)$/);
                  if (!m || m[1].length <= 4) return;
                  var clamped = m[1].slice(0, 4) + m[2];
                  // React の controlled input にも反映するため native setter で値を上書き
                  var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                  setter.call(el, clamped);
                  el.dispatchEvent(new Event('input', { bubbles: true }));
                }
                function init() {
                  document.querySelectorAll('input').forEach(applyLimit);
                  if (window.MutationObserver) {
                    new MutationObserver(function(mutations) {
                      mutations.forEach(function(mu) {
                        mu.addedNodes.forEach(function(n) {
                          if (n.nodeType !== 1) return;
                          if (n.tagName === 'INPUT') applyLimit(n);
                          else if (n.querySelectorAll) n.querySelectorAll('input').forEach(applyLimit);
                        });
                      });
                    }).observe(document.body, { childList: true, subtree: true });
                  }
                  document.addEventListener('input', function(e) {
                    var t = e.target;
                    if (t && t.tagName === 'INPUT' && DATE_TYPES.indexOf(t.type) !== -1) {
                      clampYear(t);
                    }
                  }, true);
                }
                if (document.readyState === 'loading') {
                  document.addEventListener('DOMContentLoaded', init);
                } else {
                  init();
                }
              })();
            `,
          }}
        />
      </body>
    </html>
  );
}
