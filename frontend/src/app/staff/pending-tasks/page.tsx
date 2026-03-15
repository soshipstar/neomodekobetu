'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { CheckCircle, MessageCircle, FileText, CalendarDays, ClipboardList } from 'lucide-react';

interface PendingTask {
  id: number;
  type: string;
  label: string;
  detail: string | null;
  student: { id: number; student_name: string } | null;
  due_date: string | null;
}

const typeIcons: Record<string, typeof MessageCircle> = {
  unread_chat: MessageCircle,
  unsigned_plan: FileText,
  meeting_response: CalendarDays,
  monitoring_due: ClipboardList,
  kakehashi_due: ClipboardList,
};

export default function PendingTasksPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const { data: tasks, isLoading } = useQuery({
    queryKey: ['staff', 'pending-tasks'],
    queryFn: async () => {
      const response = await api.get<{ data: PendingTask[] }>('/api/staff/pending-tasks');
      return response.data.data;
    },
  });

  const completeMutation = useMutation({
    mutationFn: async (taskId: number) => {
      await api.post(`/api/staff/pending-tasks/${taskId}/complete`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'pending-tasks'] });
      toast.success('タスクを完了しました');
    },
    onError: () => toast.error('エラーが発生しました'),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">未対応タスク</h1>
        {tasks && (
          <span className="text-sm text-[var(--neutral-foreground-3)]">{tasks.length}件</span>
        )}
      </div>

      {isLoading ? (
        <SkeletonList items={5} />
      ) : tasks && tasks.length > 0 ? (
        <div className="space-y-3">
          {tasks.map((task) => {
            const Icon = typeIcons[task.type] || ClipboardList;
            return (
              <Card key={task.id}>
                <div className="flex items-start gap-4">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--neutral-background-3)]">
                    <Icon className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                        {task.label}
                      </span>
                      {task.student && (
                        <Badge variant="primary">{task.student.student_name}</Badge>
                      )}
                    </div>
                    {task.detail && <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">{task.detail}</p>}
                    {task.due_date && <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">期限: {task.due_date}</p>}
                  </div>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => completeMutation.mutate(task.id)}
                    isLoading={completeMutation.isPending}
                  >
                    <CheckCircle className="h-4 w-4" />
                  </Button>
                </div>
              </Card>
            );
          })}
        </div>
      ) : (
        <Card>
          <CardBody>
            <div className="flex flex-col items-center py-12">
              <CheckCircle className="mb-3 h-12 w-12 text-[var(--status-success-fg)]" />
              <p className="text-sm font-medium text-[var(--neutral-foreground-2)]">すべてのタスクが完了しています</p>
              <p className="text-xs text-[var(--neutral-foreground-3)]">お疲れさまです!</p>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
