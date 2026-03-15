'use client';

import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { FileText, Upload, CheckCircle, Clock, AlertCircle } from 'lucide-react';

interface SubmissionRequest {
  id: number;
  title: string;
  description: string;
  due_date: string | null;
  my_submission: {
    id: number;
    file_name: string | null;
    file_url: string | null;
    comment: string | null;
    submitted_at: string | null;
    status: 'pending' | 'submitted' | 'reviewed';
  } | null;
}

export default function StudentSubmissionsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [submitModal, setSubmitModal] = useState(false);
  const [selectedRequest, setSelectedRequest] = useState<SubmissionRequest | null>(null);
  const [comment, setComment] = useState('');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const { data: requests = [], isLoading } = useQuery({
    queryKey: ['student', 'submissions'],
    queryFn: async () => {
      const res = await api.get<{ data: SubmissionRequest[] }>('/api/student/submissions');
      return res.data.data;
    },
  });

  const submitMutation = useMutation({
    mutationFn: async ({ requestId, file, comment }: { requestId: number; file: File | null; comment: string }) => {
      const formData = new FormData();
      if (file) formData.append('file', file);
      if (comment) formData.append('comment', comment);
      return api.post(`/api/student/submissions/${requestId}/submit`, formData);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student', 'submissions'] });
      toast.success('提出しました');
      setSubmitModal(false);
      setSelectedRequest(null);
      setComment('');
      setSelectedFile(null);
    },
    onError: () => toast.error('提出に失敗しました'),
  });

  const openSubmit = (request: SubmissionRequest) => {
    setSelectedRequest(request);
    setComment(request.my_submission?.comment || '');
    setSelectedFile(null);
    setSubmitModal(true);
  };

  const statusConfig: Record<string, { text: string; icon: React.ReactNode; variant: 'default' | 'success' | 'warning' }> = {
    pending: { text: '未提出', icon: <Clock className="h-4 w-4" />, variant: 'default' },
    submitted: { text: '提出済み', icon: <CheckCircle className="h-4 w-4" />, variant: 'success' },
    reviewed: { text: '確認済み', icon: <CheckCircle className="h-4 w-4" />, variant: 'warning' },
  };

  const pendingRequests = requests.filter((r) => !r.my_submission || r.my_submission.status === 'pending');
  const submittedRequests = requests.filter((r) => r.my_submission && r.my_submission.status !== 'pending');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">提出物</h1>

      {isLoading ? (
        <SkeletonList items={4} />
      ) : requests.length === 0 ? (
        <Card>
          <div className="py-12 text-center">
            <FileText className="mx-auto h-12 w-12 text-gray-300" />
            <p className="mt-2 text-sm text-gray-500">提出物はありません</p>
          </div>
        </Card>
      ) : (
        <>
          {/* Pending */}
          {pendingRequests.length > 0 && (
            <div>
              <h2 className="mb-3 text-lg font-semibold text-gray-800">未提出</h2>
              <div className="space-y-3">
                {pendingRequests.map((req) => {
                  const isOverdue = req.due_date && new Date(req.due_date) < new Date();
                  return (
                    <Card key={req.id} className={isOverdue ? 'border-red-200 bg-red-50/50' : ''}>
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <div className="flex items-center gap-2">
                            <h3 className="font-medium text-gray-900">{req.title}</h3>
                            {isOverdue && <Badge variant="danger">期限超過</Badge>}
                          </div>
                          {req.description && <p className="mt-1 text-sm text-gray-600">{req.description}</p>}
                          {req.due_date && (
                            <p className={`mt-1 text-sm ${isOverdue ? 'text-red-600 font-medium' : 'text-gray-500'}`}>
                              期限: {format(new Date(req.due_date), 'M月d日(E)', { locale: ja })}
                            </p>
                          )}
                        </div>
                        <Button size="sm" onClick={() => openSubmit(req)} leftIcon={<Upload className="h-4 w-4" />}>
                          提出する
                        </Button>
                      </div>
                    </Card>
                  );
                })}
              </div>
            </div>
          )}

          {/* Submitted */}
          {submittedRequests.length > 0 && (
            <div>
              <h2 className="mb-3 text-lg font-semibold text-gray-800">提出済み</h2>
              <div className="space-y-3">
                {submittedRequests.map((req) => {
                  const sub = req.my_submission!;
                  const config = statusConfig[sub.status] || statusConfig.pending;
                  return (
                    <Card key={req.id}>
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <div className="flex items-center gap-2">
                            <h3 className="font-medium text-gray-900">{req.title}</h3>
                            <Badge variant={config.variant}>{config.text}</Badge>
                          </div>
                          {sub.submitted_at && (
                            <p className="mt-1 text-sm text-gray-500">
                              提出日: {format(new Date(sub.submitted_at), 'M月d日 HH:mm', { locale: ja })}
                            </p>
                          )}
                          {sub.file_name && (
                            <p className="mt-0.5 text-sm text-blue-600">{sub.file_name}</p>
                          )}
                          {sub.comment && <p className="mt-0.5 text-sm text-gray-600">{sub.comment}</p>}
                        </div>
                        <Button variant="outline" size="sm" onClick={() => openSubmit(req)}>
                          再提出
                        </Button>
                      </div>
                    </Card>
                  );
                })}
              </div>
            </div>
          )}
        </>
      )}

      {/* Submit Modal */}
      <Modal isOpen={submitModal} onClose={() => setSubmitModal(false)} title={`提出: ${selectedRequest?.title || ''}`} size="lg">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (selectedRequest) submitMutation.mutate({ requestId: selectedRequest.id, file: selectedFile, comment });
          }}
          className="space-y-4"
        >
          {selectedRequest?.description && (
            <div className="rounded-lg bg-gray-50 p-3">
              <p className="text-sm text-gray-600">{selectedRequest.description}</p>
            </div>
          )}

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">ファイル</label>
            <div
              onClick={() => fileInputRef.current?.click()}
              className="flex cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-gray-300 p-8 hover:border-blue-400 hover:bg-blue-50 transition-colors"
            >
              {selectedFile ? (
                <div className="text-center">
                  <FileText className="mx-auto h-8 w-8 text-blue-600" />
                  <p className="mt-1 text-sm font-medium text-gray-900">{selectedFile.name}</p>
                  <p className="text-xs text-gray-500">{(selectedFile.size / 1024).toFixed(1)} KB</p>
                </div>
              ) : (
                <div className="text-center">
                  <Upload className="mx-auto h-8 w-8 text-gray-400" />
                  <p className="mt-1 text-sm text-gray-600">クリックしてファイルを選択</p>
                </div>
              )}
            </div>
            <input
              ref={fileInputRef}
              type="file"
              onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
              className="hidden"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">コメント（任意）</label>
            <textarea
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              rows={3}
              placeholder="先生へのコメントがあれば..."
            />
          </div>

          <div className="flex justify-end gap-2">
            <Button variant="secondary" type="button" onClick={() => setSubmitModal(false)}>キャンセル</Button>
            <Button type="submit" isLoading={submitMutation.isPending} leftIcon={<Upload className="h-4 w-4" />}>
              提出する
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
