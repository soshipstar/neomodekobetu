import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  devIndicators: false,
  output: "standalone",
  // 本番ブラウザ向けソースマップを配信しない (既定 false だが明示)。
  // 配信すると元の TSX ソース構造が DevTools で復元でき、コピー解析が容易になるため。
  productionBrowserSourceMaps: false,
};

export default nextConfig;
