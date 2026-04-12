'use client';

import { useEffect, useState, useCallback } from 'react';
import api from '@/lib/api';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

export interface PhotoOption {
  id: number;
  url: string;
  file_path: string;
  file_size: number;
  activity_description: string | null;
  activity_date: string | null;
  students?: { id: number; student_name: string }[];
}

interface Props {
  isOpen: boolean;
  multiple?: boolean;           // 複数選択可か
  initialSelected?: number[];   // 初期選択済みID
  onClose: () => void;
  onConfirm: (photos: PhotoOption[]) => void;
}

/**
 * 事業所写真ライブラリから写真を選択するピッカーモーダル。
 * チャット・連絡帳・施設通信から共通で利用する。
 */
export function PhotoPickerModal({ isOpen, multiple = false, initialSelected = [], onClose, onConfirm }: Props) {
  const { toast } = useToast();
  const [photos, setPhotos] = useState<PhotoOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [keyword, setKeyword] = useState('');
  const [selected, setSelected] = useState<Set<number>>(new Set(initialSelected));

  const fetchPhotos = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, string | number> = { per_page: 60 };
      if (keyword) params.keyword = keyword;
      const res = await api.get('/api/staff/classroom-photos', { params });
      setPhotos(res.data.data.data || []);
    } catch {
      toast('写真取得に失敗しました', 'error');
    } finally {
      setLoading(false);
    }
  }, [keyword, toast]);

  useEffect(() => {
    if (isOpen) {
      fetchPhotos();
      setSelected(new Set(initialSelected));
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen]);

  const togglePhoto = (id: number) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (multiple) {
        if (next.has(id)) next.delete(id);
        else next.add(id);
      } else {
        next.clear();
        next.add(id);
      }
      return next;
    });
  };

  const handleConfirm = () => {
    const chosen = photos.filter((p) => selected.has(p.id));
    onConfirm(chosen);
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={multiple ? '写真を選択 (複数可)' : '写真を選択'} size="lg">
      <div className="space-y-3">
        <div className="flex gap-2">
          <Input
            placeholder="キーワードで検索..."
            value={keyword}
            onChange={(e) => setKeyword(e.target.value)}
          />
          <Button variant="outline" onClick={fetchPhotos} leftIcon={<MaterialIcon name="search" size={16} />}>
            検索
          </Button>
        </div>

        {loading ? (
          <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
        ) : photos.length === 0 ? (
          <p className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">
            該当する写真がありません
          </p>
        ) : (
          <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2 max-h-[50vh] overflow-y-auto">
            {photos.map((p) => (
              <button
                key={p.id}
                onClick={() => togglePhoto(p.id)}
                className={`relative overflow-hidden rounded border-2 transition-colors ${
                  selected.has(p.id)
                    ? 'border-[var(--brand-80)]'
                    : 'border-[var(--neutral-stroke-2)] hover:border-[var(--brand-60)]'
                }`}
              >
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={p.url} alt={p.activity_description ?? ''} className="h-full w-full aspect-square object-cover" />
                {selected.has(p.id) && (
                  <div className="absolute inset-0 bg-[var(--brand-80)]/30 flex items-center justify-center">
                    <MaterialIcon name="check_circle" size={24} className="text-white" />
                  </div>
                )}
                {p.students && p.students.length > 0 && (
                  <div className="absolute bottom-0 left-0 right-0 bg-black/50 px-1 py-0.5">
                    <p className="truncate text-[9px] text-white">
                      {p.students.map((s) => s.student_name).join(', ')}
                    </p>
                  </div>
                )}
              </button>
            ))}
          </div>
        )}

        <div className="flex items-center justify-between pt-2 border-t border-[var(--neutral-stroke-2)]">
          <span className="text-xs text-[var(--neutral-foreground-3)]">
            {selected.size} 件選択中
          </span>
          <div className="flex gap-2">
            <Button variant="outline" onClick={onClose}>キャンセル</Button>
            <Button variant="primary" onClick={handleConfirm} disabled={selected.size === 0}>
              決定
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  );
}
