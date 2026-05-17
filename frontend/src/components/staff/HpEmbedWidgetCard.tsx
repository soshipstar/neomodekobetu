'use client';

import { useEffect, useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

/**
 * HP 埋め込みウィジェット の発行・テーマ調整・プレビュー カード。
 *
 * /staff/waiting-list の最下部に表示する。
 *
 * 機能:
 *   - トークン未発行ならボタンで発行
 *   - 発行済みなら iframe コード/URL のコピーボタン
 *   - 6 つのプリセットテーマ + Primary 色のカスタム
 *   - 背景 (白 / 透過 / 暗色) と角丸度合いの調整
 *   - 教室名ヘッダ表示の ON/OFF、コンパクト幅 (サイドバー用)
 *   - HP の URL を入力すると `<meta name="theme-color">` を取得して
 *     primary 色を自動サジェスト
 *   - 設定変更ごとに iframe プレビューがリアルタイム更新
 *   - iframe コードに上記設定をクエリパラメータとして埋め込んで出力
 */

interface WidgetEmbed {
  classroom_id: number;
  classroom_name: string;
  token: string | null;
  widget_url: string | null;
  data_url: string | null;
  iframe_html: string | null;
}

type ThemeKey = 'brand' | 'light' | 'dark' | 'warm' | 'cool' | 'minimal' | 'transparent';
type RadiusKey = 'none' | 'sm' | 'md' | 'lg';

interface ThemeState {
  theme: ThemeKey;
  primary: string;     // hex no #
  bg: string;          // hex no # or "transparent"
  radius: RadiusKey;
  compact: boolean;
  header: boolean;
}

const THEME_PRESETS: { key: ThemeKey; label: string; swatch: string }[] = [
  { key: 'brand',   label: 'ブランド (緑)', swatch: '#14a898' },
  { key: 'light',   label: 'ライト (青)',  swatch: '#2563eb' },
  { key: 'dark',    label: 'ダーク',       swatch: '#111827' },
  { key: 'warm',    label: '暖色 (橙)',    swatch: '#ea580c' },
  { key: 'cool',    label: '寒色 (水色)',  swatch: '#0891b2' },
  { key: 'minimal', label: 'モノクロ',     swatch: '#333333' },
];

const DEFAULT_STATE: ThemeState = {
  theme: 'brand',
  primary: '14a898',
  bg: 'ffffff',
  radius: 'md',
  compact: false,
  header: true,
};

export function HpEmbedWidgetCard() {
  const toast = useToast();
  const queryClient = useQueryClient();

  // 1. 自教室の embed info
  const { data: embed, isLoading } = useQuery({
    queryKey: ['staff', 'widget-token'],
    queryFn: async () => {
      const res = await api.get<{ data: WidgetEmbed }>('/api/staff/widget-token');
      return res.data.data;
    },
  });

  const issueMutation = useMutation({
    mutationFn: () => api.post('/api/staff/widget-token'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'widget-token'] });
      toast.success('埋め込みコードを発行しました');
    },
    onError: () => toast.error('発行に失敗しました'),
  });

  const revokeMutation = useMutation({
    mutationFn: () => api.delete('/api/staff/widget-token'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'widget-token'] });
      toast.success('無効化しました');
    },
    onError: () => toast.error('無効化に失敗しました'),
  });

  // 2. テーマ状態 (localStorage に永続化して再読み込み時にも保持)
  const [state, setState] = useState<ThemeState>(DEFAULT_STATE);
  useEffect(() => {
    try {
      const saved = localStorage.getItem('kiduri-widget-theme');
      if (saved) setState({ ...DEFAULT_STATE, ...JSON.parse(saved) });
    } catch {/* ignore */}
  }, []);
  useEffect(() => {
    try { localStorage.setItem('kiduri-widget-theme', JSON.stringify(state)); } catch {/* ignore */}
  }, [state]);

  // 3. プリセット変更時に primary / bg を一括差し替え (ユーザーがその後個別に上書き可能)
  const applyPreset = (key: ThemeKey) => {
    const map: Record<ThemeKey, { primary: string; bg: string }> = {
      brand:       { primary: '14a898', bg: 'ffffff' },
      light:       { primary: '2563eb', bg: 'ffffff' },
      dark:        { primary: '38bdf8', bg: '111827' },
      warm:        { primary: 'ea580c', bg: 'fff7ed' },
      cool:        { primary: '0891b2', bg: 'ecfeff' },
      minimal:     { primary: '333333', bg: 'ffffff' },
      transparent: { primary: '14a898', bg: 'transparent' },
    };
    setState((p) => ({ ...p, theme: key, ...map[key] }));
  };

  // 4. HP URL から theme-color をサジェスト
  const [suggestUrl, setSuggestUrl] = useState('');
  const suggestMutation = useMutation({
    mutationFn: (url: string) =>
      api.post<{ success: boolean; data: { theme_color: string | null; title: string | null; favicon_url: string | null; suggested: { primary: string } | null } }>(
        '/api/admin/widget-tokens/suggest-theme', { url },
      ),
    onSuccess: (res) => {
      const d = res.data?.data;
      if (d?.suggested?.primary) {
        setState((p) => ({ ...p, primary: d.suggested!.primary }));
        toast.success(`HP の theme-color (#${d.suggested.primary}) を反映しました`);
      } else if (d) {
        toast.error('テーマカラーが取得できませんでした。手動でカラーを選んでください。');
      }
    },
    onError: () => toast.error('URL の解析に失敗しました'),
  });

  // 5. iframe URL / コード生成 (テーマ反映済み)
  const queryString = useMemo(() => {
    const params = new URLSearchParams({
      theme: state.theme,
      primary: state.primary,
      bg: state.bg,
      radius: state.radius,
    });
    if (state.compact) params.set('compact', '1');
    if (!state.header) params.set('header', '0');
    return params.toString();
  }, [state]);

  const previewUrl = embed?.widget_url ? `${embed.widget_url}?${queryString}` : null;
  const iframeCode = useMemo(() => {
    if (!previewUrl || !embed) return '';
    const heightDefault = state.compact ? 380 : 430;
    return `<iframe src="${previewUrl}" width="100%" height="${heightDefault}" style="border:0;max-width:${state.compact ? '320' : '640'}px;" title="${embed.classroom_name} 空き状況" loading="lazy"></iframe>`;
  }, [previewUrl, embed, state.compact]);

  const copy = async (text: string, label: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast.success(`${label}をコピーしました`);
    } catch {
      toast.error('コピーに失敗しました');
    }
  };

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between gap-2 flex-wrap">
          <CardTitle>
            <MaterialIcon name="integration_instructions" size={18} className="mr-1 inline-block align-middle text-[var(--brand-80)]" />
            HP に貼り付けて空き状況を公開する
          </CardTitle>
          {embed?.token && (
            <span className="flex items-center gap-1 rounded-full bg-[var(--status-success-bg)] px-2.5 py-0.5 text-xs font-medium text-[var(--status-success-fg)]">
              <MaterialIcon name="check_circle" size={12} />
              発行済
            </span>
          )}
        </div>
      </CardHeader>
      <CardBody>
        <p className="mb-3 text-sm text-[var(--neutral-foreground-2)]">
          下のコードを教室 HP の HTML 編集画面 (WordPress なら「カスタム HTML」ブロック、
          Wix / Jimdo / Studio などなら「埋め込み」「HTML」ブロック) に貼り付けると、
          曜日別の空き状況がリアルタイムに表示されます。<strong className="text-[var(--neutral-foreground-1)]">個人情報は一切公開されません。</strong>
        </p>

        {isLoading ? (
          <p className="text-sm text-[var(--neutral-foreground-4)]">読み込み中...</p>
        ) : !embed?.token ? (
          <div className="rounded-lg border border-dashed border-[var(--neutral-stroke-2)] p-6 text-center">
            <p className="mb-3 text-sm text-[var(--neutral-foreground-3)]">
              まだ埋め込みコードが発行されていません。
            </p>
            <Button
              variant="primary"
              size="md"
              onClick={() => issueMutation.mutate()}
              isLoading={issueMutation.isPending}
              leftIcon={<MaterialIcon name="add_link" size={16} />}
            >
              埋め込みコードを発行する
            </Button>
          </div>
        ) : (
          <div className="space-y-5">
            {/* テーマカスタマイザ */}
            <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)] p-3 sm:p-4">
              <h4 className="mb-3 text-sm font-semibold text-[var(--neutral-foreground-1)]">
                HP のデザインに合わせて見た目を調整
              </h4>

              {/* プリセット */}
              <div className="mb-3">
                <div className="mb-1 text-xs font-medium text-[var(--neutral-foreground-3)]">テーマ</div>
                <div className="flex flex-wrap gap-1.5">
                  {THEME_PRESETS.map((p) => (
                    <button
                      key={p.key}
                      type="button"
                      onClick={() => applyPreset(p.key)}
                      className={`flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors ${
                        state.theme === p.key
                          ? 'border-[var(--brand-80)] bg-[var(--brand-160)] text-[var(--brand-60)]'
                          : 'border-[var(--neutral-stroke-2)] bg-white text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]'
                      }`}
                    >
                      <span className="inline-block h-3 w-3 rounded-full" style={{ background: p.swatch }} />
                      {p.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* HP URL からの自動サジェスト */}
              <div className="mb-3 rounded border border-[var(--neutral-stroke-2)] bg-white p-2">
                <div className="mb-1 text-xs font-medium text-[var(--neutral-foreground-3)]">
                  HP の URL からテーマカラーを自動取得
                </div>
                <div className="flex items-stretch gap-2">
                  <input
                    type="url"
                    placeholder="https://example.com/"
                    value={suggestUrl}
                    onChange={(e) => setSuggestUrl(e.target.value)}
                    className="flex-1 rounded border border-[var(--neutral-stroke-2)] px-2 py-1.5 text-xs"
                  />
                  <button
                    type="button"
                    onClick={() => suggestUrl.trim() && suggestMutation.mutate(suggestUrl.trim())}
                    disabled={!suggestUrl.trim() || suggestMutation.isPending}
                    className="shrink-0 rounded bg-[var(--brand-80)] px-3 py-1.5 text-xs text-white hover:bg-[var(--brand-100)] disabled:opacity-50"
                  >
                    {suggestMutation.isPending ? '解析中…' : '取得'}
                  </button>
                </div>
                <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">
                  HP の <code>&lt;meta name=&quot;theme-color&quot;&gt;</code> タグの色を読み取って primary 色に反映します。
                </p>
              </div>

              {/* 色 / 角丸 / オプション */}
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">
                    Primary 色
                  </label>
                  <div className="flex items-center gap-2">
                    <input
                      type="color"
                      value={`#${state.primary}`}
                      onChange={(e) => setState((p) => ({ ...p, primary: e.target.value.replace('#', '') }))}
                      className="h-8 w-12 cursor-pointer rounded border border-[var(--neutral-stroke-2)]"
                    />
                    <input
                      type="text"
                      value={`#${state.primary}`}
                      onChange={(e) => {
                        const v = e.target.value.replace('#', '').toLowerCase();
                        if (/^[0-9a-f]{0,6}$/.test(v)) setState((p) => ({ ...p, primary: v }));
                      }}
                      className="flex-1 rounded border border-[var(--neutral-stroke-2)] px-2 py-1.5 font-mono text-xs"
                    />
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">
                    背景色
                  </label>
                  <div className="flex items-center gap-2">
                    <input
                      type="color"
                      value={`#${state.bg === 'transparent' ? 'ffffff' : state.bg}`}
                      disabled={state.bg === 'transparent'}
                      onChange={(e) => setState((p) => ({ ...p, bg: e.target.value.replace('#', '') }))}
                      className="h-8 w-12 cursor-pointer rounded border border-[var(--neutral-stroke-2)] disabled:opacity-40"
                    />
                    <button
                      type="button"
                      onClick={() => setState((p) => ({ ...p, bg: p.bg === 'transparent' ? 'ffffff' : 'transparent' }))}
                      className={`shrink-0 rounded border px-2 py-1.5 text-[11px] ${
                        state.bg === 'transparent'
                          ? 'border-[var(--brand-80)] bg-[var(--brand-160)] text-[var(--brand-60)]'
                          : 'border-[var(--neutral-stroke-2)] bg-white text-[var(--neutral-foreground-2)]'
                      }`}
                      title="HP 自体の背景を透過させたい場合に使う"
                    >
                      透過 ON/OFF
                    </button>
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">
                    角丸
                  </label>
                  <div className="flex gap-1">
                    {(['none', 'sm', 'md', 'lg'] as RadiusKey[]).map((r) => (
                      <button
                        key={r}
                        type="button"
                        onClick={() => setState((p) => ({ ...p, radius: r }))}
                        className={`flex-1 rounded border px-2 py-1 text-[11px] ${
                          state.radius === r
                            ? 'border-[var(--brand-80)] bg-[var(--brand-160)] text-[var(--brand-60)]'
                            : 'border-[var(--neutral-stroke-2)] bg-white text-[var(--neutral-foreground-2)]'
                        }`}
                      >
                        {r === 'none' ? '無し' : r === 'sm' ? '小' : r === 'md' ? '中' : '大'}
                      </button>
                    ))}
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">
                    オプション
                  </label>
                  <div className="flex flex-wrap gap-2 text-xs text-[var(--neutral-foreground-2)]">
                    <label className="flex items-center gap-1.5">
                      <input
                        type="checkbox"
                        checked={state.header}
                        onChange={(e) => setState((p) => ({ ...p, header: e.target.checked }))}
                      />
                      ヘッダ表示
                    </label>
                    <label className="flex items-center gap-1.5">
                      <input
                        type="checkbox"
                        checked={state.compact}
                        onChange={(e) => setState((p) => ({ ...p, compact: e.target.checked }))}
                      />
                      サイドバー幅 (320px)
                    </label>
                  </div>
                </div>
              </div>
            </div>

            {/* プレビュー */}
            <div>
              <div className="mb-1 flex items-center justify-between text-xs">
                <span className="font-medium text-[var(--neutral-foreground-2)]">プレビュー (HP 上での見え方)</span>
                <span className="text-[var(--neutral-foreground-4)]">設定変更で即時反映</span>
              </div>
              <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
                {previewUrl && (
                  <iframe
                    key={previewUrl}
                    src={previewUrl}
                    width="100%"
                    height={state.compact ? 380 : 430}
                    style={{ border: 0, maxWidth: state.compact ? 320 : 640, margin: '0 auto', display: 'block' }}
                    title="プレビュー"
                  />
                )}
              </div>
            </div>

            {/* iframe コード */}
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
                貼り付け用 iframe コード
              </label>
              <div className="flex items-stretch gap-2">
                <textarea
                  value={iframeCode}
                  readOnly
                  onClick={(e) => (e.target as HTMLTextAreaElement).select()}
                  className="flex-1 resize-none rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-3 py-2 font-mono text-[11px] leading-snug text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
                  rows={3}
                />
                <button
                  type="button"
                  onClick={() => copy(iframeCode, 'iframe コード')}
                  className="flex shrink-0 items-center gap-1 rounded bg-[var(--brand-80)] px-3 py-2 text-xs font-medium text-white hover:bg-[var(--brand-100)]"
                >
                  <MaterialIcon name="content_copy" size={14} />
                  コピー
                </button>
              </div>
            </div>

            {/* 操作 */}
            <div className="flex flex-wrap gap-2 border-t border-[var(--neutral-stroke-2)] pt-3 text-xs">
              <Button
                variant="outline"
                size="sm"
                onClick={() => previewUrl && window.open(previewUrl, '_blank')}
                leftIcon={<MaterialIcon name="open_in_new" size={14} />}
              >
                別タブで開く
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setState(DEFAULT_STATE)}
                leftIcon={<MaterialIcon name="refresh" size={14} />}
              >
                テーマをリセット
              </Button>
              <div className="grow" />
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  if (confirm('コードを再発行します。HP に貼っている iframe は表示されなくなり、新しいコードを貼り直す必要があります。続けますか?')) {
                    issueMutation.mutate();
                  }
                }}
                isLoading={issueMutation.isPending}
                leftIcon={<MaterialIcon name="autorenew" size={14} />}
              >
                再発行
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  if (confirm('HP の iframe を停止します。続けますか?')) {
                    revokeMutation.mutate();
                  }
                }}
                isLoading={revokeMutation.isPending}
                leftIcon={<MaterialIcon name="link_off" size={14} />}
              >
                無効化
              </Button>
            </div>
          </div>
        )}
      </CardBody>
    </Card>
  );
}
