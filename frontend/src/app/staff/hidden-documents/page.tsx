'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface HiddenDocument {
  id: number;
  document_type: string;
  document_id: number;
  student_name: string | null;
  document_title: string | null;
  document_date: string | null;
  hidden_by: number | null;
  hidden_by_name: string | null;
  created_at: string;
}

const DOC_TYPE_OPTIONS = [
  { value: '', label: 'すべて' },
  { value: 'support_plan', label: '個別支援計画書' },
  { value: 'monitoring', label: 'モニタリング' },
  { value: 'kakehashi', label: 'かけはし' },
  { value: 'newsletter', label: 'お便り' },
] as const;

const DOC_TYPE_LABELS: Record<string, string> = {
  support_plan: '個別支援計画書',
  monitoring: 'モニタリング',
  kakehashi: 'かけはし',
  newsletter: 'お便り',
};

const DOC_TYPE_VARIANTS: Record<string, 'default' | 'info' | 'warning' | 'success'> = {
  support_plan: 'info',
  monitoring: 'warning',
  kakehashi: 'success',
  newsletter: 'default',
};

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

export default function HiddenDocumentsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [docTypeFilter, setDocTypeFilter] = useState('');

  const { data: documents = [], isLoading } = useQuery({
    queryKey: ['staff', 'hidden-documents', docTypeFilter],
    queryFn: async () => {
      const params: Record<string, string> = {};
      if (docTypeFilter) params.document_type = docTypeFilter;
      const res = await api.get('/api/staff/hidden-documents', { params });
      return (res.data?.data ?? []) as HiddenDocument[];
    },
  });

  const restoreMutation = useMutation({
    mutationFn: async (doc: HiddenDocument) => {
      return api.post('/api/staff/hidden-documents/toggle', {
        document_type: doc.document_type,
        document_id: doc.document_id,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'hidden-documents'] });
      toast.success('表示に戻しました');
    },
    onError: () => {
      toast.error('復元に失敗しました');
    },
  });

  const handleRestore = (doc: HiddenDocument) => {
    if (confirm(`「${doc.document_title ?? doc.document_type}」を表示に戻しますか？`)) {
      restoreMutation.mutate(doc);
    }
  };

  const inputCls =
    'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]';

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">書類表示管理</h1>

      {/* Filter */}
      <Card>
        <CardBody>
          <div className="flex items-center gap-3">
            <MaterialIcon name="filter_list" size={16} className="text-[var(--neutral-foreground-3)]" />
            <label className="text-sm font-medium text-[var(--neutral-foreground-2)]">種類で絞り込み</label>
            <select
              value={docTypeFilter}
              onChange={(e) => setDocTypeFilter(e.target.value)}
              className={inputCls + ' max-w-xs'}
            >
              {DOC_TYPE_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
            <span className="ml-auto text-sm text-[var(--neutral-foreground-3)]">
              {documents.length}件
            </span>
          </div>
        </CardBody>
      </Card>

      {/* List */}
      {isLoading ? (
        <div className="space-y-2">
          {[...Array(4)].map((_, i) => (
            <Skeleton key={i} className="h-16 rounded-lg" />
          ))}
        </div>
      ) : documents.length === 0 ? (
        <Card>
          <CardBody>
            <div className="flex flex-col items-center py-12">
              <MaterialIcon name="description" size={48} className="mb-3 text-[var(--neutral-foreground-4)]" />
              <p className="text-sm font-medium text-[var(--neutral-foreground-2)]">
                非表示の書類はありません
              </p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-2">
          {documents.map((doc) => (
            <div
              key={doc.id}
              className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-4 py-3"
            >
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <Badge variant={DOC_TYPE_VARIANTS[doc.document_type] ?? 'default'}>
                    {DOC_TYPE_LABELS[doc.document_type] ?? doc.document_type}
                  </Badge>
                  {doc.student_name && (
                    <span className="font-medium text-[var(--neutral-foreground-1)]">
                      {doc.student_name}
                    </span>
                  )}
                  {doc.document_title && !doc.student_name && (
                    <span className="font-medium text-[var(--neutral-foreground-1)]">
                      {doc.document_title}
                    </span>
                  )}
                </div>
                <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                  {doc.document_title && doc.student_name && <>{doc.document_title} / </>}
                  {doc.document_date && <>{doc.document_date} / </>}
                  非表示: {format(new Date(doc.created_at), 'yyyy/MM/dd')}
                  {doc.hidden_by_name && <> ({doc.hidden_by_name})</>}
                </p>
              </div>
              <Button
                variant="outline"
                size="sm"
                leftIcon={<MaterialIcon name="visibility" size={14} />}
                onClick={() => handleRestore(doc)}
                disabled={restoreMutation.isPending}
              >
                復元
              </Button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
