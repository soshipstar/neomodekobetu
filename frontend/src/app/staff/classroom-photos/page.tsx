'use client';

import { useEffect, useState, useCallback, useRef } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';
import { useAuthStore } from '@/stores/authStore';

interface Photo {
  id: number;
  classroom_id: number;
  file_path: string;
  url: string;
  file_size: number;
  activity_description: string | null;
  activity_date: string | null;
  created_at: string;
  students?: { id: number; student_name: string }[];
  uploader?: { id: number; full_name: string };
}

interface StorageUsage {
  used_bytes: number;
  limit_bytes: number;
  used_mb: number;
  limit_mb: number;
}

interface StudentOption {
  id: number;
  student_name: string;
}

const fmtKB = (bytes: number) => `${(bytes / 1024).toFixed(0)}KB`;

export default function ClassroomPhotosPage() {
  const { toast } = useToast();
  const { user } = useAuthStore();
  const [photos, setPhotos] = useState<Photo[]>([]);
  const [loading, setLoading] = useState(true);
  const [usage, setUsage] = useState<StorageUsage | null>(null);
  const [keyword, setKeyword] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [studentFilter, setStudentFilter] = useState<string>('');
  const [students, setStudents] = useState<StudentOption[]>([]);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [detailPhoto, setDetailPhoto] = useState<Photo | null>(null);

  const classroomId = user?.classroom_id ?? 0;

  const fetchPhotos = useCallback(async () => {
    if (!classroomId) return;
    setLoading(true);
    try {
      const params: Record<string, string | number> = { per_page: 60 };
      if (keyword) params.keyword = keyword;
      if (fromDate) params.from = fromDate;
      if (toDate) params.to = toDate;
      if (studentFilter) params.student_id = studentFilter;
      const res = await api.get('/api/staff/classroom-photos', { params });
      setPhotos(res.data.data.data || []);
    } catch {
      toast('写真一覧の取得に失敗しました', 'error');
    } finally {
      setLoading(false);
    }
  }, [classroomId, keyword, fromDate, toDate, studentFilter, toast]);

  const fetchUsage = useCallback(async () => {
    if (!classroomId) return;
    try {
      const res = await api.get<{ data: StorageUsage }>('/api/staff/classroom-photos/storage-usage', {
        params: { classroom_id: classroomId },
      });
      setUsage(res.data.data);
    } catch {
      // 無視
    }
  }, [classroomId]);

  const fetchStudents = useCallback(async () => {
    if (!classroomId) return;
    try {
      const res = await api.get('/api/staff/students', { params: { per_page: 200 } });
      const list = res.data?.data?.data || res.data?.data || [];
      setStudents(list.map((s: { id: number; student_name: string }) => ({ id: s.id, student_name: s.student_name })));
    } catch {
      // 無視
    }
  }, [classroomId]);

  useEffect(() => { fetchPhotos(); }, [fetchPhotos]);
  useEffect(() => { fetchUsage(); }, [fetchUsage]);
  useEffect(() => { fetchStudents(); }, [fetchStudents]);

  const handleDelete = async (photo: Photo) => {
    if (!window.confirm('この写真を削除しますか？')) return;
    try {
      await api.delete(`/api/staff/classroom-photos/${photo.id}`);
      toast('削除しました', 'success');
      setPhotos((prev) => prev.filter((p) => p.id !== photo.id));
      fetchUsage();
    } catch {
      toast('削除に失敗しました', 'error');
    }
  };

  const usedPercent = usage ? (usage.used_bytes / usage.limit_bytes) * 100 : 0;

  return (
    <div className="space-y-4 p-4">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[var(--neutral-foreground-1)]">写真ライブラリ</h1>
          <p className="text-xs text-[var(--neutral-foreground-3)] mt-1">
            事業所で共有される写真を管理します。圧縮されて保存されます。
          </p>
        </div>
        <Button
          variant="primary"
          onClick={() => setUploadOpen(true)}
          leftIcon={<MaterialIcon name="upload" size={16} />}
        >
          写真をアップロード
        </Button>
      </div>

      {/* ストレージ使用量 */}
      {usage && (
        <Card>
          <CardBody>
            <div className="flex items-center justify-between text-sm mb-2">
              <span className="font-medium">保存容量</span>
              <span className="text-xs text-[var(--neutral-foreground-3)]">
                {usage.used_mb}MB / {usage.limit_mb}MB
              </span>
            </div>
            <div className="h-2 rounded-full bg-[var(--neutral-background-4)] overflow-hidden">
              <div
                className="h-full transition-all"
                style={{
                  width: `${Math.min(100, usedPercent)}%`,
                  backgroundColor:
                    usedPercent > 90 ? 'var(--status-danger-fg)' :
                    usedPercent > 70 ? 'var(--status-warning-fg)' :
                    'var(--status-success-fg)',
                }}
              />
            </div>
          </CardBody>
        </Card>
      )}

      {/* フィルタ */}
      <Card>
        <CardBody>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
            <Input
              label="活動内容キーワード"
              value={keyword}
              onChange={(e) => setKeyword(e.target.value)}
              placeholder="例: 工作"
            />
            <Input
              label="活動日 (から)"
              type="date"
              value={fromDate}
              onChange={(e) => setFromDate(e.target.value)}
            />
            <Input
              label="活動日 (まで)"
              type="date"
              value={toDate}
              onChange={(e) => setToDate(e.target.value)}
            />
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">児童</label>
              <select
                value={studentFilter}
                onChange={(e) => setStudentFilter(e.target.value)}
                className="block w-full rounded border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
              >
                <option value="">すべて</option>
                {students.map((s) => (
                  <option key={s.id} value={s.id}>{s.student_name}</option>
                ))}
              </select>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* グリッド */}
      {loading ? (
        <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
      ) : photos.length === 0 ? (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-4)]">
              該当する写真がありません
            </p>
          </CardBody>
        </Card>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
          {photos.map((photo) => (
            <div key={photo.id} className="group relative overflow-hidden rounded-lg border border-[var(--neutral-stroke-2)] bg-white">
              <button
                onClick={() => setDetailPhoto(photo)}
                className="block w-full aspect-square bg-[var(--neutral-background-3)]"
              >
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={photo.url} alt={photo.activity_description ?? ''} className="h-full w-full object-cover" />
              </button>
              <div className="p-2">
                {photo.activity_date && (
                  <p className="text-[10px] text-[var(--neutral-foreground-4)]">{photo.activity_date}</p>
                )}
                <p className="line-clamp-1 text-xs text-[var(--neutral-foreground-2)]">
                  {photo.activity_description || '—'}
                </p>
                {photo.students && photo.students.length > 0 && (
                  <div className="mt-1 flex flex-wrap gap-0.5">
                    {photo.students.slice(0, 3).map((s) => (
                      <Badge key={s.id} variant="default" className="text-[9px]">{s.student_name}</Badge>
                    ))}
                    {photo.students.length > 3 && (
                      <span className="text-[9px] text-[var(--neutral-foreground-4)]">+{photo.students.length - 3}</span>
                    )}
                  </div>
                )}
                <div className="mt-1 flex items-center justify-between">
                  <span className="text-[9px] text-[var(--neutral-foreground-4)]">{fmtKB(photo.file_size)}</span>
                  <button
                    onClick={() => handleDelete(photo)}
                    className="rounded p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--status-danger-fg)]"
                    title="削除"
                  >
                    <MaterialIcon name="delete" size={14} />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* アップロードモーダル */}
      {uploadOpen && (
        <PhotoUploadModal
          classroomId={classroomId}
          students={students}
          onClose={() => setUploadOpen(false)}
          onUploaded={() => {
            setUploadOpen(false);
            fetchPhotos();
            fetchUsage();
          }}
        />
      )}

      {/* 詳細モーダル */}
      {detailPhoto && (
        <Modal isOpen={true} onClose={() => setDetailPhoto(null)} title="写真詳細" size="lg">
          <div className="space-y-3">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={detailPhoto.url} alt="" className="w-full max-h-[60vh] object-contain rounded" />
            <div className="grid grid-cols-2 gap-2 text-sm">
              <div><strong>活動日:</strong> {detailPhoto.activity_date ?? '—'}</div>
              <div><strong>サイズ:</strong> {fmtKB(detailPhoto.file_size)}</div>
            </div>
            <div className="text-sm"><strong>活動内容:</strong> {detailPhoto.activity_description ?? '—'}</div>
            {detailPhoto.students && (
              <div className="text-sm">
                <strong>写った児童:</strong>{' '}
                {detailPhoto.students.map((s) => s.student_name).join('、') || '—'}
              </div>
            )}
            <div className="flex justify-end gap-2 pt-2">
              <Button variant="outline" onClick={() => setDetailPhoto(null)}>閉じる</Button>
              <Button
                variant="ghost"
                onClick={() => { handleDelete(detailPhoto); setDetailPhoto(null); }}
                leftIcon={<MaterialIcon name="delete" size={16} />}
                className="text-[var(--status-danger-fg)]"
              >
                削除
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}

function PhotoUploadModal({
  classroomId,
  students,
  onClose,
  onUploaded,
}: {
  classroomId: number;
  students: StudentOption[];
  onClose: () => void;
  onUploaded: () => void;
}) {
  const { toast } = useToast();
  const fileRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [activityDescription, setActivityDescription] = useState('');
  const [activityDate, setActivityDate] = useState(new Date().toISOString().slice(0, 10));
  const [selectedStudents, setSelectedStudents] = useState<Set<number>>(new Set());
  const [saving, setSaving] = useState(false);

  const handleSubmit = async () => {
    if (!file) {
      toast('写真を選択してください', 'error');
      return;
    }
    setSaving(true);
    try {
      const formData = new FormData();
      formData.append('photo', file);
      formData.append('classroom_id', String(classroomId));
      formData.append('activity_description', activityDescription);
      formData.append('activity_date', activityDate);
      Array.from(selectedStudents).forEach((id) => formData.append('student_ids[]', String(id)));
      const res = await api.post('/api/staff/classroom-photos', formData);
      toast(res.data.message || 'アップロードしました', 'success');
      onUploaded();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'アップロードに失敗しました';
      toast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal isOpen={true} onClose={onClose} title="写真をアップロード" size="md">
      <div className="space-y-4">
        <div>
          <label className="mb-1 block text-sm font-medium">写真ファイル</label>
          {file ? (
            <div className="flex items-center gap-2 rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-2">
              <MaterialIcon name="image" size={18} />
              <span className="flex-1 truncate text-sm">{file.name}</span>
              <span className="text-xs text-[var(--neutral-foreground-4)]">
                {(file.size / 1024).toFixed(0)}KB
              </span>
              <button onClick={() => setFile(null)} className="text-[var(--status-danger-fg)]">
                <MaterialIcon name="close" size={16} />
              </button>
            </div>
          ) : (
            <button
              onClick={() => fileRef.current?.click()}
              className="flex w-full items-center justify-center gap-2 rounded border border-dashed border-[var(--neutral-stroke-2)] py-6 text-sm text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]"
            >
              <MaterialIcon name="add_photo_alternate" size={20} />
              画像を選択 (自動で 100KB 以下に圧縮されます)
            </button>
          )}
          <input
            ref={fileRef}
            type="file"
            accept="image/*"
            className="hidden"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
        </div>

        <Input
          label="活動内容"
          value={activityDescription}
          onChange={(e) => setActivityDescription(e.target.value)}
          placeholder="例: 公園で水遊び"
        />

        <Input
          label="活動日"
          type="date"
          value={activityDate}
          onChange={(e) => setActivityDate(e.target.value)}
        />

        <div>
          <label className="mb-1 block text-sm font-medium">写った児童 (複数選択可)</label>
          <div className="max-h-[200px] overflow-y-auto rounded border border-[var(--neutral-stroke-2)] p-2">
            {students.length === 0 ? (
              <p className="text-xs text-[var(--neutral-foreground-4)]">児童がいません</p>
            ) : (
              students.map((s) => (
                <label key={s.id} className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-[var(--neutral-background-3)] cursor-pointer">
                  <input
                    type="checkbox"
                    checked={selectedStudents.has(s.id)}
                    onChange={() => {
                      setSelectedStudents((prev) => {
                        const next = new Set(prev);
                        if (next.has(s.id)) next.delete(s.id);
                        else next.add(s.id);
                        return next;
                      });
                    }}
                  />
                  {s.student_name}
                </label>
              ))
            )}
          </div>
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" onClick={onClose}>キャンセル</Button>
          <Button
            variant="primary"
            onClick={handleSubmit}
            isLoading={saving}
            disabled={!file}
            leftIcon={<MaterialIcon name="upload" size={16} />}
          >
            アップロード
          </Button>
        </div>
      </div>
    </Modal>
  );
}
