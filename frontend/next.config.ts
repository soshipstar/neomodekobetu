import type { NextConfig } from "next";

// Content-Security-Policy
// ------------------------------------------------------------------
// frontend (Next.js) のページにのみ適用される。/api と /api/widget は
// nginx/Laravel が配信するため、この CSP の影響を受けない
// (= 外部サイト埋め込みの空き状況ウィジェットは従来どおり動作する)。
//
// 方針:
//  - 高価値の制限 (object-src 'none' / base-uri 'self' / form-action 'self' /
//    frame-ancestors 'self') は厳格に。クリックジャッキング・base 乗っ取り・
//    フォーム流出・プラグイン実行を防ぐ。
//  - Next.js はインラインスクリプト/スタイルを多用するため script/style は
//    'unsafe-inline' を許可 (これが無いとアプリが動かない)。XSS 完全防御には
//    ならないが、上記の制限と多層防御で実効リスクを下げる。
//  - 既知の外部リソースを明示許可:
//      Google Fonts (fonts.googleapis.com / fonts.gstatic.com)
//      Material Symbols (cdn.jsdelivr.net)
//      アバター (api.dicebear.com / images.unsplash.com など https: 画像)
//      Reverb WebSocket (wss://kiduri.xyz)
const CSP = [
  "default-src 'self'",
  "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
  "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
  "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net",
  "img-src 'self' data: blob: https:",
  "connect-src 'self' https://kiduri.xyz wss://kiduri.xyz https://api.dicebear.com",
  "frame-ancestors 'self'",
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
].join('; ');

const nextConfig: NextConfig = {
  devIndicators: false,
  output: "standalone",
  // 本番ブラウザ向けソースマップを配信しない (既定 false だが明示)。
  // 配信すると元の TSX ソース構造が DevTools で復元でき、コピー解析が容易になるため。
  productionBrowserSourceMaps: false,

  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          { key: 'Content-Security-Policy', value: CSP },
          // 参照元ヘッダを最小化 (外部遷移時に URL パスを漏らさない)
          { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
          // 位置情報・カメラ・マイクなど不要な機能を既定で無効化
          { key: 'Permissions-Policy', value: 'geolocation=(), microphone=(), camera=(), payment=()' },
        ],
      },
    ];
  },
};

export default nextConfig;
