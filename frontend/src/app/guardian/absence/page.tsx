'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { absenceSchema, type AbsenceFormData } from '@/lib/validators';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { formatDate } from '@/lib/utils';
import { Send, CheckCircle } from 'lucide-react';

interface AbsenceRecord {
  id: number;
  student_id: number;
  student_name: string;
  absence_date: string;
  reason: string;
  status: 'pending' | 'confirmed';
  created_at: string;
}

interface ChildOption {
  id: number;
  student_name: string;
}

export default function AbsenceNotificationPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const { data: children } = useQuery({
    queryKey: ['guardian', 'children'],
    queryFn: async () => {
      const response = await api.get<{ data: ChildOption[] }>('/api/guardian/children');
      return response.data.data;
    },
  });

  const { data: absences, isLoading } = useQuery({
    queryKey: ['guardian', 'absences'],
    queryFn: async () => {
      const response = await api.get<{ data: AbsenceRecord[] }>('/api/guardian/absences');
      return response.data.data;
    },
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<AbsenceFormData>({
    resolver: zodResolver(absenceSchema),
  });

  const mutation = useMutation({
    mutationFn: async (data: AbsenceFormData) => {
      await api.post('/api/guardian/absences', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'absences'] });
      reset();
      toast.success('欠席連絡を送信しました');
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">欠席連絡</h1>

      {/* Form */}
      <Card>
        <CardHeader>
          <CardTitle>欠席連絡を送信</CardTitle>
        </CardHeader>
        <CardBody>
          <form onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">お子様</label>
              <select
                {...register('student_id', { valueAsNumber: true })}
                className="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              >
                <option value="">選択してください</option>
                {children?.map((child) => (
                  <option key={child.id} value={child.id}>{child.student_name}</option>
                ))}
              </select>
              {errors.student_id && <p className="mt-1 text-sm text-red-600">{errors.student_id.message}</p>}
            </div>

            <Input
              label="日付"
              type="date"
              error={errors.absence_date?.message}
              {...register('absence_date')}
            />

            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">理由</label>
              <textarea
                {...register('reason')}
                className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                rows={3}
                placeholder="欠席理由を入力..."
              />
              {errors.reason && <p className="mt-1 text-sm text-red-600">{errors.reason.message}</p>}
            </div>

            <Button type="submit" leftIcon={<Send className="h-4 w-4" />} isLoading={mutation.isPending}>
              送信する
            </Button>
          </form>
        </CardBody>
      </Card>

      {/* History */}
      <Card>
        <CardHeader>
          <CardTitle>送信履歴</CardTitle>
        </CardHeader>
        <CardBody>
          {isLoading ? (
            <SkeletonList items={3} />
          ) : absences && absences.length > 0 ? (
            <div className="space-y-2">
              {absences.map((absence) => (
                <div key={absence.id} className="flex items-center justify-between rounded-lg border border-gray-100 p-3">
                  <div>
                    <p className="text-sm font-medium text-gray-900">
                      {absence.student_name} - {formatDate(absence.absence_date)}
                    </p>
                    <p className="text-xs text-gray-500">{absence.reason}</p>
                  </div>
                  <Badge variant={absence.status === 'confirmed' ? 'success' : 'warning'} dot>
                    {absence.status === 'confirmed' ? '確認済' : '未確認'}
                  </Badge>
                </div>
              ))}
            </div>
          ) : (
            <p className="py-4 text-center text-sm text-gray-500">送信履歴はありません</p>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
