/**
 * 画像をクライアント側で目標バイト数以下まで自動的にダウンサイズ/再エンコードする。
 *
 * 用途: チャット添付などで大きい画像をはじかずに送れるようにする。
 * 旧仕様: 3MB を超えたら alert で拒否
 * 新仕様: 画像なら最大 300KB まで自動圧縮 (Canvas + JPEG 再エンコード)
 *
 * アルゴリズム:
 *  - 元画像を Image にロード
 *  - 最大辺の長さ (1920→1280→...→640) と JPEG quality (0.92→...→0.4) の
 *    組み合わせを順に試し、最初に targetBytes 以下になったものを返す
 *  - どれもダメなら最小サイズの blob を返す (= 元より小さい何かは返す)
 *  - 画像でないファイルや既に十分小さいファイルは元のまま返す
 *
 * 注意点:
 *  - 結果は image/jpeg として書き出す (透過は黒背景として描画)
 *  - 元の拡張子に関わらずファイル名末尾を .jpg に置換
 *  - HEIC/HEIF など一部ブラウザがデコードできない形式は throw する
 */

const MAX_DIMS = [1920, 1280, 1024, 800, 640];
const QUALITIES = [0.92, 0.85, 0.78, 0.7, 0.6, 0.5, 0.4];

export const DEFAULT_TARGET_BYTES = 300 * 1024;

export interface CompressResult {
  file: File;
  compressed: boolean;
  originalBytes: number;
  resultBytes: number;
}

export async function compressImageToBytes(
  source: File,
  targetBytes: number = DEFAULT_TARGET_BYTES,
): Promise<CompressResult> {
  // 画像でなければ何もしない
  if (!source.type.startsWith('image/')) {
    return { file: source, compressed: false, originalBytes: source.size, resultBytes: source.size };
  }
  // 既に十分小さい
  if (source.size <= targetBytes) {
    return { file: source, compressed: false, originalBytes: source.size, resultBytes: source.size };
  }

  const img = await loadImage(source);

  let best: Blob | null = null;
  for (const maxDim of MAX_DIMS) {
    for (const quality of QUALITIES) {
      const blob = await renderToJpegBlob(img, maxDim, quality);
      if (blob.size <= targetBytes) {
        best = blob;
        break;
      }
      if (!best || blob.size < best.size) best = blob;
    }
    if (best && best.size <= targetBytes) break;
  }

  if (!best) {
    // 何らかの理由で blob 生成に失敗
    return { file: source, compressed: false, originalBytes: source.size, resultBytes: source.size };
  }

  const newName = source.name.replace(/\.[^.]+$/, '') + '.jpg';
  const compressedFile = new File([best], newName, {
    type: 'image/jpeg',
    lastModified: Date.now(),
  });
  return {
    file: compressedFile,
    compressed: true,
    originalBytes: source.size,
    resultBytes: compressedFile.size,
  };
}

function loadImage(file: File): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = (err) => {
      URL.revokeObjectURL(url);
      // HEIC など Safari でしか開けないフォーマットや破損データはここに来る
      reject(new Error('画像を読み込めませんでした (対応していないフォーマットの可能性があります)'));
    };
    img.src = url;
  });
}

function renderToJpegBlob(img: HTMLImageElement, maxDim: number, quality: number): Promise<Blob> {
  return new Promise((resolve, reject) => {
    const { width: w0, height: h0 } = img;
    const scale = Math.min(1, maxDim / Math.max(w0, h0));
    const w = Math.max(1, Math.round(w0 * scale));
    const h = Math.max(1, Math.round(h0 * scale));

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
      reject(new Error('canvas context が取得できませんでした'));
      return;
    }
    // JPEG は透過を持たないので、PNG の透明部分を黒/白で塗り潰す代わりに白で。
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(img, 0, 0, w, h);

    canvas.toBlob(
      (blob) => {
        if (!blob) {
          reject(new Error('JPEG エンコードに失敗しました'));
          return;
        }
        resolve(blob);
      },
      'image/jpeg',
      quality,
    );
  });
}
