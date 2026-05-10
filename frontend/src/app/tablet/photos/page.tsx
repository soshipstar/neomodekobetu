'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import api from '@/lib/api';
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
  used_mb: number;
  limit_mb: number;
}

interface StudentOption {
  id: number;
  student_name: string;
}

export default function TabletPhotosPage() {
  const toast = useToast();
  const { user } = useAuthStore();
  const classroomId = user?.classroom_id ?? 0;

  const [photos, setPhotos] = useState<Photo[]>([]);
  const [loading, setLoading] = useState(true);
  const [usage, setUsage] = useState<StorageUsage | null>(null);
  const [students, setStudents] = useState<StudentOption[]>([]);

  // アップロードフォーム
  const [showUpload, setShowUpload] = useState(false);
  // 複数ファイル対応: 選択された各ファイルとそのプレビュー URL を index 同期で保持
  const [pendingFiles, setPendingFiles] = useState<File[]>([]);
  const [previews, setPreviews] = useState<string[]>([]);
  const [activityDescription, setActivityDescription] = useState('');
  const [activityDate, setActivityDate] = useState(new Date().toISOString().slice(0, 10));
  const [selectedStudents, setSelectedStudents] = useState<Set<number>>(new Set());
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState<{ current: number; total: number } | null>(null);
  // capture="environment" 付きの input → ブラウザ/OS が直接カメラを起動 (1枚ずつ)
  const cameraRef = useRef<HTMLInputElement>(null);
  // capture 属性なし + multiple → 写真ライブラリから複数選択可
  const libraryRef = useRef<HTMLInputElement>(null);

  // HEIC/HEIF 検出 + ユーザーへの案内文
  const isHeicFile = (f: File): boolean =>
    /\.(heic|heif)$/i.test(f.name) || f.type === 'image/heic' || f.type === 'image/heif';
  const HEIC_HINT = 'iPhoneで撮影したHEIC形式の写真が含まれています。一部の端末では読み込めない場合があります。\n対処: iPhoneの「設定」→「カメラ」→「フォーマット」を「互換性優先」に切り替えると JPEG で保存されます。';

  // 詳細表示
  const [detailPhoto, setDetailPhoto] = useState<Photo | null>(null);

  const fetchPhotos = useCallback(async () => {
    if (!classroomId) return;
    setLoading(true);
    try {
      const res = await api.get('/api/tablet/photos', { params: { per_page: 60 } });
      setPhotos(res.data.data.data || []);
    } catch {
      toast.error('写真一覧の取得に失敗しました');
    } finally {
      setLoading(false);
    }
  }, [classroomId, toast]);

  const fetchUsage = useCallback(async () => {
    if (!classroomId) return;
    try {
      const res = await api.get<{ data: StorageUsage }>('/api/tablet/photos/storage-usage', {
        params: { classroom_id: classroomId },
      });
      setUsage(res.data.data);
    } catch {
      // ignore
    }
  }, [classroomId]);

  const fetchStudents = useCallback(async () => {
    if (!classroomId) return;
    try {
      const res = await api.get('/api/tablet/students');
      setStudents(res.data?.data || []);
    } catch {
      // ignore
    }
  }, [classroomId]);

  useEffect(() => { fetchPhotos(); }, [fetchPhotos]);
  useEffect(() => { fetchUsage(); }, [fetchUsage]);
  useEffect(() => { fetchStudents(); }, [fetchStudents]);

  // ファイル選択時 (複数対応): 既存の選択に append
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newFiles = Array.from(e.target.files ?? []);
    if (newFiles.length === 0) return;

    // HEIC が混じっていれば一度だけ案内 (アップロードは試行する)
    if (newFiles.some(isHeicFile)) {
      toast.warning(HEIC_HINT);
    }

    setPendingFiles((prev) => [...prev, ...newFiles]);
    setPreviews((prev) => [...prev, ...newFiles.map((f) => URL.createObjectURL(f))]);
    // 同じファイルを再度選べるように value をリセット
    e.target.value = '';
  };

  // プレビューサムネから1件削除
  const removePendingFile = (index: number) => {
    setPendingFiles((prev) => prev.filter((_, i) => i !== index));
    setPreviews((prev) => {
      const url = prev[index];
      if (url) URL.revokeObjectURL(url);
      return prev.filter((_, i) => i !== index);
    });
  };

  const clearPendingFiles = () => {
    previews.forEach((u) => URL.revokeObjectURL(u));
    setPendingFiles([]);
    setPreviews([]);
  };

  // フロントエンドでJPEG圧縮+リサイズ
  const compressImage = (src: File, maxWidth = 2048, quality = 0.85): Promise<File> =>
    new Promise((resolve, reject) => {
      const url = URL.createObjectURL(src);
      const img = new Image();
      img.onload = () => {
        let w = img.naturalWidth;
        let h = img.naturalHeight;
        if (w > maxWidth) {
          h = Math.round(h * (maxWidth / w));
          w = maxWidth;
        }
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        if (!ctx) { reject(new Error('Canvas not supported')); return; }
        ctx.drawImage(img, 0, 0, w, h);
        canvas.toBlob(
          (blob) => {
            URL.revokeObjectURL(url);
            if (!blob) { reject(new Error('Blob conversion failed')); return; }
            const name = src.name.replace(/\.[^.]+$/, '') + '.jpg';
            resolve(new File([blob], name, { type: 'image/jpeg' }));
          },
          'image/jpeg',
          quality,
        );
      };
      img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('画像を読み込めませんでした')); };
      img.src = url;
    });

  const handleUpload = async () => {
    if (pendingFiles.length === 0) {
      toast.error('写真を選択してください');
      return;
    }
    setUploading(true);

    // 1) 圧縮フェーズ (進捗カウンタは「圧縮中 (i/N)」で表示)
    setUploadProgress({ current: 0, total: pendingFiles.length });
    const compressed: { file: File; original: File }[] = [];
    const compressFailures: { file: File; reason: 'heic' | 'other' }[] = [];

    for (let i = 0; i < pendingFiles.length; i++) {
      const f = pendingFiles[i];
      setUploadProgress({ current: i + 1, total: pendingFiles.length });
      try {
        const jpeg = await compressImage(f);
        compressed.push({ file: jpeg, original: f });
      } catch (err) {
        const reason: 'heic' | 'other' = isHeicFile(f) ? 'heic' : 'other';
        console.warn('photo compress failed', f.name, err);
        compressFailures.push({ file: f, reason });
      }
    }

    // 2) バッチ送信フェーズ (圧縮済を1リクエストにまとめる)
    let serverCreated = 0;
    let serverFailures: { reason: string }[] = [];
    if (compressed.length > 0) {
      setUploadProgress({ current: pendingFiles.length, total: pendingFiles.length });
      try {
        const formData = new FormData();
        compressed.forEach((c) => formData.append('photos[]', c.file));
        formData.append('classroom_id', String(classroomId));
        formData.append('activity_description', activityDescription);
        formData.append('activity_date', activityDate);
        Array.from(selectedStudents).forEach((id) => formData.append('student_ids[]', String(id)));

        const res = await api.post('/api/tablet/photos/batch', formData);
        serverCreated = Number(res.data?.data?.success_count ?? 0);
        const fails = Array.isArray(res.data?.data?.failed) ? res.data.data.failed : [];
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        serverFailures = fails.map((x: any) => ({ reason: String(x?.reason ?? '不明') }));
      } catch (err: unknown) {
        // バッチ全体失敗 (容量超過の早期 422 / ネットワーク等)
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
          || 'アップロードに失敗しました';
        console.warn('batch upload failed', err);
        serverFailures = compressed.map(() => ({ reason: msg }));
      }
    }

    setUploadProgress(null);
    setUploading(false);

    // 3) 結果通知 (圧縮失敗とサーバー失敗を統合)
    const totalFail = compressFailures.length + serverFailures.length;
    if (totalFail === 0) {
      toast.success(`${serverCreated}件の写真をアップロードしました`);
    } else if (serverCreated === 0) {
      const heicOnly = compressFailures.length > 0 && compressFailures.every((x) => x.reason === 'heic');
      toast.error(heicOnly ? HEIC_HINT : 'すべての写真のアップロードに失敗しました');
    } else {
      const heicIncluded = compressFailures.some((x) => x.reason === 'heic');
      toast.warning(`${serverCreated}件成功 / ${totalFail}件失敗${heicIncluded ? `\n${HEIC_HINT}` : ''}`);
    }

    // 4) リセット (失敗分も含めて全クリア。リトライ時は再選択する想定)
    clearPendingFiles();
    setActivityDescription('');
    setSelectedStudents(new Set());
    setShowUpload(false);
    fetchPhotos();
    fetchUsage();
  };

  const handleDelete = async (photo: Photo) => {
    if (!confirm('この写真を削除しますか？')) return;
    try {
      await api.delete(`/api/tablet/photos/${photo.id}`);
      toast.success('削除しました');
      setPhotos((prev) => prev.filter((p) => p.id !== photo.id));
      if (detailPhoto?.id === photo.id) setDetailPhoto(null);
      fetchUsage();
    } catch {
      toast.error('削除に失敗しました');
    }
  };

  const toggleStudent = (id: number) => {
    setSelectedStudents((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  return (
    <div className="space-y-6">
      {/* ヘッダー */}
      <div className="flex items-center justify-between rounded-xl bg-white p-6 shadow-md">
        <div>
          <h1 className="text-2xl font-bold">写真ライブラリ</h1>
          {usage && (
            <p className="mt-1 text-lg text-[var(--neutral-foreground-3)]">
              使用量: {usage.used_mb}MB / {usage.limit_mb}MB
            </p>
          )}
        </div>
        <button
          onClick={() => setShowUpload(!showUpload)}
          className="rounded-lg bg-green-600 px-6 py-3 text-xl font-bold text-white hover:bg-green-700"
        >
          {showUpload ? '閉じる' : '+ 写真をアップロード'}
        </button>
      </div>

      {/* アップロードフォーム */}
      {showUpload && (
        <div className="rounded-xl bg-white p-6 shadow-md space-y-5">
          <h2 className="text-xl font-bold">写真をアップロード</h2>

          {/* ファイル選択 */}
          <div>
            {pendingFiles.length === 0 ? (
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {/* カメラ撮影 (capture="environment" でその場でカメラ起動・1枚ずつ) */}
                <button
                  onClick={() => cameraRef.current?.click()}
                  className="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-blue-300 bg-blue-50 py-10 text-xl font-bold text-blue-700 hover:bg-blue-100"
                >
                  <span className="text-4xl">📷</span>
                  撮影してアップロード
                </button>
                {/* 既存写真をライブラリから選択 (multiple で複数選択可) */}
                <button
                  onClick={() => libraryRef.current?.click()}
                  className="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-green-300 bg-green-50 py-10 text-xl font-bold text-green-700 hover:bg-green-100"
                >
                  <span className="text-4xl">🖼️</span>
                  写真を選択 (複数可)
                </button>
              </div>
            ) : (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <p className="text-lg font-medium">{pendingFiles.length} 枚選択中</p>
                  <div className="flex gap-2">
                    <button
                      onClick={() => libraryRef.current?.click()}
                      className="rounded-lg bg-green-100 px-4 py-2 text-base font-bold text-green-700 hover:bg-green-200"
                    >
                      + 追加
                    </button>
                    <button
                      onClick={() => cameraRef.current?.click()}
                      className="rounded-lg bg-blue-100 px-4 py-2 text-base font-bold text-blue-700 hover:bg-blue-200"
                    >
                      📷 撮影追加
                    </button>
                    <button
                      onClick={clearPendingFiles}
                      className="rounded-lg bg-gray-100 px-4 py-2 text-base font-bold text-gray-700 hover:bg-gray-200"
                    >
                      全クリア
                    </button>
                  </div>
                </div>
                <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                  {previews.map((url, idx) => {
                    const f = pendingFiles[idx];
                    const heic = f && isHeicFile(f);
                    return (
                      <div key={`${url}-${idx}`} className="relative aspect-square overflow-hidden rounded-lg border-2 border-gray-200 bg-gray-100">
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img src={url} alt={f?.name ?? ''} className="h-full w-full object-cover" />
                        <button
                          onClick={() => removePendingFile(idx)}
                          className="absolute right-1 top-1 rounded-full bg-red-500 px-2 py-0.5 text-sm text-white shadow"
                          aria-label="削除"
                        >
                          ✕
                        </button>
                        {heic && (
                          <span className="absolute bottom-1 left-1 rounded bg-yellow-400/90 px-1 py-0.5 text-[10px] font-bold text-yellow-900" title="HEIC形式">
                            HEIC
                          </span>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
            {/* カメラ専用 input (1枚ずつ) */}
            <input
              ref={cameraRef}
              type="file"
              accept="image/*"
              capture="environment"
              className="hidden"
              onChange={handleFileChange}
            />
            {/* ライブラリ選択用 input (capture 属性なし、複数選択可) */}
            <input
              ref={libraryRef}
              type="file"
              accept="image/*"
              multiple
              className="hidden"
              onChange={handleFileChange}
            />
          </div>

          {/* 活動内容 */}
          <div>
            <label className="mb-2 block text-lg font-medium">活動内容</label>
            <input
              type="text"
              value={activityDescription}
              onChange={(e) => setActivityDescription(e.target.value)}
              placeholder="例: 公園で水遊び"
              className="w-full rounded-lg border-2 border-gray-300 px-4 py-3 text-lg"
            />
          </div>

          {/* 活動日 */}
          <div>
            <label className="mb-2 block text-lg font-medium">活動日</label>
            <input
              type="date"
              value={activityDate}
              onChange={(e) => setActivityDate(e.target.value)}
              className="w-full rounded-lg border-2 border-gray-300 px-4 py-3 text-lg"
            />
          </div>

          {/* 児童選択 */}
          <div>
            <label className="mb-2 block text-lg font-medium">
              写った児童 ({selectedStudents.size}名選択中)
            </label>
            <div className="flex flex-wrap gap-2 max-h-48 overflow-y-auto rounded-lg border-2 border-gray-300 p-3">
              {students.length === 0 ? (
                <p className="text-gray-400">児童がいません</p>
              ) : (
                students.map((s) => (
                  <button
                    key={s.id}
                    onClick={() => toggleStudent(s.id)}
                    className={`rounded-full px-4 py-2 text-lg font-medium transition-colors ${
                      selectedStudents.has(s.id)
                        ? 'bg-blue-500 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    {s.student_name}
                  </button>
                ))
              )}
            </div>
          </div>

          {/* アップロードボタン */}
          <div className="flex gap-4">
            <button
              onClick={() => setShowUpload(false)}
              disabled={uploading}
              className="flex-1 rounded-lg border-2 border-gray-300 px-6 py-3 text-xl font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
              キャンセル
            </button>
            <button
              onClick={handleUpload}
              disabled={pendingFiles.length === 0 || uploading}
              className="flex-1 rounded-lg bg-blue-600 px-6 py-3 text-xl font-bold text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {uploading
                ? `アップロード中... ${uploadProgress ? `(${uploadProgress.current}/${uploadProgress.total})` : ''}`
                : `${pendingFiles.length || ''}枚アップロード`}
            </button>
          </div>
        </div>
      )}

      {/* 写真グリッド */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <h2 className="mb-4 text-xl font-bold">写真一覧</h2>
        {loading ? (
          <p className="py-12 text-center text-xl text-gray-400">読み込み中...</p>
        ) : photos.length === 0 ? (
          <p className="py-12 text-center text-xl text-gray-400">
            まだ写真がありません。<br />
            「写真をアップロード」ボタンから追加してください。
          </p>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            {photos.map((photo) => (
              <div key={photo.id} className="overflow-hidden rounded-lg border-2 border-gray-200 bg-white">
                <button
                  onClick={() => setDetailPhoto(photo)}
                  className="block w-full aspect-square bg-gray-100"
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={photo.url} alt={photo.activity_description ?? ''} className="h-full w-full object-cover" />
                </button>
                <div className="p-3">
                  {photo.activity_date && (
                    <p className="text-sm text-gray-500">{photo.activity_date}</p>
                  )}
                  <p className="line-clamp-1 text-base font-medium">
                    {photo.activity_description || '—'}
                  </p>
                  {photo.students && photo.students.length > 0 && (
                    <div className="mt-1 flex flex-wrap gap-1">
                      {photo.students.slice(0, 3).map((s) => (
                        <span key={s.id} className="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">
                          {s.student_name}
                        </span>
                      ))}
                      {photo.students.length > 3 && (
                        <span className="text-xs text-gray-400">+{photo.students.length - 3}</span>
                      )}
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* 詳細モーダル */}
      {detailPhoto && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onClick={() => setDetailPhoto(null)}>
          <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={detailPhoto.url} alt="" className="w-full max-h-[50vh] rounded-lg object-contain" />
            <div className="mt-4 space-y-2">
              {detailPhoto.activity_date && (
                <p className="text-lg"><span className="font-bold">活動日:</span> {detailPhoto.activity_date}</p>
              )}
              <p className="text-lg"><span className="font-bold">活動内容:</span> {detailPhoto.activity_description || '—'}</p>
              {detailPhoto.students && detailPhoto.students.length > 0 && (
                <p className="text-lg">
                  <span className="font-bold">児童:</span>{' '}
                  {detailPhoto.students.map((s) => s.student_name).join('、')}
                </p>
              )}
              {detailPhoto.uploader && (
                <p className="text-base text-gray-500">アップロード: {detailPhoto.uploader.full_name}</p>
              )}
            </div>
            <div className="mt-6 flex gap-4">
              <button
                onClick={() => setDetailPhoto(null)}
                className="flex-1 rounded-lg border-2 border-gray-300 px-6 py-3 text-xl font-bold hover:bg-gray-50"
              >
                閉じる
              </button>
              <button
                onClick={() => handleDelete(detailPhoto)}
                className="flex-1 rounded-lg bg-red-500 px-6 py-3 text-xl font-bold text-white hover:bg-red-600"
              >
                削除
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
