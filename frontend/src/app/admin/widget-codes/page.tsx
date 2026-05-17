'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';
import { Skeleton } from '@/components/ui/Skeleton';

/**
 * 教室ごとの HP 埋め込みウィジェット コード管理画面。
 *
 * 用途:
 *   各教室の HP に「曜日別空き状況」を iframe で公開するためのコードを
 *   発行・コピー・無効化する。
 *
 * 公開される情報:
 *   - 教室名
 *   - 曜日別の在籍数 / 定員 / 空き数 / 状態 (空きあり/残り少/満員/休業)
 *   個人情報 (児童名・保護者名) は一切公開しない。
 *
 * 動作:
 *   1. 「コードを発行」を押す → /api/admin/classrooms/{id}/widget-token (POST)
 *      でランダムトークンが生成され、URL と iframe スニペットが返る
 *   2. iframe スニペットを HP の HTML に貼り付ける
 *   3. ウィジェットは 60 秒ごとに自動的に最新の空き状況を取得して描画する
 *   4. 公開停止したい場合は「無効化」ボタン (※既に貼られた iframe は壊れる)
 */

interface WidgetInfo {
  classroom_id: number;
  classroom_name: string;
  token: string | null;
  widget_url: string | null;
  data_url: string | null;
  iframe_html: string | null;
}

