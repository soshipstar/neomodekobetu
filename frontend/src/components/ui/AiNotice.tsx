import { MaterialIcon } from '@/components/ui/MaterialIcon';

/**
 * AI生成機能の明示＋免責の統一表示 (AIセーフティ観点7 説明可能性 / 観点2 偽誤情報)。
 * AI生成ボタンの近くに配置し、「AI生成であること」「必ず職員が確認すること」
 * 「誤情報を含み得ること」を一貫して伝える。
 */
export function AiNotice({ className = '' }: { className?: string }) {
  return (
    <div
      role="note"
      className={`flex items-start gap-2 rounded-md border border-[var(--status-warning-fg)] bg-[rgba(var(--status-warning-rgb,255,149,0),0.08)] p-2.5 text-xs leading-relaxed text-[var(--neutral-foreground-2)] ${className}`}
    >
      <MaterialIcon name="smart_toy" size={16} className="mt-0.5 shrink-0 text-[var(--status-warning-fg)]" />
      <span>
        <strong className="text-[var(--status-warning-fg)]">AIが生成する下書きです。</strong>
        内容は参考情報であり、必ず専門職員が確認・修正してから保存・確定してください。
        AIは入力情報に無い事実（誤情報）を含むことがあります。
      </span>
    </div>
  );
}

export default AiNotice;
