'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

/**
 * 保護者選択コンポーネント (部分一致検索付き)。
 *
 * 旧 select ドロップダウンの問題:
 *   - 同企業内に保護者が数十〜数百名いるとプルダウンが長すぎ操作困難
 *   - 教室名を出すと「保護者に教室属性がある」と誤解させる UX 問題
 *
 * 新 UI:
 *   - 入力欄に名前/メールアドレスの一部を打つと候補がフィルタされる
 *   - 候補は最大 8 件まで表示 (それ以上は絞り込みを促す)
 *   - クリックで選択、× で紐づけ解除 (null)
 *   - 教室名は出さない (保護者は概念上「企業に属する」想定)
 *   - 既に紐づけ済みの保護者は最初に「現在の選択」として表示
 *
 * 使用:
 *   <GuardianPicker
 *     value={form.guardian_id}
 *     onChange={(id) => setForm({ ...form, guardian_id: id })}
 *   />
 */

interface GuardianOption {
  id: number;
  full_name: string;
  email: string | null;
}

interface Props {
  value: string;                // '' なら未紐づけ。それ以外は guardian.id を文字列化
  onChange: (value: string) => void;
  placeholder?: string;
}

const MAX_RESULTS = 8;

export function GuardianPicker({ value, onChange, placeholder }: Props) {
  const { data: guardians = [], isLoading } = useQuery<GuardianOption[]>({
    queryKey: ['staff', 'students', 'guardians'],
    queryFn: async () => {
      const res = await api.get<{ data: GuardianOption[] }>('/api/staff/students/guardians');
      return res.data.data;
    },
  });

  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  // 現在選択中の保護者
  const selected = useMemo(
    () => guardians.find((g) => String(g.id) === value) || null,
    [guardians, value],
  );

  // 部分一致フィルタ。空クエリ時はそのまま全件 (ただし上限 MAX_RESULTS)
  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return guardians.slice(0, MAX_RESULTS);
    return guardians
      .filter((g) =>
        g.full_name.toLowerCase().includes(q) ||
        (g.email ?? '').toLowerCase().includes(q),
      )
      .slice(0, MAX_RESULTS);
  }, [guardians, query]);

  // 外側クリックでドロップダウンを閉じる
  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (!containerRef.current?.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  const inputCls =
    'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]';

  return (
    <div className="relative" ref={containerRef}>
      {/* 選択済み表示 + 検索入力 */}
      {selected && !open ? (
        <div
          className="flex cursor-text items-center justify-between gap-2 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
          onClick={() => {
            setOpen(true);
            setQuery('');
            setTimeout(() => inputRef.current?.focus(), 0);
          }}
        >
          <span className="font-medium text-[var(--neutral-foreground-1)]">
            {selected.full_name}
            {selected.email && (
              <span className="ml-2 text-xs font-normal text-[var(--neutral-foreground-4)]">
                {selected.email}
              </span>
            )}
          </span>
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                onChange('');
              }}
              className="rounded p-1 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--status-danger-fg)]"
              title="紐づけを解除"
              aria-label="紐づけを解除"
            >
              <MaterialIcon name="close" size={14} />
            </button>
            <MaterialIcon name="arrow_drop_down" size={18} className="text-[var(--neutral-foreground-4)]" />
          </div>
        </div>
      ) : (
        <div className="relative">
          <input
            ref={inputRef}
            type="text"
            className={inputCls + ' pr-8'}
            placeholder={placeholder ?? '名前・メールの一部を入力して検索…'}
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setOpen(true);
            }}
            onFocus={() => setOpen(true)}
          />
          <div className="pointer-events-none absolute inset-y-0 right-2 flex items-center">
            <MaterialIcon name="search" size={16} className="text-[var(--neutral-foreground-4)]" />
          </div>
        </div>
      )}

      {/* ドロップダウン: 検索結果 + 「未紐づけ」項目 */}
      {open && (
        <div className="absolute left-0 right-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] shadow-lg">
          {/* 「未紐づけ」を選ぶオプション */}
          <button
            type="button"
            onClick={() => { onChange(''); setOpen(false); setQuery(''); }}
            className={`flex w-full items-center gap-2 border-b border-[var(--neutral-stroke-3)] px-3 py-2 text-left text-sm text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] ${
              !value ? 'bg-[var(--brand-160)]' : ''
            }`}
          >
            <MaterialIcon name="link_off" size={14} />
            未紐づけ（保護者なし）
          </button>

          {isLoading ? (
            <div className="px-3 py-2 text-xs text-[var(--neutral-foreground-4)]">読み込み中…</div>
          ) : filtered.length === 0 ? (
            <div className="px-3 py-3 text-xs text-[var(--neutral-foreground-4)]">
              該当する保護者が見つかりません。<br />
              保護者管理画面で先にユーザーを作成してください。
            </div>
          ) : (
            <>
              {filtered.map((g) => {
                const isSelected = String(g.id) === value;
                return (
                  <button
                    key={g.id}
                    type="button"
                    onClick={() => { onChange(String(g.id)); setOpen(false); setQuery(''); }}
                    className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-[var(--neutral-background-3)] ${
                      isSelected ? 'bg-[var(--brand-160)] text-[var(--brand-60)]' : 'text-[var(--neutral-foreground-1)]'
                    }`}
                  >
                    <span className="min-w-0 truncate">
                      <span className="font-medium">{g.full_name}</span>
                      {g.email && (
                        <span className="ml-2 text-xs font-normal text-[var(--neutral-foreground-4)]">
                          {g.email}
                        </span>
                      )}
                    </span>
                    {isSelected && <MaterialIcon name="check" size={14} className="shrink-0 text-[var(--brand-80)]" />}
                  </button>
                );
              })}
              {!query && guardians.length > MAX_RESULTS && (
                <div className="border-t border-[var(--neutral-stroke-3)] px-3 py-2 text-[10px] text-[var(--neutral-foreground-4)]">
                  ほか {guardians.length - MAX_RESULTS} 件あります。名前で絞り込んでください。
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
}
