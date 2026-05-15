// PWA 用 PNG アイコンを SVG から生成する。
//
// Chrome の installability 要件:
//   - 192x192 PNG (purpose: any) を最低1つ
//   - 512x512 PNG (purpose: any) を最低1つ
//   - 512x512 PNG (purpose: maskable) があると Android のアダプティブアイコンとして綺麗に表示される
//
// SVG だけでは「インストール」プロンプトが表示されないブラウザが多いため、PNG を必ず用意する。
//
// 実行: node scripts/build-pwa-icons.js
//
// 既存の SVG (public/assets/icons/icon-512x512.svg) を読み込み、sharp で
// PNG にラスタライズして public/assets/icons/ に出力する。
// maskable は safe zone (中央80%) に縮小し、theme_color の余白で囲む。

const fs = require("fs");
const path = require("path");
const sharp = require("sharp");

const ICONS_DIR = path.join(__dirname, "..", "public", "assets", "icons");
const SVG_SRC = path.join(ICONS_DIR, "icon-512x512.svg");
const THEME_COLOR = "#14a898"; // root layout の viewport.themeColor と一致

async function main() {
  if (!fs.existsSync(SVG_SRC)) {
    console.error("SVG not found:", SVG_SRC);
    process.exit(1);
  }
  const svgBuf = fs.readFileSync(SVG_SRC);

  // any (透過なし、全面利用) - 192x192 と 512x512
  for (const size of [192, 512]) {
    const out = path.join(ICONS_DIR, `icon-${size}x${size}.png`);
    await sharp(svgBuf, { density: 384 })
      .resize(size, size, { fit: "cover" })
      .png({ compressionLevel: 9 })
      .toFile(out);
    console.log("wrote", out);
  }

  // maskable - 512x512、内側 80% に SVG を配置、外側余白は theme color
  const maskableSize = 512;
  const inner = Math.round(maskableSize * 0.8);
  const innerPng = await sharp(svgBuf, { density: 384 })
    .resize(inner, inner, { fit: "cover" })
    .png()
    .toBuffer();
  const maskableOut = path.join(ICONS_DIR, "icon-512x512-maskable.png");
  await sharp({
    create: {
      width: maskableSize,
      height: maskableSize,
      channels: 4,
      background: THEME_COLOR,
    },
  })
    .composite([{ input: innerPng, gravity: "center" }])
    .png({ compressionLevel: 9 })
    .toFile(maskableOut);
  console.log("wrote", maskableOut);

  // apple-touch-icon.png (180x180) も SVG から再生成して品質を揃える
  const appleOut = path.join(__dirname, "..", "public", "apple-touch-icon.png");
  await sharp(svgBuf, { density: 384 })
    .resize(180, 180, { fit: "cover" })
    .png({ compressionLevel: 9 })
    .toFile(appleOut);
  console.log("wrote", appleOut);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
