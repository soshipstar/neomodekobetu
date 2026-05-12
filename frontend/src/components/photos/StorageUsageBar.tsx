'use client';

import { MaterialIcon } from '@/components/ui/MaterialIcon';

/**
 * 写真ライブラリの容量使用状況を進捗バー + 段階的警告で表示する共通コンポーネント。
 *
 * 段階的色変化:
 *  - 0%〜79%:   緑 (通常)
 *  - 80%〜94%:  黄 (注意)
 *  - 95%以上:   赤 (警告: 古い写真の削除を促す)
 *
 * 100% 達成時は「これ以上アップロードできません」のメッセージを追加表示。
 * 「ただ写真が送れなくなる」状態をユーザーに気づかせるための事前可視化が目的。
 */
export interface StorageUsageBarProps {
  /** 現在の使用量 (bytes) */
  usedBytes: number;
  /** 上限 (bytes) */
  limitBytes: number;
  /** タイトル/ラベル (省略時: 「写真保存容量」) */
  label?: string;
  /** 余白を詰めて表示する (小さなインライン表示用) */
  compact?: boolean;
  className?: string;
}

function formatMB(bytes: number): string {
  return (bytes / 1024 / 1024).toFixed(1);
}

export function StorageUsageBar({
  usedBytes,
  limitBytes,
  label = '写真保存容量',
  compact = false,
  className = '',
}: StorageUsageBarProps) {
  const ratio = limitBytes > 0 ? usedBytes / limitBytes : 0;
  const percent = Math.min(100, Math.round(ratio * 100));

  const isCritical = ratio >= 0.95;
  const isWarning = !isCritical && ratio >= 0.80;
  const isFull = ratio >= 1.0;

  // バーの色 (進行中の塗り)
  const barColor = isCritical
    ? 'bg-[var(--status-danger-fg)]'
    : isWarning
      ? 'bg-[var(--status-warning-fg)]'
      : 'bg-[var(--status-success-fg)]';

  // 数字テキストの色
  const textColor = isCritical
    ? 'text-[var(--status-danger-fg)]'
    : isWarning
      ? 'text-[var(--status-warning-fg)]'
      : 'text-[var(--neutral-foreground-2)]';

  // 警告メッセージ
  const message = isFull
    ? '容量上限に達しました。新規アップロードできません。古い写真を削除してください。'
    : isCritical
      ? 'まもなく容量上限に達します。古い写真の削除を検討してください。'
      : isWarning
        ? '容量の使用が増えています。'
        : null;

  return (
    <div className={`${compact ? 'space-y-1' : 'space-y-2'} ${className}`}>
      <div className="flex items-center justify-between text-xs">
        <span className="font-semibold text-[var(--neutral-foreground-3)]">{label}</span>
        <span className={`font-mono font-semibold ${textColor}`}>
          {formatMB(usedBytes)} / {formatMB(limitBytes)} MB ({percent}%)
        </span>
      </div>
      <div className={`h-2 w-full overflow-hidden rounded-full bg-[var(--neutral-background-3)]`}>
        <div
          className={`h-full transition-all duration-300 ${barColor}`}
          style={{ width: `${percent}%` }}
          role="progressbar"
          aria-valuenow={percent}
          aria-valuemin={0}
          aria-valuemax={100}
        />
      </div>
      {message && (
        <div
          className={`flex items-start gap-1.5 rounded-md px-2 py-1.5 text-xs ${
            isFull || isCritical
              ? 'bg-[var(--status-danger-bg)] text-[var(--status-danger-fg)]'
              : 'bg-[var(--status-warning-bg)] text-[var(--status-warning-fg)]'
          }`}
        >
          <MaterialIcon name={isFull || isCritical ? 'error' : 'warning'} size={14} />
          <span>{message}</span>
        </div>
      )}
    </div>
  );
}

export default StorageUsageBar;
