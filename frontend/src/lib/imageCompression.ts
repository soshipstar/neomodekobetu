/**
 * 画像圧縮ユーティリティ (ブラウザサイド)
 *
 * スマホで撮影した画像は 5-10MB の JPEG/HEIC が多く、そのままアップロードすると
 * サーバー側の容量制限 (例: 生徒チャット 3MB) に引っかかる。ブラウザの canvas
 * API でリサイズ + JPEG 再エンコードして容量を削減する。
 *
 * 主な用途:
 * - タブレット写真ライブラリ (frontend/src/app/tablet/photos/page.tsx)
 * - 生徒チャット添付 (frontend/src/app/student/chat/page.tsx)
 * - 事業所写真 (frontend/src/app/staff/classroom-photos/page.tsx)
 *
 * HEIC/HEIF は <img> が直接デコードできない端末 (Android Chrome 等) があるため、
 * compressImage が失敗した際に isHeicFile() で判定して案内文を出す。
 */

const HEIC_EXTENSIONS = ['.heic', '.heif'];
const HEIC_MIMES = ['image/heic', 'image/heif'];

/**
 * HEIC/HEIF 画像か (拡張子・MIME ベースの判定)
 */
export function isHeicFile(file: File): boolean {
  const name = (file.name || '').toLowerCase();
  if (HEIC_EXTENSIONS.some((ext) => name.endsWith(ext))) return true;
  const mime = (file.type || '').toLowerCase();
  return HEIC_MIMES.some((m) => mime === m);
}

/**
 * HEIC/HEIF 検出時にユーザーに案内するメッセージ。
 * iOS 写真設定の「フォーマット > 互換性優先」を案内する。
 */
export const HEIC_HINT =
  'HEIC形式の画像はこのブラウザで読み込めません。iPhone の「設定 > カメラ > フォーマット」を「互換性優先」に変更してから撮り直してください。';

/**
 * 画像を JPEG として圧縮する。
 *
 * - 横幅が maxWidth を超えていれば縦横比を保ってリサイズ
 * - canvas で再描画して JPEG (quality) で再エンコード
 * - ファイル名は `<元名>.jpg` に置き換え
 *
 * 失敗時 (HEIC で <img> がデコード不能 / canvas 不利用 等) は reject。
 * 呼び出し側で isHeicFile() を使って案内文を出すとよい。
 */
export function compressImage(
  src: File,
  maxWidth = 2048,
  quality = 0.85,
): Promise<File> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(src);
    const img = new Image();
    img.onload = () => {
      let w = img.naturalWidth;
      let h = img.naturalHeight;
      if (w > maxWidth) {
        h = Math.round(h * (maxWidth / w));
        w = maxWidth;
      }
      const canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        URL.revokeObjectURL(url);
        reject(new Error('Canvas not supported'));
        return;
      }
      ctx.drawImage(img, 0, 0, w, h);
      canvas.toBlob(
        (blob) => {
          URL.revokeObjectURL(url);
          if (!blob) {
            reject(new Error('Blob conversion failed'));
            return;
          }
          const name = src.name.replace(/\.[^.]+$/, '') + '.jpg';
          resolve(new File([blob], name, { type: 'image/jpeg' }));
        },
        'image/jpeg',
        quality,
      );
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('画像を読み込めませんでした'));
    };
    img.src = url;
  });
}

/**
 * 指定サイズ以下になるまで画像を段階的に圧縮する。
 * 初回 maxWidth/quality でだめなら徐々に縮小・低品質化を試す。
 *
 * @param src     元ファイル
 * @param targetBytes 目標バイト数 (例: 3 * 1024 * 1024)
 * @param opts.initialMaxWidth   初回の最大幅 (デフォルト 2048)
 * @param opts.initialQuality    初回の品質 (デフォルト 0.85)
 * @param opts.minWidth          これ以上小さくはしない (デフォルト 800)
 * @param opts.minQuality        これ以上下げない (デフォルト 0.55)
 */
export async function compressImageUnderSize(
  src: File,
  targetBytes: number,
  opts: {
    initialMaxWidth?: number;
    initialQuality?: number;
    minWidth?: number;
    minQuality?: number;
  } = {},
): Promise<File> {
  const initialMaxWidth = opts.initialMaxWidth ?? 2048;
  const initialQuality = opts.initialQuality ?? 0.85;
  const minWidth = opts.minWidth ?? 800;
  const minQuality = opts.minQuality ?? 0.55;

  // 元から target 以下なら無加工で返す
  if (src.size <= targetBytes && src.type === 'image/jpeg') {
    return src;
  }

  let width = initialMaxWidth;
  let quality = initialQuality;
  let result = await compressImage(src, width, quality);

  // 上限以下になるまで縮小ループ (安全策で最大8回)
  for (let i = 0; i < 8 && result.size > targetBytes; i++) {
    if (width > minWidth) {
      width = Math.max(minWidth, Math.round(width * 0.8));
    } else if (quality > minQuality) {
      quality = Math.max(minQuality, quality - 0.1);
    } else {
      break;
    }
    result = await compressImage(src, width, quality);
  }

  return result;
}
