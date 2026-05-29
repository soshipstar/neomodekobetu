'use client';

/**
 * チャットメッセージ本文を描画するコンポーネント。
 *
 * 要件 (現場要望):
 *   - メッセージ中に URL があれば、そのまま生の URL を出さず
 *     「リンクを開く」というボタンに変換する。
 *   - ボタンは別ウィンドウ (target="_blank") で開く。
 *   - 送信者・受信者どちらの吹き出しでも同じ見た目にする。
 *
 * 実装ポイント:
 *   - https?:// から始まる文字列を URL とみなす。trailing の句点や閉じ括弧は
 *     URL に含めず本文側に戻す ("詳細は https://example.com。" のようなケース対策)。
 *   - 同じメッセージに複数の URL が含まれる場合は順番に分割してボタンが並ぶ。
 *   - URL 部分以外の改行・空白は親要素の whitespace-pre-wrap で保持する。
 *   - セキュリティのため target=_blank には rel="noopener noreferrer" を付与。
 *   - title 属性に実 URL を入れて、ホバーで遷移先を確認できるようにする。
 *
 * @param text  既に nl() で実改行に正規化済の文字列が前提
 * @param tone  'mine' = 自分の吹き出し (白背景ボタン), 'other' = 相手の吹き出し (青背景ボタン)
 */

import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface MessageBodyProps {
  text: string;
  tone?: 'mine' | 'other';
}

// 末尾に付きやすい句読点・記号 (URL の一部ではなく文の区切り)
const TRAILING_PUNCT = /[　-〿＀-￯.,!?;:。、）)\]」』>＞]+$/;

/**
 * 文字列を「テキスト」と「URL」のセグメントに分割する。
 * URL の判定は https?:// で始まる連続非空白文字列。末尾の句読点は本文に戻す。
 */
export function splitByUrl(text: string): Array<{ type: 'text' | 'url'; value: string }> {
  const result: Array<{ type: 'text' | 'url'; value: string }> = [];
  const re = /(https?:\/\/[^\s<>"]+)/g;
  let lastIndex = 0;
  for (const match of text.matchAll(re)) {
    const idx = match.index ?? 0;
    if (idx > lastIndex) {
      result.push({ type: 'text', value: text.slice(lastIndex, idx) });
    }
    let url = match[1];
    // 末尾の句読点/閉じ括弧は本文側に戻す
    const trailing = url.match(TRAILING_PUNCT)?.[0] ?? '';
    if (trailing) {
      url = url.slice(0, url.length - trailing.length);
    }
    result.push({ type: 'url', value: url });
    if (trailing) {
      result.push({ type: 'text', value: trailing });
    }
    lastIndex = idx + match[1].length;
  }
  if (lastIndex < text.length) {
    result.push({ type: 'text', value: text.slice(lastIndex) });
  }
  return result;
}

export function MessageBody({ text, tone = 'other' }: MessageBodyProps) {
  const segments = splitByUrl(text);

  // URL が 1 つも無ければ単純な p で描画 (旧挙動と完全同等)
  if (!segments.some((s) => s.type === 'url')) {
    return <p className="whitespace-pre-wrap break-words">{text}</p>;
  }

  return (
    <p className="whitespace-pre-wrap break-words">
      {segments.map((seg, i) => {
        if (seg.type === 'text') {
          return <span key={i}>{seg.value}</span>;
        }
        return (
          <a
            key={i}
            href={seg.value}
            target="_blank"
            rel="noopener noreferrer"
            title={seg.value}
            className={
              tone === 'mine'
                ? 'my-1 inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white hover:bg-white/30 break-all'
                : 'my-1 inline-flex items-center gap-1 rounded-full bg-[var(--brand-80)] px-3 py-1 text-xs font-semibold text-white hover:bg-[var(--brand-60)] break-all'
            }
          >
            <MaterialIcon name="open_in_new" size={12} />
            リンクを開く
          </a>
        );
      })}
    </p>
  );
}

export default MessageBody;
