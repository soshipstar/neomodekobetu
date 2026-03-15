'use client';

import {
  useRef,
  useEffect,
  useState,
  useCallback,
  forwardRef,
  useImperativeHandle,
} from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { cn } from '@/lib/utils';
import { Button } from './Button';
import { X, Maximize2, RotateCcw, Check } from 'lucide-react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface SignaturePadProps {
  /** Called when the user completes a signature (base64 PNG data URL) */
  onSave?: (base64: string) => void;
  /** Called whenever the drawn state changes (has content or not) */
  onChange?: (hasContent: boolean) => void;
  /** Pre-existing signature to display (base64 data URL) */
  initialValue?: string;
  /** If true, the pad is view-only and shows the saved image */
  readOnly?: boolean;
  /** Canvas width in CSS pixels (default 400) */
  width?: number;
  /** Canvas height in CSS pixels (default 150) */
  height?: number;
  /** Label displayed above the canvas */
  label?: string;
  /** Additional class names for the outer wrapper */
  className?: string;
}

export interface SignaturePadRef {
  /** Clear the canvas */
  clear: () => void;
  /** Returns true if the user has drawn anything */
  isEmpty: () => boolean;
  /** Export the canvas as a base64 PNG data URL */
  toDataURL: () => string;
}

// ---------------------------------------------------------------------------
// Internal canvas drawing helper
// ---------------------------------------------------------------------------

class CanvasDrawer {
  canvas: HTMLCanvasElement;
  ctx: CanvasRenderingContext2D;
  drawing = false;
  points: { x: number; y: number }[] = [];
  hasDrawn = false;
  onChangeCallback?: (hasContent: boolean) => void;

  constructor(canvas: HTMLCanvasElement, onChange?: (hasContent: boolean) => void) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d')!;
    this.onChangeCallback = onChange;
  }

  init() {
    const rect = this.canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    this.canvas.width = rect.width * dpr;
    this.canvas.height = rect.height * dpr;
    this.ctx.scale(dpr, dpr);
    this.clear();
    this.bindEvents();
  }

  private getCoords(e: MouseEvent | TouchEvent): { x: number; y: number } {
    const rect = this.canvas.getBoundingClientRect();
    if ('touches' in e && e.touches.length > 0) {
      return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
    }
    const me = e as MouseEvent;
    return { x: me.clientX - rect.left, y: me.clientY - rect.top };
  }

  private startDrawing = (e: MouseEvent | TouchEvent) => {
    this.drawing = true;
    const coords = this.getCoords(e);
    this.points = [coords];
    this.ctx.beginPath();
    this.ctx.moveTo(coords.x, coords.y);
  };

  private draw = (e: MouseEvent | TouchEvent) => {
    if (!this.drawing) return;
    e.preventDefault();

    const coords = this.getCoords(e);
    this.points.push(coords);

    this.ctx.strokeStyle = '#000000';
    this.ctx.lineWidth = 2.5;
    this.ctx.lineCap = 'round';
    this.ctx.lineJoin = 'round';

    if (this.points.length >= 3) {
      const len = this.points.length;
      const p0 = this.points[len - 3];
      const p1 = this.points[len - 2];
      const p2 = this.points[len - 1];
      const midX = (p1.x + p2.x) / 2;
      const midY = (p1.y + p2.y) / 2;
      this.ctx.beginPath();
      this.ctx.moveTo((p0.x + p1.x) / 2, (p0.y + p1.y) / 2);
      this.ctx.quadraticCurveTo(p1.x, p1.y, midX, midY);
      this.ctx.stroke();
    } else if (this.points.length === 2) {
      this.ctx.beginPath();
      this.ctx.moveTo(this.points[0].x, this.points[0].y);
      this.ctx.lineTo(coords.x, coords.y);
      this.ctx.stroke();
    }

    if (!this.hasDrawn) {
      this.hasDrawn = true;
      this.onChangeCallback?.(true);
    }
  };

  private stopDrawing = () => {
    if (this.drawing && this.points.length === 1) {
      const p = this.points[0];
      this.ctx.beginPath();
      this.ctx.arc(p.x, p.y, 1.25, 0, Math.PI * 2);
      this.ctx.fillStyle = '#000000';
      this.ctx.fill();
      if (!this.hasDrawn) {
        this.hasDrawn = true;
        this.onChangeCallback?.(true);
      }
    }
    this.drawing = false;
    this.points = [];
  };

  bindEvents() {
    const c = this.canvas;
    c.addEventListener('mousedown', this.startDrawing);
    c.addEventListener('mousemove', this.draw);
    c.addEventListener('mouseup', this.stopDrawing);
    c.addEventListener('mouseleave', this.stopDrawing);
    c.addEventListener('touchstart', (e) => { e.preventDefault(); this.startDrawing(e); }, { passive: false });
    c.addEventListener('touchmove', (e) => { e.preventDefault(); this.draw(e); }, { passive: false });
    c.addEventListener('touchend', this.stopDrawing);
  }

  unbindEvents() {
    const c = this.canvas;
    c.removeEventListener('mousedown', this.startDrawing);
    c.removeEventListener('mousemove', this.draw);
    c.removeEventListener('mouseup', this.stopDrawing);
    c.removeEventListener('mouseleave', this.stopDrawing);
    // Touch events are added inline so we rely on canvas removal for cleanup
  }

  clear() {
    const dpr = window.devicePixelRatio || 1;
    this.ctx.save();
    this.ctx.setTransform(1, 0, 0, 1, 0, 0);
    this.ctx.fillStyle = '#ffffff';
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.restore();
    this.hasDrawn = false;
    this.onChangeCallback?.(false);
  }

  isEmpty() {
    return !this.hasDrawn;
  }

  toDataURL() {
    return this.canvas.toDataURL('image/png');
  }

  drawImage(img: HTMLImageElement) {
    const dpr = window.devicePixelRatio || 1;
    this.ctx.save();
    this.ctx.setTransform(1, 0, 0, 1, 0, 0);
    this.ctx.fillStyle = '#ffffff';
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.drawImage(img, 0, 0, this.canvas.width, this.canvas.height);
    this.ctx.restore();
    this.hasDrawn = true;
    this.onChangeCallback?.(true);
  }
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

