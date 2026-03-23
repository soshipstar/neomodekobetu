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
  themeColor: '#2563eb',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="ja">
      <head>
        <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.svg" />
        <link rel="apple-touch-icon" sizes="152x152" href="/assets/icons/icon-152x152.svg" />
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/icon-192x192.svg" />
        <link rel="apple-touch-icon" sizes="167x167" href="/assets/icons/icon-192x192.svg" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" />
      </head>
      <body className={`${notoSansJp.variable} font-sans antialiased`}>
        <Providers>{children}</Providers>
        <script
          dangerouslySetInnerHTML={{
            __html: `
              if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                  navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                      console.log('ServiceWorker registered:', registration.scope);
                    })
                    .catch(function(error) {
                      console.log('ServiceWorker registration failed:', error);
                    });
                });
              }
            `,
          }}
        />
      </body>
    </html>
  );
}
