'use client';

import { useEffect, useCallback } from 'react';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface PhotoLightboxProps {
  url: string;
  alt?: string;
  filename?: string;
  onClose: () => void;
}

/**
 * iOS PWA でも戻れる画像ビューア。
 * target="_blank" で新タブを開くと standalone PWA では戻るボタンがなく
 * 連絡帳に戻れなくなるため、モーダル方式で表示する。
 */
export function PhotoLightbox({ url, alt, filename, onClose }: PhotoLightboxProps) {
  // Esc キーで閉じる
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [onClose]);

  // body スクロール固定
  useEffect(() => {
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prev;
    };
  }, []);

  const handleDownload = useCallback(async () => {
    const downloadName = filename || url.split('/').pop()?.split('?')[0] || 'photo.jpg';
    try {
      const res = await fetch(url, { credentials: 'include' });
      const blob = await res.blob();
      const objectUrl = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = objectUrl;
      link.download = downloadName;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    } catch {
      // フォールバック: 直接 href でダウンロード
      const link = document.createElement('a');
      link.href = url;
      link.download = downloadName;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  }, [url, filename]);

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label="写真ビューア"
      className="fixed inset-0 z-[100] flex flex-col bg-black/90"
      onClick={onClose}
    >
      {/* Top bar */}
      <div
        className="flex items-center justify-between gap-2 p-3 text-white"
        onClick={(e) => e.stopPropagation()}
      >
        <button
          type="button"
          onClick={handleDownload}
          className="flex items-center gap-1 rounded-md bg-white/10 px-3 py-2 text-sm font-medium text-white hover:bg-white/20 active:bg-white/30"
          aria-label="ダウンロード"
        >
          <MaterialIcon name="download" size={20} />
          <span>ダウンロード</span>
        </button>
        <button
          type="button"
          onClick={onClose}
          className="flex items-center gap-1 rounded-md bg-white/10 px-3 py-2 text-sm font-medium text-white hover:bg-white/20 active:bg-white/30"
          aria-label="閉じる"
        >
          <MaterialIcon name="close" size={20} />
          <span>閉じる</span>
        </button>
      </div>

      {/* Image area */}
      <div
        className="flex flex-1 items-center justify-center overflow-auto p-2"
        onClick={onClose}
      >
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={url}
          alt={alt ?? '写真'}
          className="max-h-full max-w-full object-contain"
          onClick={(e) => e.stopPropagation()}
        />
      </div>
    </div>
  );
}

export default PhotoLightbox;
