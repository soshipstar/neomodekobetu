'use client';

import { useState } from 'react';
import { usePagination } from '@/hooks/usePagination';
import { useDebounce } from '@/hooks/useDebounce';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import type { Student } from '@/types/user';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { StudentCopyModal } from '@/components/admin/StudentCopyModal';
import { StudentLinkedSyncModal } from '@/components/admin/StudentLinkedSyncModal';

const statusLabels: Record<string, string> = {
  active: '在籍', trial: '体験', short_term: '短期', withdrawn: '退所', waiting: '待機',
};

const statusVariants: Record<string, 'success' | 'warning' | 'danger' | 'default' | 'info'> = {
  active: 'success', trial: 'info', short_term: 'warning', withdrawn: 'danger', waiting: 'default',
};

const gradeLabels: Record<string, string> = {
  preschool: '未就学',
  elementary_1: '小1', elementary_2: '小2', elementary_3: '小3',
  elementary_4: '小4', elementary_5: '小5', elementary_6: '小6',
  junior_high_1: '中1', junior_high_2: '中2', junior_high_3: '中3',
  high_school_1: '高1', high_school_2: '高2', high_school_3: '高3',
  elementary: '小学生', junior_high: '中学生', high_school: '高校生',
};

interface GradeChange {
  id: number;
  student_name: string;
  old_grade: string;
  new_grade: string;
}

