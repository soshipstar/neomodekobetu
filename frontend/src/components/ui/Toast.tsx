'use client';

import { createContext, useContext, useState, useCallback, type ReactNode } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { cn } from '@/lib/utils';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

type ToastType = 'success' | 'error' | 'info' | 'warning';

interface Toast {
  id: string;
  type: ToastType;
  message: string;
  duration?: number;
}

interface ToastContextType {
  toast: (message: string, type?: ToastType, duration?: number) => void;
  success: (message: string) => void;
  error: (message: string) => void;
  info: (message: string) => void;
  warning: (message: string) => void;
}

const ToastContext = createContext<ToastContextType | null>(null);

const toastIcons: Record<ToastType, ReactNode> = {
  success: <MaterialIcon name="check_circle" size={16} className="text-[var(--status-success-fg)]" />,
  error: <MaterialIcon name="error" size={16} className="text-[var(--status-danger-fg)]" />,
  info: <MaterialIcon name="info" size={16} className="text-[var(--status-info-fg)]" />,
  warning: <MaterialIcon name="warning" size={16} className="text-[var(--status-warning-fg)]" />,
};

const toastStyles: Record<ToastType, string> = {
  success: 'border-[var(--status-success-fg)]/20 bg-[var(--status-success-bg)]',
  error: 'border-[var(--status-danger-fg)]/20 bg-[var(--status-danger-bg)]',
  info: 'border-[var(--status-info-fg)]/20 bg-[var(--status-info-bg)]',
  warning: 'border-[var(--status-warning-fg)]/20 bg-[var(--status-warning-bg)]',
};

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const removeToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const addToast = useCallback(
    (message: string, type: ToastType = 'info', duration: number = 4000) => {
      const id = `${Date.now()}-${Math.random()}`;
      setToasts((prev) => [...prev, { id, type, message, duration }]);

      if (duration > 0) {
        setTimeout(() => removeToast(id), duration);
      }
    },
    [removeToast]
  );

  const contextValue: ToastContextType = {
    toast: addToast,
    success: (msg) => addToast(msg, 'success'),
    error: (msg) => addToast(msg, 'error'),
    info: (msg) => addToast(msg, 'info'),
    warning: (msg) => addToast(msg, 'warning'),
  };

  return (
    <ToastContext.Provider value={contextValue}>
      {children}
      {/* Toast container */}
      <div className="fixed bottom-4 right-4 z-[100] flex flex-col gap-2">
        <AnimatePresence>
          {toasts.map((t) => (
            <motion.div
              key={t.id}
              initial={{ opacity: 0, y: 16, scale: 0.98 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: -8, scale: 0.98 }}
              transition={{ duration: 0.2, ease: [0.33, 0, 0.67, 1] }}
              className={cn(
                'flex items-center gap-2.5 rounded-md border px-4 py-3 shadow-[var(--shadow-16)]',
                toastStyles[t.type]
              )}
            >
              {toastIcons[t.type]}
              <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">{t.message}</p>
              <button
                onClick={() => removeToast(t.id)}
                className="ml-2 rounded-md p-0.5 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-1)] transition-colors"
              >
                <MaterialIcon name="close" size={14} />
              </button>
            </motion.div>
          ))}
        </AnimatePresence>
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastContextType {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error('useToast must be used within a ToastProvider');
  return ctx;
}

export default ToastProvider;
