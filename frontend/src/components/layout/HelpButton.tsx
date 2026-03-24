'use client';

import { useState, useMemo, useCallback, useEffect, useRef } from 'react';
import { usePathname } from 'next/navigation';
import { useAuthStore } from '@/stores/authStore';
import {
  getHelpData,
  getDefaultCategoryId,
  type HelpCategory,
  type HelpItem,
} from '@/lib/helpContent';
import type { UserType } from '@/types/user';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

type ViewState =
  | { type: 'categories' }
  | { type: 'items'; categoryId: string }
  | { type: 'answer'; categoryId: string; itemIndex: number };

export function HelpButton() {
  const [isOpen, setIsOpen] = useState(false);
  const [viewState, setViewState] = useState<ViewState>({ type: 'categories' });
  const [searchQuery, setSearchQuery] = useState('');
  const panelRef = useRef<HTMLDivElement>(null);

  // Drag state for the toggle button
  const [btnPos, setBtnPos] = useState<{ x: number; y: number } | null>(null);
  const dragRef = useRef<{ startX: number; startY: number; startBtnX: number; startBtnY: number; dragging: boolean } | null>(null);
  const btnRef = useRef<HTMLButtonElement>(null);

  const handlePointerDown = useCallback((e: React.PointerEvent) => {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    dragRef.current = {
      startX: e.clientX,
      startY: e.clientY,
      startBtnX: rect.left,
      startBtnY: rect.top,
      dragging: false,
    };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
  }, []);

  const handlePointerMove = useCallback((e: React.PointerEvent) => {
    if (!dragRef.current) return;
    const dx = e.clientX - dragRef.current.startX;
    const dy = e.clientY - dragRef.current.startY;
    if (Math.abs(dx) > 5 || Math.abs(dy) > 5) {
      dragRef.current.dragging = true;
    }
    if (dragRef.current.dragging) {
      const newX = dragRef.current.startBtnX + dx;
      const newY = dragRef.current.startBtnY + dy;
      // Clamp to viewport
      const size = 56;
      const x = Math.max(0, Math.min(window.innerWidth - size, newX));
      const y = Math.max(0, Math.min(window.innerHeight - size, newY));
      setBtnPos({ x, y });
    }
  }, []);


  const pathname = usePathname();
  const { user } = useAuthStore();
  const userType = (user?.user_type ?? 'staff') as UserType;

  const helpData = useMemo(() => getHelpData(userType), [userType]);

  const defaultCategoryId = useMemo(
    () => getDefaultCategoryId(pathname, userType),
    [pathname, userType]
  );

  // Reset view when panel is opened
  const togglePanel = useCallback(() => {
    setIsOpen((prev) => {
      if (!prev) {
        // Opening: set to default category if available
        if (defaultCategoryId) {
          setViewState({ type: 'items', categoryId: defaultCategoryId });
        } else {
          setViewState({ type: 'categories' });
        }
        setSearchQuery('');
      }
      return !prev;
    });
  }, [defaultCategoryId]);

  const handlePointerUp = useCallback((_e: React.PointerEvent) => {
    if (dragRef.current && !dragRef.current.dragging) {
      togglePanel();
    }
    dragRef.current = null;
  }, [togglePanel]);

  // Close panel on outside click
  useEffect(() => {
    if (!isOpen) return;

    function handleClickOutside(e: MouseEvent) {
      const target = e.target as HTMLElement;
      if (
        panelRef.current &&
        !panelRef.current.contains(target) &&
        !target.closest('[data-help-toggle]')
      ) {
        setIsOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen]);

  // Close on Escape
  useEffect(() => {
    if (!isOpen) return;

    function handleEscape(e: KeyboardEvent) {
      if (e.key === 'Escape') setIsOpen(false);
    }

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen]);

  // Search filter
  const filteredResults = useMemo(() => {
    if (!searchQuery.trim()) return null;

    const query = searchQuery.toLowerCase();
    const results: { category: HelpCategory; item: HelpItem; itemIndex: number }[] = [];

    for (const category of helpData.categories) {
      for (let i = 0; i < category.items.length; i++) {
        const item = category.items[i];
        if (
          item.question.toLowerCase().includes(query) ||
          item.answer.toLowerCase().includes(query)
        ) {
          results.push({ category, item, itemIndex: i });
        }
      }
    }

    return results;
  }, [searchQuery, helpData]);

  const showCategories = useCallback(() => {
    setViewState({ type: 'categories' });
    setSearchQuery('');
  }, []);

  const showCategory = useCallback((categoryId: string) => {
    setViewState({ type: 'items', categoryId });
    setSearchQuery('');
  }, []);

  const showAnswer = useCallback((categoryId: string, itemIndex: number) => {
    setViewState({ type: 'answer', categoryId, itemIndex });
  }, []);

  const goBack = useCallback(() => {
    if (viewState.type === 'answer') {
      setViewState({ type: 'items', categoryId: viewState.categoryId });
    } else {
      showCategories();
    }
  }, [viewState, showCategories]);

  // Determine accent color by user type
  const accentGradient = useMemo(() => {
    // 全ユーザータイプでブランドカラーに統一
    return 'from-[var(--brand-70)] to-[var(--brand-50)]';
  }, [userType]);

  const accentColor = useMemo(() => {
    return 'text-[var(--brand-70)]';
  }, [userType]);

  const currentCategory = useMemo(() => {
    if (viewState.type === 'items' || viewState.type === 'answer') {
      return helpData.categories.find((c) => c.id === viewState.categoryId);
    }
    return null;
  }, [viewState, helpData]);

  const currentItem = useMemo(() => {
    if (viewState.type === 'answer' && currentCategory) {
      return currentCategory.items[viewState.itemIndex];
    }
    return null;
  }, [viewState, currentCategory]);

  // Don't render for tablet users
  if (userType === 'tablet') return null;

  return (
    <>
      {/* Toggle Button (draggable) */}
      <button
        ref={btnRef}
        data-help-toggle
        onPointerDown={handlePointerDown}
        onPointerMove={handlePointerMove}
        onPointerUp={handlePointerUp}
        className={`fixed z-50 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br ${accentGradient} text-white shadow-lg transition-shadow hover:shadow-xl lg:h-14 lg:w-14 touch-none select-none cursor-grab active:cursor-grabbing`}
        style={btnPos ? { left: btnPos.x, top: btnPos.y, right: 'auto', bottom: 'auto' } : { bottom: '6rem', right: '1rem' }}
        aria-label="ヘルプを開く（ドラッグで移動可能）"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          strokeWidth={2}
          stroke="currentColor"
          className="h-6 w-6 lg:h-7 lg:w-7"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"
          />
        </svg>
      </button>

      {/* Panel */}
      <div
        ref={panelRef}
        className={`fixed z-50 flex w-[360px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl transition-all duration-300 ${
          isOpen
            ? 'pointer-events-auto translate-y-0 scale-100 opacity-100'
            : 'pointer-events-none translate-y-4 scale-95 opacity-0'
        }`}
        style={btnPos
          ? { left: Math.min(btnPos.x, window.innerWidth - 380), bottom: `${window.innerHeight - btnPos.y + 16}px`, height: isOpen ? '480px' : '0', maxHeight: 'calc(100vh - 200px)' }
          : { bottom: '10rem', right: '1rem', height: isOpen ? '480px' : '0', maxHeight: 'calc(100vh - 200px)' }
        }
      >
        {/* Header */}
        <div
          className={`flex shrink-0 items-center justify-between bg-gradient-to-r ${accentGradient} px-5 py-4 text-white`}
        >
          <div className="flex items-center gap-2">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={1.5}
              stroke="currentColor"
              className="h-5 w-5"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"
              />
            </svg>
            <span className="text-base font-semibold">操作ヘルプ</span>
          </div>
          <button
            onClick={() => setIsOpen(false)}
            className="flex h-7 w-7 items-center justify-center rounded-full bg-white/20 transition-colors hover:bg-white/30"
            aria-label="閉じる"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={2}
              stroke="currentColor"
              className="h-4 w-4"
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Search bar */}
        <div className="shrink-0 border-b border-[var(--neutral-stroke-3)] px-4 py-3">
          <div className="relative">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={1.5}
              stroke="currentColor"
              className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--neutral-foreground-4)]"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
              />
            </svg>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="ヘルプを検索..."
              className="w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] py-2 pl-9 pr-3 text-sm outline-none transition-colors focus:border-[var(--brand-120)] focus:bg-white"
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery('')}
                className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-3)]"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  strokeWidth={2}
                  stroke="currentColor"
                  className="h-3.5 w-3.5"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
              </button>
            )}
          </div>
        </div>

        {/* Content area */}
        <div className="flex-1 overflow-y-auto bg-[var(--neutral-background-3)] p-4">
          {/* Search results */}
          {filteredResults !== null ? (
            <div>
              <p className="mb-3 text-xs text-[var(--neutral-foreground-3)]">
                {filteredResults.length > 0
                  ? `${filteredResults.length}件の結果`
                  : '該当するヘルプが見つかりません'}
              </p>
              {filteredResults.map(({ category, item, itemIndex }) => (
                <button
                  key={`${category.id}-${itemIndex}`}
                  onClick={() => {
                    setSearchQuery('');
                    showAnswer(category.id, itemIndex);
                  }}
                  className="mb-2 flex w-full items-start gap-3 rounded-xl bg-white p-3 text-left transition-all hover:bg-[var(--neutral-background-4)] hover:translate-x-1"
                >
                  <span className="mt-0.5 text-xs text-[var(--neutral-foreground-4)]">{category.title}</span>
                  <span className="flex-1 text-sm text-[var(--neutral-foreground-1)]">{item.question}</span>
                  <span className="text-[var(--neutral-foreground-disabled)]">&#8250;</span>
                </button>
              ))}
            </div>
          ) : viewState.type === 'categories' ? (
            /* Category list */
            <div>
              <p className="mb-4 text-center text-sm text-[var(--neutral-foreground-3)]">
                知りたい項目を選んでください
              </p>
              {helpData.categories.map((category) => (
                <button
                  key={category.id}
                  onClick={() => showCategory(category.id)}
                  className="mb-2 flex w-full items-center gap-3 rounded-xl bg-white p-3.5 text-left transition-all hover:bg-[var(--neutral-background-4)] hover:translate-x-1"
                >
                  <span
                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--neutral-background-4)] text-lg ${accentColor}`}
                  >
                    <MaterialIcon name={category.icon} size={20} />
                  </span>
                  <span className="flex-1 text-[15px] font-medium text-[var(--neutral-foreground-1)]">
                    {category.title}
                  </span>
                  <span className="text-xl text-[var(--neutral-foreground-disabled)]">&#8250;</span>
                </button>
              ))}
            </div>
          ) : viewState.type === 'items' && currentCategory ? (
            /* Items list for a category */
            <div>
              <button
                onClick={showCategories}
                className={`mb-4 text-sm font-medium ${accentColor} transition-colors hover:opacity-80`}
              >
                &#8592; カテゴリに戻る
              </button>
              <h3 className={`mb-4 flex items-center gap-2 border-b-2 pb-2 text-lg font-semibold text-[var(--neutral-foreground-1)]`}>
                <span className={accentColor}>
                  <MaterialIcon name={currentCategory.icon} size={20} />
                </span>
                {currentCategory.title}
              </h3>
              {currentCategory.items.map((item, index) => (
                <button
                  key={index}
                  onClick={() => showAnswer(currentCategory.id, index)}
                  className="mb-2 flex w-full items-center gap-2 rounded-xl bg-white p-3.5 text-left transition-all hover:bg-[var(--neutral-background-4)] hover:translate-x-1"
                >
                  <span className="flex-1 text-sm text-[var(--neutral-foreground-1)]">{item.question}</span>
                  <span className="text-lg text-[var(--neutral-foreground-disabled)]">&#8250;</span>
                </button>
              ))}
            </div>
          ) : viewState.type === 'answer' && currentItem ? (
            /* Answer view */
            <div>
              <button
                onClick={goBack}
                className={`mb-4 text-sm font-medium ${accentColor} transition-colors hover:opacity-80`}
              >
                &#8592; 質問一覧に戻る
              </button>
              <div className="rounded-xl bg-white p-5">
                <h4 className="mb-3 text-[15px] font-semibold text-[var(--neutral-foreground-1)]">
                  {currentItem.question}
                </h4>
                <div className="whitespace-pre-wrap text-sm leading-7 text-[var(--neutral-foreground-2)]">
                  {currentItem.answer}
                </div>
              </div>
            </div>
          ) : null}
        </div>
      </div>
    </>
  );
}

// MaterialIcon is now imported from @/components/ui/MaterialIcon

export default HelpButton;
