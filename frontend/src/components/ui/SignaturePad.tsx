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
import { X, Maximize2, RotateCcw, Check, Undo2, Pen } from 'lucide-react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface SignaturePadProps {
  onSave?: (base64: string) => void;
  onChange?: (hasContent: boolean) => void;
  initialValue?: string;
  readOnly?: boolean;
  width?: number;
  height?: number;
  label?: string;
  className?: string;
}

export interface SignaturePadRef {
  clear: () => void;
  isEmpty: () => boolean;
  toDataURL: () => string;
}

// ---------------------------------------------------------------------------
// Canvas Drawing Helper with velocity-based line width
// ---------------------------------------------------------------------------

class CanvasDrawer {
  canvas: HTMLCanvasElement;
  ctx: CanvasRenderingContext2D;
  drawing = false;
  points: { x: number; y: number; time: number }[] = [];
  hasDrawn = false;
  onChangeCallback?: (hasContent: boolean) => void;
  // Undo support: store canvas state after each stroke
  history: ImageData[] = [];
  maxHistory = 20;

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

  private getLineWidth(p1: { x: number; y: number; time: number }, p2: { x: number; y: number; time: number }): number {
    const dx = p2.x - p1.x;
    const dy = p2.y - p1.y;
    const dt = Math.max(p2.time - p1.time, 1);
    const speed = Math.sqrt(dx * dx + dy * dy) / dt;
    // Faster movement = thinner line (like a real pen)
    const minWidth = 1.5;
    const maxWidth = 4;
    const width = maxWidth - Math.min(speed * 0.8, maxWidth - minWidth);
    return Math.max(minWidth, width);
  }

  private saveState() {
    const dpr = window.devicePixelRatio || 1;
    const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
    this.history.push(imageData);
    if (this.history.length > this.maxHistory) this.history.shift();
  }

  undo() {
    if (this.history.length <= 1) {
      this.clear();
      return;
    }
    this.history.pop(); // Remove current state
    const prev = this.history[this.history.length - 1];
    this.ctx.putImageData(prev, 0, 0);
    this.hasDrawn = this.history.length > 1;
    this.onChangeCallback?.(this.hasDrawn);
  }

  private startDrawing = (e: MouseEvent | TouchEvent) => {
    this.drawing = true;
    const coords = this.getCoords(e);
    this.points = [{ ...coords, time: Date.now() }];
    this.ctx.beginPath();
    this.ctx.moveTo(coords.x, coords.y);
  };

  private lastWidth = 2.5;

