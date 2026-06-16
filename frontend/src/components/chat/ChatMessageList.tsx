'use client';

import { useEffect, useRef } from 'react';
import { ChatBubble } from './ChatBubble';
import type { ChatMessage } from '@/types/chat';

interface ChatMessageListProps {
  messages: ChatMessage[];
  currentUserId: number;
}

export function ChatMessageList({ messages, currentUserId }: ChatMessageListProps) {
  const bottomRef = useRef<HTMLDivElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const prevRoomKeyRef = useRef<string | null>(null);

  // 最下部へスクロールする共通処理。
  // behavior='auto' なら即時ジャンプ (初回表示用)、'smooth' なら新着用。
  const scrollToBottom = (behavior: ScrollBehavior) => {
    bottomRef.current?.scrollIntoView({ behavior, block: 'end' });
  };

  // メッセージ集合が変わったら最下部へ。
  // バグ: チャット履歴を開いた直後、画像添付の読み込み完了で高さが伸び、
  //       smooth スクロールが途中で止まり「最新が見えない」ことがあった
  //       (画像がキャッシュ済みか否かで再現が変わる=「人/時による」)。
  // 対策:
  //   - 別ルームを開いた初回や件数が変わった瞬間は即時ジャンプを複数回行う
  //     (レイアウト確定後にも当てる)。
  //   - 添付画像の load 完了 (= 高さ変化) でも最下部へ再スクロールする。
  useEffect(() => {
    // ルーム切替や初回ロードかどうかを「先頭メッセージID+件数」で判定
    const first = messages[0];
    const roomKey = first ? `${first.id}:${messages.length}` : `empty:${messages.length}`;
    const isInitialForRoom = prevRoomKeyRef.current === null
      || prevRoomKeyRef.current.split(':')[0] !== roomKey.split(':')[0];
    prevRoomKeyRef.current = roomKey;

    // 即時で当てたあと、レイアウト/フォント確定後にもう一度当てる
    scrollToBottom('auto');
    const raf = requestAnimationFrame(() => scrollToBottom('auto'));
    const t = setTimeout(() => scrollToBottom(isInitialForRoom ? 'auto' : 'smooth'), 120);

    return () => {
      cancelAnimationFrame(raf);
      clearTimeout(t);
    };
  }, [messages]);

  // 添付画像の読み込み完了で高さが変わったら最下部へ再スクロール。
  // (初回表示時に画像が後から読み込まれて途中で止まる問題への保険)
  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;
    const imgs = Array.from(container.querySelectorAll('img'));
    if (imgs.length === 0) return;

    const onLoad = () => scrollToBottom('auto');
    imgs.forEach((img) => {
      if (!img.complete) img.addEventListener('load', onLoad);
    });
    return () => imgs.forEach((img) => img.removeEventListener('load', onLoad));
  }, [messages]);

  if (messages.length === 0) {
    return (
      <div className="flex h-full items-center justify-center">
        <p className="text-sm text-[var(--neutral-foreground-4)]">メッセージはまだありません</p>
      </div>
    );
  }

  // 日付グループ用に「直前のメッセージの日付」を事前計算する。
  // render 中の let 変数再代入は React 19 strict (react-hooks/immutability) で
  // エラーになるため、map の前に index 配列を組み立てる純粋計算に変更。
  const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString('ja-JP', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  const dateBoundaries = messages.map((m, i) => {
    const cur = formatDate(m.created_at);
    const prev = i === 0 ? '' : formatDate(messages[i - 1].created_at);
    return { messageDate: cur, showDateSeparator: cur !== prev };
  });

  return (
    <div ref={containerRef} className="flex flex-col gap-1 p-4">
      {messages.map((message, i) => {
        const { messageDate, showDateSeparator } = dateBoundaries[i];

        return (
          <div key={message.id}>
            {showDateSeparator && (
              <div className="my-4 flex items-center justify-center">
                <span className="rounded-full bg-[var(--neutral-background-5)] px-3 py-1 text-xs text-[var(--neutral-foreground-3)]">
                  {messageDate}
                </span>
              </div>
            )}
            <ChatBubble
              message={message}
              isMine={message.sender_id === currentUserId}
            />
          </div>
        );
      })}
      <div ref={bottomRef} />
    </div>
  );
}

export default ChatMessageList;
