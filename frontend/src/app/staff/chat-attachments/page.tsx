'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';
import { useAuthStore } from '@/stores/authStore';
import { ChatStorageBar } from '@/components/chat/ChatStorageBar';

type Source = 'guardian' | 'student' | 'staff';

interface AttachmentRef {
  source: Source;
  id: number;
}

// attachment_path 単位で集約された1ファイル。一斉送信は複数チャットを link_count/refs で束ねる。
interface Attachment {
  key: string;
  source: Source;
  name: string;
  size: number;
  mime: string | null;
  uploaded_at: string | null;
  uploader_name: string | null;
  rooms: string[];
  url: string;
  is_deleted: boolean;
  is_shared_photo: boolean;
  link_count: number;
  refs: AttachmentRef[];
}

interface Summary {
  used_bytes: number;
  limit_bytes: number;
  used_mb: number;
  limit_mb: number;
}

const SOURCE_LABEL: Record<Source, string> = {
  guardian: '保護者チャット',
  student: '生徒チャット',
  staff: 'スタッフ間チャット',
};

const fmtSize = (bytes: number): string =>
  bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)}MB` : `${Math.max(1, Math.round(bytes / 1024))}KB`;

const fmtDate = (iso: string | null): string => {
  if (!iso) return '';
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleString('ja-JP', { dateStyle: 'short', timeStyle: 'short' });
};

const isImage = (a: Attachment): boolean =>
  (a.mime?.startsWith('image/') ?? false) || /\.(png|jpe?g|gif|webp|heic)$/i.test(a.name);

const keyOf = (a: Attachment): string => a.key;

export default function ChatAttachmentsPage() {
  const { toast } = useToast();
  const { user } = useAuthStore();
  const queryClient = useQueryClient();
  const classroomId = user?.classroom_id ?? 0;

  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [sourceFilter, setSourceFilter] = useState<Source | 'all'>('all');
  const [deleting, setDeleting] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);

  const fetchList = useCallback(async () => {
    if (!classroomId) return;
    setLoading(true);
    try {
      const res = await api.get<{ data: { attachments: Attachment[]; summary: Summary } }>(
        '/api/staff/chat/attachments',
        { params: { classroom_id: classroomId } },
      );
      setAttachments(res.data.data.attachments || []);
      setSelected(new Set());
    } catch {
      toast('添付ファイル一覧の取得に失敗しました', 'error');
    } finally {
      setLoading(false);
    }
  }, [classroomId, toast]);

  useEffect(() => {
    fetchList();
  }, [fetchList, reloadKey]);

  const filtered = useMemo(
    () => (sourceFilter === 'all' ? attachments : attachments.filter((a) => a.source === sourceFilter)),
    [attachments, sourceFilter],
  );

  const allSelected = filtered.length > 0 && filtered.every((a) => selected.has(keyOf(a)));

  const toggle = (a: Attachment) => {
    setSelected((prev) => {
      const next = new Set(prev);
      const k = keyOf(a);
      if (next.has(k)) next.delete(k);
      else next.add(k);
      return next;
    });
  };

  const toggleAll = () => {
    setSelected((prev) => {
      if (filtered.every((a) => prev.has(keyOf(a)))) {
        const next = new Set(prev);
        filtered.forEach((a) => next.delete(keyOf(a)));
        return next;
      }
      const next = new Set(prev);
      filtered.forEach((a) => next.add(keyOf(a)));
      return next;
    });
  };

  const selectedSizeBytes = useMemo(
    () => attachments.filter((a) => selected.has(keyOf(a))).reduce((sum, a) => sum + a.size, 0),
    [attachments, selected],
  );

  const handleDelete = async () => {
    if (selected.size === 0) return;
    if (!window.confirm(`選択した ${selected.size} 件の添付ファイルを削除します。\nファイルの実体は完全に削除され、元に戻せません。よろしいですか？`)) {
      return;
    }
    setDeleting(true);
    try {
      // 集約された各ファイルの全参照(チャット)をまとめて削除する。
      const items = attachments
        .filter((a) => selected.has(keyOf(a)))
        .flatMap((a) => a.refs);
      const res = await api.post<{ data: { deleted_count: number; freed_bytes: number; summary: Summary } }>(
        '/api/staff/chat/attachments/delete',
        { classroom_id: classroomId, items },
      );
      const { deleted_count, freed_bytes } = res.data.data;
      toast(`${deleted_count}件削除しました（${fmtSize(freed_bytes)} 解放）`, 'success');
      // 上部の容量バー (ChatStorageBar) を最新化する
      queryClient.invalidateQueries({ queryKey: ['chat', 'storage-usage'] });
      setReloadKey((k) => k + 1);
    } catch {
      toast('削除に失敗しました', 'error');
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="space-y-4 p-4">
      <div className="flex items-center gap-2">
        <MaterialIcon name="attach_file" size={24} className="text-[var(--brand-80)]" />
        <h1 className="text-lg font-bold">チャット添付ファイル管理</h1>
      </div>
      <p className="text-xs text-[var(--neutral-foreground-3)]">
        保護者・生徒・スタッフ間チャットの添付ファイルを一覧し、不要なものを選択して削除できます。
        削除するとファイルの実体が消え、教室の保存容量（200MB）が解放されます。本文テキストは残ります。
      </p>

      <ChatStorageBar role="staff" classroomId={classroomId || undefined} />

      {/* ツールバー */}
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-1">
            {(['all', 'guardian', 'student', 'staff'] as const).map((s) => (
              <Button
                key={s}
                size="sm"
                variant={sourceFilter === s ? 'primary' : 'ghost'}
                onClick={() => setSourceFilter(s)}
              >
                {s === 'all' ? 'すべて' : SOURCE_LABEL[s]}
              </Button>
            ))}
          </div>
          <div className="flex-1" />
          <span className="text-xs text-[var(--neutral-foreground-3)]">
            {selected.size > 0 ? `${selected.size}件選択中（${fmtSize(selectedSizeBytes)}）` : `${filtered.length}件`}
          </span>
          <Button
            variant="danger"
            size="sm"
            leftIcon={<MaterialIcon name="delete" size={16} />}
            disabled={selected.size === 0 || deleting}
            isLoading={deleting}
            onClick={handleDelete}
          >
            選択した{selected.size > 0 ? ` ${selected.size} ` : ''}件を削除
          </Button>
        </CardBody>
      </Card>

      {/* 一覧 */}
      <Card>
        <CardBody className="p-0">
          {loading ? (
            <div className="flex items-center justify-center gap-2 py-12 text-sm text-[var(--neutral-foreground-3)]">
              <MaterialIcon name="progress_activity" size={20} className="animate-spin" />
              読み込み中...
            </div>
          ) : filtered.length === 0 ? (
            <div className="py-12 text-center text-sm text-[var(--neutral-foreground-3)]">
              添付ファイルはありません。
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                  <tr>
                    <th className="w-10 p-2">
                      <input type="checkbox" checked={allSelected} onChange={toggleAll} aria-label="全選択" />
                    </th>
                    <th className="p-2">ファイル</th>
                    <th className="p-2">区分</th>
                    <th className="p-2">投稿者</th>
                    <th className="p-2 text-right">サイズ</th>
                    <th className="p-2">日時</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((a) => {
                    const k = keyOf(a);
                    return (
                      <tr key={k} className="border-b border-[var(--neutral-stroke-3)] hover:bg-[var(--neutral-background-2)]">
                        <td className="p-2">
                          <input type="checkbox" checked={selected.has(k)} onChange={() => toggle(a)} aria-label={`${a.name} を選択`} />
                        </td>
                        <td className="p-2">
                          <a href={a.url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-[var(--brand-80)] hover:underline">
                            <MaterialIcon name={isImage(a) ? 'image' : 'description'} size={18} className="shrink-0" />
                            <span className="break-all">{a.name}</span>
                          </a>
                          <div className="mt-0.5 flex flex-wrap gap-1">
                            {a.link_count > 1 && <Badge variant="info">一斉送信 {a.link_count}件にリンク</Badge>}
                            {a.is_shared_photo && <Badge variant="warning">写真ライブラリ共有</Badge>}
                            {a.is_deleted && <Badge variant="default">送信取消済</Badge>}
                          </div>
                        </td>
                        <td className="p-2">
                          <span className="whitespace-nowrap">{SOURCE_LABEL[a.source]}</span>
                          {a.rooms.length > 0 && (
                            <div className="text-xs text-[var(--neutral-foreground-3)]">
                              {a.rooms.slice(0, 2).join('、')}{a.rooms.length > 2 ? ` 他${a.rooms.length - 2}件` : ''}
                            </div>
                          )}
                        </td>
                        <td className="p-2 whitespace-nowrap">{a.uploader_name ?? '—'}</td>
                        <td className="p-2 text-right whitespace-nowrap">{fmtSize(a.size)}</td>
                        <td className="p-2 whitespace-nowrap text-xs">{fmtDate(a.uploaded_at)}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