const SignaturePad = forwardRef<SignaturePadRef, SignaturePadProps>(
  (
    {
      onSave,
      onChange,
      initialValue,
      readOnly = false,
      width = 400,
      height = 150,
      label,
      className,
    },
    ref
  ) => {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawerRef = useRef<CanvasDrawer | null>(null);
    const modalCanvasRef = useRef<HTMLCanvasElement>(null);
    const modalDrawerRef = useRef<CanvasDrawer | null>(null);

    const [modalOpen, setModalOpen] = useState(false);
    const [hasContent, setHasContent] = useState(!!initialValue);

    // Expose imperative methods
    useImperativeHandle(ref, () => ({
      clear() {
        drawerRef.current?.clear();
        setHasContent(false);
      },
      isEmpty() {
        return drawerRef.current?.isEmpty() ?? true;
      },
      toDataURL() {
        return drawerRef.current?.toDataURL() ?? '';
      },
    }));

    // Handle change from the inline canvas
    const handleChange = useCallback(
      (has: boolean) => {
        setHasContent(has);
        onChange?.(has);
      },
      [onChange]
    );

    // Initialize the inline canvas
    useEffect(() => {
      if (readOnly || !canvasRef.current) return;
      const drawer = new CanvasDrawer(canvasRef.current, handleChange);
      drawer.init();
      drawerRef.current = drawer;

      // Load initial value if present
      if (initialValue) {
        const img = new Image();
        img.onload = () => drawer.drawImage(img);
        img.src = initialValue;
      }

      return () => {
        drawer.unbindEvents();
      };
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [readOnly]);

    // Initialize modal canvas when modal opens
    useEffect(() => {
      if (!modalOpen || !modalCanvasRef.current) return;
      const timer = setTimeout(() => {
        if (!modalCanvasRef.current) return;
        const drawer = new CanvasDrawer(modalCanvasRef.current);
        drawer.init();
        modalDrawerRef.current = drawer;
      }, 50);
      return () => clearTimeout(timer);
    }, [modalOpen]);

    // Clear inline canvas
    const handleClear = () => {
      drawerRef.current?.clear();
      setHasContent(false);
      onChange?.(false);
    };

    // Open fullscreen modal
    const openModal = () => {
      setModalOpen(true);
      document.body.style.overflow = 'hidden';
    };

    // Close modal
    const closeModal = () => {
      setModalOpen(false);
      document.body.style.overflow = '';
      modalDrawerRef.current = null;
    };

    // Clear modal canvas
    const clearModal = () => {
      modalDrawerRef.current?.clear();
    };

    // Apply modal signature to inline canvas
    const applyModal = () => {
      if (!modalDrawerRef.current || modalDrawerRef.current.isEmpty()) return;
      const dataURL = modalDrawerRef.current.toDataURL();
      const img = new Image();
      img.onload = () => {
        drawerRef.current?.drawImage(img);
        setHasContent(true);
        onChange?.(true);
        closeModal();
      };
      img.src = dataURL;
    };

    // ---------- Read-only mode ----------
    if (readOnly) {
      return (
        <div className={cn('space-y-1', className)}>
          {label && (
            <p className="text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</p>
          )}
          {initialValue ? (
            <div className="inline-block rounded-md border border-[var(--neutral-stroke-2)] bg-white p-2">
              <img
                src={initialValue}
                alt={label ?? '署名'}
                style={{ maxWidth: width, maxHeight: height }}
                className="block"
              />
            </div>
          ) : (
            <p className="text-sm text-[var(--neutral-foreground-3)]">署名なし</p>
          )}
        </div>
      );
    }

    // ---------- Editable mode ----------
    return (
      <div className={cn('space-y-2', className)}>
        {label && (
          <p className="text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</p>
        )}

        {/* Inline canvas */}
        <div
          className="overflow-hidden rounded-md border-2 border-[var(--neutral-stroke-2)] bg-white"
          style={{ width, maxWidth: '100%' }}
        >
          <canvas
            ref={canvasRef}
            style={{ display: 'block', width: '100%', height, cursor: 'crosshair', touchAction: 'none' }}
          />
        </div>

        {/* Controls */}
        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" variant="primary" size="sm" leftIcon={<Maximize2 className="h-3.5 w-3.5" />} onClick={openModal}>
            サインを記入
          </Button>
          <Button type="button" variant="secondary" size="sm" leftIcon={<RotateCcw className="h-3.5 w-3.5" />} onClick={handleClear}>
            クリア
          </Button>
          {hasContent ? (
            <span className="inline-flex items-center gap-1 rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
              <Check className="h-3 w-3" /> 署名済み
            </span>
          ) : (
            <span className="text-xs text-[var(--neutral-foreground-3)]">未署名</span>
          )}
        </div>

        {/* Fullscreen modal */}
        <AnimatePresence>
          {modalOpen && (
            <div className="fixed inset-0 z-[10000] flex items-center justify-center p-4">
              {/* Overlay */}
              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                transition={{ duration: 0.15 }}
                className="absolute inset-0 bg-black/70"
                onClick={closeModal}
              />
              {/* Modal */}
              <motion.div
                initial={{ opacity: 0, scale: 0.96 }}
                animate={{ opacity: 1, scale: 1 }}
                exit={{ opacity: 0, scale: 0.96 }}
                transition={{ duration: 0.2 }}
                className="relative w-full max-w-[700px] rounded-lg bg-white shadow-xl md:max-h-[95vh]
                  max-md:max-w-full max-md:rounded-none max-md:h-full"
              >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                  <h3 className="text-base font-semibold text-gray-900">
                    {label ? `${label}を記入` : '署名を記入'}
                  </h3>
                  <button
                    type="button"
                    onClick={closeModal}
                    className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                  >
                    <X className="h-5 w-5" />
                  </button>
                </div>

                {/* Body */}
                <div className="p-5">
                  <canvas
                    ref={modalCanvasRef}
                    style={{
                      display: 'block',
                      width: '100%',
                      height: 300,
                      border: '2px solid #d1d5db',
                      borderRadius: 8,
                      cursor: 'crosshair',
                      touchAction: 'none',
                      background: 'white',
                    }}
                    className="max-md:!h-[50vh]"
                  />
                </div>

                {/* Footer */}
                <div className="flex justify-end gap-2 border-t border-gray-200 px-5 py-3">
                  <Button type="button" variant="secondary" onClick={clearModal} leftIcon={<RotateCcw className="h-4 w-4" />}>
                    クリア
                  </Button>
                  <Button type="button" onClick={applyModal} leftIcon={<Check className="h-4 w-4" />}>
                    署名を適用
                  </Button>
                </div>
              </motion.div>
            </div>
          )}
        </AnimatePresence>
      </div>
    );
  }
);

SignaturePad.displayName = 'SignaturePad';

export { SignaturePad };
export default SignaturePad;