export default function AdminStudentsPage() {
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const [showPromotion, setShowPromotion] = useState(false);
  const [preview, setPreview] = useState<GradeChange[] | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [copySource, setCopySource] = useState<Student | null>(null);
  const [linkedTarget, setLinkedTarget] = useState<Student | null>(null);
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const { data: students, meta, isLoading, goToPage } = usePagination<Student>({
    endpoint: '/api/admin/students',
    queryKey: ['admin', 'students'],
    params: { search: debouncedSearch || undefined },
  });

  const executeMutation = useMutation({
    mutationFn: () => api.post('/api/admin/students/grade-promotion/execute'),
    onSuccess: (res) => {
      toast(res.data?.message || '学年を更新しました', 'success');
      setShowPromotion(false);
      setPreview(null);
      queryClient.invalidateQueries({ queryKey: ['admin', 'students'] });
    },
    onError: () => {
      toast('学年更新に失敗しました', 'error');
    },
  });

  const handleOpenPromotion = async () => {
    setShowPromotion(true);
    setPreviewLoading(true);
    try {
      const res = await api.get('/api/admin/students/grade-promotion/preview');
      setPreview(res.data?.data || []);
    } catch {
      toast('プレビューの取得に失敗しました', 'error');
      setShowPromotion(false);
    } finally {
      setPreviewLoading(false);
    }
  };

  const columns: Column<Student>[] = [
    {
      key: 'student_name',
      label: '生徒名',
      sortable: true,
      render: (s) => (
        <div className="flex items-center gap-2">
          <span className="font-medium">{s.student_name}</span>
          {s.person_id && (
            <Badge variant="info" title={`同一人物としてリンク (person_id=${s.person_id.slice(0, 8)}…)`}>
              <MaterialIcon name="link" size={10} className="mr-0.5 inline" />
              同一人物
            </Badge>
          )}
        </div>
      ),
    },
    { key: 'classroom', label: '事業所', render: (s) => s.classroom?.classroom_name || '-' },
    { key: 'grade_level', label: '学年', render: (s) => gradeLabels[s.grade_level || ''] || s.grade_level || '-' },
    {
      key: 'status',
      label: 'ステータス',
      render: (s) => <Badge variant={statusVariants[s.status] || 'default'}>{statusLabels[s.status]}</Badge>,
    },
    { key: 'guardian', label: '保護者', render: (s) => s.guardian?.full_name || '-' },
    {
      key: 'actions',
      label: '操作',
      render: (s) => (
        <div className="flex items-center gap-1 flex-wrap">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setCopySource(s)}
            leftIcon={<MaterialIcon name="content_copy" size={14} />}
            title="同一企業内の別教室にこの児童を複製します"
          >
            別教室に複製
          </Button>
          {s.person_id && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setLinkedTarget(s)}
              leftIcon={<MaterialIcon name="sync" size={14} />}
              title="リンク先のレコードにこの児童の情報を同期します"
            >
              同期
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">生徒管理 (管理者)</h1>
        <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="school" size={16} />} onClick={handleOpenPromotion}>
          学年更新
        </Button>
      </div>

      <div className="relative">
        <MaterialIcon name="search" size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input placeholder="生徒名で検索..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
      </div>

      {isLoading ? (
        <SkeletonTable rows={8} cols={6} />
      ) : (
        <Table
          columns={columns}
          data={students}
          keyExtractor={(item) => item.id}
          currentPage={meta?.current_page}
          totalPages={meta?.last_page}
          onPageChange={goToPage}
          emptyMessage="生徒が見つかりません"
        />
      )}

      {/* 学年更新モーダル */}
      <Modal isOpen={showPromotion} onClose={() => { setShowPromotion(false); setPreview(null); }} title="学年更新" size="lg">
        <div className="space-y-4">
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            生年月日をもとに全在籍生徒の学年を再計算します。変更がある生徒のみ表示されます。
          </p>

          {previewLoading ? (
            <div className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">読み込み中...</div>
          ) : preview && preview.length > 0 ? (
            <>
              <div className="max-h-[400px] overflow-y-auto rounded-md border border-[var(--neutral-stroke-2)]">
                <table className="w-full text-sm">
                  <thead className="sticky top-0 bg-[var(--neutral-background-3)]">
                    <tr>
                      <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">生徒名</th>
                      <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">現在の学年</th>
                      <th className="px-3 py-2 text-center text-[var(--neutral-foreground-4)]"></th>
                      <th className="px-3 py-2 text-left font-medium text-[var(--neutral-foreground-2)]">更新後の学年</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--neutral-stroke-2)]">
                    {preview.map((c) => (
                      <tr key={c.id}>
                        <td className="px-3 py-2 font-medium">{c.student_name}</td>
                        <td className="px-3 py-2">{gradeLabels[c.old_grade] || c.old_grade}</td>
                        <td className="px-3 py-2 text-center text-[var(--neutral-foreground-4)]">
                          <MaterialIcon name="arrow_forward" size={16} />
                        </td>
                        <td className="px-3 py-2 font-semibold text-[var(--brand-80)]">{gradeLabels[c.new_grade] || c.new_grade}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="text-sm text-[var(--neutral-foreground-2)]">{preview.length}名の学年が変更されます。</p>
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => { setShowPromotion(false); setPreview(null); }}>キャンセル</Button>
                <Button variant="primary" onClick={() => executeMutation.mutate()} isLoading={executeMutation.isPending}>
                  更新実行
                </Button>
              </div>
            </>
          ) : preview ? (
            <div className="py-8 text-center">
              <MaterialIcon name="check_circle" size={40} className="mx-auto mb-3 text-[var(--status-success-fg)]" />
              <p className="text-sm font-medium text-[var(--neutral-foreground-3)]">全生徒の学年は最新です。更新の必要はありません。</p>
              <div className="mt-4 flex justify-end">
                <Button variant="outline" onClick={() => { setShowPromotion(false); setPreview(null); }}>閉じる</Button>
              </div>
            </div>
          ) : null}
        </div>
      </Modal>

      {/* 別教室に複製モーダル */}
      {copySource && (
        <StudentCopyModal
          student={copySource}
          onClose={() => setCopySource(null)}
          onCopied={() => queryClient.invalidateQueries({ queryKey: ['admin', 'students'] })}
        />
      )}

      {/* 同一人物同期モーダル */}
      {linkedTarget && (
        <StudentLinkedSyncModal
          student={linkedTarget}
          onClose={() => setLinkedTarget(null)}
          onSynced={() => queryClient.invalidateQueries({ queryKey: ['admin', 'students'] })}
        />
      )}
    </div>
  );
}