export default function WidgetCodesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const { data: widgets = [], isLoading } = useQuery({
    queryKey: ['admin', 'widget-tokens'],
    queryFn: async () => {
      const res = await api.get<{ data: WidgetInfo[] }>('/api/admin/widget-tokens');
      return res.data.data;
    },
  });

  const issueMutation = useMutation({
    mutationFn: (classroomId: number) =>
      api.post(`/api/admin/classrooms/${classroomId}/widget-token`),
    onSuccess: (_, classroomId) => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'widget-tokens'] });
      toast.success('ウィジェットコードを発行しました');
      // 視覚的に動作確認しやすいように、発行直後は preview を自動展開する
      setExpanded((p) => ({ ...p, [classroomId]: true }));
    },
    onError: (e: { response?: { data?: { message?: string } } }) =>
      toast.error(e?.response?.data?.message || '発行に失敗しました'),
  });

  const revokeMutation = useMutation({
    mutationFn: (classroomId: number) =>
      api.delete(`/api/admin/classrooms/${classroomId}/widget-token`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'widget-tokens'] });
      toast.success('ウィジェットコードを無効化しました');
    },
    onError: () => toast.error('無効化に失敗しました'),
  });

  const [expanded, setExpanded] = useState<Record<number, boolean>>({});

  const copy = async (text: string, label: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast.success(`${label}をコピーしました`);
    } catch {
      toast.error('コピーに失敗しました');
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-xl font-bold text-[var(--neutral-foreground-1)] sm:text-2xl">
          HP 埋め込みコード
        </h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          教室の HP に貼り付けると、曜日別の空き状況がリアルタイム (60秒ごと更新)
          で表示される iframe コードを発行します。個人情報は公開されません。
        </p>
      </div>

      {/* 使い方の手引き */}
      <Card>
        <CardBody>
          <details>
            <summary className="cursor-pointer text-sm font-medium text-[var(--neutral-foreground-1)]">
              使い方 (クリックで開閉)
            </summary>
            <ol className="mt-3 ml-5 list-decimal space-y-2 text-sm text-[var(--neutral-foreground-2)]">
              <li>下の教室一覧から、対象の教室の「コードを発行」を押す</li>
              <li>表示された <strong>iframe コード</strong> を「コピー」ボタンでコピー</li>
              <li>
                教室 HP の HTML 編集画面で、空き状況を表示したい場所に貼り付ける
                <br />
                (WordPress なら「カスタム HTML ブロック」、Wix / Jimdo なら「HTML 埋め込み」など)
              </li>
              <li>HP を保存 → 自動的に最新の空き状況が表示されます</li>
              <li>表示を止めたいときは「無効化」を押すと iframe は表示されなくなります</li>
            </ol>
            <p className="mt-3 rounded bg-[var(--neutral-background-3)] p-3 text-xs text-[var(--neutral-foreground-3)]">
              注意:「再発行」を押すと、これまで HP に貼っていたコードは無効になります。
              新しいコードを HP に貼り直す必要があります。
            </p>
          </details>
        </CardBody>
      </Card>

      {/* 教室一覧 */}
      {isLoading ? (
        <Skeleton className="h-32 w-full" />
      ) : widgets.length === 0 ? (
        <Card>
          <CardBody>
            <p className="text-sm text-[var(--neutral-foreground-3)]">
              対象の教室がありません。
            </p>
          </CardBody>
        </Card>
      ) : (
        widgets.map((w) => (
          <Card key={w.classroom_id}>
            <CardHeader>
              <div className="flex items-center justify-between gap-2 flex-wrap">
                <CardTitle>{w.classroom_name}</CardTitle>
                <div className="flex items-center gap-2">
                  {w.token ? (
                    <>
                      <span className="flex items-center gap-1 rounded-full bg-[var(--status-success-bg)] px-2.5 py-0.5 text-xs font-medium text-[var(--status-success-fg)]">
                        <MaterialIcon name="check_circle" size={12} />
                        発行済
                      </span>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          if (
                            confirm(
                              `${w.classroom_name} のコードを再発行します。\n\n再発行すると、これまで HP に貼っていた iframe は表示されなくなります。HP のコードを差し替える必要があります。\n\n続けますか？`,
                            )
                          ) {
                            issueMutation.mutate(w.classroom_id);
                          }
                        }}
                        isLoading={issueMutation.isPending && issueMutation.variables === w.classroom_id}
                        leftIcon={<MaterialIcon name="refresh" size={14} />}
                      >
                        再発行
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          if (
                            confirm(
                              `${w.classroom_name} のコードを無効化します。\nHP に貼っている iframe は表示されなくなります。\n\n続けますか？`,
                            )
                          ) {
                            revokeMutation.mutate(w.classroom_id);
                          }
                        }}
                        isLoading={revokeMutation.isPending && revokeMutation.variables === w.classroom_id}
                        leftIcon={<MaterialIcon name="delete" size={14} />}
                      >
                        無効化
                      </Button>
                    </>
                  ) : (
                    <Button
                      variant="primary"
                      size="sm"
                      onClick={() => issueMutation.mutate(w.classroom_id)}
                      isLoading={issueMutation.isPending && issueMutation.variables === w.classroom_id}
                      leftIcon={<MaterialIcon name="add_link" size={14} />}
                    >
                      コードを発行
                    </Button>
                  )}
                </div>
              </div>
            </CardHeader>

            {w.token && (
              <CardBody>
                {/* iframe コード */}
                <div className="space-y-3">
                  <CodeBlock
                    label="貼り付け用 iframe コード"
                    value={w.iframe_html!}
                    onCopy={() => copy(w.iframe_html!, 'iframe コード')}
                    multiline
                  />

                  <CodeBlock
                    label="表示 URL (ブラウザで直接開いて動作確認)"
                    value={w.widget_url!}
                    onCopy={() => copy(w.widget_url!, '表示 URL')}
                  />

                  <details
                    open={!!expanded[w.classroom_id]}
                    onToggle={(e) =>
                      setExpanded((p) => ({
                        ...p,
                        [w.classroom_id]: (e.currentTarget as HTMLDetailsElement).open,
                      }))
                    }
                  >
                    <summary className="cursor-pointer text-xs font-medium text-[var(--brand-80)]">
                      プレビュー (実際の表示を確認)
                    </summary>
                    <div className="mt-2 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
                      <iframe
                        src={w.widget_url!}
                        width="100%"
                        height="430"
                        style={{ border: 0, maxWidth: 640 }}
                        title={`${w.classroom_name} 空き状況プレビュー`}
                        loading="lazy"
                      />
                    </div>
                  </details>

                  <details>
                    <summary className="cursor-pointer text-xs font-medium text-[var(--brand-80)]">
                      上級者向け: JSON データの URL (自前で JS から取得する場合)
                    </summary>
                    <div className="mt-2">
                      <CodeBlock
                        label=""
                        value={w.data_url!}
                        onCopy={() => copy(w.data_url!, 'JSON URL')}
                      />
                    </div>
                  </details>
                </div>
              </CardBody>
            )}
          </Card>
        ))
      )}
    </div>
  );
}

function CodeBlock({
  label,
  value,
  onCopy,
  multiline = false,
}: {
  label: string;
  value: string;
  onCopy: () => void;
  multiline?: boolean;
}) {
  return (
    <div>
      {label && (
        <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
          {label}
        </label>
      )}
      <div className="flex items-stretch gap-2">
        {multiline ? (
          <textarea
            value={value}
            readOnly
            onClick={(e) => (e.target as HTMLTextAreaElement).select()}
            className="flex-1 resize-none rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-3 py-2 font-mono text-[11px] leading-snug text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
            rows={3}
          />
        ) : (
          <input
            type="text"
            value={value}
            readOnly
            onClick={(e) => (e.target as HTMLInputElement).select()}
            className="flex-1 rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-3 py-2 font-mono text-[11px] text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
          />
        )}
        <button
          type="button"
          onClick={onCopy}
          className="flex shrink-0 items-center gap-1 rounded bg-[var(--brand-80)] px-3 py-2 text-xs font-medium text-white hover:bg-[var(--brand-100)]"
        >
          <MaterialIcon name="content_copy" size={14} />
          コピー
        </button>
      </div>
    </div>
  );
}