  private draw = (e: MouseEvent | TouchEvent) => {
    if (!this.drawing) return;
    e.preventDefault();

    const coords = this.getCoords(e);
    const now = Date.now();
    this.points.push({ ...coords, time: now });

    this.ctx.strokeStyle = '#000000';
    this.ctx.lineCap = 'round';
    this.ctx.lineJoin = 'round';

    const len = this.points.length;

    if (len >= 3) {
      const p0 = this.points[len - 3];
      const p1 = this.points[len - 2];
      const p2 = this.points[len - 1];

      // Smooth width transition for natural pen feel
      const targetWidth = this.getLineWidth(p1, p2);
      this.lastWidth = this.lastWidth * 0.6 + targetWidth * 0.4;
      this.ctx.lineWidth = this.lastWidth;

      // Catmull-Rom style smooth curve through midpoints
      const mid0x = (p0.x + p1.x) / 2;
      const mid0y = (p0.y + p1.y) / 2;
      const mid1x = (p1.x + p2.x) / 2;
      const mid1y = (p1.y + p2.y) / 2;

      this.ctx.beginPath();
      this.ctx.moveTo(mid0x, mid0y);
      this.ctx.quadraticCurveTo(p1.x, p1.y, mid1x, mid1y);
      this.ctx.stroke();
    } else if (len === 2) {
      this.lastWidth = 2.5;
      this.ctx.lineWidth = 2.5;
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
    if (this.drawing) {
      if (this.points.length === 1) {
        const p = this.points[0];
        this.ctx.beginPath();
        this.ctx.arc(p.x, p.y, 1.5, 0, Math.PI * 2);
        this.ctx.fillStyle = '#000000';
        this.ctx.fill();
        if (!this.hasDrawn) {
          this.hasDrawn = true;
          this.onChangeCallback?.(true);
        }
      }
      this.saveState();
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
  }

  clear() {
    this.ctx.save();
    this.ctx.setTransform(1, 0, 0, 1, 0, 0);
    this.ctx.fillStyle = '#ffffff';
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.restore();
    this.hasDrawn = false;
    this.history = [];
    this.saveState(); // Save blank state
    this.onChangeCallback?.(false);
  }

  isEmpty() { return !this.hasDrawn; }

  toDataURL() { return this.canvas.toDataURL('image/png'); }

  drawImage(img: HTMLImageElement) {
    this.ctx.save();
    this.ctx.setTransform(1, 0, 0, 1, 0, 0);
    this.ctx.fillStyle = '#ffffff';
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.drawImage(img, 0, 0, this.canvas.width, this.canvas.height);
    this.ctx.restore();
    this.hasDrawn = true;
    this.saveState();
    this.onChangeCallback?.(true);
  }

  drawGuideline() {
    const rect = this.canvas.getBoundingClientRect();
    const y = rect.height * 0.75;
    this.ctx.save();
    this.ctx.strokeStyle = '#e5e7eb';
    this.ctx.lineWidth = 1;
    this.ctx.setLineDash([4, 4]);
    this.ctx.beginPath();
    this.ctx.moveTo(20, y);
    this.ctx.lineTo(rect.width - 20, y);
    this.ctx.stroke();
    this.ctx.restore();
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
      width = 500,
      height = 180,
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

    useImperativeHandle(ref, () => ({
      clear() {
        drawerRef.current?.clear();
        drawerRef.current?.drawGuideline();
        setHasContent(false);
      },
      isEmpty() {
        return drawerRef.current?.isEmpty() ?? true;
      },
      toDataURL() {
        return drawerRef.current?.toDataURL() ?? '';
      },
    }));

    const handleChange = useCallback(
      (has: boolean) => {
        setHasContent(has);
        onChange?.(has);
      },
      [onChange]
    );

    // Initialize inline canvas
    useEffect(() => {
      if (readOnly || !canvasRef.current) return;
      const drawer = new CanvasDrawer(canvasRef.current, handleChange);
      drawer.init();
      drawerRef.current = drawer;

      if (initialValue) {
        const img = new Image();
        img.onload = () => drawer.drawImage(img);
        img.src = initialValue;
      } else {
        drawer.drawGuideline();
      }

      return () => { drawer.unbindEvents(); };
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [readOnly]);

    // Initialize modal canvas
    useEffect(() => {
      if (!modalOpen || !modalCanvasRef.current) return;
      const timer = setTimeout(() => {
        if (!modalCanvasRef.current) return;
        const drawer = new CanvasDrawer(modalCanvasRef.current);
        drawer.init();
        drawer.drawGuideline();
        modalDrawerRef.current = drawer;
      }, 80);
      return () => clearTimeout(timer);
    }, [modalOpen]);

    const handleClear = () => {
      drawerRef.current?.clear();
      drawerRef.current?.drawGuideline();
      setHasContent(false);
      onChange?.(false);
    };

    const handleUndo = () => {
      drawerRef.current?.undo();
    };

    const openModal = () => {
      setModalOpen(true);
      document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
      setModalOpen(false);
      document.body.style.overflow = '';
      modalDrawerRef.current = null;
    };

    const clearModal = () => {
      modalDrawerRef.current?.clear();
      modalDrawerRef.current?.drawGuideline();
    };

    const undoModal = () => {
      modalDrawerRef.current?.undo();
    };

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
            <div className="inline-block rounded-lg border border-[var(--neutral-stroke-2)] bg-white p-2">
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

        {/* Inline canvas - click to open fullscreen */}
        <div
          className="overflow-hidden rounded-lg border-2 border-dashed border-[var(--neutral-stroke-1)] bg-white transition-colors hover:border-[var(--brand-80)] cursor-pointer relative group"
          style={{ width, maxWidth: '100%' }}
          onClick={openModal}
        >
          <canvas
            ref={canvasRef}
            style={{ display: 'block', width: '100%', height, touchAction: 'none', pointerEvents: 'none' }}
          />
          {!hasContent && (
            <div className="absolute inset-0 flex items-center justify-center bg-white/60 opacity-0 group-hover:opacity-100 transition-opacity">
              <span className="flex items-center gap-1.5 text-sm text-[var(--brand-80)] font-medium">
                <Maximize2 className="h-4 w-4" />
                クリックして署名
              </span>
            </div>
          )}
        </div>

        {/* Hint */}
        <p className="text-xs text-[var(--neutral-foreground-4)]">
          <Pen className="inline h-3 w-3 mr-0.5" />
          上のエリアに直接署名するか、「大きく書く」で拡大表示して記入できます
        </p>

        {/* Controls */}
        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" variant="primary" size="sm" leftIcon={<Maximize2 className="h-3.5 w-3.5" />} onClick={openModal}>
            大きく書く
          </Button>
          <Button type="button" variant="outline" size="sm" leftIcon={<Undo2 className="h-3.5 w-3.5" />} onClick={handleUndo}>
            戻す
          </Button>
          <Button type="button" variant="ghost" size="sm" leftIcon={<RotateCcw className="h-3.5 w-3.5" />} onClick={handleClear}>
            クリア
          </Button>
          {hasContent ? (
            <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
              <Check className="h-3 w-3" /> 署名済み
            </span>
          ) : (
            <span className="text-xs text-[var(--neutral-foreground-3)]">未署名</span>
          )}
        </div>

        {/* Fullscreen modal */}
        <AnimatePresence>
          {modalOpen && (
            <div className="fixed inset-0 z-[10000] flex items-center justify-center p-4 max-md:p-0">
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
                className="relative w-full max-w-[800px] rounded-xl bg-white shadow-2xl
                  max-md:max-w-full max-md:rounded-none max-md:h-full max-md:flex max-md:flex-col"
              >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-5 py-3">
                  <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">
                    {label ? `${label}を記入` : '署名を記入'}
                  </h3>
                  <button
                    type="button"
                    onClick={closeModal}
                    className="rounded-lg p-1.5 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-4)] hover:text-[var(--neutral-foreground-3)] transition-colors"
                  >
                    <X className="h-5 w-5" />
                  </button>
                </div>

                {/* Body */}
                <div className="p-5 max-md:flex-1 max-md:flex max-md:flex-col max-md:justify-center">
                  <p className="mb-2 text-xs text-[var(--neutral-foreground-3)] text-center">点線の上に署名してください</p>
                  <canvas
                    ref={modalCanvasRef}
                    style={{
                      display: 'block',
                      width: '100%',
                      height: 350,
                      border: '2px solid #d1d5db',
                      borderRadius: 12,
                      cursor: 'crosshair',
                      touchAction: 'none',
                      background: 'white',
                    }}
                    className="max-md:!h-[60vh]"
                  />
                </div>

                {/* Footer */}
                <div className="flex justify-between border-t border-[var(--neutral-stroke-2)] px-5 py-3">
                  <div className="flex gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={undoModal} leftIcon={<Undo2 className="h-4 w-4" />}>
                      戻す
                    </Button>
                    <Button type="button" variant="ghost" size="sm" onClick={clearModal} leftIcon={<RotateCcw className="h-4 w-4" />}>
                      クリア
                    </Button>
                  </div>
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
